<?php

/**
 * Admin view for ACF-Aware Search & Replace
 */
if (!defined('ABSPATH')) exit;

// From query (optional)
$needle_q = isset($_GET['needle']) ? sanitize_text_field(wp_unslash($_GET['needle'])) : '';

// Retrieve cached data
$results      = isset($results) ? $results : (get_transient('acfsr_last_results') ?: []);
$last_params  = get_transient('acfsr_last_params') ?: [];
$all_rows     = get_transient('acfsr_last_rows'); // all rows across batch
$summary      = $results['summary'] ?? [];

// Total results across all rows
$total_rows   = is_array($all_rows) ? count($all_rows)
    : (isset($summary['total_rows']) ? (int)$summary['total_rows'] : 0);

// Current page & per page
$current_page = isset($_REQUEST['acfsr_page']) ? max(1, (int)$_REQUEST['acfsr_page']) : (int)($last_params['page'] ?? 1);
$per_page     = isset($_REQUEST['acfsr_per'])  ? max(1, (int)$_REQUEST['acfsr_per'])  : (int)($last_params['perPage'] ?? 50);
$total_pages  = ($total_rows > 0) ? (int)ceil($total_rows / max(1, $per_page)) : 1;
$current_page = min($current_page, $total_pages);

// Slice rows for current page
if (is_array($all_rows) && $all_rows) {
    $offset = ($current_page - 1) * $per_page;
    $rows   = array_slice($all_rows, $offset, $per_page);
} else {
    // Fallback: just show whatever the scanner returned
    $rows   = $results['rows'] ?? [];
}

// Helper flags
$display_needle = $last_params['needle'] ?? $needle_q ?? '';
$likely_more    = !empty($summary['likely_has_more']);
$caseSensitive  = !empty($last_params['caseSensitive']);
$wholeWord      = !empty($last_params['wholeWord']);

