import pathlib
import re


REPOSITORY_ROOT = pathlib.Path(__file__).resolve().parents[3]
WORKFLOW = REPOSITORY_ROOT / ".github/workflows/pr-release-cleanup.yml"
PINNED_ACTION = re.compile(r"^[^@\s]+@[0-9a-f]{40}$")


def workflow_text() -> str:
    assert WORKFLOW.is_file(), "pull request release cleanup workflow is missing"
    return WORKFLOW.read_text(encoding="utf-8")


def test_pull_request_release_cleanup_runs_on_every_close():
    workflow = workflow_text()
    assert "pull_request_target:" in workflow
    assert "types: [closed]" in workflow
    assert "if: github.event.pull_request.merged" not in workflow
    assert "PULL_NUMBER: ${{ github.event.pull_request.number }}" in workflow
    assert "cleanup-pull-request" in workflow


def test_pull_request_release_cleanup_uses_only_trusted_code():
    workflow = workflow_text()
    references = re.findall(r"^\s+(?:-\s+)?uses:\s+([^\s#]+)", workflow, re.MULTILINE)
    assert references
    assert all(PINNED_ACTION.fullmatch(reference) for reference in references)
    assert "ref: ${{ github.workflow_sha }}" in workflow
    assert "persist-credentials: false" in workflow
    for untrusted_ref in (
        "pull_request.head",
        "github.head_ref",
        "refs/pull/",
    ):
        assert untrusted_ref not in workflow


def test_pull_request_release_cleanup_has_only_required_write_permission():
    workflow = workflow_text()
    cleanup = workflow.split("  cleanup:", 1)[1]
    assert "permissions: {}" in workflow.split("jobs:", 1)[0]
    assert "permissions:\n      contents: write" in cleanup
    assert "GH_TOKEN: ${{ github.token }}" in cleanup
