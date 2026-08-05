import os
import pathlib
import re


REPOSITORY_ROOT = pathlib.Path(
    os.environ.get('REPOSITORY_ROOT', pathlib.Path(__file__).resolve().parents[3])
)
WORKFLOW = REPOSITORY_ROOT / '.github/workflows/upstream-sync.yml'
PINNED_ACTION = re.compile(r'^[^@\s]+@[0-9a-f]{40}$')


def workflow_text() -> str:
    assert WORKFLOW.is_file(), 'daily upstream synchronization workflow is missing'
    return WORKFLOW.read_text(encoding='utf-8')


def top_level_mapping(workflow: str, key: str) -> list[str]:
    lines = workflow.splitlines()
    start = lines.index(f'{key}:') + 1
    block = []
    for line in lines[start:]:
        if line and not line.startswith((' ', '\t')):
            break
        if line.strip() and not line.lstrip().startswith('#'):
            block.append(line.strip())
    return block


def action_references(workflow: str) -> list[str]:
    return re.findall(r'^\s+(?:-\s+)?uses:\s+([^\s#]+)', workflow, re.MULTILINE)


def test_workflow_runs_daily_and_manually_with_exact_permissions():
    workflow = workflow_text()
    cron_expressions = re.findall(
        r'^\s*-\s+cron:\s*["\']([^"\']+)["\']\s*$', workflow, re.MULTILINE
    )

    assert re.search(r'^\s{2}schedule:\s*$', workflow, re.MULTILINE)
    assert re.search(r'^\s{2}workflow_dispatch:\s*$', workflow, re.MULTILINE)
    assert any(expression.split()[2:] == ['*', '*', '*'] for expression in cron_expressions)
    assert top_level_mapping(workflow, 'permissions') == [
        'contents: write',
        'pull-requests: write',
    ]
    assert workflow.count('permissions:') == 2
    assert '  test:\n    if: github.ref == \'refs/heads/master\'\n    runs-on: ubuntu-24.04\n    permissions:\n      contents: read' in workflow


def test_workflow_provisions_the_pinned_python_test_runtime():
    workflow = workflow_text()
    test_job = workflow.split('  test:', 1)[1].split('  reconcile:', 1)[0]

    assert re.search(r'actions/setup-python@[0-9a-f]{40}', test_job)
    assert "python-version: '3.12.13'" in test_job
    assert "python -m pip install --disable-pip-version-check 'pytest==8.3.5'" in test_job


def test_workflow_fetches_control_inputs_and_plans_before_apply():
    workflow = workflow_text()
    plan_index = workflow.index('.github/ci/sync_upstream.py plan')
    apply_index = workflow.index('.github/ci/sync_upstream.py apply')

    assert 'refs/heads/release/bind-rp/*:refs/heads/release/bind-rp/*' in workflow
    assert 'https://github.com/opnsense/plugins.git' in workflow
    assert 'refs/heads/stable/*:refs/remotes/upstream/stable/*' in workflow
    assert 'https://github.com/opnsense/tools.git' in workflow
    assert '--tools-repository "$RUNNER_TEMP/opnsense-tools"' in workflow
    assert 'opnsense/changelog' not in workflow
    assert '--release-notes-directory' not in workflow
    assert "'tools_tag'," in workflow
    assert plan_index < apply_index
    assert 'plan.json' in workflow


def test_workflow_resolves_and_hashes_immutable_core_archive_before_apply():
    workflow = workflow_text()
    resolve_index = workflow.index('git ls-remote https://github.com/opnsense/core.git')
    download_index = workflow.index('https://github.com/opnsense/core/archive/$core_commit.tar.gz')
    hash_index = workflow.index('sha256sum')
    apply_index = workflow.index('.github/ci/sync_upstream.py apply')

    assert 'refs/heads/stable/$series' in workflow
    assert 'curl --fail --location' in workflow
    assert resolve_index < download_index < hash_index < apply_index
    assert '--core-commit "$core_commit"' in workflow
    assert '--core-archive-url "$core_archive_url"' in workflow
    assert '--core-archive-sha256 "$core_archive_sha256"' in workflow


def test_workflow_uses_api_only_credentials_for_recovery_and_publication():
    workflow = workflow_text()
    vm_index = workflow.index('vmactions/freebsd-vm@')

    assert 'persist-credentials: false' in workflow
    assert 'git push' not in workflow
    assert workflow.count('GH_TOKEN: ${{ github.token }}') == 2
    assert workflow.rindex('GH_TOKEN: ${{ github.token }}') < vm_index
    assert workflow.count('RP_SYNC_REVIEWER: ${{ vars.RP_SYNC_REVIEWER }}') == 2


def test_workflow_recovers_partial_review_state_before_planning_and_uses_api_publisher():
    workflow = workflow_text()
    recover_index = workflow.index('.github/ci/publish_upstream.py recover')
    plan_index = workflow.index('.github/ci/sync_upstream.py plan')
    apply_index = workflow.index('.github/ci/sync_upstream.py apply')
    publish_index = workflow.index('.github/ci/publish_upstream.py publish')

    assert recover_index < plan_index < apply_index < publish_index
    assert "steps.recovery.outputs.handled != 'true'" in workflow


def test_bootstrap_build_uses_the_planner_profile_and_expires():
    workflow = workflow_text()
    bootstrap = workflow.split('Build bootstrap in planner-selected FreeBSD release', 1)[1].split(
        'Upload bootstrap artifact', 1
    )[0]

    assert "steps.plan.outputs.action == 'bootstrap-build'" in workflow
    assert 'release: ${{ steps.plan.outputs.freebsd_release }}' in workflow
    assert 'set -eu' in bootstrap
    assert 'export IGNORE_OSVERSION=yes' in bootstrap
    assert 'pkg update -f' in bootstrap
    assert 'pkg install -y python3' in bootstrap
    assert bootstrap.index('pkg install -y python3') < bootstrap.index('.github/ci/build-bind920.sh')
    assert 'output="artifacts/$series"' in bootstrap
    assert 'source_commit="$(git rev-parse HEAD)"' in bootstrap
    assert 'RP_UPSTREAM_METADATA=.resolver-plugins/upstream.json' in bootstrap
    assert bootstrap.count('SOURCE_COMMIT="$source_commit"') == 2
    bind_index = bootstrap.index('.github/ci/build-bind920.sh "$series" "$output"')
    plugin_index = bootstrap.index('.github/ci/build-os-bind-rp.sh "$series" "$output"')
    assert bind_index < plugin_index
    assert 'actions/upload-artifact@ea165f8d65b6e75b540449e92b4886f43607fa02' in workflow
    assert 'retention-days: 7' in workflow


def test_workflow_pins_actions_and_has_no_publication_authority_or_commands():
    workflow = workflow_text()
    references = action_references(workflow)
    lowered = workflow.lower()

    assert references
    assert all(PINNED_ACTION.fullmatch(reference) for reference in references)
    assert 'actions/checkout@d23441a48e516b6c34aea4fa41551a30e30af803' in references
    assert 'vmactions/freebsd-vm@77ed28d336d03fe19a3f4f7266c1d2c4714dd79d' in references
    assert 'secrets.' not in workflow
    assert not re.search(r'^\s*environment:', workflow, re.MULTILINE)
    for forbidden in (
        'gh release', 'create-release', 'pages:', 'id-token:', 'packages:',
        'pkg repo', 'docker push', 'npm publish', 'twine upload',
    ):
        assert forbidden not in lowered
