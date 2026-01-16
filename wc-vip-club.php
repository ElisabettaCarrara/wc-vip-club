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
 * Text Domain: vip-club
 * Domain Path: /languages
 * Requires at least: 5.8
 * Tested up to: 6.6
 * Requires PHP: 8.0
 * Requires Plugins: woocommerce
 *
 * @package    WC_VIP_Club
 * @author     Elisabetta Carrara <elisabetta.marina.clelia@gmail.com>
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define plugin constants.
if ( ! defined( 'WC_VIP_CLUB_VERSION' ) ) {
	define( 'WC_VIP_CLUB_VERSION', '1.0.0' );
}

if ( ! defined( 'WC_VIP_CLUB_PLUGIN_FILE' ) ) {
	define( 'WC_VIP_CLUB_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'WC_VIP_CLUB_PLUGIN_DIR' ) ) {
	define( 'WC_VIP_CLUB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'WC_VIP_CLUB_PLUGIN_URL' ) ) {
	define( 'WC_VIP_CLUB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

/**
 * Check if WooCommerce is active.
 *
 * @return bool True if WooCommerce is active, false otherwise.
 */
function wc_vip_club_is_woocommerce_active() {
	return in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true );
}

/**
 * Display admin notice if WooCommerce is not active.
 *
 * @return void
 */
function wc_vip_club_woocommerce_missing_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: %s: Plugin name. */
				esc_html__( '%s requires WooCommerce to be installed and active.', 'vip-club' ),
				'<strong>' . esc_html__( 'VIP Club', 'vip-club' ) . '</strong>'
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Initialize the plugin.
 *
 * @return void
 */
function wc_vip_club_init() {
	// Check if WooCommerce is active.
	if ( ! wc_vip_club_is_woocommerce_active() ) {
		add_action( 'admin_notices', 'wc_vip_club_woocommerce_missing_notice' );
		return;
	}

	// Load the main plugin class.
	require_once WC_VIP_CLUB_PLUGIN_DIR . 'includes/class-wc-vip-club.php';

	// Initialize the plugin.
	WC_VIP_Club::get_instance();
}
add_action( 'plugins_loaded', 'wc_vip_club_init' );

/**
 * Enqueue plugin styles.
 *
 * @return void
 */
function wc_vip_club_enqueue_styles() {
	// Only enqueue on account pages.
	if ( ! is_account_page() ) {
		return;
	}

	wp_enqueue_style(
		'wc-vip-club',
		WC_VIP_CLUB_PLUGIN_URL . 'assets/wc-vip-club.css',
		array(),
		WC_VIP_CLUB_VERSION
	);
}
add_action( 'wp_enqueue_scripts', 'wc_vip_club_enqueue_styles' );

/**
 * Plugin activation hook.
 *
 * @return void
 */
function wc_vip_club_activate() {
	// Check if WooCommerce is active.
	if ( ! wc_vip_club_is_woocommerce_active() ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die(
			sprintf(
				/* translators: %s: Plugin name. */
				esc_html__( '%s requires WooCommerce to be installed and active.', 'vip-club' ),
				'<strong>' . esc_html__( 'VIP Club', 'vip-club' ) . '</strong>'
			),
			esc_html__( 'Plugin Activation Error', 'vip-club' ),
			array( 'back_link' => true )
		);
		return;
	}

	// Add VIP Club endpoint for account pages.
	add_rewrite_endpoint( 'vip_club', EP_ROOT | EP_PAGES );
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'wc_vip_club_activate' );

/**
 * Plugin deactivation hook.
 *
 * @return void
 */
function wc_vip_club_deactivate() {
	// Flush rewrite rules.
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'wc_vip_club_deactivate' );
