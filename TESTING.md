# Testing

This project includes:

- Unit tests (PHPUnit, no WordPress runtime required)
- Integration tests (PHPUnit + the WordPress PHPUnit test suite)

## Versions

- PHP: 8.3, which is both the minimum and the only version CI tests, because it is what the production site runs
- WordPress: 7.1

Override the WordPress version for a run with `WP_TEST_VERSION`, for example
`WP_TEST_VERSION=6.9.1 composer test:integration`. An `x.y` value such as `7.1`
is the wordpress.org release name; the installer maps it to the matching
`wordpress-develop` tag (`7.1.0`) automatically.

## 1. Install dependencies

```bash
composer install
npm install
```

## 2. Unit tests

```bash
composer test:unit
```

or

```bash
vendor/bin/phpunit -c phpunit.unit.xml.dist
```

`tests/bootstrap-unit.php` stubs the small slice of WordPress the plugin touches (options, post meta, the hook system, sanitizers), so the whole schema transformation path — including `RankMathIntegration` driven through `apply_filters()` — is covered without a database.

Covered by the unit suite:

- publisher promotion to `NewsMediaOrganization`, including a single `@type` and preserved `@id`
- inheritance of existing Rank Math values, and blank overrides not erasing them
- explicit overrides, invalid emails, logo `ImageObject` handling and `sameAs` replacement
- rejection of invalid, relative, non-`http(s)` and placeholder policy URLs
- idempotency: transforming an already promoted entity is a no-op
- missing publisher and `Person` publisher left untouched
- all six article subtype mappings and rejection of arbitrary `@type` values
- whole-graph preservation: unrelated entities and every `publisher` / `worksFor` reference intact
- settings sanitization, revalidation on read, and attachment-id validation

## 3. Integration tests (WordPress test suite)

Start the integration database (Docker):

```bash
composer test:integration:env:up
```

Install the WP test library and DB fixtures:

```bash
composer test:integration:install
```

Shortcut for both steps:

```bash
composer test:integration:setup
```

Run integration tests:

```bash
composer test:integration
```

Stop and clean the integration DB:

```bash
composer test:integration:env:down
```

Fully reset the integration DB volume:

```bash
composer test:integration:env:reset
```

Covered by the integration suite:

- the `rank_math/json_ld` filter registered at priority 99 in a real hook registry
- promotion and policy output through `apply_filters()` on a real site URL
- per-post subtype selection applied on an actual singular request
- meta box save path: nonce, capability, allowlist, and default-choice removal
- settings registration under Settings, capability enforcement on save, and asset scoping

## Notes

- The integration DB runs via `mysql:8.4` in `tests/integration/docker-compose.yml` on port 3308, and persists in the `fn_structured_data_test_db_data` volume.
- Rank Math is not installed for tests. The plugin only consumes Rank Math's public filter, so the tests drive that filter with realistic graph fixtures (`tests/support/Fixtures.php`) instead of depending on a third-party plugin in CI.
