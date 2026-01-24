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
	private ?WC_VIP_Club_MyAccount $myaccount = null;

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

	/**
	 * Load plugin textdomain for translations
	 */
	private function load_textdomain(): void {
		load_plugin_textdomain(
			'wc-vip-club',
			false,
			dirname( plugin_basename( WC_VIP_CLUB_PLUGIN_FILE ) ) . '/languages'
		);
	}

	/**
	 * Load all required classes
	 */
	private function load_dependencies(): void {
		require_once WC_VIP_CLUB_PLUGIN_DIR . 'includes/class-wc-vip-club-admin.php';
		require_once WC_VIP_CLUB_PLUGIN_DIR . 'includes/class-wc-vip-club-roles.php';
		require_once WC_VIP_CLUB_PLUGIN_DIR . 'includes/class-wc-vip-club-threshold.php';
		require_once WC_VIP_CLUB_PLUGIN_DIR . 'includes/class-wc-vip-club-myaccount.php';
	}

	/**
 * Instantiate classes
 */
private function init_components(): void {
    if ( is_admin() ) {
        $this->admin = new WC_VIP_Club_Admin();
    }

    $this->roles     = new WC_VIP_Club_Roles();
    $this->threshold = new WC_VIP_Club_Threshold();
    $this->myaccount = new WC_VIP_Club_MyAccount();
}

	/**
	 * Register plugin hooks
	 */
	private function register_hooks(): void {
		// Enqueue frontend CSS for My Account tab only
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
	}

	/**
	 * Enqueue frontend CSS
	 */
	public function enqueue_frontend_assets(): void {
		if ( is_account_page() ) {
			wp_enqueue_style(
				'wc-vip-club-style',
				WC_VIP_CLUB_PLUGIN_URL . 'assets/css/wc-vip-club.css',
				array(),
				WC_VIP_CLUB_VERSION
			);
		}
	}
}
