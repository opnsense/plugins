from pathlib import Path


ROOT = Path(__file__).resolve().parents[3]
VERIFIER = ROOT / ".github" / "ci" / "verify-bind-runtime.sh"


def test_runtime_verifier_checks_config_restarts_managed_bind_and_answers_a_query():
    assert VERIFIER.is_file()
    assert VERIFIER.stat().st_mode & 0o111
    text = VERIFIER.read_text(encoding="utf-8")
    assert "named-checkconf" in text
    assert "service named onerestart" in text
    assert "drill" in text
    assert "canary.invalid" in text
    assert "192.0.2.53" in text
    assert "trap" in text
