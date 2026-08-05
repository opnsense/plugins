import json
import os
import pathlib
import subprocess


REPOSITORY_ROOT = pathlib.Path(__file__).resolve().parents[3]
SETUP_SCRIPT = REPOSITORY_ROOT / '.github/ci/setup-opnsense-repository.sh'
UPSTREAM_COMMIT = '6f3937f938377464534ebebde66cc13d84186542'


def git(directory: pathlib.Path, *arguments: str) -> str:
    return subprocess.run(
        ['git', '-C', directory, *arguments], check=True, text=True, capture_output=True
    ).stdout.strip()


def create_core_repository(path: pathlib.Path) -> str:
    git(path.parent, 'init', path)
    git(path, 'config', 'user.name', 'CI test')
    git(path, 'config', 'user.email', 'ci@example.invalid')
    template = path / 'src/etc/pkg/repos/OPNsense.conf.shadow.in'
    template.parent.mkdir(parents=True)
    template.write_text(
        'OPNsense: {\n'
        '  url: "%%CORE_PACKAGESITE%%/${ABI}/%%CORE_ABI%%/latest",\n'
        '  signature_type: "fingerprints",\n'
        '  enabled: yes\n'
        '}\n',
        encoding='utf-8',
    )
    fingerprint = path / 'src/etc/pkg/fingerprints/OPNsense/trusted/pkg.opnsense.org.fixture'
    fingerprint.parent.mkdir(parents=True)
    fingerprint.write_text('function: "sha256"\nfingerprint: "fixture"\n', encoding='utf-8')
    git(path, 'add', 'src')
    git(path, 'commit', '-m', 'fixture core')
    return git(path, 'rev-parse', 'HEAD')


def write_upstream_metadata(path: pathlib.Path, core_commit: str, archive_sha256: str = 'a' * 64) -> None:
    path.write_text(
        json.dumps(
            {
                'series': '26.1',
                'upstream_branch': 'stable/26.1',
                'upstream_commit': UPSTREAM_COMMIT,
                'tools_tag': '26.1.11',
                'freebsd_release': '14.3',
                'core_commit': core_commit,
                'core_archive_url': f'https://github.com/opnsense/core/archive/{core_commit}.tar.gz',
                'core_archive_sha256': archive_sha256,
            }
        ),
        encoding='utf-8',
    )


def environment(tmp_path: pathlib.Path, core_repository: pathlib.Path, metadata: pathlib.Path) -> dict[str, str]:
    result = os.environ.copy()
    result.update(
        {
            'OPNSENSE_CORE_REPOSITORY': str(core_repository),
            'PKG_REPOS_DIR': str(tmp_path / 'repos'),
            'PKG_FINGERPRINTS_DIR': str(tmp_path / 'fingerprints' / 'OPNsense'),
            'RP_UPSTREAM_METADATA': str(metadata),
        }
    )
    return result


def test_requires_immutable_upstream_metadata(tmp_path):
    result = subprocess.run([SETUP_SCRIPT, '26.1'], text=True, capture_output=True, check=False)
    assert result.returncode != 0
    assert 'RP_UPSTREAM_METADATA is required' in result.stderr


def test_checks_out_exact_pinned_core_commit_and_installs_fingerprints(tmp_path):
    core = tmp_path / 'core'
    commit = create_core_repository(core)
    metadata = tmp_path / 'upstream.json'
    write_upstream_metadata(metadata, commit)
    result = subprocess.run(
        [SETUP_SCRIPT, '26.1'], text=True, capture_output=True, check=False,
        env=environment(tmp_path, core, metadata),
    )
    assert result.returncode == 0, result.stderr
    assert result.stdout == f'{commit}\n'
    assert 'https://pkg.opnsense.org/${ABI}/26.1/latest' in (tmp_path / 'repos/OPNsense.conf').read_text()
    assert (tmp_path / 'repos/FreeBSD.conf').read_text() == 'FreeBSD: {\n  enabled: no\n}\n'
    assert (tmp_path / 'fingerprints/OPNsense/trusted/pkg.opnsense.org.fixture').is_file()


def test_rejects_a_core_repository_without_the_pinned_commit(tmp_path):
    core = tmp_path / 'core'
    create_core_repository(core)
    metadata = tmp_path / 'upstream.json'
    write_upstream_metadata(metadata, '0' * 40)
    result = subprocess.run(
        [SETUP_SCRIPT, '26.1'], text=True, capture_output=True, check=False,
        env=environment(tmp_path, core, metadata),
    )
    assert result.returncode != 0


def test_legacy_archive_digest_does_not_replace_git_commit_verification(tmp_path):
    core = tmp_path / 'core'
    commit = create_core_repository(core)
    metadata = tmp_path / 'upstream.json'
    write_upstream_metadata(metadata, commit, '0' * 64)
    result = subprocess.run(
        [SETUP_SCRIPT, '26.1'], text=True, capture_output=True, check=False,
        env=environment(tmp_path, core, metadata),
    )
    assert result.returncode == 0, result.stderr
    assert result.stdout == f'{commit}\n'
