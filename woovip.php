<?php
/**
 * Plugin Name: WooVIP
 * Description: Create a VIP Area on your website that only specific customers can see.
 * Plugin URI: https://plugins.longwatchstudio.com/product/woovip/
 * Author: Long Watch Studio
 * Author URI: https://longwatchstudio.com
 * Version: 1.4.4
 * License: Copyright LongWatchStudio 2021
 * Text Domain: woovip-lite
 * Domain Path: /languages
 * WC requires at least: 3.4.0
 * WC tested up to: 4.9
 *
 * Copyright (c) 2021 Long Watch Studio (email: contact@longwatchstudio.com). All rights reserved.
 *
 *
 */


// don't call the file directly
if( !defined( 'ABSPATH' ) ) exit();

/** That class holds the entire plugin. */
final class LWS_WooVIP
{

	public static function init()
	{
		static $instance = false;
		if( !$instance )
		{
			$instance = new self();
			$instance->defineConstants();
			$instance->load_plugin_textdomain();

			add_action( 'lws_adminpanel_register', array($instance, 'register') );
			add_action( 'lws_adminpanel_plugins', array($instance, 'plugin') );

			add_filter( 'plugin_action_links_'.plugin_basename( __FILE__ ), array($instance, 'extensionListActions'), 10, 2 );
			add_filter( 'plugin_row_meta', array($instance, 'addLicenceLink'), 10, 4 );
			add_filter( 'lws_adminpanel_purchase_url_woovip', array($instance, 'addPurchaseUrl'), 10, 1 );
			foreach( array('') as $page)
				add_filter( 'lws_adminpanel_plugin_version_'.LWS_WOOVIP_PAGE.$page, array($instance, 'addPluginVersion'), 10, 1 );
			add_filter( 'lws_adminpanel_documentation_url_woovip', array($instance, 'addDocUrl'), 10, 1 );

			if( \is_admin() && !(defined('DOING_AJAX') && DOING_AJAX) )
			{
				require_once LWS_WOOVIP_INCLUDES.'/updater.php';
				// piority as soon as possible, But sad bug from WP.
				// Trying to get property of non-object in ./wp-includes/post.php near line 3917: $feeds = $wp_rewrite->feeds;
				// cannot do it sooner.
				add_action('setup_theme', array('\LWS\WOOVIP\Updater', 'checkUpdate'), -100);
				add_action('setup_theme', array($instance, 'forceVisitLicencePage'), 0);
			}

			$instance->install();

			register_activation_hook( __FILE__, 'LWS_WooVIP::activation' );
		}
		return $instance;
	}

	function forceVisitLicencePage()
	{
		if( \get_option('lws_woovip_redirect_to_licence', 0) > 0 )
		{
			\update_option('lws_woovip_redirect_to_licence', 0);
			if( \wp_redirect(\add_query_arg(array('page'=>LWS_WOOVIP_PAGE.'-system', 'tab'=>'lic'), admin_url('admin.php'))) )
				exit;
		}
	}

	public function v()
	{
		static $version = '';
		if( empty($version) ){
			if( !function_exists('get_plugin_data') ) require_once(ABSPATH . 'wp-admin/includes/plugin.php');
			$data = \get_plugin_data(__FILE__, false);
			$version = (isset($data['Version']) ? $data['Version'] : '0');
		}
		return $version;
	}

