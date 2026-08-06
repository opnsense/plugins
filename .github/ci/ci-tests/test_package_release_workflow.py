import pathlib
import re


REPOSITORY_ROOT = pathlib.Path(__file__).resolve().parents[3]
WORKFLOW = REPOSITORY_ROOT / '.github/workflows/package-release.yml'
PINNED_ACTION = re.compile(r'^[^@\s]+@[0-9a-f]{40}$')


def workflow_text() -> str:
    assert WORKFLOW.is_file(), 'package release workflow is missing'
    return WORKFLOW.read_text(encoding='utf-8')


def action_references(workflow: str) -> list[str]:
    return re.findall(r'^\s+(?:-\s+)?uses:\s+([^\s#]+)', workflow, re.MULTILINE)


def test_workflow_selects_an_immutable_release_source():
    workflow = workflow_text()
    assert 'workflow_dispatch:' in workflow
    assert 'pull_request_target:' not in workflow
    assert 'refs/heads/release/bind-rp/$series' in workflow
    assert 'refs/pull/$INPUT_PULL_NUMBER/head' in workflow
    assert 'gh api "repos/$GITHUB_REPOSITORY/pulls/$INPUT_PULL_NUMBER" --jq .base.ref' in workflow
    assert 'if [[ "$pr_base" == master ]]' in workflow
    assert 'elif [[ "$pr_base" == "release/bind-rp/$series" ]]' in workflow
    assert 'git fetch --no-tags origin "$SOURCE_REF:refs/remotes/origin/package-source"' in workflow
    assert 'source_commit=$(git rev-parse refs/remotes/origin/package-source)' in workflow
    assert 'git checkout "$SOURCE_COMMIT" -- .resolver-plugins/upstream.json Mk dns/bind' in workflow


def test_production_runs_only_from_the_master_control_plane():
    workflow = workflow_text()
    select = workflow.split('  select:', 1)[1].split('  profile:', 1)[0]
    profile = workflow.split('  profile:', 1)[1].split('  bind:', 1)[0]
    test = workflow.split('  test:', 1)[1].split('  select:', 1)[0]
    bind = workflow.split('  bind:', 1)[1].split('  build:', 1)[0]
    build = workflow.split('  build:', 1)[1].split('  publish-development:', 1)[0]
    assert 'GITHUB_REF: ${{ github.ref }}' in select
    assert '[[ "$GITHUB_REF" == refs/heads/master ]]' in select
    assert 'control_ref=$GITHUB_SHA' in select
    assert 'ref: ${{ needs.select.outputs.control_ref }}' in profile
    for job in (test, bind, build):
        assert 'ref: ${{ needs.profile.outputs.control_commit }}' in job
    assert 'group: package-release-${{ inputs.series }}' in workflow
    assert 'cancel-in-progress: false' in workflow


def test_workflow_validates_metadata_before_selecting_the_freebsd_vm():
    workflow = workflow_text()
    validator_index = workflow.index('python3 .github/ci/metadata_profile.py')
    vm_index = workflow.index('vmactions/freebsd-vm@')
    assert validator_index < vm_index
    assert 'release: ${{ needs.profile.outputs.freebsd_release }}' in workflow
    assert 'RP_UPSTREAM_METADATA=.resolver-plugins/upstream.json' in workflow
    assert '.github/ci/build-os-bind-rp.sh "$series" "$output"' in workflow
    assert 'RP_BIND920_FALLBACK=yes' in workflow
    assert 'Build or reuse BIND pair' in workflow


def test_workflow_materializes_the_distribution_bind_pair_before_building_the_plugin():
    workflow = workflow_text()
    assert '  bind:' in workflow
    assert 'RP_BIND920_CHANNEL_URL: https://github.com/resolver-plugins/repository/releases/download/pkg-${{ needs.select.outputs.series }}' in workflow
    assert 'name: Materialize BIND pair' in workflow
    assert 'needs: [select, profile, test, bind]' in workflow
    build = workflow.split('  build:', 1)[1].split('  publish-development:', 1)[0]
    assert 'RP_BIND920_FALLBACK=yes' in build
    assert 'pkg add "$output"/bind-tools-*.pkg "$output"/bind920-*.pkg' in build
    assert 'pkg query -F "$package" \'%dn\'' in build
    assert build.index('.github/ci/setup-opnsense-repository.sh') < build.index(
        'pkg add "$output"/bind-tools-*.pkg'
    )


def test_workflow_uses_sha_pinned_actions_and_nonpersistent_checkout_credentials():
    workflow = workflow_text()
    references = action_references(workflow)
    assert references
    assert all(PINNED_ACTION.fullmatch(reference) for reference in references)
    assert workflow.count('persist-credentials: false') == 9
    assert 'actions/checkout@d23441a48e516b6c34aea4fa41551a30e30af803' in references
    assert 'vmactions/freebsd-vm@77ed28d336d03fe19a3f4f7266c1d2c4714dd79d' in references


