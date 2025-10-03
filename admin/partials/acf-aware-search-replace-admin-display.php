<?php

/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       https://shandyward.com
 * @since      1.0.0
 *
 * @package    Acf_Aware_Search_Replace
 * @subpackage Acf_Aware_Search_Replace/admin/partials
 */
if (!defined('ABSPATH')) exit;


$needle = isset($_GET['needle']) ? sanitize_text_field(wp_unslash($_GET['needle'])) : '';
$page = isset($_GET['acfsr_page']) ? max(1, (int)$_GET['acfsr_page']) : 1;
$per = isset($_GET['acfsr_per']) ? max(1, (int)$_GET['acfsr_per']) : 2000;
?>
<div class="wrap acfsr-wrap">
    <h1><?php esc_html_e('ACF-Aware Search & Replace', 'acf-aware-search-replace'); ?></h1>
    <p><?php esc_html_e('Scan/replace posts, ACF postmeta, and options. Use a dry-run scan first.', 'acf-aware-search-replace'); ?>
    </p>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="acfsr-form">
        <?php wp_nonce_field(\ACFSR\Admin\Admin::NONCE_RUN); ?>
        <input type="hidden" name="action" value="acfsr_run">
        <table class="form-table">
            <tr>
                <th><label for="needle"><?php esc_html_e('Search for', 'acf-aware-search-replace'); ?></label></th>
                <td><input required class="regular-text" id="needle" name="needle"
                        value="<?php echo esc_attr($needle); ?>" placeholder="Text to find"></td>
            </tr>
            <tr>
                <th><label for="replace"><?php esc_html_e('Replace with', 'acf-aware-search-replace'); ?></label></th>
                <td><input class="regular-text" id="replace" name="replace" placeholder="(leave blank to scan only)">
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('Options', 'acf-aware-search-replace'); ?></th>
                <td>
                    <label><input type="checkbox" name="case" value="1">
                        <?php esc_html_e('Case sensitive', 'acf-aware-search-replace'); ?></label><br>
                    <label><input type="checkbox" name="scan_core" value="1" checked>
                        <?php esc_html_e('Scan core post fields', 'acf-aware-search-replace'); ?></label><br>
                    <label><input type="checkbox" name="scan_meta" value="1" checked>
                        <?php esc_html_e('Scan postmeta (ACF)', 'acf-aware-search-replace'); ?></label><br>
                    <label><input type="checkbox" name="scan_options" value="1" checked>
                        <?php esc_html_e('Scan options (ACF Options)', 'acf-aware-search-replace'); ?></label>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('Batching', 'acf-aware-search-replace'); ?></th>
                <td>
                    <label><?php esc_html_e('Per page (per table):', 'acf-aware-search-replace'); ?>
                        <input type="number" name="per_page" value="<?php echo (int)$per; ?>" min="1" step="1"
                            style="width:8rem">
                    </label>
                    &nbsp;&nbsp;
                    <label><?php esc_html_e('Page:', 'acf-aware-search-replace'); ?>
                        <input type="number" name="page" value="<?php echo (int)$page; ?>" min="1" step="1"
                            style="width:6rem">
                    </label>
                    <p class="description">
                        <?php esc_html_e('For huge sites, lower per-page or iterate pages.', 'acf-aware-search-replace'); ?>
                    </p>
                    <label><input type="checkbox" name="process_all" value="1">
                        <?php esc_html_e('Replace across ALL batches (use with caution; backup first)', 'acf-aware-search-replace'); ?></label>
                </td>
            </tr>
        </table>
        <p class="submit">
            <button class="button button-primary"
                type="submit"><?php esc_html_e('Run Scan (this page)', 'acf-aware-search-replace'); ?></button>
            <button class="button" type="submit" name="do_replace" value="1"
                onclick="return confirm('<?php echo esc_js(__('Have you backed up your DB? This will replace matches for this batch. Continue?', 'acf-aware-search-replace')); ?>')">
                <?php esc_html_e('Replace Matches (this page)', 'acf-aware-search-replace'); ?>
            </button>
            <button class="button button-secondary" type="submit" name="do_replace" value="1"
                onclick="document.querySelector('input[name=process_all]').checked=true;return confirm('<?php echo esc_js(__('Have you backed up your DB? This will iterate ALL batches until no matches remain. Continue?', 'acf-aware-search-replace')); ?>')">
                <?php esc_html_e('Replace in ALL Batches', 'acf-aware-search-replace'); ?>
            </button>
        </p>
    </form>

    <?php if (!empty($results)): $summary = $results['summary'] ?? [];
        $rows = $results['rows'] ?? []; ?>
    <hr>
    <h2><?php esc_html_e('Results', 'acf-aware-search-replace'); ?></h2>
    <?php if (!empty($summary['error'])): ?>
    <div class="notice notice-error">
        <p><?php echo esc_html($summary['error']); ?></p>
    </div>
    <?php endif; ?>
    <p><strong><?php esc_html_e('Total matches (this batch):', 'acf-aware-search-replace'); ?></strong>
        <?php echo (int)($summary['total_matches'] ?? 0); ?>,
        <strong><?php esc_html_e('Records touched:', 'acf-aware-search-replace'); ?></strong>
        <?php echo (int)($summary['records_touched'] ?? 0); ?>
    </p>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:10px 0 20px">
        <?php wp_nonce_field(\ACFSR\Admin\Admin::NONCE_EXPORT); ?>
        <input type="hidden" name="action" value="acfsr_export">
        <button class="button"
            type="submit"><?php esc_html_e('Export CSV (re-runs scan with current params)', 'acf-aware-search-replace'); ?></button>
    </form>

    <?php if (!empty($rows)): ?>
    <table class="widefat striped">
        <thead>
            <tr>
                <th><?php esc_html_e('Type', 'acf-aware-search-replace'); ?></th>
                <th><?php esc_html_e('Post/Option', 'acf-aware-search-replace'); ?></th>
                <th><?php esc_html_e('Field / Meta key', 'acf-aware-search-replace'); ?></th>
                <th><?php esc_html_e('Matches', 'acf-aware-search-replace'); ?></th>
                <th><?php esc_html_e('Snippet', 'acf-aware-search-replace'); ?></th>
                <th><?php esc_html_e('Action', 'acf-aware-search-replace'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $r): ?>
            <tr>
                <td><?php echo esc_html($r['type']); ?></td>
                <td>
                    <?php
                                $title = $r['title'] ?? '';
                                $id    = $r['id'] ?? '';
                                echo $title ? '<strong>' . esc_html($title) . '</strong>' : '';
                                echo $id ? ' <code>#' . (int)$id . '</code>' : '';
                                ?>
                </td>
                <td><?php echo !empty($r['meta_key']) ? '<code>' . esc_html($r['meta_key']) . '</code>' : esc_html($r['field'] ?? ''); ?>
                </td>
                <td><?php echo (int)($r['match_count'] ?? 0); ?></td>
                <td><code><?php echo esc_html($r['snippet'] ?? ''); ?></code></td>
                <td><?php if (!empty($r['edit_link'])): ?>
                    <a class="button button-small"
                        href="<?php echo esc_url($r['edit_link']); ?>"><?php esc_html_e('Edit', 'acf-aware-search-replace'); ?></a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <p><?php esc_html_e('No matches found for this batch.', 'acf-aware-search-replace'); ?></p>
    <?php endif; ?>
    <?php endif; ?>
</div>