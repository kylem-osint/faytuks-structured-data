=== Faytuks Structured Data ===
Contributors: grey_kestrel
Tags: schema, structured data, json-ld, news, rank math
Requires at least: 7.0
Tested up to: 7.1
Requires PHP: 8.2
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Promotes Rank Math's publisher entity to a Schema.org NewsMediaOrganization, adds newsroom transparency properties, and adds per-post NewsArticle subtypes.

== Description ==
Faytuks Structured Data extends the structured data Rank Math already generates. Rank Math stays in charge of the JSON-LD graph; this plugin modifies two entities inside it.

Author: Grey Kestrel
Email: korvath85@gmail.com
Website: https://www.faytuksnetwork.com

What it does:
- Converts the Rank Math publisher `Organization` into a `NewsMediaOrganization`, preserving the existing `@id` so every graph reference keeps working.
- Adds the Schema.org newsroom transparency properties: publishingPrinciples, ethicsPolicy, correctionsPolicy, verificationFactCheckingPolicy, unnamedSourcesPolicy, noBylinesPolicy, ownershipFundingInfo, masthead, missionCoveragePrioritiesPolicy, actionableFeedbackPolicy, diversityPolicy and diversityStaffingReport.
- Offers optional overrides for standard organization values, defaulting to whatever Rank Math already supplies.
- Adds a per-post News Article Type control for ReportageNewsArticle, AnalysisNewsArticle, OpinionNewsArticle, BackgroundNewsArticle and ReviewNewsArticle.
- Leaves titles, meta descriptions, OpenGraph, X cards, canonicals and sitemaps completely untouched.

What it does not do:
- It does not replace or disable Rank Math schema.
- It does not print a second JSON-LD graph, and never creates a duplicate Organization or Article node.
- It does not claim any search ranking or rich result benefit from the specialised news types. Those types are semantic markup from Schema.org's newer vocabulary.

== Installation ==
1. Download the release ZIP `fn-structured-data-X.Y.Z.zip` (not a source archive - the release ZIP contains the required `vendor/` directory).
2. WordPress Admin > Plugins > Add New > Upload Plugin, then activate.
3. Confirm the plugin directory is `wp-content/plugins/fn-structured-data/`.
4. Go to Settings > Faytuks Structured Data and enter the newsroom policy URLs.
5. Optionally set a per-post type in the News Article Type box in the post editor.

Rank Math (free or Pro) with its Schema module enabled is required for the structured data changes to take effect. Without it the plugin stays inactive and structured data output is unchanged.

== Frequently Asked Questions ==
= Does this require Rank Math Pro? =
No. It uses the public `rank_math/json_ld` filter, which ships in Rank Math Free as well.

= What happens if Rank Math is deactivated? =
Nothing breaks. The filter simply never runs, no schema is emitted by this plugin, and administrators see a notice explaining that Rank Math is required.

= Will my existing Rank Math organization values be overwritten? =
No. Every value is inherited from Rank Math unless an override is explicitly filled in on the settings screen. Blank fields never erase Rank Math data.

= Do existing posts change article type automatically? =
No. Posts keep Rank Math's normal NewsArticle behaviour until an editor selects a specific type.

= Are invalid policy URLs published? =
No. Values must be absolute http(s) URLs; anything else is discarded on save and again before output.

== Changelog ==
= 1.0.1 =
- Fixed php: support php 8.2 due to dev server requirements.

= 1.0.0 =
- Added Rank Math publisher promotion to Schema.org NewsMediaOrganization with the original @id preserved.
- Added the twelve newsroom transparency properties, emitted only when a valid URL is configured.
- Added optional overrides for standard organization properties, inheriting Rank Math values by default.
- Added a per-post News Article Type control with an allowlisted set of Schema.org news subtypes.
- Added a diagnostics panel with a read-only organization entity preview.
- Added GitHub release updates through Plugin Update Checker.

== Upgrade Notice ==
= 1.0.1 =
Fixed php: support php 8.2 due to dev server requirements.

= 1.0.0 =
First release: promotes the Rank Math publisher entity to NewsMediaOrganization, adds newsroom transparency properties and per-post news article subtypes.
