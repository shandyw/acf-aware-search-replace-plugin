<?php
class ACFSR
{
	protected $loader;
	protected $plugin_name = 'acf-aware-search-replace';
	protected $version     = ACFSR_VERSION;

	public function __construct()
	{
		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
		$this->define_cli();
	}

	private function load_dependencies()
	{
		require_once ACFSR_DIR . 'includes/class-acf-aware-search-replace-loader.php';
		require_once ACFSR_DIR . 'includes/class-acf-aware-search-replace-i18n.php';

		// Admin/Public
		require_once ACFSR_DIR . 'admin/class-acf-aware-search-replace-admin.php';
		require_once ACFSR_DIR . 'public/class-acf-aware-search-replace-public.php';

		// Core logic
		require_once ACFSR_DIR . 'core/class-helpers.php';
		require_once ACFSR_DIR . 'core/class-deep-replace.php';
		require_once ACFSR_DIR . 'core/class-scanner.php';
		require_once ACFSR_DIR . 'core/class-cli.php';

		$this->loader = new ACFSR_Loader();
	}

	private function set_locale()
	{
		$plugin_i18n = new ACFSR_i18n();
		$this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');
	}

	private function define_admin_hooks()
	{
		$plugin_admin = new ACFSR_Admin($this->get_plugin_name(), $this->get_version());

		$this->loader->add_action('admin_menu', $plugin_admin, 'add_plugin_admin_menu');
		$this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
		$this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');

		// Form handlers (scan/replace + CSV export)
		$this->loader->add_action('admin_post_acfsr_run',    $plugin_admin, 'handle_run');
		$this->loader->add_action('admin_post_acfsr_export', $plugin_admin, 'handle_export');
	}

	private function define_public_hooks()
	{
		$plugin_public = new ACFSR_Public($this->get_plugin_name(), $this->get_version());
		$this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_styles');
		$this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_scripts');
		$this->loader->add_shortcode('acfsr_count', [$plugin_public, 'shortcode_count']);
	}

	private function define_cli()
	{
		if (defined('WP_CLI') && WP_CLI) {
			\WP_CLI::add_command('acfsr', [new \ACFSR_Core_CLI(), 'handle']);
		}
	}

	public function run()
	{
		$this->loader->run();
	}
	public function get_plugin_name()
	{
		return $this->plugin_name;
	}
	public function get_version()
	{
		return $this->version;
	}
}