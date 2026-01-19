<?php
/**
 * Plugin Name:       VIP Club for WooCommerce
 * Plugin URI:        https://example.com/wc-vip-club
 * Description:       Automatically upgrades customers to VIP roles based on lifetime spending in WooCommerce.
 * Version:           1.0.0
 * Author:            Elisabetta Carrara
 * Author URI:        https://example.com
 * Text Domain:       wc-vip-club
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      8.2
 * Requires Plugins:  woocommerce
 * WC requires at least: 6.0
 * WC tested up to:   9.5
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plugin constants
 */
define( 'WC_VIP_CLUB_VERSION', '1.0.0' );
define( 'WC_VIP_CLUB_PLUGIN_FILE', __FILE__ );
define( 'WC_VIP_CLUB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WC_VIP_CLUB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Check if WooCommerce is active
 */
function wc_vip_club_is_woocommerce_active(): bool {
	if ( ! function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	return is_plugin_active( 'woocommerce/woocommerce.php' );
}

/**
 * Admin notice shown when WooCommerce is missing
 */
function wc_vip_club_missing_woocommerce_notice(): void {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			echo esc_html__(
				'VIP Club for WooCommerce requires WooCommerce to be installed and active.',
				'wc-vip-club'
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Activation hook
 */
function wc_vip_club_activate(): void {

	if ( ! wc_vip_club_is_woocommerce_active() ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );

		wp_die(
			esc_html__(
				'VIP Club for WooCommerce cannot be activated because WooCommerce is not active.',
				'wc-vip-club'
			),
			esc_html__( 'Activation error', 'wc-vip-club' ),
			array(
				'back_link' => true,
			)
		);
	}

	// Register endpoints or rewrite rules if needed
	add_rewrite_endpoint( 'vip_club', EP_ROOT | EP_PAGES );
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'wc_vip_club_activate' );

/**
 * Deactivation hook
 */
function wc_vip_club_deactivate(): void {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'wc_vip_club_deactivate' );

/**
 * Bootstrap plugin
 */
function wc_vip_club_init(): void {

	if ( ! wc_vip_club_is_woocommerce_active() ) {
		add_action(
			'admin_notices',
			'wc_vip_club_missing_woocommerce_notice'
		);
		return;
	}

	require_once WC_VIP_CLUB_PLUGIN_DIR . 'includes/class-wc-vip-club.php';

	if ( class_exists( 'WC_VIP_Club' ) ) {
		WC_VIP_Club::get_instance();
	}
}
add_action( 'plugins_loaded', 'wc_vip_club_init' );
