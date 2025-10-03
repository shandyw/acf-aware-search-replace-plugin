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
	public function enqueue_scripts()
	{
		wp_enqueue_script($this->plugin_name, ACFSR_URL . 'admin/js/admin.js', ['jquery'], $this->version, true);
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

		$needle  = isset($_POST['needle']) ? wp_unslash($_POST['needle']) : '';
		$replace = ($_POST['replace'] ?? '') !== '' ? wp_unslash($_POST['replace']) : null;
		$page    = isset($_POST['page']) ? max(1, (int)$_POST['page']) : 1;
		$per     = isset($_POST['per_page']) ? max(1, (int)$_POST['per_page']) : 2000;

		$args = [
			'needle' => $needle,
			'replace' => $replace,
			'dryRun' => !isset($_POST['do_replace']),
			'caseSensitive' => !empty($_POST['case']),
			'scanCore' => !empty($_POST['scan_core']),
			'scanMeta' => !empty($_POST['scan_meta']),
			'scanOptions' => !empty($_POST['scan_options']),
			'fieldsCore' => ['post_title', 'post_content', 'post_excerpt'],
			'perPage' => $per,
			'page' => $page,
		];

		$process_all = !empty($_POST['process_all']) && isset($_POST['do_replace']);
		if ($process_all) {
			$total_touched = 0;
			$total_matches = 0;
			$batch = 1;
			do {
				$args['page'] = $batch;
				$args['dryRun'] = false;
				$scan = new ACFSR_Core_Scanner($args);
				$res  = $scan->run();
				$mt   = (int)($res['summary']['total_matches'] ?? 0);
				$tc   = (int)($res['summary']['records_touched'] ?? 0);
				$total_matches += $mt;
				$total_touched += $tc;
				$batch++;
			} while ($mt > 0 || $scan->likely_has_more());

			$results = ['rows' => [], 'summary' => [
				'total_matches' => $total_matches,
				'records_touched' => $total_touched,
				'note' => __('Processed all batches; row details omitted.', 'acf-aware-search-replace'),
			]];
		} else {
			$scan = new ACFSR_Core_Scanner($args);
			$results = $scan->run();
		}

		set_transient('acfsr_last_results', $results, MINUTE_IN_SECONDS * 30);
		set_transient('acfsr_last_params',  $args,    MINUTE_IN_SECONDS * 30);

		wp_safe_redirect(add_query_arg([
			'page' => 'acfsr',
			'needle' => rawurlencode($needle),
			'acfsr_page' => (int)$args['page'],
			'acfsr_per' => (int)$args['perPage'],
		], admin_url('tools.php')));
		exit;
	}

	public function handle_export()
	{
		if (! current_user_can('manage_options')) wp_die('Forbidden');
		check_admin_referer(self::NONCE_EXPORT);

		$params = get_transient('acfsr_last_params');
		if (empty($params) || empty($params['needle'])) wp_die(__('Please run a scan first.', 'acf-aware-search-replace'));

		$params['dryRun'] = true;
		$scan = new ACFSR_Core_Scanner($params);
		$res  = $scan->run();
		$rows = $res['rows'] ?? [];

		nocache_headers();
		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename=acfsr-results-' . date('Ymd-His') . '.csv');

		$out = fopen('php://output', 'w');
		fputcsv($out, ['type', 'post_id_or_option_id', 'title_or_option', 'field_or_meta_key', 'match_count', 'snippet']);
		foreach ($rows as $r) {
			fputcsv($out, [
				$r['type'] ?? '',
				$r['id'] ?? '',
				$r['title'] ?? '',
				$r['meta_key'] ?? ($r['field'] ?? ''),
				$r['match_count'] ?? 0,
				mb_strimwidth((string)($r['snippet'] ?? ''), 0, 300, '…'),
			]);
		}
		fclose($out);
		exit;
	}
}