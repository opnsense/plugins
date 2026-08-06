# Package repository

`os-bind-rp` is published as signed GitHub Release assets in
`resolver-plugins/repository`. The distribution repository contains generated
package channels; source releases contain only the plugin archive and build
metadata.

## Channels

Every supported OPNsense series has a self-contained current channel and up
to five self-contained immutable rollback snapshots:

| Purpose | Release tag | Default state |
| --- | --- | --- |
| Current plugin and BIND baseline | `pkg-<series>` | enabled |
| Plugin rollback snapshot and its BIND baseline | `pkg-<series>-os-bind-rp-<version>` | enabled only while rolling back |

The current channel and every rollback snapshot contain exactly one
`os-bind-rp` package, the matching `bind920`/`bind-tools` pair, BIND
provenance, `channel.json`, and the signed catalogue. `pkg` catalogues expose
one selected version per package name, so rollback temporarily selects a
retained snapshot URL from the same distribution repository. Publication
retains the five newest snapshots and reuses a compatible BIND pair instead
of rebuilding it for every plugin release.

All channels include the signed `pkg` catalogue and `resolver-plugins.pub`.
Clients verify both using that public key.

## Host operation

Choose the matching OPNsense series and configure the current plugin channel:

```sh
series=26.7
base="https://github.com/resolver-plugins/repository/releases/download/pkg-$series"
install -d -m 0755 /usr/local/etc/pkg/keys /usr/local/etc/pkg/repos
fetch -o /usr/local/etc/pkg/keys/resolver-plugins.pub "$base/resolver-plugins.pub"
test "$(sha256 -q /usr/local/etc/pkg/keys/resolver-plugins.pub)" = \
  bd89d6f91807c71f8a744532c9ce2f97e9590f8858ac779bfb2f23c10804e07e || exit 1
cat > /usr/local/etc/pkg/repos/resolver-plugins.conf <<EOF
resolver-plugins: {
  url: "$base",
  mirror_type: "none",
  signature_type: "pubkey",
  pubkey: "/usr/local/etc/pkg/keys/resolver-plugins.pub",
  enabled: yes
}
EOF
pkg update -r resolver-plugins
pkg install os-bind-rp
```

### Rollback

Back up the OPNsense configuration before changing plugin versions:

```sh
cp /conf/config.xml "/conf/config.xml.os-bind-rp.$(date +%Y%m%d%H%M%S).bak"
```

Configure `resolver-plugins-rollback` with the same key and the exact snapshot
URL, for example `pkg-$series-os-bind-rp-1.36_2`. Dry-run and then install the
only plugin package exposed by that snapshot:

```sh
snapshot="pkg-$series-os-bind-rp-1.36_2"
cat > /usr/local/etc/pkg/repos/resolver-plugins-rollback.conf <<EOF
resolver-plugins-rollback: {
  url: "https://github.com/resolver-plugins/repository/releases/download/$snapshot",
  mirror_type: "none",
  signature_type: "pubkey",
  pubkey: "/usr/local/etc/pkg/keys/resolver-plugins.pub",
  enabled: yes
}
EOF
pkg update -r resolver-plugins-rollback
pkg install -n -r resolver-plugins-rollback os-bind-rp
pkg install -f -r resolver-plugins-rollback os-bind-rp
pkg query -e '%n = os-bind-rp' '%n-%v'
configctl template reload OPNsense/Bind || true
configctl service restart bind || true
rm -f /usr/local/etc/pkg/repos/resolver-plugins-rollback.conf
```

If configuration, template generation, or service validation fails, restore
the saved configuration and run the exact latest-channel install command:

```sh
pkg install -f -r resolver-plugins os-bind-rp
```

The rollback snapshot is not a separate product feed; leave it disabled
outside an explicit rollback to keep ordinary upgrades on `pkg-<series>`.

Development builds use pre-release tags such as `pr-123-26.7`. They are for
review testing only and are neither signed nor promoted into a stable channel.

## Publication

The `Publish os-bind-rp package release` workflow builds from the selected
`release/bind-rp/<series>` source branch. It first reuses a matching BIND pair
from the current distribution channel or builds the pinned pair on a verified
cache miss. The plugin is built against that exact pair.

Production publication is an explicit `workflow_dispatch` from the `master`
branch after the release-source change has been reviewed and merged. Select
`production` and the target series; a production dispatch from any other ref
is rejected. Release branches supply immutable build inputs only and never run
publication helpers. Runs are serialized per series so two promotions cannot
replace or restore the same current channel concurrently.

The source repository must define this Actions variable and these Actions
secrets before production:

- `RP_PKG_SIGNING_KEY`: the base64-encoded private package-signing key. It is
  exposed only to the FreeBSD signing job.
- `RP_DISTRIBUTION_APP_ID`: the non-secret numeric ID of the organization-owned
  Resolver Plugins publisher GitHub App. Store it as an Actions repository
  variable.
- `RP_DISTRIBUTION_APP_PRIVATE_KEY`: the complete PEM private key for that App.
  Store it as an Actions repository secret and rotate it through the App's
  credential settings.

The publisher App is owned by `resolver-plugins`, has webhooks disabled, has
only `Contents: write`, and is installed only on
`resolver-plugins/repository`. The publication job exchanges its ID and
private key for a short-lived installation token; no personal access token is
stored. A missing App variable, missing private key, or suspended installation
fails the publication job before channel mutation. Never replace the App
credential with a broad source-repository or session credential.

The production signer resolves and checks out a specific `master`
control-plane SHA, verifies the finished artifact's source commit, and receives
no release-source helper code with `RP_PKG_SIGNING_KEY`. It validates BIND
provenance and every security-relevant build field against trusted release
metadata, then stages identical
self-contained current and immutable rollback repositories. It generates the
signed catalogue once and copies those exact bytes to both publication paths.
The derived public key must match the key committed in this repository before
either path can proceed.

Before replacing mutable Release assets, publication downloads every prior
asset—packages, catalogues, metadata, provenance, and public key—to local
recovery storage, validates its audit checksums and expected channel structure,
and confirms the remote release did not change during preflight. Every uploaded
asset is downloaded again and compared byte-for-byte. If an upload or verification fails,
it restores each affected Release from those preserved bytes. The snapshot
and current channel have their published asset sets checked after upload;
pruning to the newest five snapshots happens only after promotion succeeds.

Do not publish a stable channel manually from a workstation. A successful
workflow run is the release record and source of the signed catalogue.

## Verification

Before production publication, the workflow uses a disposable FreeBSD VM to
add the staged signed current and snapshot repositories, install all three
current packages, and force-install the plugin through the snapshot path. The
two staged paths contain the same new version, so this gate proves snapshot
catalogue installability rather than a transition to an older version. After
publication, when an older retained snapshot exists, verify the actual version
transition from its public URL and confirm package identities with `pkg
rquery`.

If a signing-key rotation is required, replace `RP_PKG_SIGNING_KEY`, commit
the replacement public key, and republish every channel for every supported
series. Announce the new fingerprint; existing clients must update their key
before they can verify the replacement catalogues.
