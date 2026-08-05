# Package repository

`os-bind-rp` is published as signed GitHub Release assets in
`resolver-plugins/plugins`. GitHub Releases contain package binaries and the
small `pkg` catalogues; the source repository remains binary-free.

## Channels

Every supported OPNsense series has three distinct signed channels:

| Purpose | Release tag | Default state |
| --- | --- | --- |
| Current plugin | `pkg-<series>` | enabled |
| Plugin rollback snapshot | `pkg-<series>-os-bind-rp-<version>` | enabled only while rolling back |
| Resolver BIND fallback | `pkg-<series>-bind920` | disabled |

The current plugin channel contains exactly the newest `os-bind-rp` package.
Each rollback snapshot is an immutable, one-package catalogue for a plugin
version that declares `bind920 >= 9.20.26`; it never contains a legacy plugin
that pins a particular Resolver BIND revision. `pkg` repositories cannot
catalogue multiple versions under the same package name, so rollback selects
the desired snapshot repository and installs `os-bind-rp` from that source.
Publication retains the five newest snapshots for each series.

The BIND fallback channel contains `bind-tools-9.20.26_1.pkg`,
`bind920-9.20.26_1.pkg`, and `bind920-provenance.json`. It is a separate
source because hosts should use OPNsense's BIND when it is eligible. The
fallback repository is deliberately disabled, so its `9.20.26_1` revision
cannot supersede an eligible official package with the same version.

All channels include the signed `pkg` catalogue and `resolver-plugins.pub`.
Clients verify both using that public key.

## Host operation

Choose the matching OPNsense series and configure the current plugin channel:

```sh
series=26.7
base="https://github.com/resolver-plugins/plugins/releases/download/pkg-$series"
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
  url: "https://github.com/resolver-plugins/plugins/releases/download/$snapshot",
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
fallback when present, and latest channel are verified after upload; pruning
to the newest five snapshots happens only after that promotion succeeds.

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
