#!/usr/bin/env python3
"""Publish validated synchronization commits through GitHub's create-only APIs."""

import argparse
import base64
import json
import re
import subprocess
from datetime import datetime, timedelta, timezone
from pathlib import Path
from typing import Any
from urllib.parse import quote, urlencode


METADATA_PATH = '.resolver-plugins/upstream.json'
PLAN_FIELDS = {
    'action', 'series', 'upstream_ref', 'upstream_commit', 'source_release',
    'target_release', 'sync_branch', 'freebsd_release', 'bind_changed', 'reason',
}
SERIES_PATTERN = re.compile(r'^\d+\.\d+$')
SYNC_PATTERN = re.compile(r'^sync/(bootstrap|bind)/(\d+\.\d+)/([0-9a-f]{12})$')
IDENTITY_PATTERN = re.compile(r'^(.*) <([^>]*)> ([0-9]+) ([+-])([0-9]{2})([0-9]{2})$')


def local_git(repository: Path, *arguments: str, binary: bool = False) -> str | bytes:
    result = subprocess.run(
        ['git', '-C', str(repository), *arguments],
        check=False,
        capture_output=True,
    )
    if result.returncode:
        raise ValueError(result.stderr.decode(errors='replace').strip() or 'Git command failed')
    return result.stdout if binary else result.stdout.decode().strip()


def local_commit(repository: Path, branch: str) -> str:
    commit = local_git(repository, 'rev-parse', f'refs/heads/{branch}^{{commit}}')
    if not isinstance(commit, str) or not re.fullmatch(r'[0-9a-f]{40}', commit):
        raise ValueError(f'missing local branch: {branch}')
    return commit


def metadata_at(repository: Path, revision: str) -> dict[str, str]:
    try:
        metadata = json.loads(local_git(repository, 'show', f'{revision}:{METADATA_PATH}'))
    except (ValueError, json.JSONDecodeError):
        raise ValueError(f'missing synchronization metadata at {revision}') from None
    if not isinstance(metadata, dict):
        raise ValueError(f'missing synchronization metadata at {revision}')
    required = ('series', 'upstream_commit')
    if any(not isinstance(metadata.get(field), str) or not metadata[field] for field in required):
        raise ValueError(f'missing synchronization metadata at {revision}')
    return metadata


def valid_release_metadata(
    metadata: dict[str, str],
    series: str,
    upstream_commit: str,
) -> bool:
    required = (
        'series',
        'upstream_branch',
        'upstream_commit',
        'freebsd_release',
        'core_commit',
        'core_archive_url',
        'core_archive_sha256',
    )
    if any(
        not isinstance(metadata.get(field), str) or not metadata[field]
        for field in required
    ):
        return False
    core_commit = metadata['core_commit']
    return (
        metadata['series'] == series
        and metadata['upstream_branch'] == f'stable/{series}'
        and metadata['upstream_commit'] == upstream_commit
        and re.fullmatch(r'[0-9a-f]{40}', upstream_commit) is not None
        and re.fullmatch(r'[0-9a-f]{40}', core_commit) is not None
        and metadata['core_archive_url']
        == f'https://github.com/opnsense/core/archive/{core_commit}.tar.gz'
        and re.fullmatch(r'[0-9a-f]{64}', metadata['core_archive_sha256']) is not None
    )


def valid_sync_only_bootstrap(
    repository: Path,
    sync_commit: str,
    target_commit: str,
    series: str,
    sync_metadata: dict[str, str],
    target_metadata: dict[str, str],
) -> bool:
    upstream_commit = sync_metadata['upstream_commit']
    if (
        sync_metadata != target_metadata
        or not valid_release_metadata(target_metadata, series, upstream_commit)
    ):
        return False
    try:
        current_upstream = local_git(
            repository,
            'rev-parse',
            f'refs/remotes/upstream/stable/{series}^{{commit}}',
        )
        sync_parents = local_git(
            repository, 'rev-list', '--parents', '-n', '1', sync_commit
        ).split()
        target_parents = local_git(
            repository, 'rev-list', '--parents', '-n', '1', target_commit
        ).split()
        changed_paths = local_git(
            repository,
            'diff-tree',
            '--no-commit-id',
            '--name-only',
            '-r',
            upstream_commit,
            target_commit,
        ).splitlines()
    except ValueError:
        return False
    return (
        current_upstream == upstream_commit
        and sync_parents == [sync_commit, target_commit]
        and target_parents == [target_commit, upstream_commit]
        and changed_paths == [METADATA_PATH]
    )


