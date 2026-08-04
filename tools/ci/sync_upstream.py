#!/usr/bin/env python3
"""Inspect fetched OPNsense refs and plan a safe BIND synchronization."""

import argparse
import json
import os
import re
import subprocess
import tempfile
from pathlib import Path
from typing import Any

from metadata_profile import (
    COMMIT_PATTERN,
    FREEBSD_RELEASE_PATTERN,
    validate_core_archive,
    validate_profile,
)


SERIES_PATTERN = re.compile(r'^stable/(\d+)\.(\d+)$')
RELEASE_PATTERN = re.compile(r'^(\d+)\.(\d+)$')
FREEBSD_PATTERN = re.compile(r'\bFreeBSD(?:\s+base)?\s+(\d+(?:\.\d+)?)\b', re.I)
METADATA_PATH = '.resolver-plugins/upstream.json'
OVERLAY_MANIFEST = '.resolver-plugins/overlay-paths.txt'
PLAN_FIELDS = {
    'action', 'series', 'upstream_ref', 'upstream_commit', 'source_release',
    'target_release', 'sync_branch', 'freebsd_release', 'bind_changed', 'reason',
}


def run_git(repository: Path, *arguments: str) -> str:
    """Run a read-only Git command and return its stripped standard output."""
    return subprocess.run(
        ['git', '-C', str(repository), *arguments],
        check=True,
        text=True,
        capture_output=True,
    ).stdout.strip()


def series_key(series: str) -> tuple[int, int]:
    match = RELEASE_PATTERN.fullmatch(series)
    if not match:
        raise ValueError(f'invalid series: {series}')
    return int(match.group(1)), int(match.group(2))


def stable_refs(repository: Path, upstream: str) -> dict[str, str]:
    refs = run_git(
        repository,
        'for-each-ref',
        '--format=%(refname:short) %(objectname)',
        f'refs/remotes/{upstream}',
    )
    result = {}
    for line in refs.splitlines():
        reference, commit = line.split(maxsplit=1)
        prefix = f'{upstream}/'
        if not reference.startswith(prefix):
            continue
        upstream_branch = reference[len(prefix):]
        match = SERIES_PATTERN.fullmatch(upstream_branch)
        if match and COMMIT_PATTERN.fullmatch(commit):
            result[f'{match.group(1)}.{match.group(2)}'] = commit
    return result


def release_refs(repository: Path, release_prefix: str) -> dict[str, str]:
    refs = run_git(
        repository,
        'for-each-ref',
        '--format=%(refname:short) %(objectname)',
        'refs/heads',
    )
    releases = {}
    for line in refs.splitlines():
        reference, commit = line.split(maxsplit=1)
        if not reference.startswith(release_prefix):
            continue
        series = reference[len(release_prefix):]
        if RELEASE_PATTERN.fullmatch(series):
            releases[series] = commit
    return releases


def load_metadata(
    repository: Path,
    release: str,
    metadata_path: str,
    source_series: str,
    upstream: str,
) -> dict:
    try:
        metadata = json.loads(run_git(repository, 'show', f'{release}:{metadata_path}'))
    except (subprocess.CalledProcessError, json.JSONDecodeError):
        raise ValueError('missing or invalid source metadata') from None
    expected_branch = f'stable/{source_series}'
    try:
        profile = validate_profile(metadata, source_series)
    except ValueError:
        raise ValueError('missing or invalid source metadata')
    run_git(
        repository,
        'merge-base',
        '--is-ancestor',
        profile['upstream_commit'],
        f'{upstream}/{expected_branch}',
    )
    return profile


def bind_tree(repository: Path, revision: str) -> str:
    return run_git(repository, 'rev-parse', f'{revision}:dns/bind')


