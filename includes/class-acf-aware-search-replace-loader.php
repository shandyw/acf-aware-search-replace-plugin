<?php

/**
 * Register all actions and filters for the plugin
 *
 * @link       https://shandyward.com
 * @since      1.0.0
 *
 * @package    Acf_Aware_Search_Replace
 * @subpackage Acf_Aware_Search_Replace/includes
 */

/**
 * Register all actions and filters for the plugin.
 *
 * Maintain a list of all hooks that are registered throughout
 * the plugin, and register them with the WordPress API. Call the
 * run function to execute the list of actions and filters.
 *
 * @package    Acf_Aware_Search_Replace
 * @subpackage Acf_Aware_Search_Replace/includes
 * @author     Shandy Ward <shandy@shandyward.com>
 */
class ACFSR_Loader
{
	protected $actions   = [];
	protected $filters   = [];
	protected $shortcodes = [];

	public function add_action($hook, $component, $callback, $priority = 10, $accepted_args = 1)
	{
		$this->actions[] = compact('hook', 'component', 'callback', 'priority', 'accepted_args');
	}

	public function add_filter($hook, $component, $callback, $priority = 10, $accepted_args = 1)
	{
		$this->filters[] = compact('hook', 'component', 'callback', 'priority', 'accepted_args');
	}

	public function add_shortcode($tag, $callback)
	{
		$this->shortcodes[] = compact('tag', 'callback');
	}

	public function run()
	{
		foreach ($this->filters as $hook) {
			add_filter($hook['hook'], [$hook['component'], $hook['callback']], $hook['priority'], $hook['accepted_args']);
		}
		foreach ($this->actions as $hook) {
			add_action($hook['hook'], [$hook['component'], $hook['callback']], $hook['priority'], $hook['accepted_args']);
		}
		foreach ($this->shortcodes as $sc) {
			add_shortcode($sc['tag'], $sc['callback']);
		}
	}
}