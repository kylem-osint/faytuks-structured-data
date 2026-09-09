# Changelog

## 1.0.0 (2026-09-08)

### Features

* **schema:** promote the Rank Math publisher entity to Schema.org NewsMediaOrganization while preserving its `@id` and every incoming graph reference
* **schema:** add the twelve NewsMediaOrganization newsroom transparency properties, emitted only when a valid URL is configured
* **schema:** add optional overrides for standard organization properties, inheriting Rank Math values by default
* **editor:** add a per-post News Article Type control mapping editorial choices to allowlisted Schema.org news subtypes
* **admin:** add a Settings screen with organization, editorial transparency and identity sections, plus a diagnostics panel and read-only organization preview
* **updates:** integrate Plugin Update Checker for GitHub release updates
