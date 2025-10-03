<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://shandyward.com
 * @since             1.0.0
 * @package           Acf_Aware_Search_Replace
 *
 * @wordpress-plugin
 * Plugin Name:       ACF-Aware Search & Replace
 * Plugin URI:        https://wordpress.org/plugins/acf-aware-search-replace/
 * Description:       Safe, serialization-aware search/replace across posts, postmeta (incl. ACF), and options (incl. ACF Options). Shows counts and per-page results. Includes WP-CLI.
 * Version:           1.0.0
 * Author:            Shandy Ward
 * Author URI:        https://shandyward.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       acf-aware-search-replace
 * Domain Path:       /languages
 * Requires PHP:      7.4
 */

// If this file is called directly, abort.
if (! defined('WPINC')) {
	die;
}

define('ACFSR_VERSION', '1.0.0');
define('ACFSR_FILE', __FILE__);
define('ACFSR_DIR', plugin_dir_path(__FILE__));
define('ACFSR_URL', plugin_dir_url(__FILE__));
/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define('ACF_AWARE_SEARCH_REPLACE_VERSION', '1.0.0');

require plugin_dir_path(__FILE__) . 'includes/class-acf-aware-search-replace-activator.php';
require plugin_dir_path(__FILE__) . 'includes/class-acf-aware-search-replace-deactivator.php';

function acfsr_activate()
{
	ACFSR_Activator::activate();
}
function acfsr_deactivate()
{
	ACFSR_Deactivator::deactivate();
}
register_activation_hook(__FILE__, 'acfsr_activate');
register_deactivation_hook(__FILE__, 'acfsr_deactivate');

require plugin_dir_path(__FILE__) . 'includes/class-acf-aware-search-replace.php';

function run_acf_aware_search_replace()
{
	$plugin = new ACFSR();
	$plugin->run();
}
run_acf_aware_search_replace();