def api_date(identity: str) -> dict[str, str]:
    match = IDENTITY_PATTERN.fullmatch(identity)
    if not match:
        raise ValueError('unsupported local commit identity')
    name, email, timestamp, sign, hours, minutes = match.groups()
    offset = timedelta(hours=int(hours), minutes=int(minutes))
    if sign == '-':
        offset = -offset
    date = datetime.fromtimestamp(int(timestamp), timezone(offset)).isoformat(timespec='seconds')
    return {'name': name, 'email': email, 'date': date}


def commit_data(repository: Path, commit_sha: str) -> dict[str, Any]:
    raw_bytes = local_git(repository, 'cat-file', 'commit', commit_sha, binary=True)
    if not isinstance(raw_bytes, bytes):
        raise ValueError('cannot inspect local commit')
    raw = raw_bytes.decode()
    headers, separator, message = raw.partition('\n\n')
    if not separator:
        raise ValueError('cannot inspect local commit')
    values: dict[str, list[str]] = {}
    for line in headers.splitlines():
        key, _, value = line.partition(' ')
        values.setdefault(key, []).append(value)
    if len(values.get('tree', [])) != 1 or len(values.get('parent', [])) != 1:
        raise ValueError('synchronization commits must have exactly one parent')
    if len(values.get('author', [])) != 1 or len(values.get('committer', [])) != 1:
        raise ValueError('cannot inspect local commit identity')
    return {
        'tree': values['tree'][0],
        'parent_tree': local_git(repository, 'rev-parse', f"{values['parent'][0]}^{{tree}}"),
        'parent': values['parent'][0],
        'author': api_date(values['author'][0]),
        'committer': api_date(values['committer'][0]),
        'message': message,
    }


