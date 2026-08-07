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
    assert workflow.count('persist-credentials: false') == 10
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
    assert signer.count('--target-pkg-metadata .resolver-plugins/target-pkg.json') == 3
    assert 'id: reuse-snapshot' in signer
    assert 'reuse-snapshot --repository resolver-plugins/repository' in signer
    assert "if: steps.reuse-snapshot.outputs.reused != 'true'" in signer
    assert '--public-key docs/package-repository/resolver-plugins.pub' in signer
    assert 'repository/bind920' not in signer


def test_publisher_mints_a_repository_scoped_github_app_token():
    workflow = workflow_text()
    publisher = workflow.split('  publish:', 1)[1].split('  verify:', 1)[0]
    assert 'permissions:\n      contents: read' in publisher
    assert 'fetch-depth: 0' in publisher
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
    assert 'mark-latest-package-channel' in workflow
    assert publisher.index('prune-snapshots') < publisher.index('mark-latest-package-channel')
    assert '--recovery "$RUNNER_TEMP/recovery"' in workflow


def test_publication_waits_for_current_and_snapshot_installability_in_freebsd():
    workflow = workflow_text()
    publisher = workflow.split('  publish:', 1)[1].split('  verify:', 1)[0]
    verifier = workflow.split('  verify:', 1)[1].split('  verify-published:', 1)[0]
    assert 'needs: [select, profile, sign, verify]' in publisher
    assert 'permissions:\n      contents: read' in publisher
    assert 'pkg install -y -r OPNsense opnsense os-bind' in verifier
    assert '/usr/local/sbin/pkg-static install -y -r resolver-plugins bind-tools bind920 os-bind-rp' in verifier
    assert '"$root"/current/bind-tools-*.pkg' in verifier
    assert '"$root"/current/bind920-*.pkg' in verifier
    assert '"$root"/current/os-bind-rp-*.pkg' in verifier
    assert '"$root"/current/*.pkg' not in verifier
    assert '/usr/local/sbin/pkg-static query -F "$package" \'%dn\'' in verifier
    assert verifier.index('.github/ci/setup-opnsense-repository.sh') < verifier.index(
        'pkg install -y -r OPNsense opnsense os-bind'
    ) < verifier.index(
        '/usr/local/sbin/pkg-static install -y -r resolver-plugins bind-tools bind920 os-bind-rp'
    )
    assert 'url: "file://$PWD/$root/snapshot"' in verifier
    assert '/usr/local/sbin/pkg-static install -f -y -r resolver-plugins-rollback os-bind-rp' in verifier
    assert ' OR ' not in verifier


def test_published_channel_is_installed_from_github_in_freebsd():
    workflow = workflow_text()
    verifier = workflow.split('  verify-published:', 1)[1].split('  source-release:', 1)[0]
    source_release = workflow.split('  source-release:', 1)[1]
    assert 'needs: [select, profile, publish]' in verifier
    assert 'permissions:\n      contents: read' in verifier
    assert 'name: os-bind-rp-production-repository-${{ needs.select.outputs.series }}' in verifier
    assert 'https://github.com/resolver-plugins/repository/releases/download/pkg-$series' in verifier
    assert '.github/ci/setup-opnsense-repository.sh "$series"' in verifier
    assert 'pkg install -y -r OPNsense opnsense os-bind' in verifier
    assert '"$root"/bind-tools-*.pkg' in verifier
    assert '"$root"/bind920-*.pkg' in verifier
    assert '"$root"/os-bind-rp-*.pkg' in verifier
    assert '"$root"/*.pkg' not in verifier
    assert 'pkg update -f -r resolver-plugins' in verifier
    assert 'cmp -s "$root/channel.json" "$public_channel"' in verifier
    assert '/usr/local/sbin/pkg-static query -F "$archive" \'%n|%v|%o\'' in verifier
    assert '[ "$channel_identities" = "$expected_identities" ]' in verifier
    assert '[ "$attempt" -ge 20 ]' in verifier
    assert 'sleep 30' in verifier
    assert "printf 'expected identities:\\n%s\\n' \"$expected_identities\"" in verifier
    assert "printf 'published identities:\\n%s\\n' \"$channel_identities\"" in verifier
    assert 'pkg rquery -r resolver-plugins -e "%n = $package" \'%dn\'' in verifier
    assert 'RP_PKG_STATIC_COMMAND=/usr/local/sbin/pkg-static scripts/install-os-bind-rp.sh' in verifier
    assert verifier.index('pkg install -y -r OPNsense opnsense os-bind') < verifier.index(
        'RP_PKG_STATIC_COMMAND=/usr/local/sbin/pkg-static scripts/install-os-bind-rp.sh'
    )
    assert '[ -z "$(pkg query -e \'%n = os-bind\'' in verifier
    assert "'%n|%v|%o'" in verifier
    assert 'dns/bind-tools' in verifier
    assert 'dns/bind920' in verifier
    assert 'opnsense/os-bind-rp' in verifier
    assert '[ "$channel_identity" = "$expected_identity" ]' in verifier
    assert '[ "$installed_identity" = "$channel_identity" ]' in verifier
    assert 'needs: [select, profile, verify-published]' in source_release


