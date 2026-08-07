import json
import hashlib
import os
import pathlib
import shutil
import subprocess
import tempfile


REPOSITORY_ROOT = pathlib.Path(__file__).resolve().parents[3]
OPNSENSE_26_1_ARCHIVE_SHA256 = (
    '95cb9d549165520de984adbe7bd740ca237dd470b779d7ef3706d5f11b8c321e'
)
UPSTREAM_COMMIT = '6f3937f938377464534ebebde66cc13d84186542'
FREEBSD_RELEASE = '14.3'
TARGET_ARCHIVE_BYTES = b'fixture target package archive\n'
TARGET_STATIC_BYTES = (
    b'#!/bin/sh\n[ "$1" = -v ] || exit 64\nprintf \'%s\\n\' \'2.3.1\'\n'
)


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
        'OPNsense: {\n  url: "%%CORE_PACKAGESITE%%/${ABI}/%%CORE_ABI%%/latest",\n'
        '  signature_type: "fingerprints",\n  enabled: yes\n}\n', encoding='utf-8'
    )
    fingerprint = path / 'src/etc/pkg/fingerprints/OPNsense/trusted/pkg.fixture'
    fingerprint.parent.mkdir(parents=True)
    fingerprint.write_text('fixture\n', encoding='utf-8')
    git(path, 'add', 'src')
    git(path, 'commit', '-m', 'fixture core')
    return git(path, 'rev-parse', 'HEAD')


def write_upstream_metadata(path: pathlib.Path, core_commit: str) -> None:
    path.write_text(
        json.dumps(
            {
                'series': '26.1',
                'upstream_branch': 'stable/26.1',
                'upstream_commit': UPSTREAM_COMMIT,
                'tools_tag': '26.1.11',
                'freebsd_release': FREEBSD_RELEASE,
                'core_commit': core_commit,
                'core_archive_url': (
                    f'https://github.com/opnsense/core/archive/{core_commit}.tar.gz'
                ),
                'core_archive_sha256': OPNSENSE_26_1_ARCHIVE_SHA256,
            }
        )
    )


def configure_target_pkg_fixture(
    environment: dict[str, str], directory: pathlib.Path, executable_directory: pathlib.Path
) -> None:
    metadata = directory / 'target-pkg.json'
    record = {
        'name': 'pkg',
        'version': '2.3.1_1',
        'origin': 'ports-mgmt/pkg',
        'abi': 'FreeBSD:14:amd64',
        'filename': 'pkg-2.3.1_1.pkg',
        'sha256': hashlib.sha256(TARGET_ARCHIVE_BYTES).hexdigest(),
        'pkg_static_sha256': hashlib.sha256(TARGET_STATIC_BYTES).hexdigest(),
    }
    metadata.write_text(
        json.dumps(
            {
                'schema': 1,
                'series': {
                    '26.1': record,
                    '26.7': dict(record, abi='FreeBSD:15:amd64'),
                },
            }
        ),
        encoding='utf-8',
    )
    environment['RP_TARGET_PKG_METADATA'] = str(metadata)
    environment['RP_PKG_STATIC_COMMAND'] = str(executable_directory / 'pkg-static')
    environment['PKG_STATIC_PATH'] = str(executable_directory / 'pkg-static')
    environment['PKG_LOCK_MARKER'] = str(directory / 'pkg.locked')


def materialize_build_repository(request) -> pathlib.Path:
    local_tests = REPOSITORY_ROOT / '.github/ci-local'
    local_tests.mkdir(exist_ok=True)
    build_repository = pathlib.Path(
        tempfile.mkdtemp(prefix='build-os-bind-rp-', dir=local_tests)
    )
    request.addfinalizer(lambda: shutil.rmtree(build_repository, ignore_errors=True))
    shutil.copytree(
        REPOSITORY_ROOT / '.github',
        build_repository / '.github',
        ignore=shutil.ignore_patterns('ci-local', '__pycache__', '.pytest_cache'),
    )
    shutil.copytree(
        REPOSITORY_ROOT / 'dns/bind',
        build_repository / 'dns/bind',
        ignore=shutil.ignore_patterns('work', '__pycache__', '.pytest_cache'),
    )
    shutil.copytree(
        REPOSITORY_ROOT / '.resolver-plugins',
        build_repository / '.resolver-plugins',
        ignore=shutil.ignore_patterns('__pycache__', '.pytest_cache'),
    )
    return build_repository


def test_materialize_build_repository_creates_a_disposable_copy(request):
    build_repository = materialize_build_repository(request)

    assert build_repository != REPOSITORY_ROOT
    assert build_repository.parent == REPOSITORY_ROOT / '.github/ci-local'
    assert (build_repository / '.github/ci/build-os-bind-rp.sh').is_file()
    assert (build_repository / 'dns/bind').is_dir()
    assert not (build_repository / 'dns/bind/work').exists()