	/** Load translation file
	 * If called via a hook like this
	 * @code
	 * add_action( 'plugins_loaded', array($instance,'load_plugin_textdomain'), 1 );
	 * @endcode
	 * Take care no text is translated before. */
	function load_plugin_textdomain() {
		load_plugin_textdomain( 'woovip-lite', FALSE, basename( dirname( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Define the plugin constants
	 *
	 * @return void
	 */
	private function defineConstants()
	{
		define( 'LWS_WOOVIP_VERSION', $this->v() );
		define( 'LWS_WOOVIP_FILE', __FILE__ );
		define( 'LWS_WOOVIP_DOMAIN', 'woovip-lite' );
		define( 'LWS_WOOVIP_PAGE', 'woovip' );

		define( 'LWS_WOOVIP_PATH', dirname( LWS_WOOVIP_FILE ) );
		define( 'LWS_WOOVIP_INCLUDES', LWS_WOOVIP_PATH . '/include' );
		define( 'LWS_WOOVIP_SNIPPETS', LWS_WOOVIP_PATH . '/snippets' );
		define( 'LWS_WOOVIP_ASSETS',   LWS_WOOVIP_PATH . '/assets' );

		define( 'LWS_WOOVIP_URL', 		plugins_url( '', LWS_WOOVIP_FILE ) );
		define( 'LWS_WOOVIP_JS',  		plugins_url( '/js', LWS_WOOVIP_FILE ) );
		define( 'LWS_WOOVIP_CSS', 		plugins_url( '/css', LWS_WOOVIP_FILE ) );
		define( 'LWS_WOOVIP_IMG', 		plugins_url( '/img', LWS_WOOVIP_FILE ) );
	}

	public function extensionListActions($links, $file)
	{
		$label = __('Settings'); // use standart wp sentence, no text domain
		$url = add_query_arg(array('page'=>LWS_WOOVIP_PAGE), admin_url('admin.php'));
		array_unshift($links, "<a href='$url'>$label</a>");
		$label = __('Help'); // use standart wp sentence, no text domain
		$url = esc_attr($this->addDocUrl(''));
		$links[] = "<a href='$url'>$label</a>";
		return $links;
	}

	public function addLicenceLink($links, $file, $data, $status)
	{
		if( (!defined('LWS_WOOVIP_ACTIVATED') || !LWS_WOOVIP_ACTIVATED) && plugin_basename(__FILE__)==$file)
		{
			$label = __('Add Licence Key', 'woovip-lite');
			$url = add_query_arg(array('page'=>LWS_WOOVIP_PAGE, 'tab'=>'license'), admin_url('admin.php'));
			$links[] = "<a href='$url'>$label</a>";
		}
		return $links;
	}

	public function addPurchaseUrl($url)
	{
		return __("https://plugins.longwatchstudio.com/en/product/woovip-vip-lounge-membership/", 'woovip-lite');
	}

	public function addPluginVersion($url)
	{
		return $this->v();
	}

	public function addDocUrl($url)
	{
		return __("https://plugins.longwatchstudio.com/docs/woovip/", 'woovip-lite');
	}

	function register()
	{
		require_once LWS_WOOVIP_INCLUDES . '/ui/admin.php';
		new \LWS\WOOVIP\Ui\Admin();
	}

	public function plugin()
	{
		\lws_plugin(__FILE__, LWS_WOOVIP_PAGE, md5(\get_class() . 'update'), 'LWS_WOOVIP_ACTIVATED', LWS_WOOVIP_PAGE . '-system');
	}

	/** Add elements we need on this plugin to work */
	public static function activation()
	{
		require_once dirname(__FILE__).'/include/updater.php';
		\LWS\WOOVIP\Updater::checkUpdate();

		wp_schedule_event(time(), 'daily', 'lws_woovip_daily_event');
	}

	/** autoload WooVIP core and collection classes. */
	public function autoload($class)
	{
		$domain = 'LWS\WOOVIP\\';
		if( 0 === strpos($class, $domain) )
		{
			$rest = substr($class, strlen($domain));
			$publicNamespaces = array(
				'Core'
			);
			$publicClasses = array(
			);

			if( in_array(explode('\\', $rest, 2)[0], $publicNamespaces) || in_array($rest, $publicClasses) )
			{
				$basename = str_replace('\\', '/', strtolower($rest));
				$filepath = LWS_WOOVIP_INCLUDES . '/' . $basename . '.php';
				if( file_exists($filepath) )
				{
					@include_once $filepath;
					return true;
				}
			}
		}
	}

	/**	Is WooCommerce installed and activated.
	 *	Could be sure only after hook 'plugins_loaded'.
	 *	@return is WooCommerce installed and activated. */
	static public function isWC()
	{
		return function_exists('wc');
	}

	private function install()
	{
		spl_autoload_register(array($this, 'autoload'));

		require_once LWS_WOOVIP_INCLUDES . '/ui/pages.php';
		new \LWS\WOOVIP\Ui\Pages();
		require_once LWS_WOOVIP_INCLUDES . '/ui/users.php';
		new \LWS\WOOVIP\Ui\Users();
		require_once LWS_WOOVIP_INCLUDES . '/ui/menus.php';
		new \LWS\WOOVIP\Ui\Menus();
	}
}

LWS_WooVIP::init();
{
	if( \file_exists($asset = (dirname(__FILE__) . '/assets/lws-adminpanel/lws-adminpanel.php')) )
		include_once $asset;
	if( \file_exists($asset = (dirname(__FILE__) . '/modules/woovip-pro/woovip-pro.php')) )
		include_once $asset;
}
