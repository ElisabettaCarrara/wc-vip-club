<?php
/**
 * Plugin Name:       WC VIP Club
 * Plugin URI:        https://elica-webservices.it
 * Description:       Automatically upgrades customers to VIP roles based on lifetime spending in WooCommerce. VIP role is a clone of customer role, customizable via role editors, email campaigns (Brevo), pricing rules, etc.
 * Version:           1.1.0
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
 * WC VIP Club main plugin file.
 *
 * @package WC_VIP_Club
 */

// Declare HPOS compatibility.
add_action(
	'before_woocommerce_init',
	function() {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

/**
 * Plugin constants.
 *
 * @since 1.0.0
 */
define( 'WC_VIP_CLUB_VERSION', '1.0.0' );
define( 'WC_VIP_CLUB_PLUGIN_FILE', __FILE__ );
define( 'WC_VIP_CLUB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WC_VIP_CLUB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Check whether WooCommerce is active.
 *
 * @since 1.0.0
 *
 * @return bool True if WooCommerce is active, false otherwise.
 */
function wc_vip_club_is_woocommerce_active(): bool {
	if ( ! function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	return is_plugin_active( 'woocommerce/woocommerce.php' );
}

/**
 * Display an admin notice when WooCommerce is missing.
 *
 * @since 1.0.0
 *
 * @return void
 */
function wc_vip_club_missing_woocommerce_notice(): void {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			echo esc_html__(
				'WC VIP Club requires WooCommerce to be installed and active.',
				'wc-vip-club'
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Plugin activation callback.
 *
 * Ensures WooCommerce is active and flushes rewrite rules for the VIP Club endpoint.
 *
 * @since 1.0.0
 *
 * @return void
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
			[
				'back_link' => true,
			]
		);
	}

	// Flush rewrite rules to register the vip_club endpoint.
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'wc_vip_club_activate' );

/**
 * Plugin deactivation callback.
 *
 * Flushes rewrite rules to clean up.
 *
 * @since 1.0.0
 *
 * @return void
 */
function wc_vip_club_deactivate(): void {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'wc_vip_club_deactivate' );

/**
 * Bootstrap the plugin.
 *
 * Loads the main class if WooCommerce is active.
 *
 * @since 1.0.0
 *
 * @return void
 */
function wc_vip_club_init(): void {
	if ( ! wc_vip_club_is_woocommerce_active() ) {
		add_action( 'admin_notices', 'wc_vip_club_missing_woocommerce_notice' );
		return;
	}

	$class_file = WC_VIP_CLUB_PLUGIN_DIR . 'includes/class-wc-vip-club.php';

	if ( file_exists( $class_file ) ) {
		require_once $class_file;
	}

	if ( class_exists( 'WC_VIP_Club' ) ) {
		WC_VIP_Club::get_instance();
	}
}
add_action( 'plugins_loaded', 'wc_vip_club_init' );
