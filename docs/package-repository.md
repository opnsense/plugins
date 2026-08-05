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

Before enabling any fallback, check the official packages. An eligible BIND is
`bind920` from `dns/bind920`, at least `9.20.26`, with `bind-tools` from
`dns/bind-tools`. The plugin formula accepts that official package and does not
require the Resolver fallback.

Only if that check fails, add the fallback configuration with `enabled: no`:

```sh
cat > /usr/local/etc/pkg/repos/resolver-plugins-bind920.conf <<EOF
resolver-plugins-bind920: {
  url: "https://github.com/resolver-plugins/plugins/releases/download/pkg-$series-bind920",
  mirror_type: "none",
  signature_type: "pubkey",
  pubkey: "/usr/local/etc/pkg/keys/resolver-plugins.pub",
  enabled: no
}
EOF
```

Set `enabled: yes` in that file only after recording why the OPNsense BIND is
ineligible, run `pkg update -r resolver-plugins-bind920`, and install the
fallback BIND pair. Disable the repository again before normal upgrades.

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
`release/bind-rp/<series>` source branch. It first attempts an OPNsense BIND
that satisfies the policy. If it cannot, it builds or reuses the separate
Resolver fallback pair and records that source in `build-metadata.txt`.

The production signer resolves and checks out a specific `master`
control-plane SHA, verifies the finished artifact's source commit, and receives
no release-source helper code with `RP_PKG_SIGNING_KEY`. It stages the latest
plugin, one immutable formula-compatible rollback snapshot, and any new BIND
fallback catalogue separately.

Before replacing mutable Release assets, publication downloads every prior
asset—packages, catalogues, metadata, provenance, and public key—to local
recovery storage and verifies checksums. If an upload or verification fails,
it restores each affected Release from those preserved bytes. The snapshot,
fallback when present, and latest channel have their published asset sets
checked after upload; pruning to the newest five snapshots happens only after
that promotion succeeds.

Do not publish a stable channel manually from a workstation. A successful
workflow run is the release record and source of the signed catalogue.

## Verification

After a production publication, inspect the three Release tags and use a
disposable FreeBSD VM to verify the current package, named rollback, and (when
present) explicitly enabled BIND fallback. Confirm the public URL and package
identity with `pkg rquery` before using the channel on a host.

If a signing-key rotation is required, replace `RP_PKG_SIGNING_KEY`, commit
the replacement public key, and republish every channel for every supported
series. Announce the new fingerprint; existing clients must update their key
before they can verify the replacement catalogues.
