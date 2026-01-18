<?php
/**
 * Plugin Name: VIP Club
 * Plugin URI:  https://elica-webservices.it
 * Description: Automatic VIP role assignment based on customer lifetime spending in WooCommerce.
 * Version:     1.0.0
 * Author:      Elisabetta Carrara
 * Author URI:  https://elica-webservices.it
 * License:     GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wc-vip-club
 * Domain Path: /languages
 * Requires at least: 5.8
 * Tested up to: 6.6
 * Requires PHP: 8.2
 * Requires Plugins: woocommerce
 *
 * @package    WC_VIP_Club
 * @author     Elisabetta Carrara <elisabetta.marina.clelia@gmail.com>
 */

defined( 'ABSPATH' ) || exit;

/*
|--------------------------------------------------------------------------
| Constants
|--------------------------------------------------------------------------
*/
define( 'WC_VIP_CLUB_VERSION', '1.0.0' );
define( 'WC_VIP_CLUB_PLUGIN_FILE', __FILE__ );
define( 'WC_VIP_CLUB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WC_VIP_CLUB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Check if ClassicPress is active.
 *
 * @since 1.0.0
 *
 * @return bool True if ClassicPress is active, false otherwise.
 */
function wc_vip_club_is_classicpress() {
	return function_exists( 'classicpress_version' );
}

/**
 * Check if WooCommerce is active.
 *
 * @since 1.0.0
 *
 * @return bool True if WooCommerce is active, false otherwise.
 */
function wc_vip_club_is_woocommerce_active() {
	include_once ABSPATH . 'wp-admin/includes/plugin.php';
	return is_plugin_active( 'woocommerce/woocommerce.php' );
}

/**
 * Check if Classic Commerce is active.
 *
 * @since 1.0.0
 *
 * @return bool True if Classic Commerce is active, false otherwise.
 */
function wc_vip_club_is_classic_commerce_active() {
	include_once ABSPATH . 'wp-admin/includes/plugin.php';
	return is_plugin_active( 'classic-commerce/classic-commerce.php' );
}

/**
 * Display admin notice for missing WooCommerce.
 *
 * @since 1.0.0
 *
 * @return void
 */
function wc_vip_club_missing_woocommerce_notice() {
	echo '<div class="notice notice-error"><p>';
	echo esc_html__( 'VIP Club requires WooCommerce to be installed and active.', 'wc-vip-club' );
	echo '</p></div>';
}

/**
 * Display admin notice for missing Classic Commerce.
 *
 * @since 1.0.0
 *
 * @return void
 */
function wc_vip_club_missing_classic_commerce_notice() {
	echo '<div class="notice notice-error"><p>';
	echo esc_html__( 'VIP Club requires Classic Commerce to be installed and active when running on ClassicPress.', 'wc-vip-club' );
	echo '</p></div>';
}

/**
 * Check if plugin requirements are met.
 *
 * @since 1.0.0
 *
 * @return bool True if requirements are met, false otherwise.
 */
function wc_vip_club_requirements_met() {

	if ( wc_vip_club_is_classicpress() ) {
		if ( ! wc_vip_club_is_classic_commerce_active() ) {
			add_action( 'admin_notices', 'wc_vip_club_missing_classic_commerce_notice' );
			return false;
		}
	} elseif ( ! wc_vip_club_is_woocommerce_active() ) {
		add_action( 'admin_notices', 'wc_vip_club_missing_woocommerce_notice' );
		return false;
	}

	return true;
}

/**
 * Bootstrap the plugin.
 *
 * @since 1.0.0
 *
 * @return void
 */
function wc_vip_club_bootstrap() {

	if ( ! wc_vip_club_requirements_met() ) {
		return;
	}

	require_once WC_VIP_CLUB_PLUGIN_DIR . 'includes/class-wc-vip-club.php';

	add_action(
		'plugins_loaded',
		array( 'WC_VIP_Club', 'get_instance' )
	);
}
wc_vip_club_bootstrap();

/**
 * Enqueue plugin styles.
 *
 * @since 1.0.0
 *
 * @return void
 */
function wc_vip_club_enqueue_styles() {
	if ( function_exists( 'is_account_page' ) && is_account_page() ) {
		wp_enqueue_style(
			'wc-vip-club',
			WC_VIP_CLUB_PLUGIN_URL . 'assets/wc-vip-club.css',
			array(),
			WC_VIP_CLUB_VERSION
		);
	}
}
add_action( 'wp_enqueue_scripts', 'wc_vip_club_enqueue_styles' );

/**
 * Plugin activation hook.
 *
 * @since 1.0.0
 *
 * @return void
 */
function wc_vip_club_activate() {

	if ( ! wc_vip_club_requirements_met() ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die(
			esc_html__( 'VIP Club cannot be activated because its required commerce plugin is missing.', 'wc-vip-club' ),
			esc_html__( 'Activation error', 'wc-vip-club' ),
			array( 'back_link' => true )
		);
	}

	add_rewrite_endpoint( 'vip_club', EP_ROOT | EP_PAGES );
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'wc_vip_club_activate' );

/**
 * Plugin deactivation hook.
 *
 * @since 1.0.0
 *
 * @return void
 */
function wc_vip_club_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'wc_vip_club_deactivate' );
