<?php

if (!defined('ABSPATH')) exit;

class ACFSR_Core_Scanner
{
    protected $needle;
    protected $replace;
    protected $dryRun;
    protected $caseSensitive;
    protected $scanCore;
    protected $scanMeta;
    protected $scanOptions;
    protected $fieldsCore;

    // batching / pagination
    protected $perPage; // items per table per scan (LIMIT)
    protected $page;    // 1-based page number (OFFSET = (page-1)*perPage)

    public function __construct(array $args)
    {
        $this->needle        = (string)($args['needle'] ?? '');
        $this->replace       = array_key_exists('replace', $args) ? (string)$args['replace'] : null;
        $this->dryRun        = (bool)($args['dryRun'] ?? true);
        $this->caseSensitive = (bool)($args['caseSensitive'] ?? false);
        $this->scanCore      = (bool)($args['scanCore'] ?? true);
        $this->scanMeta      = (bool)($args['scanMeta'] ?? true);
        $this->scanOptions   = (bool)($args['scanOptions'] ?? true);
        $this->fieldsCore    = $args['fieldsCore'] ?? ['post_title', 'post_content', 'post_excerpt'];
        $this->wholeWord  = (bool)($args['wholeWord'] ?? false);
        $this->useRegex   = (bool)($args['useRegex'] ?? false);

        // pagination controls
        $this->perPage       = max(1, (int)($args['perPage'] ?? 2000));  // sane default
        $this->page          = max(1, (int)($args['page'] ?? 1));
    }

    public function run()
    {
        global $wpdb;
        $rows = [];
        $totalMatches = 0;
        $recordsTouched = 0;

        if ($this->needle === '') {
            return ['rows' => [], 'summary' => ['error' => 'Empty needle']];
        }

        if (!$this->dryRun) {
            $wpdb->query('START TRANSACTION');
        }

        try {
            if ($this->scanCore) {
                $core = $this->scan_core_posts();
                $rows = array_merge($rows, $core['rows']);
                $totalMatches += $core['matches'];
                $recordsTouched += $core['touched'];
            }

            if ($this->scanMeta) {
                $meta = $this->scan_postmeta();
                $rows = array_merge($rows, $meta['rows']);
                $totalMatches += $meta['matches'];
                $recordsTouched += $meta['touched'];
            }

            if ($this->scanOptions) {
                $opts = $this->scan_options();
                $rows = array_merge($rows, $opts['rows']);
                $totalMatches += $opts['matches'];
                $recordsTouched += $opts['touched'];
            }

            if (!$this->dryRun) {
                $wpdb->query('COMMIT');
            }
        } catch (\Throwable $e) {
            if (!$this->dryRun) {
                $wpdb->query('ROLLBACK');
            }
            return [
                'rows' => $rows,
                'summary' => [
                    'error' => $e->getMessage(),
                    'total_matches' => $totalMatches,
                    'records_touched' => $recordsTouched,
                ]
            ];
        }

        return [
            'rows' => $rows,
            'summary' => [
                'total_matches' => $totalMatches,
                'records_touched' => $recordsTouched,
            ]
        ];
    }

    /** Return true if this batch likely has more candidates after current page */
    public function likely_has_more(): bool
    {
        // If any table returned a full batch, more *may* exist.
        return (bool)($this->lastCounts['posts_full'] ?? false)
            || (bool)($this->lastCounts['meta_full'] ?? false)
            || (bool)($this->lastCounts['opts_full'] ?? false);
    }

    // internal to track whether a table hit the perPage cap this batch
    protected $lastCounts = [
        'posts_full' => false,
        'meta_full'  => false,
        'opts_full'  => false,
    ];