def test_build_wrapper_creates_package_and_metadata_for_26_1(tmp_path, request):
    build_repository = materialize_build_repository(request)
    build_script = build_repository / '.github/ci/build-os-bind-rp.sh'
    core = tmp_path / 'core'
    core_commit = create_core_repository(core)
    environment = os.environ.copy()
    environment['MAKE_COMMAND'] = str(
        build_repository / '.github/ci/ci-tests/make-package-fixture.sh'
    )
    environment['PKG_COMMAND'] = str(
        build_repository / '.github/ci/ci-tests/pkg-build-fixture.sh'
    )
    python_command = build_repository / 'python3-fixture'
    environment['PYTHON_COMMAND'] = str(python_command)
    environment['GIT_CONFIG_GLOBAL'] = str(tmp_path / 'gitconfig')
    environment['OPNSENSE_CORE_REPOSITORY'] = str(core)
    environment['PKG_REPOS_DIR'] = str(tmp_path / 'repos')
    environment['PKG_FINGERPRINTS_DIR'] = str(tmp_path / 'fingerprints' / 'OPNsense')
    metadata_path = tmp_path / 'upstream.json'
    write_upstream_metadata(metadata_path, core_commit)
    environment['RP_UPSTREAM_METADATA'] = str(metadata_path)
    package_call_log = tmp_path / 'pkg-calls.log'
    environment['PKG_CALL_LOG'] = str(package_call_log)
    configure_target_pkg_fixture(environment, tmp_path, build_repository)

    assert build_script.is_file(), 'non-publishing build wrapper is missing'
    result = subprocess.run(
        [build_script, '26.1', str(tmp_path)],
        cwd=build_repository,
        text=True,
        capture_output=True,
        check=False,
        env=environment,
    )

    assert result.returncode == 0, result.stderr
    assert python_command.is_file()
    assert (tmp_path / 'os-bind-rp-1.36_3.pkg').is_file()
    assert (tmp_path / 'repos' / 'OPNsense.conf').is_file()
    metadata = (tmp_path / 'build-metadata.txt').read_text()
    assert 'series=26.1\n' in metadata
    assert 'pkg_abi=FreeBSD:14:amd64\n' in metadata
    assert 'bind920=9.20.26\n' in metadata
    assert 'bind_source=opnsense\n' in metadata
    assert 'opnsense=26.1.11_10\n' in metadata
    assert 'switch_test=' not in metadata
    assert f'upstream_commit={UPSTREAM_COMMIT}\n' in metadata
    assert f'core_commit={core_commit}\n' in metadata
    assert 'tools_tag=26.1.11\n' in metadata
    assert f'freebsd_release={FREEBSD_RELEASE}\n' in metadata
    assert 'source_commit=unknown\n' in metadata
    assert 'pkg_creator=2.3.1_1\n' in metadata
    assert f"pkg_creator_sha256={hashlib.sha256(TARGET_ARCHIVE_BYTES).hexdigest()}\n" in metadata
    package_calls = package_call_log.read_text().splitlines()
    assert 'update -f' in package_calls
    assert 'install -y python3' in package_calls
    assert 'install -y git' in package_calls
    assert 'install -y bind920' in package_calls
    target_fetch = next(call for call in package_calls if call.startswith('fetch '))
    assert target_fetch.endswith('pkg-2.3.1_1')
    target_add_index = next(
        index for index, call in enumerate(package_calls)
        if call.startswith('add -f ') and 'pkg-2.3.1_1.pkg' in call
    )
    assert target_add_index < package_calls.index('install -y bind920')
    assert package_calls.count('lock -l') >= 3
    safe_directories = subprocess.run(
        ['git', 'config', '--global', '--get-all', 'safe.directory'],
        text=True,
        capture_output=True,
        check=False,
        env=environment,
    )
    assert safe_directories.returncode == 0, safe_directories.stderr
    assert build_repository.as_posix() in safe_directories.stdout.splitlines()


def test_build_wrapper_requests_resolver_fallback_for_an_ineligible_opnsense_bind(tmp_path, request):
    build_repository = materialize_build_repository(request)
    build_script = build_repository / '.github/ci/build-os-bind-rp.sh'
    core = tmp_path / 'core'
    core_commit = create_core_repository(core)
    environment = os.environ.copy()
    environment['MAKE_COMMAND'] = str(build_repository / '.github/ci/ci-tests/make-package-fixture.sh')
    environment['PKG_COMMAND'] = str(build_repository / '.github/ci/ci-tests/pkg-build-fixture.sh')
    environment['PYTHON_COMMAND'] = 'python3'
    environment['GIT_CONFIG_GLOBAL'] = str(tmp_path / 'gitconfig')
    environment['OPNSENSE_CORE_REPOSITORY'] = str(core)
    environment['PKG_REPOS_DIR'] = str(tmp_path / 'repos')
    environment['PKG_FINGERPRINTS_DIR'] = str(tmp_path / 'fingerprints' / 'OPNsense')
    environment['PKG_VERSION_COMPARISON'] = '<'
    metadata_path = tmp_path / 'upstream.json'
    write_upstream_metadata(metadata_path, core_commit)
    environment['RP_UPSTREAM_METADATA'] = str(metadata_path)
    configure_target_pkg_fixture(environment, tmp_path, build_repository)

    result = subprocess.run(
        [build_script, '26.1', str(tmp_path / 'artifacts')],
        cwd=build_repository,
        text=True,
        capture_output=True,
        check=False,
        env=environment,
    )

    assert result.returncode == 3
