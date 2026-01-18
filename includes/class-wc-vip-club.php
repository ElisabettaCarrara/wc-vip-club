<?php
/**
 * Main VIP Club Class
 *
 * Handles VIP role management, settings, and frontend account display.
 *
 * IMPORTANT:
 * - This class assumes that the correct commerce plugin
 *   (WooCommerce or Classic Commerce) is already active.
 * - Environment and dependency checks MUST be handled
 *   in the main plugin file.
 *
 * @package WC_VIP_Club
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_VIP_Club
 *
 * @since 1.0.0
 */
final class WC_VIP_Club {

	/**
	 * Option key for VIP role display name.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const OPTION_ROLE_NAME = 'vip_club_role_name';

	/**
	 * Option key for VIP role slug.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const OPTION_ROLE_SLUG = 'vip_club_role_slug';

	/**
	 * Option key for spending threshold.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const OPTION_THRESHOLD = 'vip_club_threshold';

	/**
	 * Singleton instance.
	 *
	 * @since 1.0.0
	 * @var WC_VIP_Club|null
	 */
	private static $instance = null;

	/**
	 * Constructor.
	 *
	 * Private to enforce singleton usage.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		$this->register_hooks();
	}

	/**
	 * Get singleton instance.
	 *
	 * @since 1.0.0
	 *
	 * @return WC_VIP_Club
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register all WordPress hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function register_hooks() {

		add_action( 'init', array( $this, 'init' ) );
		add_action( 'init', array( $this, 'load_textdomain' ) );

		// Settings integration (WooCommerce / Classic Commerce compatible).
		add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_settings_tab' ) );
		add_action( 'woocommerce_settings_vip_club', array( $this, 'render_settings_tab' ) );
		add_action( 'woocommerce_update_options_vip_club', array( $this, 'save_settings' ) );

		add_action( 'admin_notices', array( $this, 'settings_preview_notice' ) );

		// My Account integration.
		add_filter( 'woocommerce_account_menu_items', array( $this, 'add_account_tab' ) );
		add_action( 'woocommerce_account_vip_club_endpoint', array( $this, 'render_account_tab' ) );
	}

	/**
	 * Initialize plugin logic.
	 *
	 * Registers rewrite endpoint and synchronizes the VIP role.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function init() {
		$this->register_endpoint();
		$this->sync_vip_role();
	}

	/**
	 * Register VIP Club endpoint for My Account page.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function register_endpoint() {
		add_rewrite_endpoint( 'vip_club', EP_ROOT | EP_PAGES );
	}

	/**
	 * Get VIP role display name.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_role_name() {
		$default = __( 'VIP Customer', 'wc-vip-club' );

		return (string) apply_filters(
			'vip_club_role_name',
			get_option( self::OPTION_ROLE_NAME, $default )
		);
	}

	/**
	 * Get VIP role slug.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_role_slug() {
		$override = get_option( self::OPTION_ROLE_SLUG );

		$slug = $override
			? sanitize_key( $override )
			: sanitize_key( $this->get_role_name() );

		return (string) apply_filters( 'vip_club_role_slug', $slug );
	}

	/**
	 * Get VIP spending threshold.
	 *
	 * @since 1.0.0
	 *
	 * @return float
	 */
	public function get_threshold() {
		return (float) apply_filters(
			'vip_club_threshold',
			get_option( self::OPTION_THRESHOLD, 1000 )
		);
	}

