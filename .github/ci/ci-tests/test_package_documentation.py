from pathlib import Path


ROOT = Path(__file__).resolve().parents[3]
USER_GUIDES = (ROOT / "README.md", ROOT / "docs/package-repository.md", ROOT / "docs/building.md")


def test_user_guides_use_only_the_distribution_repository_for_package_channels():
    text = "\n".join(path.read_text(encoding="utf-8") for path in USER_GUIDES)
    assert "github.com/resolver-plugins/repository/releases/download/pkg-" in text
    assert "resolver-plugins/plugins/releases/download/pkg-" not in text
    assert "pkg-<series>-bind920" not in text
    assert "pkg-$series-bind920" not in text