// Helper for hidden inputs
function acfsr_hidden($name, $val)
{
    echo '<input type="hidden" name="' . esc_attr($name) . '" value="' . esc_attr($val) . '">';
}
?>
<div class="wrap acfsr-wrap">
    <h1><?php esc_html_e('ACF-Aware Search & Replace', 'acf-aware-search-replace'); ?></h1>

    <p class="description">
        <?php esc_html_e('Scan/replace posts, ACF postmeta, and options. Leave "Replace" blank to do a dry-run.', 'acf-aware-search-replace'); ?>
    </p>
    <br>
    <?php
    // Manually render flash if present
    $flash = get_transient('acfsr_flash');
    if ($flash && !empty($flash['msg'])) {
        delete_transient('acfsr_flash');
        $type = isset($flash['type']) && in_array($flash['type'], ['success', 'error', 'warning', 'info'], true)
            ? $flash['type'] : 'success';
        $class = 'notice';
        if ($type === 'success') $class .= ' notice-success';
        elseif ($type === 'error') $class .= ' notice-error';
        elseif ($type === 'warning') $class .= ' notice-warning';
        else $class .= ' notice-info';
        echo '<div class="' . esc_attr($class) . ' is-dismissible"><p>' . esc_html($flash['msg']) . '</p></div>';
    }
    ?>


    <?php
    // Helper: default to true if the setting hasn't been stored yet
    $on = function (string $key, bool $default = true) use ($last_params): bool {
        return array_key_exists($key, $last_params) ? (bool)$last_params[$key] : $default;
    };
    ?>

    <!-- Search Form -->
    <form class="filters" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field(ACFSR_Admin::NONCE_RUN); ?>
        <input type="hidden" name="action" value="acfsr_run">
        <input type="hidden" name="acfsr_page" id="acfsr_page_hidden" value="<?php echo (int)$current_page; ?>">
        <input type="hidden" name="acfsr_per" id="acfsr_per_hidden" value="<?php echo (int)$per_page; ?>">

        <div>
            <h3><?php esc_html_e('Search/Replace', 'acf-aware-search-replace'); ?></h3>
            <div class="row inputs">
                <input required class="regular-text" id="needle" name="needle"
                    placeholder="<?php esc_attr_e('Search', 'acf-aware-search-replace'); ?>"
                    value="<?php echo esc_attr($last_params['needle'] ?? $needle_q); ?>">
                <input class="regular-text" id="replace" name="replace"
                    placeholder="<?php esc_attr_e('Replace', 'acf-aware-search-replace'); ?>"
                    value="<?php echo esc_attr($last_params['replace'] ?? ''); ?>">
            </div>
        </div>

        <div>
            <h3><?php esc_html_e('Areas To Scan', 'acf-aware-search-replace'); ?></h3>
            <div class="row settings">
                <label>
                    <input type="hidden" name="scan_core" value="0"><?php /* ensures value when unchecked */ ?>
                    <input type="checkbox" name="scan_core" value="1" <?php checked($on('scanCore', true)); ?>> WP core
                </label>
                <label>
                    <input type="hidden" name="scan_meta" value="0">
                    <input type="checkbox" name="scan_meta" value="1" <?php checked($on('scanMeta', true)); ?>>
                    wp_postmeta
                </label>
                <label>
                    <input type="hidden" name="scan_options" value="0">
                    <input type="checkbox" name="scan_options" value="1" <?php checked($on('scanOptions', false)); ?>>
                    wp_options
                </label>
            </div>
        </div>

        <div>
            <h3><?php esc_html_e('Other Options', 'acf-aware-search-replace'); ?></h3>
            <div class="row moresettings">
                <label>
                    <input type="hidden" name="whole_word" value="0">
                    <input type="checkbox" name="whole_word" value="1" <?php checked($on('wholeWord', true)); ?>>
                    <?php esc_html_e('Whole word only', 'acf-aware-search-replace'); ?>
                </label>
                <label>
                    <input type="hidden" name="use_regex" value="0">
                    <input type="checkbox" name="use_regex" value="1" <?php checked($on('useRegex', false)); ?>>
                    <?php esc_html_e('Use regex (advanced)', 'acf-aware-search-replace'); ?>
                </label>
                <label>
                    <input type="hidden" name="case_sensitive" value="0">
                    <input type="checkbox" name="case_sensitive" value="1"
                        <?php checked($on('caseSensitive', false)); ?>>
                    <?php esc_html_e('Match case', 'acf-aware-search-replace'); ?>
                </label>
            </div>

            <div>
                <h3><?php esc_html_e('Pagination', 'acf-aware-search-replace'); ?></h3>
                <div class="row pagination">
                    <label>
                        <?php esc_html_e('Per page:', 'acf-aware-search-replace'); ?>
                        <select name="per_page_select" id="acfsr-per-page-select">
                            <?php
                            $choices = [50, 100, 250, 500, 1000, 2000, 5000];
                            $is_custom = !in_array($per_page, $choices, true);
                            foreach ($choices as $n): ?>
                            <option value="<?php echo (int)$n; ?>"
                                <?php selected(!$is_custom && (int)$n === $per_page); ?>>
                                <?php echo (int)$n; ?>
                            </option>
                            <?php endforeach; ?>
                            <option value="custom" <?php selected($is_custom); ?>>
                                <?php esc_html_e('Custom…', 'acf-aware-search-replace'); ?></option>
                        </select>
                    </label>
                    <label id="acfsr-per-page-custom-wrap" style="<?php echo $is_custom ? '' : 'display:none'; ?>">
                        <input type="number" name="per_page" id="acfsr-per-page-custom" min="1" step="1"
                            value="<?php echo (int)($is_custom ? $per_page : 2000); ?>" style="width:8rem">
                    </label>
                </div>
            </div>

            <div class="row buttons">
                <p class="submit">
                    <button type="submit" class="button button-primary">
                        <?php esc_html_e('Run Scan', 'acf-aware-search-replace'); ?>
                    </button>
                </p>

                <?php $has_replace = !empty($last_params['replace'] ?? ''); ?>
                <span id="acfsr-replace-btn-wrap" <?php if (!$has_replace) echo 'style="display:none"'; ?>>
                    <p class="submit">
                        <button class="button" type="submit" name="do_replace" value="1" id="acfsr-replace-btn"
                            onclick="return confirm('<?php echo esc_js(__('Have you backed up your DB? This will replace matches for this batch. Continue?', 'acf-aware-search-replace')); ?>')">
                            <?php esc_html_e('Replace Matches (this page)', 'acf-aware-search-replace'); ?>
                        </button>
                    </p>
                </span>

                <?php if ($likely_more): ?>
                <p class="submit">
                    <button class="button button-secondary" type="submit" name="do_replace" value="1"
                        onclick="document.querySelector('input[name=process_all]').checked=true;return confirm('<?php echo esc_js(__('Have you backed up your DB? This will iterate ALL batches until no matches remain. Continue?', 'acf-aware-search-replace')); ?>')">
                        <?php esc_html_e('Replace in ALL Batches', 'acf-aware-search-replace'); ?>
                    </button>
                </p>
                <?php endif; ?>


            </div>

            <input type="checkbox" name="process_all" value="1" style="display:none">
    </form>

    <!-- CLEAR button -->
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field(ACFSR_Admin::NONCE_CLEAR); // ✅ match the handler 
        ?>
        <input type="hidden" name="action" value="acfsr_clear">
        <button type="submit" class="button"><?php esc_html_e('Clear Search', 'acf-aware-search-replace'); ?></button>

        <?php
        // $clear_url = wp_nonce_url(
        //     admin_url('admin-post.php?action=acfsr_clear'),
        //     ACFSR_Admin::NONCE_CLEAR // same action string
        // );
        ?>
        <!-- <a class="button" href="<?php //echo esc_url($clear_url); 
                                        ?>">
            <?php //esc_html_e('Clear (link fallback)', 'acf-aware-search-replace'); 
            ?>
        </a> -->
    </form>



    <hr>

    <h2>
        <?php esc_html_e('Results', 'acf-aware-search-replace'); ?>
        <?php if ($display_needle !== ''): ?>
        <?php printf(esc_html__('for “%s”', 'acf-aware-search-replace'), esc_html($display_needle)); ?>
        <?php endif; ?>
    </h2>

    <?php if (!empty($summary['error'])): ?>
    <div class="notice notice-error">
        <p><?php echo esc_html($summary['error']); ?></p>
    </div>
    <?php endif; ?>

    <p>
        <strong><?php esc_html_e('Total matches (this batch):', 'acf-aware-search-replace'); ?></strong>
        <?php echo (int)($summary['total_matches'] ?? 0); ?>,
        <strong><?php esc_html_e('Records touched:', 'acf-aware-search-replace'); ?></strong>
        <?php echo (int)($summary['records_touched'] ?? 0); ?>,
        <strong><?php esc_html_e('Total results:', 'acf-aware-search-replace'); ?></strong>
        <?php echo (int)$total_rows; ?>
    </p>

    <?php if (!empty($rows)): ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:10px 0 20px">
        <?php wp_nonce_field(ACFSR_Admin::NONCE_EXPORT); ?>
        <?php acfsr_hidden('needle',      $last_params['needle'] ?? ''); ?>
        <?php acfsr_hidden('replace',     $last_params['replace'] ?? ''); ?>
        <?php acfsr_hidden('scan_core',   !empty($last_params['scanCore']) ? 1 : 0); ?>
        <?php acfsr_hidden('scan_meta',   !empty($last_params['scanMeta']) ? 1 : 0); ?>
        <?php acfsr_hidden('scan_options', !empty($last_params['scanOptions']) ? 1 : 0); ?>
        <?php acfsr_hidden('case_sensitive', !empty($last_params['caseSensitive']) ? 1 : 0); ?>


        <?php acfsr_hidden('whole_word',  !empty($last_params['wholeWord']) ? 1 : 0); ?>
        <?php acfsr_hidden('use_regex',   !empty($last_params['useRegex']) ? 1 : 0); ?>
        <?php acfsr_hidden('acfsr_per',   $per_page); ?>
        <?php acfsr_hidden('acfsr_page',  $current_page); ?>

        <input type="hidden" name="action" value="acfsr_export">
        <button type="submit" class="button"><?php esc_html_e('Export CSV', 'acf-aware-search-replace'); ?></button>
    </form>

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
            <?php
                // Prepare highlight regex once per page (fallback only; scanner may supply snippet_html already)
                $allowed = ['mark' => ['class' => []], 'code' => ['class' => []], 'span' => ['class' => []]];
                $needle_for_regex = $display_needle;
                $highlight_regex = '';
                if ($needle_for_regex !== '' && empty($last_params['useRegex'])) {
                    $quoted = preg_quote($needle_for_regex, '/');
                    $boundaryL = $wholeWord ? '\b' : '';
                    $boundaryR = $wholeWord ? '\b' : '';
                    $mods = $caseSensitive ? '' : 'i';
                    $highlight_regex = "/{$boundaryL}{$quoted}{$boundaryR}/{$mods}";
                }
                foreach ($rows as $r):
                    $html = $r['snippet_html'] ?? '';
                    if ($html === '' && !empty($r['snippet'])) {
                        $safe = esc_html($r['snippet']);
                        if ($highlight_regex) {
                            // wrap matches with <mark>
                            $safe = preg_replace($highlight_regex, '<mark>$0</mark>', $safe);
                        }
                        $html = $safe;
                    }
                ?>
            <tr>
                <td><?php echo esc_html($r['type'] ?? ''); ?></td>
                <td><?php echo esc_html($r['title'] ?? ''); ?>
                    <?php echo isset($r['id']) ? '<code>#' . (int)$r['id'] . '</code>' : ''; ?></td>
                <td><?php echo !empty($r['meta_key']) ? '<code>' . esc_html($r['meta_key']) . '</code>' : esc_html($r['field'] ?? ''); ?>
                </td>
                <td><?php echo (int)($r['match_count'] ?? 0); ?></td>
                <td><code class="acfsr-snippet"><?php echo wp_kses($html, $allowed); ?></code></td>
                <td><?php if (!empty($r['edit_link'])): ?><a class="button button-small"
                        href="<?php echo esc_url($r['edit_link']); ?>"><?php esc_html_e('Edit', 'acf-aware-search-replace'); ?></a><?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <p><strong><?php printf(esc_html__('Page %1$d of %2$d', 'acf-aware-search-replace'), $current_page, $total_pages); ?></strong>
    </p>

    <div class="tablenav">
        <div class="row tablenav-pages">
            <?php if ($current_page > 1): ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="inline">
                <?php acfsr_hidden('scan_core',   !empty($last_params['scanCore']) ? 1 : 0); ?>
                <?php acfsr_hidden('scan_meta',   !empty($last_params['scanMeta']) ? 1 : 0); ?>
                <?php acfsr_hidden('scan_options', !empty($last_params['scanOptions']) ? 1 : 0); ?>
                <?php acfsr_hidden('case_sensitive', !empty($last_params['caseSensitive']) ? 1 : 0); ?>

                <?php acfsr_hidden('whole_word',  !empty($last_params['wholeWord']) ? 1 : 0); ?>
                <?php acfsr_hidden('use_regex',   !empty($last_params['useRegex']) ? 1 : 0); ?>

                <?php wp_nonce_field(ACFSR_Admin::NONCE_RUN); ?>
                <?php acfsr_hidden('action', 'acfsr_run'); ?>
                <?php acfsr_hidden('needle', $last_params['needle'] ?? ''); ?>
                <?php acfsr_hidden('replace', $last_params['replace'] ?? ''); ?>
                <?php acfsr_hidden('acfsr_per', $per_page); ?>
                <?php acfsr_hidden('acfsr_page', $current_page - 1); ?>
                <button class="button">&laquo; <?php esc_html_e('Previous', 'acf-aware-search-replace'); ?></button>
            </form>
            <?php endif; ?>

            <?php if ($current_page < $total_pages): ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="inline">

                <?php wp_nonce_field(ACFSR_Admin::NONCE_RUN); ?>
                <?php acfsr_hidden('action', 'acfsr_run'); ?>
                <?php acfsr_hidden('needle', $last_params['needle'] ?? ''); ?>
                <?php acfsr_hidden('replace', $last_params['replace'] ?? ''); ?>
                <?php acfsr_hidden('acfsr_per', $per_page); ?>
                <?php acfsr_hidden('acfsr_page', $current_page + 1); ?>
                <?php acfsr_hidden('scan_core',   !empty($last_params['scanCore']) ? 1 : 0); ?>
                <?php acfsr_hidden('scan_meta',   !empty($last_params['scanMeta']) ? 1 : 0); ?>
                <?php acfsr_hidden('scan_options', !empty($last_params['scanOptions']) ? 1 : 0); ?>
                <?php acfsr_hidden('case_sensitive', !empty($last_params['caseSensitive']) ? 1 : 0); ?>

                <?php acfsr_hidden('whole_word',  !empty($last_params['wholeWord']) ? 1 : 0); ?>
                <?php acfsr_hidden('use_regex',   !empty($last_params['useRegex']) ? 1 : 0); ?>

                <button class="button"><?php esc_html_e('Next', 'acf-aware-search-replace'); ?> &raquo;</button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <?php else: ?>
    <p><?php esc_html_e('No matches found for this page.', 'acf-aware-search-replace'); ?></p>
    <?php endif; ?>
