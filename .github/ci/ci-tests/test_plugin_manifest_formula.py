"""Regression coverage for optional plugin dependency solver formulas."""

from __future__ import annotations

from pathlib import Path


REPOSITORY_ROOT = Path(__file__).resolve().parents[3]


def test_framework_emits_an_explicit_dependency_formula_when_requested():
    framework = (REPOSITORY_ROOT / "Mk/plugins.mk").read_text(encoding="utf-8")

    assert ".if defined(PLUGIN_DEPEND_FORMULA)" in framework
    assert '@echo "dep_formula: \\"${PLUGIN_DEPEND_FORMULA}\\""' in framework
    assert "PLUGIN_DEPEND_FORMULA_DEPENDS" in framework
    assert "continue;;" in framework


def test_bind_declares_a_minimum_solver_constraint_not_a_bundled_revision():
    makefile = (REPOSITORY_ROOT / "dns/bind/Makefile").read_text(encoding="utf-8")

    assert "PLUGIN_DEPEND_FORMULA=\tbind920 >= 9.20.26" in makefile
    assert "PLUGIN_DEPEND_FORMULA_DEPENDS=\tbind920" in makefile
