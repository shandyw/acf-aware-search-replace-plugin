<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://shandyward.com
 * @since      1.0.0
 *
 * @package    Acf_Aware_Search_Replace
 * @subpackage Acf_Aware_Search_Replace/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Acf_Aware_Search_Replace
 * @subpackage Acf_Aware_Search_Replace/public
 * @author     Shandy Ward <shandy@shandyward.com>
 */
class ACFSR_Public
{
	private $plugin_name;
	private $version;

	public function __construct($plugin_name, $version)
	{
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
	}

	public function enqueue_styles()
	{ /* wp_enqueue_style if you add UI */
	}
	public function enqueue_scripts()
	{ /* wp_enqueue_script if you add UI */
	}

	public function shortcode_count()
	{
		if (! current_user_can('manage_options')) return '';
		$res = get_transient('acfsr_last_results');
		$count = (int)($res['summary']['total_matches'] ?? 0);
		return esc_html(sprintf(__('Last scan matches: %d', 'acf-aware-search-replace'), $count));
	}
}