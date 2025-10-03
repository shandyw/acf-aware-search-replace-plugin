<?php

/**
 * Fired during plugin deactivation
 *
 * @link       https://shandyward.com
 * @since      1.0.0
 *
 * @package    Acf_Aware_Search_Replace
 * @subpackage Acf_Aware_Search_Replace/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 * @package    Acf_Aware_Search_Replace
 * @subpackage Acf_Aware_Search_Replace/includes
 * @author     Shandy Ward <shandy@shandyward.com>
 */
class ACFSR_Deactivator
{
	public static function deactivate()
	{
		// cleanup scheduled events, etc.
	}
}