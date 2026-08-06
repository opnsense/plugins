# Pull Request Release Cleanup Design

## Goal

Remove development package releases when their pull request closes, whether
merged or unmerged, and prevent an in-flight development publication from
leaving a release behind after closure.

## Release identity

Development releases use the existing tag format
`pr-<pull-number>-<series>`, where the pull number is a positive decimal
integer and the series is `<major>.<minor>`. Cleanup must affect only releases
whose complete tag matches that format for the pull request that triggered the
workflow. Production source releases and package channels are never candidates.

## Architecture

A dedicated workflow listens to `pull_request_target` with `types: [closed]`.
It receives only `contents: write`, checks out only the trusted pull request
workflow SHA with persisted credentials disabled, and never checks out or
executes pull request head code. Using the workflow SHA keeps cleanup available
even when the closed pull request targeted a release branch that predates the
helper. It uses the numeric pull request number from the trusted event payload,
lists repository releases, selects exact matching development tags, and
deletes each release together with its Git tag.

The development publisher also checks the pull request state immediately
before creating or uploading a prerelease and again after upload. If the pull
request is closed at either boundary, it deletes the exact development release
and tag and exits successfully. The close-event workflow and post-upload check
cover both possible orderings between closure and an in-flight publication.

Release discovery and deletion live in the existing release helper so tag
validation, GitHub CLI failures, and not-found behavior are unit tested instead
of being duplicated in workflow shell.

## Failure behavior

- Invalid pull numbers or release tags fail before mutation.
- Failure to list releases fails the cleanup job.
- A missing release is an idempotent success.
- Failure to delete an existing release or its tag fails the job.
- Cleanup never suppresses authentication, authorization, or API errors.

## Verification

Tests must prove exact tag selection, rejection of near matches, idempotent
missing-release cleanup, release-and-tag deletion, close-event permissions and
the workflow-SHA-only checkout. Workflow tests must also require pre- and
post-publication pull request state checks and prohibit PR-head checkout.

After merge, remove the remaining PR 48 and PR 51 development releases and
their tags. Verify the source repository contains only immutable production
source releases and that the distribution repository remains unchanged.