class GitHub:
    """Small create-only GitHub API client backed by the authenticated gh CLI."""

    def call(
        self,
        method: str,
        endpoint: str,
        payload: dict[str, Any] | None = None,
    ) -> Any:
        command = ['gh', 'api', '--method', method, endpoint]
        input_data = None
        if payload is not None:
            command.extend(['--input', '-'])
            input_data = json.dumps(payload).encode()
        result = subprocess.run(command, input=input_data, capture_output=True, check=False)
        if result.returncode:
            message = result.stderr.decode(errors='replace').strip()
            raise ValueError(message or f'GitHub API {method} {endpoint} failed')
        if not result.stdout.strip():
            return None
        try:
            return json.loads(result.stdout)
        except json.JSONDecodeError:
            raise ValueError('GitHub API returned invalid JSON') from None

    def check_assignee(self, repository: str, reviewer: str) -> None:
        self.call('GET', f'repos/{repository}/assignees/{quote(reviewer, safe="")}')

    def publish_commit(self, local_repository: Path, repository: str, commit_sha: str) -> None:
        data = commit_data(local_repository, commit_sha)
        changed = local_git(
            local_repository,
            'diff-tree', '--no-commit-id', '--name-status', '-r', '-z', '--no-renames',
            data['parent'], commit_sha,
            binary=True,
        )
        if not isinstance(changed, bytes):
            raise ValueError('cannot inspect local commit changes')
        fields = changed.split(b'\0')
        if fields and fields[-1] == b'':
            fields.pop()
        if len(fields) % 2:
            raise ValueError('cannot inspect local commit changes')
        entries = []
        for index in range(0, len(fields), 2):
            status = fields[index].decode()
            path = fields[index + 1].decode()
            if status == 'D':
                entries.append({'path': path, 'sha': None})
                continue
            if status not in {'A', 'M', 'T'}:
                raise ValueError(f'unsupported local tree change: {status}')
            tree_line = local_git(local_repository, 'ls-tree', '-z', commit_sha, '--', path)
            if not isinstance(tree_line, str) or '\t' not in tree_line:
                raise ValueError(f'cannot inspect local tree entry: {path}')
            attributes, listed_path = tree_line.split('\t', maxsplit=1)
            mode, object_type, object_sha = attributes.split()
            if listed_path != path:
                raise ValueError(f'cannot inspect local tree entry: {path}')
            if object_type == 'blob':
                contents = local_git(local_repository, 'cat-file', 'blob', object_sha, binary=True)
                if not isinstance(contents, bytes):
                    raise ValueError(f'cannot read local blob: {path}')
                created = self.call(
                    'POST',
                    f'repos/{repository}/git/blobs',
                    {
                        'content': base64.b64encode(contents).decode(),
                        'encoding': 'base64',
                    },
                )
                if not isinstance(created, dict) or created.get('sha') != object_sha:
                    raise ValueError(f'GitHub did not reproduce local blob: {path}')
            elif object_type != 'commit':
                raise ValueError(f'unsupported local tree object: {object_type}')
            entries.append(
                {'path': path, 'mode': mode, 'type': object_type, 'sha': object_sha}
            )
        created_tree = self.call(
            'POST',
            f'repos/{repository}/git/trees',
            {'base_tree': data['parent_tree'], 'tree': entries},
        )
        if not isinstance(created_tree, dict):
            raise ValueError('GitHub did not create the local tree')
        if created_tree.get('sha') != data['tree']:
            raise ValueError('GitHub did not reproduce the local tree')
        created_commit = self.call(
            'POST',
            f'repos/{repository}/git/commits',
            {
                'message': data['message'],
                'tree': data['tree'],
                'parents': [data['parent']],
                'author': data['author'],
                'committer': data['committer'],
            },
        )
        if not isinstance(created_commit, dict) or created_commit.get('sha') != commit_sha:
            raise ValueError('GitHub did not reproduce the local commit')

    def ref_sha(self, repository: str, branch: str) -> str | None:
        encoded_branch = quote(branch, safe='/')
        matches = self.call(
            'GET', f'repos/{repository}/git/matching-refs/heads/{encoded_branch}'
        )
        if not isinstance(matches, list):
            raise ValueError('GitHub returned invalid reference data')
        exact = [
            item for item in matches
            if isinstance(item, dict) and item.get('ref') == f'refs/heads/{branch}'
        ]
        if not exact:
            return None
        if len(exact) != 1 or not isinstance(exact[0].get('object'), dict):
            raise ValueError(f'GitHub returned ambiguous reference data for {branch}')
        sha = exact[0]['object'].get('sha')
        if not isinstance(sha, str):
            raise ValueError(f'GitHub returned invalid reference data for {branch}')
        return sha

    def create_ref(self, repository: str, branch: str, commit_sha: str) -> None:
        try:
            self.call(
                'POST',
                f'repos/{repository}/git/refs',
                {'ref': f'refs/heads/{branch}', 'sha': commit_sha},
            )
        except ValueError:
            if self.ref_sha(repository, branch) == commit_sha:
                return
            raise

    def pulls_for(self, repository: str, head: str, base: str) -> list[dict[str, Any]]:
        owner = repository.split('/', maxsplit=1)[0]
        query = urlencode({'state': 'all', 'head': f'{owner}:{head}', 'base': base})
        pulls = self.call('GET', f'repos/{repository}/pulls?{query}')
        if not isinstance(pulls, list):
            raise ValueError('GitHub returned invalid pull request data')
        normalized = []
        for pull in pulls:
            try:
                normalized.append(
                    {
                        'number': pull['number'],
                        'head': pull['head']['ref'],
                        'base': pull['base']['ref'],
                        'state': pull['state'],
                        'assignees': [assignee['login'] for assignee in pull['assignees']],
                    }
                )
            except (KeyError, TypeError):
                raise ValueError('GitHub returned invalid pull request data') from None
        return normalized

    def create_pull(
        self, repository: str, head: str, base: str, title: str, body: str
    ) -> dict[str, Any]:
        pull = self.call(
            'POST',
            f'repos/{repository}/pulls',
            {'head': head, 'base': base, 'title': title, 'body': body},
        )
        if not isinstance(pull, dict) or not isinstance(pull.get('number'), int):
            raise ValueError('GitHub returned invalid pull request data')
        return {
            'number': pull['number'],
            'head': head,
            'base': base,
            'state': pull.get('state', 'open'),
            'assignees': [item['login'] for item in pull.get('assignees', [])],
        }

    def assign_pull(self, repository: str, number: int, reviewer: str) -> None:
        self.call(
            'POST',
            f'repos/{repository}/issues/{number}/assignees',
            {'assignees': [reviewer]},
        )


def ensure_ref(
    github: GitHub,
    repository: str,
    branch: str,
    expected_commit: str,
) -> None:
    current = github.ref_sha(repository, branch)
    if current == expected_commit:
        return
    if current is not None:
        raise ValueError(f'refusing to replace {branch}: it points to a different commit')
    github.create_ref(repository, branch, expected_commit)
    if github.ref_sha(repository, branch) != expected_commit:
        raise ValueError(f'GitHub did not create the exact reference {branch}')


def ensure_existing_ref(
    github: GitHub,
    repository: str,
    branch: str,
    expected_commit: str,
) -> None:
    if github.ref_sha(repository, branch) != expected_commit:
        raise ValueError(f'remote reference {branch} does not match the fetched commit')


