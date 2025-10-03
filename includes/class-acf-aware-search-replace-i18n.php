<?php

/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @link       https://shandyward.com
 * @since      1.0.0
 *
 * @package    Acf_Aware_Search_Replace
 * @subpackage Acf_Aware_Search_Replace/includes
 */

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      1.0.0
 * @package    Acf_Aware_Search_Replace
 * @subpackage Acf_Aware_Search_Replace/includes
 * @author     Shandy Ward <shandy@shandyward.com>
 */
class ACFSR_i18n
{
	public function load_plugin_textdomain()
	{
		load_plugin_textdomain('acf-aware-search-replace', false, dirname(plugin_basename(ACFSR_FILE)) . '/languages/');
	}
}