def test_workflow_provisions_the_pinned_python_test_runtime():
    workflow = workflow_text()
    test_job = workflow.split('  test:', 1)[1].split('  select:', 1)[0]

    assert re.search(r'actions/setup-python@[0-9a-f]{40}', test_job)
    assert "python-version: '3.12.13'" in test_job
    assert "python -m pip install --disable-pip-version-check 'pytest==8.3.5'" in test_job


def test_production_signing_and_publication_are_separate_from_builds():
    workflow = workflow_text()
    assert 'RP_PKG_SIGNING_KEY: ${{ secrets.RP_PKG_SIGNING_KEY }}' in workflow
    assert 'python3 .github/ci/release_channel.py stage-channel' in workflow
    assert 'python3 .github/ci/release_channel.py publish-channels' in workflow
    assert 'permissions:\n      contents: write' in workflow
    assert workflow.index('  sign:') < workflow.index('  publish:')


def test_signer_uses_master_control_plane_and_self_contained_channel_layout():
    workflow = workflow_text()
    signer = workflow.split('  sign:', 1)[1].split('  publish:', 1)[0]
    assert 'control_commit: ${{ steps.profile.outputs.control_commit }}' in workflow
    assert 'control_ref=$GITHUB_SHA' in workflow
    assert 'control_commit=$(git rev-parse HEAD)' in workflow
    assert 'ref: ${{ needs.profile.outputs.control_commit }}' in signer
    assert 'RP_PKG_SIGNING_KEY' in signer
    assert 'repository/current' in signer
    assert 'repository/snapshot' in signer
    assert signer.count('stage-channel') == 1
    assert 'cp -R "$output/repository/current" "$output/repository/snapshot"' in signer
    assert 'cmp -s docs/package-repository/resolver-plugins.pub "$output/resolver-plugins.pub"' in signer
    assert 'trusted-upstream.json' in signer
    assert 'validate-build-metadata' in signer
    assert 'repository/bind920' not in signer


def test_publisher_mints_a_repository_scoped_github_app_token():
    workflow = workflow_text()
    publisher = workflow.split('  publish:', 1)[1].split('  verify:', 1)[0]
    assert 'permissions:\n      contents: read' in publisher
    assert 'actions/create-github-app-token@fee1f7d63c2ff003460e3d139729b119787bc349' in publisher
    assert 'app-id: ${{ vars.RP_DISTRIBUTION_APP_ID }}' in publisher
    assert 'private-key: ${{ secrets.RP_DISTRIBUTION_APP_PRIVATE_KEY }}' in publisher
    assert 'owner: resolver-plugins' in publisher
    assert 'repositories: repository' in publisher
    assert 'permission-contents: write' in publisher
    assert 'GH_TOKEN: ${{ steps.distribution-token.outputs.token }}' in publisher
    assert 'RP_DISTRIBUTION_REPOSITORY_TOKEN' not in workflow
    assert 'resolver-plugins/repository' in workflow
    assert 'publish-channels' in workflow
    assert 'prune-snapshots' in workflow
    assert '--recovery "$RUNNER_TEMP/recovery"' in workflow


def test_publication_waits_for_current_and_snapshot_installability_in_freebsd():
    workflow = workflow_text()
    publisher = workflow.split('  publish:', 1)[1].split('  verify:', 1)[0]
    verifier = workflow.split('  verify:', 1)[1].split('  source-release:', 1)[0]
    assert 'needs: [select, profile, sign, verify]' in publisher
    assert 'permissions:\n      contents: read' in publisher
    assert 'pkg install -y -r resolver-plugins bind-tools bind920 os-bind-rp' in verifier
    assert 'pkg query -F "$package" \'%dn\'' in verifier
    assert verifier.index('.github/ci/setup-opnsense-repository.sh') < verifier.index(
        'pkg install -y -r resolver-plugins bind-tools bind920 os-bind-rp'
    )
    assert 'url: "file://$PWD/$root/snapshot"' in verifier
    assert 'pkg install -f -y -r resolver-plugins-rollback os-bind-rp' in verifier
    assert ' OR ' not in verifier


def test_development_release_installs_from_a_temporary_freebsd_repository():
    workflow = workflow_text()
    verifier = workflow.split('  verify-development:', 1)[1].split('  publish-development:', 1)[0]
    publisher = workflow.split('  publish-development:', 1)[1].split('  sign:', 1)[0]
    assert 'needs: [select, profile, build]' in verifier
    assert 'pkg repo "$output"' in verifier
    assert 'signature_type: "none"' in verifier
    assert 'pkg update -r resolver-plugins-development' in verifier
    assert 'pkg install -y -r resolver-plugins-development bind-tools bind920 os-bind-rp' in verifier
    assert "'%n-%v'" in verifier
    assert ' OR ' not in verifier
    assert 'needs: [select, build, verify-development]' in publisher


def test_source_release_contains_only_plugin_and_build_metadata():
    workflow = workflow_text()
    source_release = workflow.split('  source-release:', 1)[1]
    assert 'set -- "$output"/os-bind-rp-*.pkg' in source_release
    assert (
        'gh release create "$tag" "$1" "$output/build-metadata.txt" '
        '--repo "$GITHUB_REPOSITORY"' in source_release
    )
    assert 'bind920-*.pkg' not in source_release
    assert 'repository/' not in source_release