def review_text(series: str, source_commit: str, upstream_commit: str) -> tuple[str, str]:
    comparison = (
        f'https://github.com/opnsense/plugins/compare/{source_commit}...{upstream_commit}'
    )
    return (
        f'Review upstream BIND synchronization for {series}',
        f'Review the upstream BIND delta before merging. Upstream comparison: {comparison}',
    )


def ensure_pull(
    github: GitHub,
    repository: str,
    head: str,
    base: str,
    reviewer: str,
    title: str,
    body: str,
) -> dict[str, Any]:
    pulls = github.pulls_for(repository, head, base)
    pull = next((item for item in pulls if item.get('state') == 'open'), None)
    if pull is None:
        pull = github.create_pull(repository, head, base, title, body)
    if reviewer not in pull.get('assignees', []):
        github.assign_pull(repository, pull['number'], reviewer)
    return pull


def validate_plan(plan: dict[str, Any]) -> tuple[str, str, str, str | None]:
    if not isinstance(plan, dict) or set(plan) != PLAN_FIELDS:
        raise ValueError('missing or invalid publication plan')
    action = plan.get('action')
    if action not in {'bootstrap-build', 'bootstrap-review', 'update-review'}:
        raise ValueError('unknown publication action')
    series = plan.get('series')
    upstream_commit = plan.get('upstream_commit')
    target = plan.get('target_release')
    if (
        not isinstance(series, str)
        or not SERIES_PATTERN.fullmatch(series)
        or not isinstance(upstream_commit, str)
        or not re.fullmatch(r'[0-9a-f]{40}', upstream_commit)
        or target != f'release/bind-rp/{series}'
        or plan.get('upstream_ref') != f'upstream/stable/{series}'
    ):
        raise ValueError('missing or invalid publication plan')
    sync = plan.get('sync_branch')
    expected_sync = None
    if action == 'bootstrap-review':
        expected_sync = f'sync/bootstrap/{series}/{upstream_commit[:12]}'
    elif action == 'update-review':
        expected_sync = f'sync/bind/{series}/{upstream_commit[:12]}'
        if plan.get('source_release') != target:
            raise ValueError('missing or invalid publication plan')
    if sync != expected_sync:
        raise ValueError('missing or invalid publication plan')
    return action, series, target, sync


def publish_plan(
    local_repository: Path,
    plan: dict[str, Any],
    github_repository: str,
    reviewer: str,
    github: GitHub,
) -> None:
    action, series, target, sync = validate_plan(plan)
    target_commit = None
    sync_commit = None
    if action.startswith('bootstrap'):
        target_commit = local_commit(local_repository, target)
    if sync:
        sync_commit = local_commit(local_repository, sync)

    if sync:
        if not reviewer:
            raise ValueError('RP_SYNC_REVIEWER is required for review publication')
        github.check_assignee(github_repository, reviewer)

    if target_commit:
        github.publish_commit(local_repository, github_repository, target_commit)
    if sync_commit:
        github.publish_commit(local_repository, github_repository, sync_commit)

    if sync and sync_commit:
        ensure_ref(github, github_repository, sync, sync_commit)
    if target_commit:
        ensure_ref(github, github_repository, target, target_commit)

    if sync:
        source = plan.get('source_release')
        if not isinstance(source, str):
            raise ValueError('missing or invalid publication plan')
        source_commit = metadata_at(local_repository, source)['upstream_commit']
        title, body = review_text(series, source_commit, plan['upstream_commit'])
        ensure_pull(
            github, github_repository, sync, target, reviewer, title, body
        )


def release_branches(repository: Path) -> dict[str, str]:
    output = local_git(
        repository,
        'for-each-ref',
        '--format=%(refname:strip=4) %(objectname)',
        'refs/heads/release/bind-rp',
    )
    result = {}
    if not isinstance(output, str):
        return result
    for line in output.splitlines():
        series, commit = line.split(maxsplit=1)
        if SERIES_PATTERN.fullmatch(series):
            result[series] = commit
    return result


def series_key(series: str) -> tuple[int, int]:
    major, minor = series.split('.')
    return int(major), int(minor)


