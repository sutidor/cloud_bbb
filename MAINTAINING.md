# Maintaining this fork

This is a **fork** of [littleredbutton/cloud_bbb](https://github.com/littleredbutton/cloud_bbb)
with an added transcript / AI-meeting-notes feature. The Nextcloud app id stays
`bbb` (renaming it would orphan every existing room, share, and recording stored
under that id), so it shares an id with the upstream app on the Nextcloud App
Store. This note explains how to avoid version clashes when upstream releases.

## Why a clash can happen

The app is installed from **our build** (extracted into `apps/bbb`), not from the
App Store. But because the id is `bbb`, the store will show "update available"
whenever the store version is higher than ours. **Clicking that update — or
running `occ app:update bbb` — overwrites our files with upstream's and loses the
transcript feature.** Nextcloud never does this automatically; it only happens on
an explicit update action.

## Rules

1. **Never update `bbb` from the App Store / `occ app:update bbb`.** Update it only
   by redeploying our build (see below).
2. **Keep our version ahead of the upstream base we forked from.** Our changes are
   almost entirely additive (new files: `TranscriptController`,
   `TranscriptMailService`, `Transcript` entity/mapper, the migrations, and the
   React `TranscriptPanel`; plus small hooks in `RecordingRow.tsx`, `routes.php`,
   `App.scss`, and `info.xml`), so rebases are cheap.

## Version scheme

Forked from upstream `2.9.1`. We ship `2.9.x` **at or above** the upstream base:
current `2.9.2`. When upstream releases a new version, rebase onto it and bump so
ours is `>=` theirs (e.g. upstream `2.10.0` → we ship `2.10.0` after rebasing, or
`2.10.1` for a fork-only change on top). Staying `>=` upstream means the store
never offers an "update" that would overwrite us.

## Updating to a new upstream release

```bash
git remote -v          # origin = littleredbutton/cloud_bbb (upstream), fork = sutidor/cloud_bbb
git fetch origin --tags

# Rebase our feature commits onto the new upstream tag on a work branch
git checkout -b rebase-vX.Y.Z origin/vX.Y.Z   # or the tag
git cherry-pick <our transcript commits>       # additive → usually conflict-free
# resolve any conflicts (most likely only in RecordingRow.tsx / routes.php / info.xml)

# Rebuild and redeploy
npm ci && npm run build
docker run --rm -v "$PWD":/app -w /app composer:2 composer install --no-dev
# bump <version> in appinfo/info.xml to >= the upstream tag
# then rsync/extract into the Nextcloud apps/bbb dir and: occ upgrade
```

## Deployment (current setup)

- Nextcloud host: build is extracted into the container's `apps/bbb` volume; run
  `occ upgrade` to apply migrations. Previous app is backed up first
  (`/root/bbb-backup-*`).
- `js/`, `vendor/`, and `node_modules/` are git-ignored; the deploy step builds
  them. The BBB-side transcription pipeline lives in a separate repo
  (`openclaw-workspace/extensions/bbb-post-processor`).