    protected function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    protected function scan_core_posts()
    {
        global $wpdb;
        $rows = [];
        $matches = 0;
        $touched = 0;

        $criteria = [];
        foreach ($this->fieldsCore as $f) {
            $criteria[] = $wpdb->prepare("$f LIKE %s", ACFSR_Core_Helpers::make_like($this->needle));
        }
        if (empty($criteria)) {
            return ['rows' => [], 'matches' => 0, 'touched' => 0];
        }

        $where = implode(' OR ', $criteria);
        // Select minimal columns needed plus the fields we’ll touch
        $cols = array_unique(array_merge(['ID', 'post_title', 'post_type'], $this->fieldsCore));
        $fields = implode(',', array_map(fn($c) => $c, $cols));

        $sql = $wpdb->prepare(
            "SELECT $fields
             FROM {$wpdb->posts}
             WHERE ($where)
             LIMIT %d OFFSET %d",
            $this->perPage,
            $this->offset()
        );
        $candidates = $wpdb->get_results($sql);
        $this->lastCounts['posts_full'] = count($candidates) >= $this->perPage;

        foreach ($candidates as $p) {
            $hitCount = 0;
            foreach ($this->fieldsCore as $f) {
                $original = $p->$f;
                if (!is_string($original) || $original === '') continue;

                $count = $this->caseSensitive
                    ? substr_count($original, $this->needle)
                    : (preg_match_all('/' . preg_quote($this->needle, '/') . '/i', $original) ?: 0);
                $count = is_string($original) ? $this->count_matches($original) : 0;
                if ($count > 0) {
                    $hitCount += $count;

                    if (!$this->dryRun && $this->replace !== null) {
                        $new = $this->replace_str($original);
                        $wpdb->update($wpdb->posts, [$f => $new], ['ID' => $p->ID]);
                        clean_post_cache($p->ID);
                    }

                    $edit = get_edit_post_link((int) $p->ID, '');
                    if (empty($edit)) {
                        // Fallback if WP can’t build it for some reason
                        $edit = admin_url('post.php?post=' . (int) $p->ID . '&action=edit');
                    }

                    $rows[] = [
                        'type' => 'post',
                        'id'   => (int)$p->ID,
                        'title' => get_the_title($p->ID),
                        'post_type' => $p->post_type,
                        'field' => $f,
                        'match_count' => $count,
                        'snippet'     => ACFSR_Core_Helpers::snippet($original, $this->needle, $this->caseSensitive),
                        'snippet_html' => ACFSR_Core_Helpers::snippet_highlight(
                            $original,
                            $this->needle,
                            $this->caseSensitive,
                            $this->wholeWord ?? false,
                            $this->useRegex  ?? false
                        ),
                        'edit_link'   => $edit,
                    ];
                }
            }
            if ($hitCount > 0) {
                $matches += $hitCount;
                $touched++;
            }
        }

        return compact('rows', 'matches', 'touched');
    }

    protected function scan_postmeta()
    {
        global $wpdb;
        $rows = [];
        $matches = 0;
        $touched = 0;

        $sql = $wpdb->prepare(
            "SELECT meta_id, post_id, meta_key, meta_value
             FROM {$wpdb->postmeta}
             WHERE meta_value LIKE %s OR meta_key LIKE %s
             LIMIT %d OFFSET %d",
            ACFSR_Core_Helpers::make_like($this->needle),
            ACFSR_Core_Helpers::make_like($this->needle),
            $this->perPage,
            $this->offset()
        );
        $cands = $wpdb->get_results($sql);
        $this->lastCounts['meta_full'] = count($cands) >= $this->perPage;

        foreach ($cands as $m) {
            $value = $m->meta_value;

            $isSerialized = ACFSR_Core_Helpers::is_serialized_value($value);
            $un = $isSerialized ? @unserialize($value) : $value;

            $res = ACFSR_Core_Deep_Replace::replace(
                $un,
                $this->needle,
                (string)$this->replace,
                $this->caseSensitive,
                $this->wholeWord,
                $this->useRegex
            );
            $count = $res['matches'];

            if ($count > 0) {
                if (!$this->dryRun && $this->replace !== null) {
                    $newValue = $isSerialized ? serialize($res['value']) : $res['value'];
                    $wpdb->update($wpdb->postmeta, ['meta_value' => $newValue], ['meta_id' => $m->meta_id]);
                    clean_post_cache($m->post_id);
                }

                $edit = get_edit_post_link((int) $m->post_id, '');
                if (empty($edit)) {
                    $edit = admin_url('post.php?post=' . (int) $m->post_id . '&action=edit');
                }

                $rows[] = [
                    'type'        => 'meta',
                    'id'          => (int) $m->post_id,
                    'title'       => get_the_title($m->post_id),
                    'meta_id'     => (int) $m->meta_id,
                    'meta_key'    => $m->meta_key,
                    'match_count' => $count,
                    'snippet'     => is_string($value) ? ACFSR_Core_Helpers::snippet($value, $this->needle, $this->caseSensitive) : '',
                    'snippet_html' => is_string($value) ? ACFSR_Core_Helpers::snippet_highlight($value, $this->needle, $this->caseSensitive, $this->wholeWord ?? false, $this->useRegex ?? false) : '',
                    'edit_link'   => $edit,
                ];
                $matches += $count;
                $touched++;
            }
        }
        return compact('rows', 'matches', 'touched');
    }

