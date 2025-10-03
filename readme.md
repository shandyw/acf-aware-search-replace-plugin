# ACF-Aware Search & Replace

Safe, serialization-aware search/replace across posts, ACF fields, and options with counts, CSV export, batching, and WP-CLI.

![screenshot](assets/screenshot-1.png)

## Features

- **Serialization-aware** — safely replaces inside ACF repeaters, flexible content, and other serialized fields.
- **Dry-run mode** — scan before replacing anything.
- **Detailed results** — see counts, snippets, and affected fields.
- **CSV export** — download results for review or archiving.
- **Batching/pagination** — scan or replace massive sites in smaller chunks.
- **WP-CLI support** — run replacements from the command line.

## Installation

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate **ACF-Aware Search & Replace** from **Plugins** in WordPress.
3. Go to **Tools → ACF Search/Replace**.

## Usage

### Admin UI

- Enter the text to search for.
- Optionally enter replacement text (leave blank for a dry-run).
- Choose whether to scan posts, postmeta, and/or options.
- Review results with counts, snippets, and links.
- Export results to CSV or perform replacements.

### WP-CLI

```bash
# Dry-run only
wp acfsr "oldtext" --dry-run

# Replace across DB
wp acfsr "oldtext" --replace="newtext"

# Export matches to CSV
wp acfsr "domain.com" --dry-run --export="/tmp/results.csv"
```