</div>

<script>
(function() {
    // Minimal JS to auto-submit on per-page changes and toggle Replace button
    var form = document.querySelector('form.filters');
    var sel = document.getElementById('acfsr-per-page-select');
    var wrap = document.getElementById('acfsr-per-page-custom-wrap');
    var inp = document.getElementById('acfsr-per-page-custom');
    var perH = document.getElementById('acfsr_per_hidden');
    var pageH = document.getElementById('acfsr_page_hidden');
    var repl = document.getElementById('replace');
    var btnW = document.getElementById('acfsr-replace-btn-wrap');

    function toggleReplace() {
        if (!repl || !btnW) return;
        btnW.style.display = (repl.value.trim().length ? '' : 'none');
    }
    if (repl) {
        repl.addEventListener('input', toggleReplace);
        repl.addEventListener('change', toggleReplace);
        toggleReplace();
    }

    function submitWithPer(per) {
        if (!form) return;
        perH.value = per;
        pageH.value = 1; // reset to page 1 on page-size change
        form.submit();
    }
    if (sel) {
        sel.addEventListener('change', function() {
            if (this.value === 'custom') {
                if (wrap) wrap.style.display = '';
                if (inp && !inp.value) inp.value = 2000;
            } else {
                if (wrap) wrap.style.display = 'none';
                submitWithPer(parseInt(this.value, 10));
            }
        });
    }
    if (inp) {
        inp.addEventListener('change', function() {
            var v = parseInt(this.value, 10) || 2000;
            submitWithPer(v);
        });
    }

    ['needle', 'replace', 'acfsr-per-page-select', 'acfsr-per-page-custom'].forEach(id => {
        var el = document.getElementById(id);
        if (el) el.addEventListener('change', function() {
            pageH.value = 1;
        });
    });
    // Reset page to 1 if any checkbox changes
    document.querySelectorAll('input[type=checkbox]').forEach(cb => {
        cb.addEventListener('change', function() {
            pageH.value = 1;
        });
    });

})();
</script>