	/**
	 * Synchronize VIP role with customer capabilities.
	 *
	 * The VIP role is recreated to mirror the customer role.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function sync_vip_role() {
		$customer_role = get_role( 'customer' );

		if ( ! $customer_role ) {
			return;
		}

		$slug = $this->get_role_slug();
		$name = $this->get_role_name();

		remove_role( $slug );
		add_role( $slug, $name, $customer_role->capabilities );

		/**
		 * Fires after VIP role synchronization.
		 *
		 * @since 1.0.0
		 *
		 * @param string $slug Role slug.
		 * @param string $name Role display name.
		 */
		do_action( 'vip_club_role_synced', $slug, $name );
	}

	/**
	 * Add VIP Club settings tab.
	 *
	 * @since 1.0.0
	 *
	 * @param array $tabs Existing settings tabs.
	 * @return array
	 */
	public function add_settings_tab( $tabs ) {
		$tabs['vip_club'] = __( 'VIP Club', 'wc-vip-club' );
		return $tabs;
	}

	/**
	 * Get settings fields definition.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	private function get_settings_fields() {
		return array(
			array(
				'name' => __( 'VIP Club Settings', 'wc-vip-club' ),
				'type' => 'title',
				'id'   => 'vip_club_section',
			),
			array(
				'name'    => __( 'VIP role name', 'wc-vip-club' ),
				'type'    => 'text',
				'id'      => self::OPTION_ROLE_NAME,
				'default' => __( 'VIP Customer', 'wc-vip-club' ),
			),
			array(
				'name' => __( 'Role slug override', 'wc-vip-club' ),
				'type' => 'text',
				'id'   => self::OPTION_ROLE_SLUG,
			),
			array(
				'name'    => __( 'Spending threshold', 'wc-vip-club' ),
				'type'    => 'number',
				'id'      => self::OPTION_THRESHOLD,
				'default' => '1000',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'vip_club_section_end',
			),
		);
	}

	/**
	 * Render settings tab.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_settings_tab() {
		woocommerce_admin_fields( $this->get_settings_fields() );
	}

	/**
	 * Save settings and resync role.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function save_settings() {
		woocommerce_update_options( $this->get_settings_fields() );
		$this->sync_vip_role();
	}

	/**
	 * Show settings preview notice.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function settings_preview_notice() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['tab'] ) || 'vip_club' !== $_GET['tab'] ) {
			return;
		}
		// phpcs:enable

		printf(
			'<div class="notice notice-info"><p><strong>%s</strong></p><p>%s</p><p>%s</p></div>',
			esc_html__( 'Settings preview:', 'wc-vip-club' ),
			sprintf(
				/* translators: 1: VIP role display name, 2: VIP role slug */
				esc_html__( 'Role: %1$s (%2$s)', 'wc-vip-club' ),
				'<code>' . esc_html( $this->get_role_name() ) . '</code>',
				'<code>' . esc_html( $this->get_role_slug() ) . '</code>'
			),
			sprintf(
				/* translators: %s: Formatted spending threshold amount */
				esc_html__( 'Threshold: %s', 'wc-vip-club' ),
				'<code>' . wp_kses_post( wc_price( $this->get_threshold() ) ) . '</code>'
			)
		);
	}

	/**
	 * Add VIP Club tab to My Account.
	 *
	 * @since 1.0.0
	 *
	 * @param array $tabs Account menu items.
	 * @return array
	 */
	public function add_account_tab( $tabs ) {
		$tabs['vip_club'] = __( 'VIP Club', 'wc-vip-club' );
		return $tabs;
	}

	/**
	 * Render My Account VIP Club tab.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_account_tab() {
		$user = wp_get_current_user();

		if ( ! $user->exists() ) {
			return;
		}

		$is_vip = in_array( $this->get_role_slug(), (array) $user->roles, true );
		$total  = function_exists( 'wc_get_customer_total_spent' )
			? (float) wc_get_customer_total_spent( $user->ID )
			: 0.0;

		echo '<div class="wc-vip-club">';

		if ( $is_vip ) {
			printf(
				'<p><strong>%s</strong> %s</p>',
				esc_html__( 'VIP Status:', 'wc-vip-club' ),
				esc_html__( 'Active', 'wc-vip-club' )
			);
		} else {
			printf(
				'<p><strong>%s</strong> %s</p>',
				esc_html__( 'VIP Status:', 'wc-vip-club' ),
				esc_html__( 'Inactive', 'wc-vip-club' )
			);
		}

		printf(
			'<p>%s</p>',
			sprintf(
				/* translators: %s: Formatted lifetime spending amount */
				esc_html__( 'Lifetime spending: %s', 'wc-vip-club' ),
				'<strong>' . wp_kses_post( wc_price( $total ) ) . '</strong>'
			)
		);

		echo '</div>';
	}

	/**
	 * Load plugin translations.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'wc-vip-club',
			false,
			dirname( plugin_basename( WC_VIP_CLUB_PLUGIN_FILE ) ) . '/languages'
		);
	}
}
