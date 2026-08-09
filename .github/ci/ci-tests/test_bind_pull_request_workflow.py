import pathlib
import re


REPOSITORY_ROOT = pathlib.Path(__file__).resolve().parents[3]
WORKFLOW = REPOSITORY_ROOT / '.github/workflows/bind-tests.yml'
PINNED_ACTION = re.compile(r'^[^@\s]+@[0-9a-f]{40}$')


def workflow_text() -> str:
    assert WORKFLOW.is_file(), 'BIND pull-request test workflow is missing'
    return WORKFLOW.read_text(encoding='utf-8')


def action_references(workflow: str) -> list[str]:
    return re.findall(r'^\s+(?:-\s+)?uses:\s+([^\s#]+)', workflow, re.MULTILINE)


def test_workflow_runs_only_for_relevant_pull_request_changes():
    workflow = workflow_text()

    assert 'pull_request:' in workflow
    assert 'workflow_call:' in workflow
    assert 'pull_request_target:' not in workflow
    assert "- 'dns/bind/**'" in workflow
    assert "- '.github/ci/**'" in workflow
    assert "- '.github/workflows/bind-tests.yml'" in workflow


def test_workflow_discovers_release_branches_and_runs_canonical_tests():
    workflow = workflow_text()

    assert "refs/heads/release/bind-rp/*" in workflow
    assert "fromJSON(needs.discover.outputs.series)" in workflow
    assert 'git fetch --no-tags origin' in workflow
    assert 'refs/heads/release/bind-rp/$SERIES' in workflow
    assert 'git checkout "$source_commit" -- dns/bind/Makefile dns/bind/src' in workflow
    assert 'git cat-file -e "$source_commit:dns/bind/$fragment"' in workflow
    assert 'git show "$source_commit:dns/bind/$fragment" > "dns/bind/$fragment"' in workflow
    assert 'python3 -m pytest -q dns/bind/tests' in workflow


def test_workflow_provisions_the_pinned_python_test_runtime():
    workflow = workflow_text()
    test_job = workflow.split('  test:', 1)[1]

    assert re.search(r'actions/setup-python@[0-9a-f]{40}', test_job)
    assert "python-version: '3.12.13'" in test_job
    assert "python -m pip install --disable-pip-version-check 'pytest==8.3.5'" in test_job


def test_release_source_pull_requests_test_their_proposed_source():
    workflow = workflow_text()

    assert 'PR_BASE: ${{ inputs.pull_request_base || github.event.pull_request.base.ref }}' in workflow
    assert 'refs/heads/master:refs/remotes/origin/canonical-tests' in workflow
    assert 'git checkout refs/remotes/origin/canonical-tests -- \\' in workflow
    assert '.github/ci/metadata_profile.py' in workflow
    assert 'if [[ "$PR_BASE" != "release/bind-rp/$SERIES" ]]' in workflow


def test_reusable_workflow_accepts_the_callers_pull_request_context():
    workflow = workflow_text()

    assert 'pull_request_base:' in workflow
    assert 'pull_request_sha:' in workflow
    assert 'ref: ${{ inputs.pull_request_sha || github.sha }}' in workflow
    assert 'PR_BASE: ${{ inputs.pull_request_base || github.event.pull_request.base.ref }}' in workflow


def test_workflow_has_read_only_permissions_and_pinned_actions():
    workflow = workflow_text()
    references = action_references(workflow)

    assert 'contents: read' in workflow
    assert 'contents: write' not in workflow
    assert 'secrets.' not in workflow
    assert references
    assert all(PINNED_ACTION.fullmatch(reference) for reference in references)
