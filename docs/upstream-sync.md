# Upstream synchronization

## Workflow triggers

`Synchronize os-bind-rp upstream` runs daily at 04:17 UTC and may also be run
manually from GitHub Actions on `master`. It fetches OPNsense plugin stable
branches, the fork's release branches, and `opnsense/tools`. It then validates
the current release metadata and plans one safe outcome.

## Synchronizer outcomes

| Planner action | Meaning | Automation result |
| --- | --- | --- |
| `noop` | Current upstream BIND tree is unchanged and no newer stable series exists. | Finish without creating a ref, PR, or artifact. |
| `update-review` | The current release series has an upstream BIND tree change. | Create or recover a `sync/bind/...` review PR. No artifact is built before review. |
| `bootstrap-review` | A newer OPNsense series exists and its BIND tree differs. | Create a `sync/bootstrap/...` review PR for the new release source. No artifact is built before review. |
| `bootstrap-build` | A newer OPNsense series exists and its BIND tree is unchanged. | Create the release branch, build it in the selected FreeBSD VM, and upload a seven-day artifact. |

When a BIND change is present, review the generated PR and merge it only after
the fork-specific behavior has been verified. The synchronizer never silently
advances a release branch across an upstream BIND change.

## Provenance and recovery

Before it creates a branch, pull request, or assignment, the workflow checks
the immutable source profile and the planned upstream, tools, FreeBSD, and core
archive provenance. Invalid or mismatched provenance stops the run before any
GitHub publication action.

If a prior run was interrupted after it began creating a review publication,
the recovery step verifies the same provenance and reconciles the existing
generated branch and PR. It does not overwrite unrelated state.

## Operational response

For a blocked run, use the failure message to identify the invalid source
metadata or unavailable upstream input. Correct it through a reviewed release
branch change; do not bypass the validator or edit a generated branch in
place. For an artifact-build failure, inspect the VM build logs and the
metadata profile first, then reproduce the build using [Building](building.md).