def recovery_candidates(repository: Path) -> list[dict[str, Any]]:
    output = local_git(
        repository,
        'for-each-ref',
        '--format=%(refname:strip=3) %(objectname)',
        'refs/remotes/origin/sync',
    )
    if not isinstance(output, str):
        return []
    releases = release_branches(repository)
    candidates = []
    for line in output.splitlines():
        branch, sync_commit = line.split(maxsplit=1)
        match = SYNC_PATTERN.fullmatch(branch)
        if not match:
            continue
        kind, series, abbreviation = match.groups()
        target = f'release/bind-rp/{series}'
        try:
            sync_metadata = metadata_at(repository, sync_commit)
        except ValueError:
            continue
        try:
            target_commit = local_git(
                repository, 'rev-parse', f'refs/remotes/origin/{target}^{{commit}}'
            )
            base_missing = False
        except ValueError:
            if kind != 'bootstrap':
                continue
            try:
                target_commit = local_git(repository, 'rev-parse', f'{sync_commit}^')
            except ValueError:
                continue
            base_missing = True
        try:
            target_metadata = metadata_at(repository, target_commit)
        except ValueError:
            continue
        upstream_commit = sync_metadata['upstream_commit']
        if (
            sync_metadata['series'] != series
            or target_metadata['series'] != series
            or upstream_commit[:12] != abbreviation
        ):
            continue
        if kind == 'bootstrap':
            parent = local_git(repository, 'rev-parse', f'{sync_commit}^')
            source_series = max(
                (item for item in releases if series_key(item) < series_key(series)),
                key=series_key,
                default=None,
            )
            if parent != target_commit or source_series is None:
                continue
            if base_missing and not valid_sync_only_bootstrap(
                repository,
                sync_commit,
                target_commit,
                series,
                sync_metadata,
                target_metadata,
            ):
                continue
            source_commit = metadata_at(
                repository, f'release/bind-rp/{source_series}'
            )['upstream_commit']
        else:
            source_commit = target_metadata['upstream_commit']
        candidates.append(
            {
                'series': series,
                'head': branch,
                'head_commit': sync_commit,
                'base': target,
                'base_commit': target_commit,
                'source_commit': source_commit,
                'upstream_commit': upstream_commit,
                'base_missing': base_missing,
            }
        )
    return candidates


def recover_pending_reviews(
    local_repository: Path,
    github_repository: str,
    reviewer: str,
    github: GitHub,
) -> bool:
    pending = []
    for candidate in recovery_candidates(local_repository):
        if candidate['base_missing']:
            pending.append(candidate)
            continue
        pulls = github.pulls_for(
            github_repository, candidate['head'], candidate['base']
        )
        if any(pull.get('state') == 'open' for pull in pulls) or not pulls:
            pending.append(candidate)
    if not pending:
        return False
    if not reviewer:
        raise ValueError('RP_SYNC_REVIEWER is required for review recovery')
    github.check_assignee(github_repository, reviewer)
    for candidate in pending:
        ensure_existing_ref(
            github,
            github_repository,
            candidate['head'],
            candidate['head_commit'],
        )
        if candidate['base_missing']:
            ensure_ref(
                github,
                github_repository,
                candidate['base'],
                candidate['base_commit'],
            )
        else:
            ensure_existing_ref(
                github,
                github_repository,
                candidate['base'],
                candidate['base_commit'],
            )
        title, body = review_text(
            candidate['series'],
            candidate['source_commit'],
            candidate['upstream_commit'],
        )
        ensure_pull(
            github,
            github_repository,
            candidate['head'],
            candidate['base'],
            reviewer,
            title,
            body,
        )
    return True


def read_plan(path: str) -> dict[str, Any]:
    try:
        plan = json.loads(Path(path).read_text(encoding='utf-8'))
    except (OSError, json.JSONDecodeError):
        raise ValueError('missing or invalid publication plan') from None
    if not isinstance(plan, dict):
        raise ValueError('missing or invalid publication plan')
    return plan


def main() -> None:
    parser = argparse.ArgumentParser()
    commands = parser.add_subparsers(dest='command', required=True)
    publish_parser = commands.add_parser('publish')
    publish_parser.add_argument('--repository', required=True)
    publish_parser.add_argument('--plan', required=True)
    publish_parser.add_argument('--github-repository', required=True)
    publish_parser.add_argument('--reviewer', default='')
    recover_parser = commands.add_parser('recover')
    recover_parser.add_argument('--repository', required=True)
    recover_parser.add_argument('--github-repository', required=True)
    recover_parser.add_argument('--reviewer', default='')
    recover_parser.add_argument('--output', required=True)
    arguments = parser.parse_args()
    github = GitHub()
    try:
        if arguments.command == 'publish':
            publish_plan(
                Path(arguments.repository),
                read_plan(arguments.plan),
                arguments.github_repository,
                arguments.reviewer,
                github,
            )
        else:
            handled = recover_pending_reviews(
                Path(arguments.repository),
                arguments.github_repository,
                arguments.reviewer,
                github,
            )
            with Path(arguments.output).open('a', encoding='utf-8') as output:
                print(f'handled={str(handled).lower()}', file=output)
    except ValueError as error:
        parser.error(str(error))


if __name__ == '__main__':
    main()
