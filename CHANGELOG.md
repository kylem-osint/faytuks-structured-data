## [1.0.1](https://github.com/kylem-osint/faytuks-structured-data/compare/v1.0.0...v1.0.1) (2026-09-09)

### Bug Fixes

* **php:** support php 8.2 due to dev server requirements ([a992fd0](https://github.com/kylem-osint/faytuks-structured-data/commit/a992fd0a0ced9d578ea299206bb090247f85e511))

## 1.0.0 (2026-09-09)

### Bug Fixes

* **ci:** fix CI processes ([b1f51b8](https://github.com/kylem-osint/faytuks-structured-data/commit/b1f51b86afb1f3830215d747f3e74f38fa25188a))
* **readme:** fix to trigger initial release ([0fe2afe](https://github.com/kylem-osint/faytuks-structured-data/commit/0fe2afe4fc33cac9d004bcd59ddcd7c3e7097395))

### Features

* **schema:** promote the Rank Math publisher entity to Schema.org NewsMediaOrganization while preserving its `@id` and every incoming graph reference
* **schema:** add the twelve NewsMediaOrganization newsroom transparency properties, emitted only when a valid URL is configured
* **schema:** add optional overrides for standard organization properties, inheriting Rank Math values by default
* **editor:** add a per-post News Article Type control mapping editorial choices to allowlisted Schema.org news subtypes
* **admin:** add a Settings screen with organization, editorial transparency and identity sections, plus a diagnostics panel and read-only organization preview
* **updates:** integrate Plugin Update Checker for GitHub release updates
# Changelog
