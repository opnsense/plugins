"""Durable regression tests for target-readable package file checksums."""

from __future__ import annotations

import importlib.util
import shlex
import tempfile
from contextlib import contextmanager
from collections.abc import Iterator
from pathlib import Path

import pytest


MODULE_PATH = Path(__file__).resolve().parents[1] / "package_checksums.py"
SPEC = importlib.util.spec_from_file_location("package_checksums", MODULE_PATH)
assert SPEC is not None and SPEC.loader is not None
package_checksums = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(package_checksums)
FIXTURE_ROOT = Path(__file__).resolve().parents[1] / "ci-local"


@contextmanager
def pkg_fixture(output: str) -> Iterator[Path]:
    """Return a pkg boundary fixture with one hand-written query result."""
    FIXTURE_ROOT.mkdir(parents=True, exist_ok=True)
    with tempfile.TemporaryDirectory(dir=FIXTURE_ROOT) as directory:
        executable = Path(directory) / "pkg"
        executable.write_text(
            "#!/bin/sh\n"
            "[ \"$1\" = query ] || exit 64\n"
            "[ \"$2\" = -F ] || exit 64\n"
            "[ \"$4\" = '%Fp|%Fs' ] || exit 64\n"
            f"printf %s {shlex.quote(output)}\n",
            encoding="utf-8",
        )
        executable.chmod(0o755)
        yield executable


def test_accepts_complete_target_readable_file_checksums(tmp_path: Path) -> None:
    archive = tmp_path / "bind920.pkg"
    archive.touch()
    checksum = "1$" + "a" * 64
    with pkg_fixture(f"/usr/local/sbin/named|{checksum}\n") as pkg:
        rows = package_checksums.verify_archive(str(pkg), archive)

    assert rows == (("/usr/local/sbin/named", checksum),)


@pytest.mark.parametrize(
    "output",
    ["", "/usr/local/sbin/named|(null)\n", "/usr/local/sbin/named|\n"],
)
def test_rejects_missing_or_null_file_checksums(tmp_path: Path, output: str) -> None:
    archive = tmp_path / "bind920.pkg"
    archive.touch()
    with pkg_fixture(output) as pkg:
        with pytest.raises(package_checksums.PackageChecksumError, match="bind920.pkg"):
            package_checksums.verify_archive(str(pkg), archive)


def test_rejects_malformed_checksum_rows(tmp_path: Path) -> None:
    archive = tmp_path / "bind920.pkg"
    archive.touch()
    with pkg_fixture("not-a-file-checksum-row\n") as pkg:
        with pytest.raises(package_checksums.PackageChecksumError, match="malformed"):
            package_checksums.verify_archive(str(pkg), archive)