def declared_freebsd_release(release_notes_directory: str | None, series: str) -> str | None:
    if not release_notes_directory:
        return None
    directory = Path(release_notes_directory)
    if not directory.is_dir():
        return None
    candidates = sorted(
        path for path in directory.rglob('*')
        if path.is_file() and (path.name == series or path.stem == series)
    )
    if not candidates:
        return None
    lines = candidates[0].read_text(encoding='utf-8').splitlines()
    introduction = []
    for index, line in enumerate(lines):
        if (
            index > 1
            and index + 1 < len(lines)
            and line.strip()
            and re.fullmatch(r'[=\-~^"`:#*+]+', lines[index + 1].strip())
        ):
            break
        introduction.append(line)
    match = FREEBSD_PATTERN.search('\n'.join(introduction))
    return match.group(1) if match else None


def decision(
    action: str,
    series: str | None,
    upstream_commit: str | None,
    source_release: str | None,
    target_release: str | None,
    freebsd_release: str | None,
    bind_changed: bool,
    reason: str,
) -> dict:
    upstream_ref = f'upstream/stable/{series}' if series else None
    sync_branch = None
    if action == 'update-review':
        sync_branch = f'sync/bind/{series}/{upstream_commit[:12]}'
    elif action == 'bootstrap-review':
        sync_branch = f'sync/bootstrap/{series}/{upstream_commit[:12]}'
    return {
        'action': action,
        'series': series,
        'upstream_ref': upstream_ref,
        'upstream_commit': upstream_commit,
        'source_release': source_release,
        'target_release': target_release,
        'sync_branch': sync_branch,
        'freebsd_release': freebsd_release,
        'bind_changed': bind_changed,
        'reason': reason,
    }


def plan(arguments: argparse.Namespace) -> dict:
    repository = Path(arguments.repository)
    stable = stable_refs(repository, arguments.upstream)
    releases = release_refs(repository, arguments.release_prefix)
    available = sorted(stable, key=series_key)
    if not available or not releases:
        return decision('blocked', None, None, None, None, None, False, 'no release source')

    latest_release = max(releases, key=series_key)
    source_release = f'{arguments.release_prefix}{latest_release}'
    source_upstream_commit = stable.get(latest_release)
    try:
        metadata = load_metadata(
            repository,
            source_release,
            arguments.metadata_path,
            latest_release,
            arguments.upstream,
        )
        source_bind_tree = bind_tree(repository, metadata['upstream_commit'])
    except (ValueError, subprocess.CalledProcessError):
        return decision(
            'blocked', latest_release, source_upstream_commit, source_release, source_release,
            None, False, 'missing or invalid source metadata',
        )

    if source_upstream_commit:
        try:
            current_bind_tree = bind_tree(repository, source_upstream_commit)
        except subprocess.CalledProcessError:
            current_bind_tree = None
        if current_bind_tree and source_bind_tree != current_bind_tree:
            freebsd_release = (
                declared_freebsd_release(arguments.release_notes_directory, latest_release)
                or metadata['freebsd_release']
            )
            return decision(
                'update-review', latest_release, source_upstream_commit, source_release,
                source_release, freebsd_release, True, 'upstream BIND tree changed',
            )

    target_series = next(
        (series for series in available if series_key(series) > series_key(latest_release)), None
    )
    if target_series is None:
        return decision(
            'noop', latest_release, source_upstream_commit, source_release, source_release,
            metadata['freebsd_release'], False, 'upstream BIND tree is unchanged',
        )
    target_release = f'{arguments.release_prefix}{target_series}'
    upstream_commit = stable[target_series]
    try:
        bind_changed = source_bind_tree != bind_tree(repository, upstream_commit)
    except subprocess.CalledProcessError:
        return decision(
            'blocked', target_series, upstream_commit, source_release, target_release,
            None, False, 'upstream BIND tree is unavailable',
        )
    freebsd_release = (
        declared_freebsd_release(arguments.release_notes_directory, target_series)
        or metadata['freebsd_release']
    )
    if bind_changed:
        return decision(
            'bootstrap-review', target_series, upstream_commit, source_release,
            target_release, freebsd_release, True, 'new series has an upstream BIND change',
        )
    return decision(
        'bootstrap-build', target_series, upstream_commit, source_release,
        target_release, freebsd_release, False, 'new series has an unchanged BIND tree',
    )


