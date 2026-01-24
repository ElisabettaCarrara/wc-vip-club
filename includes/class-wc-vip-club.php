<?php
/**
 * Main plugin orchestrator.
 *
 * @package WC_VIP_Club
 */

defined( 'ABSPATH' ) || exit;

final class WC_VIP_Club {

	private static ?WC_VIP_Club $instance = null;

	private ?WC_VIP_Club_Admin $admin = null;
	private ?WC_VIP_Club_Roles $roles = null;
	private ?WC_VIP_Club_Threshold $threshold = null;

	public static function get_instance(): WC_VIP_Club {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		$this->load_textdomain();
		$this->load_dependencies();
		$this->init_components();
		$this->register_hooks();
	}

	private function load_textdomain(): void {
		load_plugin_textdomain(
			'wc-vip-club',
			false,
			dirname( plugin_basename( WC_VIP_CLUB_PLUGIN_FILE ) ) . '/languages'
		);
	}

	private function load_dependencies(): void {
		require_once WC_VIP_CLUB_PLUGIN_DIR . 'includes/class-wc-vip-club-admin.php';
		require_once WC_VIP_CLUB_PLUGIN_DIR . 'includes/class-wc-vip-club-roles.php';
		require_once WC_VIP_CLUB_PLUGIN_DIR . 'includes/class-wc-vip-club-threshold.php';
	}

	private function init_components(): void {
		$this->admin     = new WC_VIP_Club_Admin();
		$this->roles     = new WC_VIP_Club_Roles();
		$this->threshold = new WC_VIP_Club_Threshold();
	}

	private function register_hooks(): void {
		// Intentionally empty.
	}
}
