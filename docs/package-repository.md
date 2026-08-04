# Package repository

`os-bind-rp` packages are published as signed GitHub Release assets in
`resolver-plugins/plugins`. GitHub Releases store the package binary and the
small `pkg` repository catalogue; the source repository remains binary-free.

## Channels

Each supported OPNsense series has one stable Release tag:

| OPNsense series | Release tag |
| --- | --- |
| 26.1 | `pkg-26.1` |
| 26.7 | `pkg-26.7` |

The tag is the package repository base URL:

```text
https://github.com/resolver-plugins/plugins/releases/download/pkg-<series>
```

Every channel includes `meta.conf`, catalogue data, the current
`os-bind-rp-*.pkg`, and `resolver-plugins.pub`. Package clients verify the
catalogue and package using that public key.

Development builds use pre-release tags such as `pr-123-26.7`. They are for
review testing only and are never signed or promoted to a stable channel.

## Publication

The `Publish os-bind-rp package release` workflow builds from a selected
`release/bind-rp/<series>` source branch. A manual development run publishes a
PR pre-release. A production run builds, signs, and uploads the stable channel.
When a PR is merged into a release source branch, the workflow automatically
performs the production path.

The `RP_PKG_SIGNING_KEY` GitHub Actions secret contains the base64-encoded
private key. It is decoded only in the disposable FreeBSD VM, used by `pkg
repo`, and removed before the VM copyback. The committed public key is
`docs/package-repository/resolver-plugins.pub`; its SHA-256 fingerprint is
`bd89d6f91807c71f8a744532c9ce2f97e9590f8858ac779bfb2f23c10804e07e`.

Production publication has separate build, sign, and upload jobs. The build
job runs the selected release-source code but never receives the signing key.
A fresh signing VM checks out trusted `master` tooling and receives only the
finished package artifact; it generates the signed catalogue before the final
upload job publishes the channel. This keeps a release-source change from
having direct access to the signing credential.

Do not publish a stable channel manually from a workstation. A successful
workflow run is the release record and the source of the signed catalogue.

## Operations

After a production publication, check the Release has these assets and is not
marked as a pre-release:

```sh
gh release view pkg-26.7 --repo resolver-plugins/plugins
```

Use a disposable FreeBSD VM to verify the public URL before relying on a new
channel:

```sh
pkg update -r resolver-plugins
pkg rquery -r resolver-plugins -e '%n = os-bind-rp' '%n-%v'
```

If a signing-key rotation is required, generate and store the replacement
private key as `RP_PKG_SIGNING_KEY`, commit the replacement public key, and
republish every supported stable channel. Announce the new public-key
fingerprint with the release; existing clients must update their local key file
before they can verify the new catalogue.