def git_result(
    repository: Path,
    *arguments: str,
    input_data: bytes | None = None,
    environment: dict[str, str] | None = None,
) -> subprocess.CompletedProcess:
    """Run Git, keeping binary patch input intact when provided."""
    return subprocess.run(
        ['git', '-C', str(repository), *arguments],
        input=input_data,
        capture_output=True,
        check=False,
        env=environment,
    )


def require_git(
    repository: Path,
    *arguments: str,
    input_data: bytes | None = None,
    environment: dict[str, str] | None = None,
) -> str:
    result = git_result(
        repository,
        *arguments,
        input_data=input_data,
        environment=environment,
    )
    if result.returncode:
        raise ValueError(result.stderr.decode(errors='replace').strip() or 'Git command failed')
    return result.stdout.decode().strip()


def ref_exists(repository: Path, branch: str) -> bool:
    result = git_result(repository, 'show-ref', '--verify', '--quiet', f'refs/heads/{branch}')
    if result.returncode not in (0, 1):
        raise ValueError('unable to inspect local branches')
    return result.returncode == 0


def require_clean_checkout(repository: Path) -> None:
    if require_git(repository, 'status', '--porcelain', '--untracked-files=all'):
        raise ValueError('repository checkout is dirty')


def read_apply_plan(plan_path: str) -> dict[str, Any]:
    try:
        plan_data = json.loads(Path(plan_path).read_text(encoding='utf-8'))
    except (OSError, json.JSONDecodeError):
        raise ValueError('missing or invalid plan') from None
    if not isinstance(plan_data, dict) or set(plan_data) != PLAN_FIELDS:
        raise ValueError('missing or invalid plan')
    return plan_data


def required_plan_string(plan_data: dict[str, Any], field: str) -> str:
    value = plan_data.get(field)
    if not isinstance(value, str) or not value:
        raise ValueError('missing or invalid plan')
    return value


def source_metadata(repository: Path, source_release: str) -> dict[str, str]:
    try:
        metadata = json.loads(require_git(repository, 'show', f'{source_release}:{METADATA_PATH}'))
    except (ValueError, json.JSONDecodeError):
        raise ValueError('missing or invalid source metadata') from None
    series = source_release.rsplit('/', maxsplit=1)[-1]
    try:
        return validate_profile(metadata, series)
    except ValueError:
        raise ValueError('missing or invalid source metadata')


def overlay_paths(repository: Path, source_release: str) -> list[str]:
    try:
        contents = require_git(repository, 'show', f'{source_release}:{OVERLAY_MANIFEST}')
    except ValueError:
        raise ValueError('missing or invalid overlay manifest') from None
    paths = contents.splitlines()
    if not paths:
        raise ValueError('missing or invalid overlay manifest')
    for path in paths:
        parts = Path(path).parts
        if (
            not path
            or path.startswith(('/', ':', '!', '^'))
            or any(character in path for character in '*?[')
            or any(part in ('.', '..') for part in parts)
        ):
            raise ValueError('missing or invalid overlay manifest')
    return paths


