=== ACF-Aware Search & Replace ===
Contributors: your-wporg-username
Donate link: https://example.com/
Tags: acf, search replace, database, serialization, wp-cli
Requires at least: 4.7
Tested up to: 6.6
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Safe, serialization-aware search/replace across posts, ACF fields, and options with counts, CSV export, batching, and WP-CLI.

== Description ==

This plugin provides a **safe, ACF-aware search & replace tool** for your WordPress database.  
It scans and replaces text in:

* Core post fields (title, content, excerpt)
* Postmeta, including serialized ACF fields
* Options table, including ACF Options page fields

Features:

* **Serialization-aware** — safely handles ACF flexible fields and repeaters.
* **Dry-run mode** — scan before replacing.
* **Detailed results** — see counts, snippets, and affected pages/fields.
* **CSV export** — download matches for review.
* **Batching/pagination** — scan massive sites in smaller chunks.
* **WP-CLI support** — run replacements from the command line.

Perfect for safe domain migrations, text corrections, and bulk content updates across ACF-powered sites.

== Frequently Asked Questions ==

= Does it work with serialized ACF fields? =

Yes. The plugin unserializes arrays/objects, performs a deep replacement, and re-serializes safely.

= Can I run a dry-run without replacing anything? =

Yes. If you leave the "Replace with" field empty, it will scan only.

= Is there a WP-CLI command? =

Yes. Run `wp acfsr "oldtext" --replace="newtext" --dry-run`  
You can also export results to CSV with `--export=/path/file.csv`.

= What about very large sites? =

Use the batching controls to process smaller chunks, or the "Replace in ALL batches" option to iterate through every batch automatically.

== Screenshots ==

1. Admin UI showing search form and results table.  
2. Results with counts, snippets, and CSV export button.

== Changelog ==

= 1.0.0 =
* Initial release with admin UI, dry-run, replace, CSV export, batching, and WP-CLI integration.

== Upgrade Notice ==

= 1.0.0 =
First stable release — includes CSV export, batching, and WP-CLI support.

== A brief Markdown Example ==

Ordered list:

1. Scan your database for text matches.
2. Review results with counts and snippets.
3. Export matches to CSV or safely replace them in batches.

Unordered list:

* Safe with ACF serialized data
* Dry-run or replace modes
* WP-CLI integration

Here's a link to [WordPress](https://wordpress.org/ "Your favorite software") and one to [Markdown's Syntax Documentation][markdown syntax].

> Run scans in dry-run first. Always backup your database before replacements.

`<?php wp acfsr "old" --replace="new" ?>`

[markdown syntax]: https://daringfireball.net/projects/markdown/syntax
