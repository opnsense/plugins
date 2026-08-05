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
    assert 'pull_request_target:' in workflow
    assert 'refs/heads/release/bind-rp/$series' in workflow
    assert 'refs/pull/$INPUT_PULL_NUMBER/head' in workflow
    assert 'git fetch --no-tags origin "$SOURCE_REF:refs/remotes/origin/package-source"' in workflow
    assert 'source_commit=$(git rev-parse refs/remotes/origin/package-source)' in workflow
    assert 'git checkout "$SOURCE_COMMIT" -- .resolver-plugins/upstream.json Mk dns/bind' in workflow


def test_workflow_validates_metadata_before_selecting_the_freebsd_vm():
    workflow = workflow_text()
    validator_index = workflow.index('python3 .github/ci/metadata_profile.py')
    vm_index = workflow.index('vmactions/freebsd-vm@')
    assert validator_index < vm_index
    assert 'release: ${{ needs.profile.outputs.freebsd_release }}' in workflow
    assert 'RP_UPSTREAM_METADATA=.resolver-plugins/upstream.json' in workflow
    assert '.github/ci/build-bind920.sh "$series" "$output"' in workflow
    assert '.github/ci/build-os-bind-rp.sh "$series" "$output"' in workflow


def test_workflow_uses_sha_pinned_actions_and_nonpersistent_checkout_credentials():
    workflow = workflow_text()
    references = action_references(workflow)
    assert references
    assert all(PINNED_ACTION.fullmatch(reference) for reference in references)
    assert workflow.count('persist-credentials: false') == 5
    assert 'actions/checkout@d23441a48e516b6c34aea4fa41551a30e30af803' in references
    assert 'vmactions/freebsd-vm@77ed28d336d03fe19a3f4f7266c1d2c4714dd79d' in references


def test_production_signing_and_publication_are_separate_from_builds():
    workflow = workflow_text()
    assert 'RP_PKG_SIGNING_KEY: ${{ secrets.RP_PKG_SIGNING_KEY }}' in workflow
    assert 'python3 .github/ci/release_channel.py stage' in workflow
    assert 'python3 .github/ci/release_channel.py publish' in workflow
    assert 'permissions:\n      contents: write' in workflow
    assert workflow.index('  sign:') < workflow.index('  publish:')