def validate_apply_plan(repository: Path, plan_data: dict[str, Any]) -> tuple[str, str, str | None]:
    action = required_plan_string(plan_data, 'action')
    if action not in {'bootstrap-build', 'bootstrap-review', 'update-review'}:
        raise ValueError('unknown plan action')
    series = required_plan_string(plan_data, 'series')
    if not RELEASE_PATTERN.fullmatch(series):
        raise ValueError('missing or invalid plan')
    upstream_ref = required_plan_string(plan_data, 'upstream_ref')
    upstream_commit = required_plan_string(plan_data, 'upstream_commit')
    source_release = required_plan_string(plan_data, 'source_release')
    target_release = required_plan_string(plan_data, 'target_release')
    freebsd_release = required_plan_string(plan_data, 'freebsd_release')
    if upstream_ref != f'upstream/stable/{series}' or target_release.rsplit('/', 1)[-1] != series:
        raise ValueError('missing or invalid plan')
    if (
        COMMIT_PATTERN.fullmatch(upstream_commit) is None
        or FREEBSD_RELEASE_PATTERN.fullmatch(freebsd_release) is None
    ):
        raise ValueError('missing or invalid plan')
    if require_git(repository, 'rev-parse', f'{upstream_commit}^{{commit}}') != upstream_commit:
        raise ValueError('missing or invalid plan')
    if require_git(repository, 'rev-parse', f'{upstream_ref}^{{commit}}') != upstream_commit:
        raise ValueError('upstream ref does not match plan commit')
    if not ref_exists(repository, source_release):
        raise ValueError('missing source release')
    metadata = source_metadata(repository, source_release)
    source_upstream_ref = f"upstream/{metadata['upstream_branch']}"
    if git_result(repository, 'merge-base', '--is-ancestor', metadata['upstream_commit'], source_upstream_ref).returncode:
        raise ValueError('missing or invalid source metadata')
    sync_branch = plan_data['sync_branch']
    if action == 'bootstrap-build':
        if sync_branch is not None:
            raise ValueError('missing or invalid plan')
        if ref_exists(repository, target_release):
            raise ValueError('target release branch already exists')
    else:
        expected_sync = (
            f'sync/bootstrap/{series}/{upstream_commit[:12]}'
            if action == 'bootstrap-review'
            else f'sync/bind/{series}/{upstream_commit[:12]}'
        )
        if sync_branch != expected_sync:
            raise ValueError('missing or invalid plan')
        if ref_exists(repository, sync_branch):
            raise ValueError('sync branch already exists')
        if action == 'bootstrap-review':
            if ref_exists(repository, target_release):
                raise ValueError('target release branch already exists')
        elif target_release != source_release or not ref_exists(repository, target_release):
            raise ValueError('missing or invalid plan')
    return action, source_release, sync_branch


def metadata_for(
    plan_data: dict[str, Any],
    core_commit: str,
    core_archive_url: str,
    core_archive_sha256: str,
) -> str:
    profile = {
        'series': plan_data['series'],
        'upstream_branch': f"stable/{plan_data['series']}",
        'upstream_commit': plan_data['upstream_commit'],
        'freebsd_release': plan_data['freebsd_release'],
        'core_commit': core_commit,
        'core_archive_url': core_archive_url,
        'core_archive_sha256': core_archive_sha256,
    }
    validate_profile(profile, plan_data['series'])
    return json.dumps(
        profile,
        indent=2,
        sort_keys=False,
    ) + '\n'


def commit_worktree(worktree: Path, paths: list[str], metadata: str, message: str) -> None:
    conflicts = unmerged_paths(worktree)
    if conflicts:
        raise ValueError(f'overlay patch conflicts: {", ".join(conflicts)}')
    metadata_file = worktree / METADATA_PATH
    metadata_file.parent.mkdir(parents=True, exist_ok=True)
    metadata_file.write_text(metadata, encoding='utf-8')
    require_git(worktree, 'add', '--all', '--', METADATA_PATH, *paths)
    if git_result(worktree, 'diff', '--cached', '--quiet').returncode:
        parent_timestamp = require_git(worktree, 'show', '-s', '--format=%ct', 'HEAD')
        commit_environment = os.environ.copy()
        commit_environment.update(
            {
                'GIT_AUTHOR_DATE': f'@{parent_timestamp} +0000',
                'GIT_COMMITTER_DATE': f'@{parent_timestamp} +0000',
            }
        )
        require_git(
            worktree,
            'commit',
            '-m',
            message,
            environment=commit_environment,
        )


def with_worktree(repository: Path, revision: str, callback, *, detached: bool = False) -> Any:
    with tempfile.TemporaryDirectory(prefix='sync-upstream-') as directory:
        worktree = Path(directory)
        try:
            arguments = ['worktree', 'add']
            if detached:
                arguments.append('--detach')
            arguments.extend([str(worktree), revision])
            require_git(repository, *arguments)
            return callback(worktree)
        finally:
            git_result(repository, 'worktree', 'remove', '--force', str(worktree))


def unmerged_paths(worktree: Path) -> list[str]:
    result = git_result(worktree, 'diff', '--name-only', '--diff-filter=U', '-z')
    if result.returncode:
        raise ValueError('unable to inspect overlay conflicts')
    return [os.fsdecode(path) for path in result.stdout.split(b'\0') if path]


