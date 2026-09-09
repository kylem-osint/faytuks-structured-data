# Faytuks Structured Data

A WordPress plugin for [Faytuks Network](https://www.faytuksnetwork.com) that extends and corrects the structured data Rank Math generates.

Rank Math remains the owner of the JSON-LD graph. This plugin modifies two entities inside that graph:

1. the publisher `Organization` becomes a full Schema.org `NewsMediaOrganization`, carrying newsroom transparency and editorial-policy properties;
2. the article entity can be retyped to a more specific news subtype per post.

Nothing else in the graph is touched, and no second graph is ever printed.

## Requirements

| | |
|---|---|
| WordPress | 7.0+ |
| PHP | 8.3+ |
| Rank Math | Free or Pro, with the Schema module enabled |
| Node (development only) | 22.22.2+ or 24.15+ |

Rank Math is an **integration dependency**, not a hard dependency. If it is missing or its schema module is off, the plugin does nothing to the frontend and shows an admin notice. It never deactivates itself and never emits a fallback graph.

## What it does

### Organization

The plugin hooks `rank_math/json_ld` at priority 99, finds the publisher organization, and:

- sets `@type` to `NewsMediaOrganization` (a single type — `NewsMediaOrganization` already inherits from `Organization`);
- preserves the existing `@id`, so `NewsArticle.publisher`, `Person.worksFor` and `WebSite.publisher` keep resolving to the same node;
- preserves every property Rank Math produced (`name`, `url`, `logo`, `email`, `telephone`, `address`, `sameAs`, `contactPoint`, …);
- applies overrides only where one is explicitly configured;
- adds the configured newsroom transparency properties.

Precedence is always: **inherit from Rank Math → override with an explicitly configured value → add newsroom properties**. A blank setting never erases a Rank Math value.

A `Person` publisher (Rank Math's "Person" knowledge graph option) is left alone; only organization-typed entities are promoted.

Supported newsroom properties, using their exact Schema.org names:

`publishingPrinciples`, `ethicsPolicy`, `correctionsPolicy`, `verificationFactCheckingPolicy`, `unnamedSourcesPolicy`, `noBylinesPolicy`, `ownershipFundingInfo`, `masthead`, `missionCoveragePrioritiesPolicy`, `actionableFeedbackPolicy`, `diversityPolicy`, `diversityStaffingReport`.

Each accepts a URL. Fragment links such as `https://faytuksnetwork.com/editorial-standards/#corrections` are supported. Empty, relative, non-`http(s)` and placeholder (`example.com`) values are discarded rather than emitted.

### Article subtypes

Editors pick a type in the **News Article Type** box in the post editor:

| Editor choice | Emitted `@type` |
|---|---|
| Default / Use Rank Math | *(unchanged)* |
| News Article | `NewsArticle` |
| Reportage / Straight News | `ReportageNewsArticle` |
| Analysis | `AnalysisNewsArticle` |
| Opinion | `OpinionNewsArticle` |
| Background / Explainer | `BackgroundNewsArticle` |
| Review | `ReviewNewsArticle` |

Only `@type` changes: `@id`, `headline`, `author`, `publisher`, `image`, `datePublished`, `dateModified`, `mainEntityOfPage`, `articleSection` and everything else Rank Math produced pass through untouched, and no new node is created. Existing posts keep Rank Math's behaviour until an editor changes them; nothing is bulk-converted.

The stored post meta value is an internal choice key (`analysis`), never a schema type. It is resolved through a hard-coded allowlist, so a hand-edited meta row cannot inject an arbitrary `@type`.

The specialised news types live in Schema.org's newer/pending vocabulary. They are semantic enhancements. Google does not document a special rich result or ranking benefit for them, and this plugin does not claim one.

## Architecture

```
fn-structured-data.php          Direct-access guard, constants, autoloader, bootstrap
src/
  Plugin.php                    Service wiring
  Updater.php                   Plugin Update Checker integration
  Admin/
    Settings.php                Settings screen registration and rendering
    SettingsFields.php          Declarative section/field definitions
    Diagnostics.php             Status panel and organization preview
    Notices.php                 Rank Math availability notice
  PostMeta/
    ArticleType.php             Per-post control, meta registration, save handling
  Schema/
    RankMathIntegration.php     The only WordPress-aware schema class
    OrganizationTransformer.php Publisher promotion (pure)
    ArticleTransformer.php      Article retyping (pure)
    GraphLocator.php            Entity discovery in Rank Math's graph (pure)
    ArticleTypes.php            Article type allowlist (pure)
    NewsroomPolicies.php        Newsroom property definitions (pure)
    OrganizationProperties.php  Standard override definitions (pure)
  Settings/
    SettingsRepository.php      Option storage, sanitization, normalization
  Support/
    Url.php                     URL validation policy (pure)
    RankMath.php                Read-only Rank Math capability detection
    Logger.php                  WP_DEBUG-gated logging
```

The classes marked *pure* have no WordPress dependencies at all, which is what makes the transformation behaviour directly unit testable:

```php
$organization = $organization_transformer->transform( $rank_math_publisher, $settings );
$article      = $article_transformer->transform( $rank_math_article, $schema_type );
```

`RankMathIntegration` is the only place that reads WordPress state: it receives `$data`, locates the entities, fetches normalized settings, calls the transformers and returns the complete array. Settings rendering never touches transformation logic.

### Extension points

| Hook | Type | Purpose |
|---|---|---|
| `fn_structured_data_settings` | filter | Normalized settings array |
| `fn_structured_data_organization` | filter | Transformed publisher entity |
| `fn_structured_data_article` | filter | Transformed article entity |
| `fn_structured_data_article_type` | filter | Resolved article type for a post (re-validated against the allowlist) |
| `fn_structured_data_article_type_post_types` | filter | Post types offering the editor control |
| `fn_structured_data_puc_repository` | filter | Update source repository |

Transformations that drop an entity's `@id` are discarded, so a filter cannot accidentally break graph references.

## Settings

**Settings → Faytuks Structured Data** (`manage_options`). All values live in one option, `fn_structured_data_settings`. There are no custom tables, and no generated schema is ever stored.

- **Organization** — name, alternate name, legal name, description, URL, email, telephone and logo overrides. Each field states whether the effective value is inherited from Rank Math or overridden here.
- **Editorial Transparency** — the twelve newsroom policy URLs, each with a plain-language description.
- **Identity / SameAs** — one profile URL per line; a non-empty list replaces Rank Math's.
- **Diagnostics** — plugin version, Rank Math detection and version, schema module state, integration state, filter priority, configured organization type, how many policy fields are populated, update-checker state and source. Tokens are never displayed, only whether one is configured.

Security: capability checks on render and save, nonces via the Settings API and `wp_nonce_field()` for the meta box, sanitization plus revalidation on read, `esc_url_raw()` + scheme allowlisting for URLs, attachment-id validation for the logo, and translatable strings throughout. There is deliberately no raw JSON editor and no way to add arbitrary schema property names.

## Local development

```bash
composer install
npm install
```

The Node floor is set by the release tooling, not by this plugin: semantic-release 25 and its changelog/git plugins require `^22.22.2 || >=24.15` and refuse to run on anything older. CI and the release workflow use Node 24, so keep local development on the same line.

### Commands

| Command | What it does |
|---|---|
| `composer check` | composer validate, PHPCS, PHPStan, unit tests |
| `composer lint` | PHPCS against WordPress Coding Standards |
| `composer lint:fix` | PHPCBF auto-fixes |
| `composer analyse` | PHPStan (level 8, `szepeviktor/phpstan-wordpress`) |
| `composer test:unit` | Unit tests (no WordPress runtime needed) |
| `composer test:integration` | Integration tests (starts the Dockerized MySQL, installs the WP test suite, runs, tears down) |
| `composer test:report` | Render the merged test and coverage summary from `coverage/` |
| `npm run lint` | `php -l`, `node --check`, then PHPCS |
| `npm run verify-version` | Asserts every version reference agrees |
| `npm run verify-update-source` | Asserts the updater points at the current repository (CI only) |
| `npm run verify-release-notes` | Renders release notes from a synthetic commit so a broken changelog preset fails a PR, not a release |
| `npm run build` | Full production build: tests, prod-only vendor, ZIP, package verification, then restores dev dependencies |
| `npm run build:notest` | Build without re-running tests (what the release workflow calls) |
| `npm run deploy:local` | Stage a runtime-only copy and push it into the local WordPress container |

Integration tests need Docker; see `TESTING.md`.

CI posts a single pull request comment with both suites' results and their
combined coverage, editing that comment in place on each push instead of
stacking up new ones. The same summary appears in the workflow job summary, so
`push` and `workflow_dispatch` runs get it too. Coverage is reported, not
enforced: there is no threshold that can fail a build. See `TESTING.md` for how
to reproduce the report locally.

### Deploying to the local site

`npm run deploy:local` installs the plugin into the running WordPress container used for Faytuks local development, which is the same stack FN Live deploys to (`fnlive-wordpress-1`, served on port 8080). This repository has no compose file of its own; start the site from the `fnlive` checkout, then:

```bash
npm run deploy:local
docker exec -u www-data fnlive-wordpress-1 wp plugin activate fn-structured-data --path=/var/www/html
```

Override the target with `FN_WP_CONTAINER=my-container npm run deploy:local`.

The task installs prod-only dependencies before staging and restores dev dependencies afterwards. That ordering matters: the Composer autoloader generated with dev dependencies present `require`s files from packages that are not shipped, so staging a dev `vendor/` produces a plugin that fatals on load.

Rank Math is not currently installed on the local site, so the plugin will show its "Rank Math is not active" notice and leave output untouched until you install it. Settings can still be configured, and the diagnostics panel reflects the inactive integration.

## Build and release

`npm run build` produces `dist/fn-structured-data-<version>.zip`. The staged package contains only what is needed at runtime:

```
fn-structured-data/
├── fn-structured-data.php
├── uninstall.php
├── readme.txt
├── src/
├── assets/
└── vendor/
    ├── autoload.php
    ├── composer/
    └── yahnis-elsts/plugin-update-checker/
```

Excluded: `.git`, `.github`, `tests/`, `node_modules/`, `dist/`, `.build/`, dev Composer packages, tooling config, and developer documentation. `.github/scripts/verify-package.js` asserts both halves of that — required runtime files present, development material absent — and fails the build otherwise.

Releases are automated with semantic-release from Conventional Commits on `main`:

1. push or merge conventional commits to `main`;
2. **Build and Release Plugin** runs `npx semantic-release`;
3. the version is written to `composer.json`, `package.json`, the plugin header, `FN_STRUCTURED_DATA_VERSION` and `readme.txt`'s stable tag by `.github/scripts/update-version-in-files.js`;
4. the new `CHANGELOG.md` section is mirrored into `readme.txt` by `.github/scripts/sync-readme-changelog.js`, and `check-version-sync.js` verifies everything agrees;
5. `npm run build:notest` builds and verifies the ZIP;
6. a GitHub Release is created with `dist/fn-structured-data-<version>.zip` attached.

Use `npm run commit` (Commitizen) to write commits, and `npm run release:dry-run` to preview a release. Full detail is in `RELEASING.md`.

## Updates (Plugin Update Checker)

Sites update from GitHub Releases via [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker) v5, installed with Composer and wired up in `src/Updater.php` on `plugins_loaded` — not an admin-only hook — so cron and WP-CLI update checks work too.

```php
PucFactory::buildUpdateChecker( $repository_url, FN_STRUCTURED_DATA_FILE, 'fn-structured-data' );
```

The version-agnostic `v5\PucFactory` is used, and the "require release assets" preference is read from the installed API class, so a PUC minor upgrade needs no code change. Release assets are required, so sites download the built ZIP (which includes `vendor/`) rather than a source archive.

For a private repository, define a read-only token on each site:

```php
// wp-config.php
define( 'FN_STRUCTURED_DATA_PUC_GITHUB_TOKEN', 'github_pat_…' );
```

or set the `PUC_GITHUB_TOKEN` environment variable. Tokens are never stored in the database, never shown in the admin, and never committed.

If the remote differs from the default, override it with the `FN_STRUCTURED_DATA_PUC_REPOSITORY` constant or the `fn_structured_data_puc_repository` filter.

## Example output

Organization entity, with newsroom properties configured:

```json
{
  "@type": "NewsMediaOrganization",
  "@id": "https://faytuksnetwork.com/#organization",
  "name": "Faytuks Network",
  "url": "https://faytuksnetwork.com/",
  "logo": {
    "@type": "ImageObject",
    "@id": "https://faytuksnetwork.com/#logo",
    "url": "https://faytuksnetwork.com/wp-content/uploads/logo.png"
  },
  "sameAs": ["https://x.com/faytuksnetwork"],
  "publishingPrinciples": "https://faytuksnetwork.com/editorial-standards/",
  "ethicsPolicy": "https://faytuksnetwork.com/editorial-standards/#ethics",
  "correctionsPolicy": "https://faytuksnetwork.com/editorial-standards/#corrections",
  "verificationFactCheckingPolicy": "https://faytuksnetwork.com/editorial-standards/#verification",
  "unnamedSourcesPolicy": "https://faytuksnetwork.com/editorial-standards/#unnamed-sources",
  "masthead": "https://faytuksnetwork.com/about/masthead/"
}
```

An Analysis article referencing it:

```json
{
  "@type": "AnalysisNewsArticle",
  "@id": "https://faytuksnetwork.com/story/#richSnippet",
  "headline": "Story headline",
  "datePublished": "2026-09-01T09:00:00+00:00",
  "dateModified": "2026-09-01T11:30:00+00:00",
  "author": { "@id": "https://faytuksnetwork.com/author/reporter/#person" },
  "publisher": { "@id": "https://faytuksnetwork.com/#organization" },
  "image": { "@id": "https://faytuksnetwork.com/story/#primaryimage" },
  "mainEntityOfPage": { "@id": "https://faytuksnetwork.com/story/#webpage" }
}
```

## Relationship to FN Live

This project takes its engineering conventions from the sibling `fnlive` repository, not its functionality.

Inherited as-is:

- release automation: semantic-release with Conventional Commits on `main`, the same plugin set, `.releaserc.json` shape, and the `update-version-in-files.js` / `sync-readme-changelog.js` / `check-version-sync.js` / `verify-package.js` scripts;
- build mechanics: staged `.build/` directory, prod-only `composer install --no-dev`, ZIP naming, dev-dependency restore, and package verification;
- PUC integration: Composer-installed v5, GitHub Releases with required release assets, `wp-config.php` constant or environment variable for private-repo tokens;
- WordPress test scaffolding: `install-wp-tests.sh`, `normalize-wp-tests-config.sh`, Dockerized MySQL for local integration runs;
- plugin header authorship and organizational metadata, `readme.txt` plus `CHANGELOG.md`, `.gitignore`/`.editorconfig` conventions, and Dependabot coverage for Composer, npm, and Actions.

Intentional deviations, and why:

| Deviation | Reason |
|---|---|
| PSR-4 namespaced `src/` (`Faytuks\StructuredData\`) instead of FN Live's flat, prefixed class files | Requested explicitly, and the schema transformers need to be unit testable in isolation from WordPress |
| PHPCS + WordPress Coding Standards added | FN Live has no PHPCS gate; the spec asks to match or exceed its quality gates |
| PHPStan 2.x at level 8 added | Same reason. Level 8 passes with no baseline and no ignored errors. FN Live has no static analysis, so there was no version to inherit |
| Split PHPUnit configs (`phpunit.unit.xml.dist` / `phpunit.integration.xml.dist`) | Lets the pure transformation logic be tested with no database or WordPress runtime, which keeps CI's unit matrix fast |
| `composer validate --no-check-publish` rather than `--strict` | The `version` field is kept deliberately as a version-sync source, matching FN Live. `--strict` only objects to it as a Packagist publishing recommendation, and this plugin ships as a release ZIP. Schema errors still fail the gate |
| MySQL on port 3308 for integration tests | Avoids colliding with FN Live's integration database if both run locally |
| PHP 8.3 minimum, and CI tests only 8.3 | The Faytuks Network production site runs PHP 8.3. Testing a range the site will never run costs CI time and invites supporting versions nobody uses, so the floor and the tested version are deliberately the same number. Raising the floor means WordPress will refuse to activate the plugin below 8.3 rather than fataling at runtime |
| WordPress 7.0 minimum instead of FN Live's 6.0 | Same reasoning applied to the CMS. Integration tests run against 7.1, so 7.0 is the oldest release with a defensible claim of support. WordPress enforces `Requires at least` at activation, so older sites are blocked cleanly rather than silently running untested code. `minimum_wp_version` in `phpcs.xml.dist` is kept in step so WPCS flags anything that predates the supported floor |

Notes:

- All newsroom policy URLs default to empty. No Faytuks policy URL is hard-coded anywhere in business logic; the administrator configures them.

The GitHub repository is named `faytuks-structured-data` while the plugin slug, directory and release ZIP are `fn-structured-data`. That is deliberate — the slug must stay stable because it is the WordPress plugin folder name — but it means `Updater::REPOSITORY_URL` and the slug are not interchangeable. CI asserts the updater points at the repository it is running in, so a rename cannot silently break update checks. Override the source with the `FN_STRUCTURED_DATA_PUC_REPOSITORY` constant or the `fn_structured_data_puc_repository` filter.

## License

GPL-2.0-or-later.
