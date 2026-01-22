<?php
/**
 * Plugin Name:       WC VIP Club
 * Plugin URI:        https://elica-webservices.it
 * Description:       Automatically upgrades customers to VIP roles based on lifetime spending in WooCommerce. VIP role is a clone of customer role, customizable via role editors, email campaigns, and pricing rules.
 * Version:           1.2.0
 * Author:            Elisabetta Carrara
 * Author URI:        https://elica-webservices.it
 * Text Domain:       wc-vip-club
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      8.2
 * Requires Plugins:  woocommerce
 * WC requires at least: 6.0
 * WC tested up to:   9.5
 *
 * @package WC_VIP_Club
 */

defined( 'ABSPATH' ) || exit;

/**
 * Declare High-Performance Order Storage (HPOS) compatibility.
 * This ensures the plugin works with modern WooCommerce database structures.
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

/**
 * Plugin constants.
 * These are used throughout the plugin to locate files and version assets.
 */
define( 'WC_VIP_CLUB_VERSION', '1.2.0' );
define( 'WC_VIP_CLUB_PLUGIN_FILE', __FILE__ );
define( 'WC_VIP_CLUB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WC_VIP_CLUB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Check whether WooCommerce is active.
 * * @return bool True if WooCommerce is active, false otherwise.
 */
function wc_vip_club_is_woocommerce_active(): bool {
	if ( ! function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	return is_plugin_active( 'woocommerce/woocommerce.php' );
}

/**
 * Plugin activation callback.
 * Ensures requirements are met and registers the My Account rewrite rules.
 */
function wc_vip_club_activate(): void {
	if ( ! wc_vip_club_is_woocommerce_active() ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );

		wp_die(
			esc_html__(
				'WC VIP Club cannot be activated because WooCommerce is not active.',
				'wc-vip-club'
			),
			esc_html__( 'Plugin activation error', 'wc-vip-club' ),
			array(
				'back_link' => true,
			)
		);
	}

	// Flush rewrite rules to register the vip_club endpoint immediately.
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'wc_vip_club_activate' );

/**
 * Plugin deactivation callback.
 * Cleans up rewrite rules when the plugin is turned off.
 */
function wc_vip_club_deactivate(): void {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'wc_vip_club_deactivate' );

/**
 * Bootstrap the plugin.
 * Loads the core logic class once all other plugins are loaded.
 */
function wc_vip_club_init(): void {
	// Only load if WooCommerce is ready.
	if ( ! wc_vip_club_is_woocommerce_active() ) {
		add_action(
			'admin_notices',
			function () {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'WC VIP Club requires WooCommerce to be installed and active.', 'wc-vip-club' ) . '</p></div>';
			}
		);
		return;
	}

	$class_file = WC_VIP_CLUB_PLUGIN_DIR . 'includes/class-wc-vip-club.php';

	if ( file_exists( $class_file ) ) {
		require_once $class_file;

		// Initialize the main class singleton.
		if ( class_exists( 'WC_VIP_Club' ) ) {
			WC_VIP_Club::get_instance();
		}
	}
}
add_action( 'plugins_loaded', 'wc_vip_club_init' );
