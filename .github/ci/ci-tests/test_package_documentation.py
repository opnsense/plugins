from pathlib import Path


ROOT = Path(__file__).resolve().parents[3]
USER_GUIDES = (ROOT / "README.md", ROOT / "docs/package-repository.md", ROOT / "docs/building.md")


def test_user_guides_use_only_the_distribution_repository_for_package_channels():
    text = "\n".join(path.read_text(encoding="utf-8") for path in USER_GUIDES)
    assert "github.com/resolver-plugins/repository/releases/download/pkg-" in text
    assert "resolver-plugins/plugins/releases/download/pkg-" not in text
    assert "pkg-<series>-bind920" not in text
    assert "pkg-$series-bind920" not in text


def test_maintainer_guide_documents_cross_repository_publication_setup():
    text = (ROOT / "docs/package-repository.md").read_text(encoding="utf-8")
    assert "RP_DISTRIBUTION_APP_ID" in text
    assert "RP_DISTRIBUTION_APP_PRIVATE_KEY" in text
    assert "RP_DISTRIBUTION_REPOSITORY_TOKEN" not in text
    assert "Contents: write" in text
    assert "webhooks disabled" in text
    assert "installed only on\n`resolver-plugins/repository`" in text
    assert "master" in text and "workflow_dispatch" in text
