import json
import os
import pathlib
import subprocess

import pytest


REPOSITORY_ROOT = pathlib.Path(__file__).resolve().parents[3]
METADATA_PROFILE = pathlib.Path(
    os.environ.get(
        'METADATA_PROFILE', REPOSITORY_ROOT / '.github/ci/metadata_profile.py'
    )
)
UPSTREAM_COMMIT = '6f3937f938377464534ebebde66cc13d84186542'
CORE_COMMIT = '8cc69b21e0f4c2622fc8a62df2a15ba7cb1e731f'
CORE_ARCHIVE_SHA256 = (
    '95cb9d549165520de984adbe7bd740ca237dd470b779d7ef3706d5f11b8c321e'
)


def metadata() -> dict[str, str]:
    return {
        'series': '26.1',
        'upstream_branch': 'stable/26.1',
        'upstream_commit': UPSTREAM_COMMIT,
        'tools_tag': '26.1.11',
        'freebsd_release': '14.3',
        'core_commit': CORE_COMMIT,
        'core_archive_url': (
            f'https://github.com/opnsense/core/archive/{CORE_COMMIT}.tar.gz'
        ),
        'core_archive_sha256': CORE_ARCHIVE_SHA256,
    }


@pytest.mark.parametrize(
    ('field', 'invalid_value'),
    (
        ('core_commit', 'refs/heads/stable/26.1'),
        ('core_commit', CORE_COMMIT.upper()),
        ('upstream_commit', 'refs/heads/stable/26.1'),
        ('upstream_commit', UPSTREAM_COMMIT.upper()),
        ('core_archive_sha256', 'not-a-sha256'),
        ('core_archive_sha256', CORE_ARCHIVE_SHA256.upper()),
        ('upstream_branch', 'stable/26.7'),
        ('tools_tag', '26.7.1'),
        ('tools_tag', '26.1.r1'),
        ('freebsd_release', 'not-a-release'),
    ),
)
def test_rejects_invalid_strict_profile_fields(tmp_path, field, invalid_value):
    profile = metadata()
    profile[field] = invalid_value
    if field == 'core_commit':
        profile['core_archive_url'] = (
            f'https://github.com/opnsense/core/archive/{invalid_value}.tar.gz'
        )
    metadata_path = tmp_path / 'upstream.json'
    metadata_path.write_text(json.dumps(profile))

    result = subprocess.run(
        ['python3', METADATA_PROFILE, metadata_path, '26.1', 'core_commit'],
        text=True,
        capture_output=True,
        check=False,
    )

    assert result.returncode != 0
