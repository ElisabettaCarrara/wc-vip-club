<?php
/**
 * Plugin Name: WC VIP Club
 * Plugin URI:  https://example.com
 * Description: Automatically assign customers to a VIP role when a lifetime spending threshold is reached.
 * Version:     1.2.0
 * Author:      Your Name
 * Author URI:  https://example.com
 * Text Domain: wc-vip-club
 * Domain Path: /languages
 * Requires PHP: 8.2
 * Requires Plugins: woocommerce
 * WC requires at least: 8.0
 * WC tested up to: 9.3
 *
 * @package WC_VIP_Club
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plugin constants.
 */
define( 'WC_VIP_CLUB_VERSION', '1.2.0' );
define( 'WC_VIP_CLUB_PLUGIN_FILE', __FILE__ );
define( 'WC_VIP_CLUB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WC_VIP_CLUB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Declare HPOS compatibility.
 */
add_action(
	'before_woocommerce_init',
	static function (): void {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				__FILE__,
				true
			);
		}
	}
);

/**
 * Check if WooCommerce is active.
 *
 * @return bool
 */
function wc_vip_club_is_woocommerce_active(): bool {
	if ( ! function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	return is_plugin_active( 'woocommerce/woocommerce.php' );
}

/**
 * Plugin activation callback.
 *
 * @return void
 */
function wc_vip_club_activate(): void {
	if ( ! wc_vip_club_is_woocommerce_active() ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );

		wp_die(
			esc_html__(
				'WC VIP Club requires WooCommerce to be installed and active.',
				'wc-vip-club'
			),
			esc_html__( 'Plugin activation failed', 'wc-vip-club' ),
			array(
				'back_link' => true,
			)
		);
	}

	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'wc_vip_club_activate' );

/**
 * Plugin deactivation callback.
 *
 * @return void
 */
function wc_vip_club_deactivate(): void {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'wc_vip_club_deactivate' );

/**
 * Display admin notice if WooCommerce is not active.
 *
 * @return void
 */
function wc_vip_club_admin_notice_missing_woocommerce(): void {
	if ( wc_vip_club_is_woocommerce_active() ) {
		return;
	}

	echo '<div class="notice notice-error"><p>';
	echo esc_html__(
		'WC VIP Club requires WooCommerce to be installed and active.',
		'wc-vip-club'
	);
	echo '</p></div>';
}

/**
 * Initialize the plugin.
 *
 * @return void
 */
function wc_vip_club_init(): void {
	if ( ! wc_vip_club_is_woocommerce_active() ) {
		add_action(
			'admin_notices',
			'wc_vip_club_admin_notice_missing_woocommerce'
		);
		return;
	}

	$class_file = WC_VIP_CLUB_PLUGIN_DIR . 'includes/class-wc-vip-club.php';

	if ( ! file_exists( $class_file ) ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			trigger_error(
				esc_html__(
					'WC VIP Club main class file is missing.',
					'wc-vip-club'
				),
				E_USER_ERROR
			);
		}
		return;
	}

	require_once $class_file;

	if ( class_exists( 'WC_VIP_Club' ) ) {
		WC_VIP_Club::get_instance();
	}
}
add_action( 'plugins_loaded', 'wc_vip_club_init', 20 );
