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
    assert 'git checkout "$source_commit" -- dns/bind/src' in workflow
    assert 'python3 -m pytest -q dns/bind/tests' in workflow


def test_release_source_pull_requests_test_their_proposed_source():
    workflow = workflow_text()

    assert 'PR_BASE: ${{ github.event.pull_request.base.ref }}' in workflow
    assert 'refs/heads/master:refs/remotes/origin/canonical-tests' in workflow
    assert 'if [[ "$PR_BASE" != "release/bind-rp/$SERIES" ]]' in workflow


def test_workflow_has_read_only_permissions_and_pinned_actions():
    workflow = workflow_text()
    references = action_references(workflow)

    assert 'contents: read' in workflow
    assert 'contents: write' not in workflow
    assert 'secrets.' not in workflow
    assert references
    assert all(PINNED_ACTION.fullmatch(reference) for reference in references)
