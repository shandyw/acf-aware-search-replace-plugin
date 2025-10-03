<?php

namespace ACFSR\CLI;

use ACFSR\Scanner;

if (!defined('ABSPATH')) exit;

class ACFSR_Core_CLI
{
    public function handle($args, $assoc_args)
    {
        if (empty($args[0])) {
            \WP_CLI::error('Usage: wp acfsr <needle> [--replace=<replacement>] [--dry-run] [--case-sensitive] [--fields=title,content,excerpt] [--per-page=2000] [--page=1] [--export=/path.csv]');
        }
        $needle  = (string)$args[0];
        $replace = $assoc_args['replace'] ?? null;
        $dry     = isset($assoc_args['dry-run']) ? (bool)$assoc_args['dry-run'] : is_null($replace);
        $case    = isset($assoc_args['case-sensitive']);
        $fields  = !empty($assoc_args['fields']) ? array_map('trim', explode(',', $assoc_args['fields'])) : ['post_title', 'post_content', 'post_excerpt'];
        $per     = (int)($assoc_args['per-page'] ?? 2000);
        $page    = (int)($assoc_args['page'] ?? 1);
        $export  = $assoc_args['export'] ?? null;

        $scan = new Scanner([
            'needle' => $needle,
            'replace' => $replace,
            'dryRun' => $dry,
            'caseSensitive' => $case,
            'scanCore' => true,
            'scanMeta' => true,
            'scanOptions' => true,
            'fieldsCore' => $fields,
            'perPage' => $per,
            'page' => $page,
        ]);
        $res = $scan->run();

        $total = (int)($res['summary']['total_matches'] ?? 0);
        $touch = (int)($res['summary']['records_touched'] ?? 0);
        \WP_CLI::log(($dry ? 'Scan' : 'Replace') . " done. Matches: $total | Records touched: $touch");

        if ($export) {
            $fp = fopen($export, 'w');
            if (!$fp) \WP_CLI::error("Cannot write to $export");
            fputcsv($fp, ['type', 'id', 'title', 'field_or_meta_key', 'match_count', 'snippet']);
            foreach (($res['rows'] ?? []) as $r) {
                fputcsv($fp, [
                    $r['type'] ?? '',
                    $r['id'] ?? '',
                    $r['title'] ?? '',
                    $r['meta_key'] ?? ($r['field'] ?? ''),
                    $r['match_count'] ?? 0,
                    mb_strimwidth((string)($r['snippet'] ?? ''), 0, 300, '…'),
                ]);
            }
            fclose($fp);
            \WP_CLI::success("Exported CSV to $export");
        }
    }
}