    protected function scan_options()
    {
        global $wpdb;
        $rows = [];
        $matches = 0;
        $touched = 0;

        $sql = $wpdb->prepare(
            "SELECT option_id, option_name, option_value
             FROM {$wpdb->options}
             WHERE option_value LIKE %s OR option_name LIKE %s
             LIMIT %d OFFSET %d",
            ACFSR_Core_Helpers::make_like($this->needle),
            ACFSR_Core_Helpers::make_like($this->needle),
            $this->perPage,
            $this->offset()
        );
        $cands = $wpdb->get_results($sql);
        $this->lastCounts['opts_full'] = count($cands) >= $this->perPage;

        foreach ($cands as $o) {
            $val = $o->option_value;
            $isSerialized = ACFSR_Core_Helpers::is_serialized_value($val);
            $un = $isSerialized ? @unserialize($val) : $val;

            $res = ACFSR_Core_Deep_Replace::replace($un, $this->needle, (string)$this->replace, $this->caseSensitive);
            $count = $res['matches'];

            if ($count > 0) {
                if (!$this->dryRun && $this->replace !== null) {
                    $newVal = $isSerialized ? serialize($res['value']) : $res['value'];
                    $wpdb->update($wpdb->options, ['option_value' => $newVal], ['option_id' => $o->option_id]);
                }

                $edit = admin_url('options-general.php');

                // If ACF Options page exists, use it (common place for ACF option fields)
                if (function_exists('acf_add_options_page')) {
                    // Common slug; if you use a custom slug in your project, adjust here
                    $maybe = admin_url('admin.php?page=acf-options');
                    $edit  = $maybe ?: $edit;
                }

                $rows[] = [
                    'type'        => 'option',
                    'id'          => (int) $o->option_id,
                    'title'       => $o->option_name,
                    'field'       => 'option_value',
                    'match_count' => $count,
                    'snippet'     => is_string($val) ? ACFSR_Core_Helpers::snippet($val, $this->needle, $this->caseSensitive) : '',
                    'snippet_html' => is_string($val) ? ACFSR_Core_Helpers::snippet_highlight($val, $this->needle, $this->caseSensitive, $this->wholeWord ?? false, $this->useRegex ?? false) : '',
                    'edit_link'   => $edit,
                ];
                $matches += $count;
                $touched++;
            }
        }

        return compact('rows', 'matches', 'touched');
    }

    protected function pattern(): string
    {
        return ACFSR_Core_Helpers::build_pattern($this->needle, $this->caseSensitive, $this->wholeWord, $this->useRegex);
    }
    protected function count_matches(string $text): int
    {
        $pat = $this->pattern();
        if ($pat === '//') return 0;
        $ok = preg_match_all($pat, $text, $m);
        return $ok === false ? 0 : (int)$ok;
    }
    protected function replace_str(string $text): string
    {
        $pat = $this->pattern();
        $new = preg_replace($pat, (string)$this->replace, $text);
        return $new === null ? $text : $new;
    }
}