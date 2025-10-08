<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://shandyward.com
 * @since      1.0.0
 *
 * @package    Acf_Aware_Search_Replace
 * @subpackage Acf_Aware_Search_Replace/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Acf_Aware_Search_Replace
 * @subpackage Acf_Aware_Search_Replace/admin
 * @author     Shandy Ward <shandy@shandyward.com>
 */
class ACFSR_Admin
{
	private $plugin_name;
	private $version;

	const NONCE_RUN    = 'acfsr_run';
	const NONCE_EXPORT = 'acfsr_export';

	const NONCE_CLEAR  = 'acfsr_clear';


	public function handle_clear()
	{
		if (! current_user_can('manage_options')) wp_die('Forbidden');
		check_admin_referer(self::NONCE_CLEAR);

		error_log('ACFSR handle_clear fired');

		delete_transient('acfsr_last_results');
		delete_transient('acfsr_last_params');
		delete_transient('acfsr_last_rows');

		wp_safe_redirect(add_query_arg(['page' => 'acfsr'], admin_url('tools.php')));
		exit;
	}


	public function __construct($plugin_name, $version)
	{
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	public function add_plugin_admin_menu()
	{
		add_management_page(
			__('ACF-Aware Search & Replace', 'acf-aware-search-replace'),
			__('ACF Search/Replace', 'acf-aware-search-replace'),
			'manage_options',
			'acfsr',
			[$this, 'display_plugin_admin_page']
		);
	}

	public function enqueue_styles()
	{
		wp_enqueue_style($this->plugin_name, ACFSR_URL . 'admin/css/admin.css', [], $this->version);
	}
	public function enqueue_scripts($hook)
	{
		if ($hook !== 'tools_page_acfsr') return;
		wp_enqueue_script('acfsr-admin', plugins_url('admin/js/admin.js', ACFSR_FILE), ['jquery'], ACFSR_VERSION, true);
	}

	public function display_plugin_admin_page()
	{
		$results = get_transient('acfsr_last_results');
		$params  = get_transient('acfsr_last_params');
		include ACFSR_DIR . 'admin/partials/acf-aware-search-replace-admin-display.php';
	}

	public function handle_run()
	{
		if (! current_user_can('manage_options')) wp_die('Forbidden');
		check_admin_referer(self::NONCE_RUN);

		// Retrieve previous params
		$prev_params = get_transient('acfsr_last_params') ?: [];

		// Input parameters
		$needle  = isset($_REQUEST['needle'])  ? wp_unslash($_REQUEST['needle'])  : ($prev_params['needle']  ?? '');
		$replace = array_key_exists('replace', $_REQUEST)
			? (($_REQUEST['replace'] !== '') ? wp_unslash($_REQUEST['replace']) : null)
			: ($prev_params['replace'] ?? null);

		$page = isset($_REQUEST['acfsr_page'])
			? max(1, (int)$_REQUEST['acfsr_page'])
			: (isset($_REQUEST['page']) ? max(1, (int)$_REQUEST['page']) : (int)($prev_params['page'] ?? 1));

		// Per-page logic
		if (isset($_REQUEST['per_page_select']) && $_REQUEST['per_page_select'] !== 'custom') {
			$per = max(1, (int)$_REQUEST['per_page_select']);
		} elseif (isset($_REQUEST['acfsr_per'])) {
			$per = max(1, (int)$_REQUEST['acfsr_per']);
		} elseif (isset($_REQUEST['per_page'])) {
			$per = max(1, (int)$_REQUEST['per_page']);
		} else {
			$per = max(1, (int)($prev_params['perPage'] ?? 50));
		}

		// Flags

		$caseSensitive = isset($_REQUEST['case_sensitive'])
			? ((int)$_REQUEST['case_sensitive'] === 1)
			: (bool)($prev_params['caseSensitive'] ?? false);

		$wholeWord     = isset($_REQUEST['whole_word'])   ? ((int)$_REQUEST['whole_word'] === 1)   : (bool)($prev_params['wholeWord']     ?? true);
		$useRegex      = isset($_REQUEST['use_regex'])    ? ((int)$_REQUEST['use_regex'] === 1)    : (bool)($prev_params['useRegex']      ?? false);
		$scanCore      = isset($_REQUEST['scan_core'])    ? ((int)$_REQUEST['scan_core'] === 1)    : (bool)($prev_params['scanCore']      ?? true);
		$scanMeta      = isset($_REQUEST['scan_meta'])    ? ((int)$_REQUEST['scan_meta'] === 1)    : (bool)($prev_params['scanMeta']      ?? true);
		$scanOptions   = isset($_REQUEST['scan_options']) ? ((int)$_REQUEST['scan_options'] === 1) : (bool)($prev_params['scanOptions']   ?? false);

		// Build scan args
		$args = [
			'needle'        => $needle,
			'replace'       => $replace,
			'dryRun'        => !isset($_POST['do_replace']),
			'caseSensitive' => $caseSensitive,
			'wholeWord'     => $wholeWord,
			'useRegex'      => $useRegex,
			'scanCore'      => $scanCore,
			'scanMeta'      => $scanMeta,
			'scanOptions'   => $scanOptions,
			'fieldsCore'    => ['post_title', 'post_content', 'post_excerpt'],
			'perPage'       => $per,
			'page'          => $page,
		];

		$process_all = !empty($_POST['process_all']) && isset($_POST['do_replace']);

		// Detect pure pagination (same query)
		$same_keys = ['needle', 'caseSensitive', 'wholeWord', 'useRegex', 'scanCore', 'scanMeta', 'scanOptions', 'replace'];
		$same_query = !empty($prev_params);
		foreach ($same_keys as $k) {
			if (($prev_params[$k] ?? null) !== ($args[$k] ?? null)) {
				$same_query = false;
				break;
			}
		}
		$prev_rows = get_transient('acfsr_last_rows');
		$can_reuse_cache = $same_query && is_array($prev_rows) && !$process_all;

		// ──────────────────────────────────────────────
		// Process ALL batches
		// ──────────────────────────────────────────────
		if ($process_all) {
			$total_matches = 0;
			$total_touched = 0;
			$batchPage = 1;

			do {
				$args['page'] = $batchPage;
				$scan = new ACFSR_Core_Scanner($args);
				$result = $scan->run();

				$total_matches += (int)($result['summary']['total_matches'] ?? 0);
				$total_touched += (int)($result['summary']['records_touched'] ?? 0);

				$has_more = method_exists($scan, 'likely_has_more') ? $scan->likely_has_more() : false;
				$batchPage++;
			} while ($has_more);

			delete_transient('acfsr_last_rows');

			$this->set_flash(
				sprintf(
					__('Replaced %1$d occurrences across %2$d records (all batches).', 'acf-aware-search-replace'),
					(int)$total_matches,
					(int)$total_touched
				),
				'success'
			);

			$results = [
				'rows' => [],
				'summary' => [
					'total_matches'    => $total_matches,
					'records_touched'  => $total_touched,
					'note'             => __('Processed all batches; row details omitted.', 'acf-aware-search-replace'),
					'likely_has_more'  => false,
					'total_rows'       => 0,
				],
			];
		}
		// ──────────────────────────────────────────────
		// Pagination reuse
		// ──────────────────────────────────────────────
		elseif ($can_reuse_cache && ($page !== (int)($prev_params['page'] ?? 1) || $per !== (int)($prev_params['perPage'] ?? 50))) {
			$results = get_transient('acfsr_last_results') ?: ['rows' => [], 'summary' => []];
		}
		// ──────────────────────────────────────────────
		// Fresh scan
		// ──────────────────────────────────────────────
		else {
			if (isset($_POST['do_replace'])) {
				// 1) Do the replacements for this page
				$doArgs = $args;
				$doArgs['dryRun'] = false;
				$doScan = new ACFSR_Core_Scanner($doArgs);
				$doRes  = $doScan->run();

				$mt = (int)($doRes['summary']['total_matches'] ?? 0);
				$tc = (int)($doRes['summary']['records_touched'] ?? 0);
				$pg = (int)$args['page'];

				if ($mt > 0) {
					$this->set_flash(
						sprintf(
							__('Replaced %1$d occurrences across %2$d records on page %3$d.', 'acf-aware-search-replace'),
							$mt,
							$tc,
							$pg
						),
						'success'
					);
				} else {
					$this->set_flash(
						__('No matches were replaced on this page.', 'acf-aware-search-replace'),
						'warning'
					);
				}

				// 2) Immediately re-scan as dry-run to refresh the table/snippets
				$viewArgs = $args;
				$viewArgs['dryRun'] = true;
				$viewScan = new ACFSR_Core_Scanner($viewArgs);
				$results  = $viewScan->run();

				// Ensure we’re not carrying over stale “all rows” cache
				delete_transient('acfsr_last_rows');
			} else {
				// Normal dry-run scan (no replace)
				$scan    = new ACFSR_Core_Scanner($args);
				$results = $scan->run();
			}

			// Cache sliced results for pagination
			$cache_cap = 5000;
			$rows = $results['rows'] ?? [];
			if (count($rows) > $cache_cap) {
				$rows = array_slice($rows, 0, $cache_cap);
				$results['summary']['note'] = __('Showing first 5,000 results for pagination. Refine search to narrow.', 'acf-aware-search-replace');
			}
			set_transient('acfsr_last_rows', $rows, MINUTE_IN_SECONDS * 30);

			// Set summary fields again (based on the latest scan we’ll display)
			$results['summary']['total_rows'] = count($rows);
			$results['summary']['likely_has_more'] =
				isset($viewScan) && method_exists($viewScan, 'likely_has_more') ? (bool)$viewScan->likely_has_more()
				: (isset($scan) && method_exists($scan, 'likely_has_more') ? (bool)$scan->likely_has_more()
					: false);
		}

		// Save state
		set_transient('acfsr_last_results', $results, MINUTE_IN_SECONDS * 30);
		set_transient('acfsr_last_params',  $args,    MINUTE_IN_SECONDS * 30);

		wp_safe_redirect(add_query_arg([
			'page'       => 'acfsr',
			'needle'     => rawurlencode($needle),
			'acfsr_page' => (int)$args['page'],
			'acfsr_per'  => (int)$args['perPage'],
		], admin_url('tools.php')));
		exit;
	}



	public function handle_export()
	{
		if (! current_user_can('manage_options')) wp_die('Forbidden');
		check_admin_referer(self::NONCE_EXPORT);

		// 1) Prefer the cached rows (set in handle_run for global pagination)
		$rows = get_transient('acfsr_last_rows');
		if (! is_array($rows)) {
			// 2) Fallback: re-run the scanner with last params and collect rows across batches
			$params = get_transient('acfsr_last_params');
			if (empty($params) || empty($params['needle'])) {
				wp_die(__('Please run a scan first.', 'acf-aware-search-replace'));
			}

			// Ensure safe defaults for flags
			$params['dryRun']     = true;
			$params['wholeWord']  = ! empty($params['wholeWord']);
			$params['useRegex']   = ! empty($params['useRegex']);

			// Collect across batches up to a cap (avoid runaway memory)
			$cap         = 20000;                // tune as needed
			$rows        = [];
			$batchPage   = 1;
			$perPage     = max(1, (int)($params['perPage'] ?? 2000));

			do {
				$params['page']    = $batchPage;
				$params['perPage'] = $perPage;

				$scan    = new ACFSR_Core_Scanner($params);
				$result  = $scan->run();
				$chunk   = $result['rows'] ?? [];

				if ($chunk) {
					// Trim if we would exceed cap
					$space = $cap - count($rows);
					if ($space <= 0) break;
					if (count($chunk) > $space) {
						$chunk = array_slice($chunk, 0, $space);
					}
					$rows = array_merge($rows, $chunk);
				}

				$batchPage++;
				$has_more = method_exists($scan, 'likely_has_more') ? $scan->likely_has_more() : false;
			} while ($has_more && count($rows) < $cap);
		}


		// 3) Stream CSV
		nocache_headers();
		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename=acfsr-results-' . date('Ymd-His') . '.csv');

		$out = fopen('php://output', 'w');

		// Header
		fputcsv($out, ['type', 'post_id_or_option_id', 'title_or_option', 'field_or_meta_key', 'match_count', 'snippet']);

		// Rows
		foreach ((array)$rows as $r) {
			fputcsv($out, [
				$r['type'] ?? '',
				$r['id'] ?? '',
				$r['title'] ?? '',
				$r['meta_key'] ?? ($r['field'] ?? ''),
				isset($r['match_count']) ? (int)$r['match_count'] : 0,
				// use plain snippet (no HTML) and keep it short
				mb_strimwidth((string)($r['snippet'] ?? ''), 0, 300, '…'),
			]);
		}

		fclose($out);
		exit;
	}


	public function init()
	{
		add_action('admin_menu',            [$this, 'add_plugin_admin_menu']);
		add_action('admin_enqueue_scripts', [$this, 'enqueue_styles']);
		add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);

		// These MUST match your form's hidden "action" value:
		add_action('admin_post_acfsr_run',    [$this, 'handle_run']);
		add_action('admin_post_acfsr_export', [$this, 'handle_export']);
		add_action('admin_post_acfsr_clear',  [$this, 'handle_clear']);
	}



	// Convenience setter
	private function set_flash(string $msg, string $type = 'success')
	{
		set_transient('acfsr_flash', ['msg' => $msg, 'type' => $type], 60);
	}
}