def test_development_release_installs_from_a_temporary_freebsd_repository():
    workflow = workflow_text()
    verifier = workflow.split('  verify-development:', 1)[1].split('  publish-development:', 1)[0]
    publisher = workflow.split('  publish-development:', 1)[1].split('  sign:', 1)[0]
    assert 'needs: [select, profile, build]' in verifier
    assert '/usr/local/sbin/pkg-static repo "$output"' in verifier
    assert 'signature_type: "none"' in verifier
    assert '/usr/local/sbin/pkg-static update -r resolver-plugins-development' in verifier
    assert 'pkg install -y -r OPNsense opnsense os-bind' in verifier
    assert '/usr/local/sbin/pkg-static install -y -r resolver-plugins-development bind-tools bind920 os-bind-rp' in verifier
    assert verifier.index('pkg install -y -r OPNsense opnsense os-bind') < verifier.index(
        '/usr/local/sbin/pkg-static install -y -r resolver-plugins-development bind-tools bind920 os-bind-rp'
    )
    assert "'%n-%v'" in verifier
    assert ' OR ' not in verifier
    assert 'needs: [select, build, verify-development]' in publisher
    assert 'pull_number: ${{ steps.select.outputs.pull_number }}' in workflow
    assert 'PULL_NUMBER: ${{ needs.select.outputs.pull_number }}' in publisher
    assert 'permissions:\n      contents: write\n      pull-requests: read' in publisher
    assert publisher.count(
        'gh api "repos/$GITHUB_REPOSITORY/pulls/$PULL_NUMBER" --jq .state'
    ) == 2
    assert publisher.count(
        'release_channel.py cleanup-tag --repository "$GITHUB_REPOSITORY" --tag "$TAG"'
    ) == 2
    assert publisher.index('pr_state=$(gh api') < publisher.index('gh release create "$TAG"')
    assert publisher.rindex('pr_state=$(gh api') > publisher.index('gh release upload "$TAG"')


def test_source_release_contains_only_plugin_and_build_metadata():
    workflow = workflow_text()
    source_release = workflow.split('  source-release:', 1)[1]
    assert 'needs: [select, profile, verify-published]' in source_release
    assert 'name: os-bind-rp-production-repository-${{ needs.select.outputs.series }}' in source_release
    assert 'output="artifacts/$SERIES/repository/current"' in source_release
    assert 'source_output="$RUNNER_TEMP/source-release"' in source_release
    assert 'publish-immutable --repository "$GITHUB_REPOSITORY"' in source_release
    assert 'gh release view "$tag"' not in source_release
    assert 'gh release create "$tag"' not in source_release
    assert 'set -- "$output"/os-bind-rp-*.pkg' in source_release
    assert 'cp "$1" "$output/build-metadata.txt" "$source_output/"' in source_release
    assert 'bind920-*.pkg' not in source_release
    assert 'os-bind-rp-build-production-' not in source_release


def test_all_freebsd_install_gates_pin_pkg_and_test_the_official_replacement_path():
    workflow = workflow_text()
    verifiers = {
        'development': workflow.split('  verify-development:', 1)[1].split(
            '  publish-development:', 1
        )[0],
        'staged': workflow.split('  verify:', 1)[1].split('  verify-published:', 1)[0],
        'published': workflow.split('  verify-published:', 1)[1].split(
            '  source-release:', 1
        )[0],
    }

    for name, verifier in verifiers.items():
        assert 'target_pkg.py install' in verifier, name
        assert 'target_pkg.py verify' in verifier, name
        assert 'pkg install -y -r OPNsense opnsense os-bind' in verifier, name
        assert 'package_checksums.py' in verifier, name
        assert '--pkg-command /usr/local/sbin/pkg-static' in verifier, name
        assert "[ -z \"$(pkg query -e '%n = os-bind' '%n'" in verifier, name
        assert "pkg query -e \"%n = $package\" '%Fp|%Fs'" in verifier, name
        assert '$2 == "(null)"' in verifier, name
        assert 'pkg check -s bind-tools bind920 os-bind-rp' in verifier, name
        assert '.github/ci/verify-bind-runtime.sh' in verifier, name
        assert verifier.index('pkg check -s bind-tools bind920 os-bind-rp') < verifier.index(
            '.github/ci/verify-bind-runtime.sh'
        ), name
        assert verifier.index('target_pkg.py install') < verifier.index(
            'pkg install -y -r OPNsense opnsense os-bind'
        ) < verifier.index('package_checksums.py')

    assert '/usr/local/sbin/pkg-static install -y -r resolver-plugins-development' in verifiers[
        'development'
    ]
    assert '/usr/local/sbin/pkg-static install -y -r resolver-plugins' in verifiers['staged']
    assert 'RP_PKG_STATIC_COMMAND=/usr/local/sbin/pkg-static' in verifiers['published']
