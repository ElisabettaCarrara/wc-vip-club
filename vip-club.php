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
 *
 * @package    WC_VIP_Club
 * @author     Elisabetta Carrara <elisabetta.marina.clelia@gmail.com>
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! defined( 'WC_VIP_CLUB_VERSION' ) ) {
	define( 'WC_VIP_CLUB_VERSION', '1.0.0' );
}

/**
 * Main VIP Club Class
 */
final class WC_VIP_Club {

	/**
	 * Plugin option keys.
	 *
	 * @var string[]
	 */
	const OPTION_ROLE_NAME = 'vip_club_role_name';

	/**
	 * @var string
	 */
	const OPTION_ROLE_SLUG = 'vip_club_role_slug';

	/**
	 * @var string
	 */
	const OPTION_THRESHOLD = 'vip_club_threshold';

	/**
	 * Single instance.
	 *
	 * @var WC_VIP_Club
	 */
	private static $instance = null;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'init' ) );
	}

	/**
	 * Initialize plugin.
	 */
	public function init() {
		$this->sync_vip_role();
		flush_rewrite_rules();
	}

	/**
	 * Get VIP role name.
	 *
	 * @return string Role name.
	 */
	public function get_role_name() {
		return apply_filters( 'vip_club_role_name', get_option( self::OPTION_ROLE_NAME, __( 'VIP Customer', 'vip-club' ) ) );
	}

	/**
	 * Get VIP role slug.
	 *
	 * @return string Role slug.
	 */
	public function get_role_slug() {
		$override = get_option( self::OPTION_ROLE_SLUG );
		$slug     = $override ? sanitize_key( $override ) : sanitize_key( $this->get_role_name() );
		return apply_filters( 'vip_club_role_slug', $slug );
	}

	/**
	 * Get spending threshold.
	 *
	 * @return float Threshold amount.
	 */
	public function get_threshold() {
		return (float) apply_filters( 'vip_club_threshold', get_option( self::OPTION_THRESHOLD, 1000 ) );
	}

	/**
	 * Sync VIP role with customer capabilities.
	 */
	private function sync_vip_role() {
		$customer = get_role( 'customer' );
		if ( ! $customer ) {
			return;
		}

		$slug = $this->get_role_slug();
		remove_role( $slug );
		add_role( $slug, $this->get_role_name(), $customer->capabilities );
		do_action( 'vip_club_role_synced', $slug, $this->get_role_name() );
	}

	/**
	 * Add settings tab.
	 *
	 * @param array $tabs Existing tabs.
	 * @return array Updated tabs.
	 */
	public function add_settings_tab( array $tabs ) {
		$tabs['vip_club'] = __( 'VIP Club', 'vip-club' );
		return $tabs;
	}

	/**
	 * Get settings fields.
	 *
	 * @return array[] Settings fields definition.
	 */
	private function get_settings_fields() {
		return array(
			array(
				'name' => __( 'VIP Club Settings', 'vip-club' ),
				'type' => 'title',
				'id'   => 'vip_club_section',
			),
			array(
				'name'    => __( 'VIP role name', 'vip-club' ),
				'type'    => 'text',
				'id'      => self::OPTION_ROLE_NAME,
				'default' => __( 'VIP Customer', 'vip-club' ),
			),
			array(
				'name'    => __( 'Advanced: role slug override', 'vip-club' ),
				'type'    => 'text',
				'id'      => self::OPTION_ROLE_SLUG,
				'desc'    => __( 'Optional. Leave empty to auto-generate from role name.', 'vip-club' ),
			),
			array(
				'name'              => __( 'Spending threshold', 'vip-club' ),
				'type'              => 'number',
				'id'                => self::OPTION_THRESHOLD,
				'default'           => '1000',
				'custom_attributes' => array(
					'min'  => '0',
					'step' => '0.01',
				),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'vip_club_section_end',
			),
		);
	}

	/**
	 * Render settings tab content.
	 */
	public function render_settings_tab() {
		woocommerce_admin_fields( $this->get_settings_fields() );
	}

	/**
	 * Save settings.
	 */
	public function save_settings() {
		woocommerce_update_options( $this->get_settings_fields() );
		$this->sync_vip_role();
	}

	/**
	 * Settings preview notice.
	 */
	public function settings_preview_notice() {
		if ( ! isset( $_GET['page'], $_GET['tab'] ) || $_GET['tab'] !== 'vip_club' ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		printf(
			/* translators: %s: Settings preview notice. */
			'<div class="notice notice-info"><p><strong>%s</strong></p><p>%s</p><p>%s</p></div>',
			esc_html__( 'Settings preview:', 'vip-club' ),
			sprintf(
				/* translators: 1: Role name, 2: Role slug. */
				esc_html__( 'Role: %1$s (%2$s)', 'vip-club' ),
				'<code>' . esc_html( $this->get_role_name() ) . '</code>',
				'<code>' . esc_html( $this->get_role_slug() ) . '</code>'
			),
			sprintf(
				/* translators: %s: Threshold amount. */
				esc_html__( 'Threshold: %s', 'vip-club' ),
				'<code>' . wc_price( $this->get_threshold() ) . '</code>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			)
		);
	}

	/**
	 * Add account tab.
	 *
	 * @param array $tabs Account tabs.
	 * @return array Updated tabs.
	 */
	public function add_account_tab( array $tabs ) {
		$tabs['vip_club'] = array(
			'title'  => __( 'VIP Club', 'vip-club' ),
			'priority' => 50,
		);
		return $tabs;
	}

	/**
	 * Render account tab content.
	 */
	public function render_account_tab() {
		$current_user = wp_get_current_user();
		if ( ! $current_user->exists() ) {
			return;
		}

		$slug      = $this->get_role_slug();
		$threshold = $this->get_threshold();
		$is_vip    = in_array( $slug, (array) $current_user->roles, true );
		$total     = wc_get_customer_total_spent( $current_user->ID );

		if ( $is_vip ) {
			printf(
				/* translators: %s: Total spent. */
				'<p class="vip-status vip-active">%s <strong>%s</strong> &mdash; %s</p>',
				esc_html__( 'VIP Status:', 'vip-club' ),
				esc_html__( 'Active', 'vip-club' ),
				sprintf(
					/* translators: %s: Amount spent. */
					esc_html__( 'Lifetime: %s', 'vip-club' ),
					'<strong>' . wc_price( $total ) . '</strong>'
				)
			);
		} else {
			$remaining = max( 0, $threshold - $total );
			printf(
				'<p class="vip-status vip-inactive">%s <strong>%s</strong></p>',
				esc_html__( 'VIP Status:', 'vip-club' ),
				esc_html__( 'Inactive', 'vip-club' )
			);
			if ( $remaining > 0 ) {
				printf(
					/* translators: 1: Amount remaining, 2: Threshold. */
					'<p>%s <strong>%s</strong> %s %s</p>',
					esc_html__( 'Spend', 'vip-club' ),
					wc_price( $remaining ),
					esc_html__( 'more to join VIP (threshold:', 'vip-club' ),
					wc_price( $threshold ) . ')'
				);
			}
		}
	}

	/**
	 * Load textdomain.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'vip-club', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}

	/**
	 * Get instance.
	 *
	 * @return WC_VIP_Club
	 */
	public static function get_instance() {
		if ( null === static::$instance ) {
			static::$instance = new static();
		}
		return static::$instance;
	}
}

// Initialize.
WC_VIP_Club::get_instance();

/**
 * Hooks.
 */
add_action( 'woocommerce_settings_tabs_array', array( WC_VIP_Club::get_instance(), 'add_settings_tab' ) );
add_action( 'woocommerce_admin_field_vip_club_section', '__return_empty_array' );
add_action( 'woocommerce_settings_vip_club', array( WC_VIP_Club::get_instance(), 'render_settings_tab' ) );
add_action( 'woocommerce_update_options_vip_club', array( WC_VIP_Club::get_instance(), 'save_settings' ) );
add_action( 'woocommerce_admin_field_vip_club_section_end', '__return_empty_array' );
add_action( 'woocommerce_settings_save_vip_club', array( WC_VIP_Club::get_instance(), 'save_settings' ) );
add_action( 'admin_notices', array( WC_VIP_Club::get_instance(), 'settings_preview_notice' ) );
add_filter( 'woocommerce_account_menu_items', array( WC_VIP_Club::get_instance(), 'add_account_tab' ) );
add_action( 'woocommerce_account_vip_club_endpoint', array( WC_VIP_Club::get_instance(), 'render_account_tab' ) );
add_action( 'init', array( WC_VIP_Club::get_instance(), 'load_textdomain' ) );
