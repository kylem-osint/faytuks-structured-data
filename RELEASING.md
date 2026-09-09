# Releasing Faytuks Structured Data

This project publishes plugin ZIP releases via GitHub Actions using [semantic-release](https://semantic-release.gitbook.io/). Version bumps (major / minor / patch) are inferred from [Conventional Commits](https://www.conventionalcommits.org/) on `main`. Sites update from those GitHub Releases through [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker) (`Faytuks\StructuredData\Updater`).

This mirrors the FN Live release model; the mechanics below are intentionally identical apart from names, slugs and artifact paths.

## Prerequisites

- Repository secret `GH_TOKEN` is set (Personal Access Token with Contents: Read and write — enough to push release commits and create GitHub Releases / upload assets).
- Prefer conventional commits (`feat:`, `fix:`, `feat!:` / `BREAKING CHANGE`, etc.) via `npm run commit` (Commitizen).
- Sites that auto-update need a separate read token (`FN_STRUCTURED_DATA_PUC_GITHUB_TOKEN` / `PUC_GITHUB_TOKEN`) as documented below.

## What gets versioned

semantic-release updates and commits these on `main` before the plugin ZIP is built:

- `package.json` / `package-lock.json`
- `composer.json`
- `fn-structured-data.php` (plugin header `Version:` + `FN_STRUCTURED_DATA_VERSION`)
- `readme.txt` (`Stable tag:`)
- `CHANGELOG.md`

`.github/scripts/check-version-sync.js` runs during the prepare step and in CI, and fails the release if any of those disagree or if `CHANGELOG.md` has no section for the new version.

## Notes written ahead of a release

Release notes normally come from commit subjects. A version whose notes were
written by hand before any tag existed is a special case, and 1.0.0 was one: the
plugin was built under commit types that do not release, so the generated notes
would have described only the commit that happened to trigger the release.

Two steps handle it, and both are no-ops once the project is releasing normally:

- `release-notes-supplement.js` runs as `generateNotesCmd` and appends any
  pre-existing `CHANGELOG.md` section for the version being released onto the
  generated notes, so the GitHub Release body is complete. It prints nothing
  when no such section exists, and it never fails.
- `merge-changelog-sections.js` runs in the prepare step, after
  `@semantic-release/changelog` has prepended its section. That plugin does not
  check whether the file already documents the version, so a hand-written entry
  would leave two headings for it. This folds them into one, merging matching
  `###` groups and dropping duplicate bullets.

## Release Workflow

1. Merge conventional commits into `main` (or push to `main`).
2. The **Build and Release Plugin** workflow runs.
3. `npx semantic-release`:
   - analyzes commits since the last release tag
   - bumps major / minor / patch
   - writes version metadata + changelog and commits them to `main` (`chore(release): x.y.z [skip ci]`)
   - then runs `npm run build:notest`, which builds and verifies the package
   - creates a GitHub Release with `dist/fn-structured-data-{version}.zip`
4. Manual path: Actions → **Build and Release Plugin** → **Run workflow**.

If there are no releasable commits since the last tag, the workflow exits successfully without publishing.

The release ZIP includes `vendor/autoload.php`, `vendor/composer/` and `vendor/yahnis-elsts/plugin-update-checker` so both PSR-4 autoloading and auto-updates keep working after install.

## What The Release Workflow Uses

- Workflow: `.github/workflows/release.yml`
- Config: `.releaserc.json`
- Version script: `.github/scripts/update-version-in-files.js`
- WordPress readme sync: `.github/scripts/sync-readme-changelog.js` (copies the new `CHANGELOG.md` section into `readme.txt` Changelog + Upgrade Notice)
- Version consistency check: `.github/scripts/check-version-sync.js`
- Release notes smoke test: `.github/scripts/check-release-notes.js` (CI only)
- Hand-written notes supplement: `.github/scripts/release-notes-supplement.js`
- Duplicate changelog section merge: `.github/scripts/merge-changelog-sections.js`
- Package verification: `.github/scripts/verify-package.js`
- Auth secret: `GH_TOKEN`
- Build command (inside semantic-release publish, after the version commit): `npm run build:notest`

## Local dry-run

```bash
npm ci
npm run release:dry-run
```

To inspect a build without releasing:

```bash
npm run build
unzip -l dist/fn-structured-data-*.zip
```

## Site setup (private GitHub updates)

Do this once per WordPress environment (production, staging, etc.).

### 1. Create a GitHub Personal Access Token

1. GitHub → **Settings** → **Developer settings** → **Personal access tokens**.
2. Create a fine-grained token (recommended) or classic token.
3. Grant **Contents: Read** on the plugin repository (enough to list releases and download assets).
4. Copy the token and store it in a password manager.

Use a **different** token for the site than the repo secret used by CI; least privilege for the site is Contents: Read only.

### 2. Define the token on the WordPress site

**Preferred:** add this to `wp-config.php` above the "That's all, stop editing!" line:

```php
define( 'FN_STRUCTURED_DATA_PUC_GITHUB_TOKEN', 'github_pat_xxxxxxxx' );
```

**Alternative:** set a server environment variable named `PUC_GITHUB_TOKEN` to the same value (PHP-FPM pool env, Apache `SetEnv`, Docker `environment`, etc.).

Do **not** commit the token into the plugin or the theme.

### 3. Install the built plugin ZIP

1. Download the latest release asset `fn-structured-data-X.Y.Z.zip` from the repository's Releases page (not a git clone — the ZIP includes `vendor/`).
2. WordPress Admin → **Plugins** → **Add New** → **Upload Plugin** → install and activate.
3. Confirm the plugin folder is `wp-content/plugins/fn-structured-data/` (the slug must stay `fn-structured-data`).

### 4. Verify update checks

1. Confirm a newer GitHub Release exists than the installed version.
2. WordPress Admin → **Plugins**.
3. Under Faytuks Structured Data, click **Check for updates**.
4. Install the update and confirm the new version number.

Settings → Faytuks Structured Data → Diagnostics reports whether the update checker is active, which repository it reads and whether a token is configured.

## Post-Release Checks

1. Confirm a new GitHub Release exists with the expected tag/name.
2. Confirm `fn-structured-data-{version}.zip` is attached and downloadable.
3. Confirm `main` has a `chore(release): …` commit with bumped version files.
4. Install the ZIP on a test site and verify activation.
5. Verify update checks work with `FN_STRUCTURED_DATA_PUC_GITHUB_TOKEN` (or `PUC_GITHUB_TOKEN`) set.
6. View a news article and confirm one `NewsMediaOrganization` publisher entity with the expected `@id`.

## Troubleshooting

- Release publish fails with permission/auth errors:
  - verify repo secret `GH_TOKEN` exists and can push to `main`, create releases and upload assets.
- ZIP missing `vendor/`:
  - run `composer install --no-dev` then `npm run build:notest` locally and inspect `dist/`. `verify-package.js` should have caught this.
- No update available in WordPress:
  - ensure `FN_STRUCTURED_DATA_PUC_GITHUB_TOKEN` or `PUC_GITHUB_TOKEN` is set and can read the repository;
  - ensure a GitHub Release exists with an asset matching `fn-structured-data-*.zip`;
  - ensure the installed plugin directory slug is `fn-structured-data`;
  - click **Check for updates** to bypass the 12-hour cache.
- Update downloads but the plugin breaks:
  - a source archive was installed instead of the release asset; reinstall from the Release ZIP.
- Workflow runs but no release is published:
  - no releasable conventional commits since the last tag. Only `feat`, `fix`,
    `perf`, reverts and breaking changes release. `chore`, `ci`, `test`, `docs`,
    `style`, `refactor` and `build` do not, so a run made up entirely of those
    exits successfully having published nothing.
- Release fails in `generateNotes` with `Missing helper: "conventional-changelog-conventionalcommits requires conventional-changelog-writer@9 or newer"`:
  - the changelog preset was upgraded past what semantic-release's writer
    supports. `@semantic-release/release-notes-generator` 14 depends on
    `conventional-changelog-writer` 8, which cannot render preset 10, so the
    preset is pinned to `^9.3.1` and Dependabot is blocked from taking its next
    major. `npm run verify-release-notes` reproduces this locally and runs in CI.

## Changelog tooling compatibility

semantic-release only renders release notes *after* it has decided to publish.
A broken changelog preset therefore causes no CI failure at all and only
surfaces by failing the release workflow, which is how the first 1.0.0 attempt
died. Three packages have to agree:

| Package | Constraint |
|---|---|
| `semantic-release` | pulls `@semantic-release/release-notes-generator` `^14.1.0` |
| `@semantic-release/release-notes-generator` 14 | depends on `conventional-changelog-writer` `^8` |
| `conventional-changelog-conventionalcommits` 10+ | requires `conventional-changelog-writer` 9+ |

So while release-notes-generator 14 is the latest stable, the preset has to stay
on 9.x. `npm run verify-release-notes` renders notes from a synthetic commit on
every CI run so this mismatch fails a pull request rather than a release.
