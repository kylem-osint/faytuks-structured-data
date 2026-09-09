# Testing

This project includes:

- Unit tests (PHPUnit, no WordPress runtime required)
- Integration tests (PHPUnit + the WordPress PHPUnit test suite)

## Versions

- PHP: 8.2 minimum, with unit tests running on 8.2 and 8.3 in CI. 8.3 is what the production site runs, so the static analysis, integration, build and report jobs all use it
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

## 4. Coverage and the CI report

Every CI run posts a single pull request comment with the results of both
suites and their combined coverage, updating that same comment on each push
rather than adding a new one. The same report is written to the workflow's job
summary, so it is available for `push` and `workflow_dispatch` runs too.

To reproduce it locally you need a coverage driver (pcov or Xdebug); without one
the test commands still work and the report simply says coverage was not
reported.

```bash
composer test:unit:report          # writes coverage/junit-unit.xml + coverage/clover-unit.xml
composer test:integration:report   # same for the integration suite
composer test:report               # renders the Markdown summary from coverage/
```

`composer test:integration:report` expects the WordPress test suite to already
be installed and the database running, so run `composer test:integration:setup`
and `composer test:integration:prepare` first.

Two details worth knowing:

- Coverage is measured over `src/` only. `fn-structured-data.php` and
  `uninstall.php` are loaded by WordPress rather than by tests, so including
  them would report a permanently uncoverable 0%.
- The two suites are combined by taking the union of covered lines, not by
  adding their numbers up. Both suites execute many of the same lines, so
  summing them would count that overlap twice and overstate coverage.

There is deliberately no coverage threshold. The report informs review; it does
not gate the merge. The suites themselves are the gate.

## Notes

- The integration DB runs via `mysql:8.4` in `tests/integration/docker-compose.yml` on port 3308, and persists in the `fn_structured_data_test_db_data` volume.
- Rank Math is not installed for tests. The plugin only consumes Rank Math's public filter, so the tests drive that filter with realistic graph fixtures (`tests/support/Fixtures.php`) instead of depending on a third-party plugin in CI.