def apply_overlay(worktree: Path, patch: bytes) -> None:
    result = git_result(worktree, 'apply', '--3way', '--binary', input_data=patch)
    conflicts = unmerged_paths(worktree)
    if conflicts:
        raise ValueError(f'overlay patch conflicts: {", ".join(conflicts)}')
    if result.returncode:
        raise ValueError('overlay patch does not apply')
    require_git(worktree, 'reset')


def create_branches(repository: Path, branches: dict[str, str]) -> None:
    commands = ''.join(
        f'create refs/heads/{branch} {commit}\n'
        for branch, commit in branches.items()
    ).encode()
    require_git(repository, 'update-ref', '--stdin', input_data=commands)


def apply(arguments: argparse.Namespace) -> None:
    repository = Path(arguments.repository)
    if not repository.is_dir():
        raise ValueError('repository does not exist')
    require_clean_checkout(repository)
    plan_data = read_apply_plan(arguments.plan)
    try:
        validate_core_archive(
            arguments.core_commit,
            arguments.core_archive_url,
            arguments.core_archive_sha256,
        )
    except ValueError:
        raise ValueError('missing immutable core archive metadata')
    action, source_release, sync_branch = validate_apply_plan(repository, plan_data)
    metadata = source_metadata(repository, source_release)
    paths = overlay_paths(repository, source_release)
    patch = git_result(
        repository,
        'diff', '--binary', f"{metadata['upstream_commit']}..{source_release}", '--', *paths,
    )
    if patch.returncode:
        raise ValueError('unable to create overlay patch')
    base = plan_data['upstream_commit']
    new_metadata = metadata_for(
        plan_data,
        arguments.core_commit,
        arguments.core_archive_url,
        arguments.core_archive_sha256,
    )

    def build_commits(worktree: Path):
        apply_overlay(worktree, patch.stdout)
        if action == 'bootstrap-review':
            commit_worktree(
                worktree, [], new_metadata, 'bootstrap resolver plugin release',
            )
            target_commit = require_git(worktree, 'rev-parse', 'HEAD')
            commit_worktree(
                worktree, paths, new_metadata, 'bootstrap resolver plugin overlay',
            )
            return target_commit, require_git(worktree, 'rev-parse', 'HEAD')
        message = (
            'bootstrap resolver plugin release'
            if action == 'bootstrap-build'
            else 'sync BIND overlay'
        )
        commit_worktree(worktree, paths, new_metadata, message)
        return require_git(worktree, 'rev-parse', 'HEAD')

    commits = with_worktree(repository, base, build_commits, detached=True)
    if action == 'bootstrap-review':
        target_commit, sync_commit = commits
        create_branches(
            repository,
            {
                plan_data['target_release']: target_commit,
                sync_branch: sync_commit,
            },
        )
    elif action == 'bootstrap-build':
        create_branches(repository, {plan_data['target_release']: commits})
    else:
        create_branches(repository, {sync_branch: commits})


def main() -> None:
    parser = argparse.ArgumentParser()
    commands = parser.add_subparsers(dest='command', required=True)
    plan_parser = commands.add_parser('plan')
    plan_parser.add_argument('--repository', required=True)
    plan_parser.add_argument('--upstream', required=True)
    plan_parser.add_argument('--release-prefix', required=True)
    plan_parser.add_argument('--metadata-path', required=True)
    plan_parser.add_argument('--release-notes-directory')
    apply_parser = commands.add_parser('apply')
    apply_parser.add_argument('--repository', required=True)
    apply_parser.add_argument('--plan', required=True)
    apply_parser.add_argument('--core-commit', required=True)
    apply_parser.add_argument('--core-archive-url', required=True)
    apply_parser.add_argument('--core-archive-sha256', required=True)
    arguments = parser.parse_args()
    if arguments.command == 'plan':
        print(json.dumps(plan(arguments), sort_keys=True))
    elif arguments.command == 'apply':
        try:
            apply(arguments)
        except ValueError as error:
            parser.error(str(error))


if __name__ == '__main__':
    main()
