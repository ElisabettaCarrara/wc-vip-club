<?php
/**
 * Main VIP Club Class
 *
 * @package    WC_VIP_Club
 * @subpackage WC_VIP_Club/includes
 * @author     Elisabetta Carrara <elisabetta.marina.clelia@gmail.com>
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Main VIP Club Class
 *
 * Handles VIP role management and automatic assignment based on customer lifetime spending.
 * Compatible with WordPress/WooCommerce and ClassicPress/Classic Commerce.
 */
final class WC_VIP_Club {

	/**
	 * Option key for VIP role display name.
	 *
	 * @var string
	 */
	const OPTION_ROLE_NAME = 'vip_club_role_name';

	/**
	 * Option key for VIP role slug.
	 *
	 * @var string
	 */
	const OPTION_ROLE_SLUG = 'vip_club_role_slug';

	/**
	 * Option key for spending threshold amount.
	 *
	 * @var string
	 */
	const OPTION_THRESHOLD = 'vip_club_threshold';

	/**
	 * Single instance of the plugin.
	 *
	 * @var WC_VIP_Club|null
	 */
	private static $instance = null;

	/**
	 * Whether running on ClassicPress.
	 *
	 * @var bool
	 */
	private $is_classicpress = false;

	/**
	 * Whether Classic Commerce is active.
	 *
	 * @var bool
	 */
	private $is_classic_commerce = false;

	/**
	 * Constructor.
	 *
	 * Sets up plugin initialization hooks.
	 */
	public function __construct() {
		$this->detect_environment();
		$this->init_hooks();
	}

	/**
	 * Detect ClassicPress and Classic Commerce environment.
	 *
	 * @return void
	 */
	private function detect_environment() {
		$this->is_classicpress = function_exists( 'classicpress_version' );

		if ( $this->is_classicpress && did_action( 'plugins_loaded' ) ) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
			$this->is_classic_commerce = is_plugin_active( 'classic-commerce/classic-commerce.php' );
		}
	}

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	private function init_hooks() {
		add_action( 'init', array( $this, 'init' ) );

		// Commerce settings compatibility.
		$settings_hook = $this->is_classic_commerce ? 'classic_commerce_settings_tabs_array' : 'woocommerce_settings_tabs_array';
		add_filter( $settings_hook, array( $this, 'add_settings_tab' ) );

		$settings_action = $this->is_classic_commerce ? 'classic_commerce_settings_vip_club' : 'woocommerce_settings_vip_club';
		add_action( $settings_action, array( $this, 'render_settings_tab' ) );

		$save_action = $this->is_classic_commerce ? 'classic_commerce_update_options_vip_club' : 'woocommerce_update_options_vip_club';
		add_action( $save_action, array( $this, 'save_settings' ) );

		add_action( 'admin_notices', array( $this, 'settings_preview_notice' ) );

		// Account tabs - WooCommerce/Classic Commerce agnostic.
		add_filter( 'woocommerce_account_menu_items', array( $this, 'add_account_tab' ) );
		add_action( 'woocommerce_account_vip_club_endpoint', array( $this, 'render_account_tab' ) );

		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Initialize plugin.
	 *
	 * Synchronizes VIP role capabilities and registers custom endpoint.
	 *
	 * @return void
	 */
	public function init() {
		$this->sync_vip_role();
		$this->register_endpoint();
	}

	/**
	 * Register custom endpoint for account page.
	 *
	 * @return void
	 */
	private function register_endpoint() {
		add_rewrite_endpoint( 'vip_club', EP_ROOT | EP_PAGES );
	}

	/**
	 * Get VIP role name.
	 *
	 * Retrieves the VIP role display name from options, with filter support.
	 *
	 * @return string Role name.
	 */
	public function get_role_name() {
		return apply_filters( 'vip_club_role_name', get_option( self::OPTION_ROLE_NAME, __( 'VIP Customer', 'vip-club' ) ) );
	}

	/**
	 * Get VIP role slug.
	 *
	 * Retrieves the VIP role slug, either from override option or auto-generated from role name.
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
	 * Retrieves the minimum lifetime spending required for VIP status.
	 *
	 * @return float Threshold amount.
	 */
	public function get_threshold() {
		return (float) apply_filters( 'vip_club_threshold', get_option( self::OPTION_THRESHOLD, 1000 ) );
	}

	/**
	 * Sync VIP role with customer capabilities.
	 *
	 * Removes and recreates the VIP role with the same capabilities as the customer role.
	 *
	 * @return void
	 */
	private function sync_vip_role() {
		$customer = get_role( 'customer' );
		if ( ! $customer ) {
			return;
		}

		$slug = $this->get_role_slug();

		remove_role( $slug );
		add_role( $slug, $this->get_role_name(), $customer->capabilities );

		/**
		 * Fires after VIP role is synced.
		 *
		 * @param string $slug        Role slug.
		 * @param string $role_name   Role display name.
		 */
		do_action( 'vip_club_role_synced', $slug, $this->get_role_name() );
	}

	/**
	 * Add settings tab.
	 *
	 * Adds VIP Club tab to WooCommerce/Classic Commerce settings.
	 *
	 * @param array $tabs Existing settings tabs.
	 * @return array Updated tabs array.
	 */
	public function add_settings_tab( $tabs ) {
		$tabs['vip_club'] = __( 'VIP Club', 'vip-club' );
		return $tabs;
	}

	/**
	 * Get settings fields.
	 *
	 * Defines all settings fields for the VIP Club settings page.
	 *
	 * @return array Settings fields definition.
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
				'desc'    => __( 'The display name for the VIP role.', 'vip-club' ),
			),
			array(
				'name' => __( 'Advanced: role slug override', 'vip-club' ),
				'type' => 'text',
				'id'   => self::OPTION_ROLE_SLUG,
				'desc' => __( 'Optional. Leave empty to auto-generate from role name.', 'vip-club' ),
			),
			array(
				'name'              => __( 'Spending threshold', 'vip-club' ),
				'type'              => 'number',
				'id'                => self::OPTION_THRESHOLD,
				'default'           => '1000',
				'desc'              => __( 'Minimum lifetime spending to achieve VIP status.', 'vip-club' ),
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
	 *
	 * Outputs the settings fields for the VIP Club tab.
	 *
	 * @return void
	 */
	public function render_settings_tab() {
		woocommerce_admin_fields( $this->get_settings_fields() );
	}

	/**
	 * Save settings.
	 *
	 * Processes and saves VIP Club settings, then syncs the role.
	 *
	 * @return void
	 */
	public function save_settings() {
		woocommerce_update_options( $this->get_settings_fields() );
		$this->sync_vip_role();
	}

	/**
	 * Settings preview notice.
	 *
	 * Displays a notice on the settings page showing current VIP Club configuration.
	 *
	 * @return void
	 */
	public function settings_preview_notice() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['page'], $_GET['tab'] ) || 'vip_club' !== $_GET['tab'] ) {
			return;
		}
		// phpcs:enable

		printf(
			/* translators: %s: Settings preview label. */
			'<div class="notice notice-info"><p><strong>%s</strong></p>',
			esc_html__( 'Settings preview:', 'vip-club' )
		);

		printf(
			/* translators: 1: Role display name, 2: Role slug identifier. */
			'<p>%s</p>',
			sprintf(
				esc_html__( 'Role: %1$s (%2$s)', 'vip-club' ),
				'<code>' . esc_html( $this->get_role_name() ) . '</code>',
				'<code>' . esc_html( $this->get_role_slug() ) . '</code>'
			)
		);

		printf(
			/* translators: %s: Minimum spending amount to achieve VIP status. */
			'<p>%s</p></div>',
			sprintf(
				esc_html__( 'Threshold: %s', 'vip-club' ),
				'<code>' . wp_kses_post( wc_price( $this->get_threshold() ) ) . '</code>'
			)
		);
	}

	/**
	 * Add account tab.
	 *
	 * Adds VIP Club tab to customer account navigation.
	 *
	 * @param array $tabs Account navigation tabs.
	 * @return array Updated tabs array.
	 */
	public function add_account_tab( $tabs ) {
		$tabs['vip_club'] = array(
			'title'    => __( 'VIP Club', 'vip-club' ),
			'priority' => 50,
		);

		return $tabs;
	}

	/**
	 * Render account tab content.
	 *
	 * Displays VIP status and progress information on the customer account page.
	 *
	 * @return void
	 */
	public function render_account_tab() {
		$current_user = wp_get_current_user();
		if ( ! $current_user->exists() ) {
			return;
		}

		$slug      = $this->get_role_slug();
		$threshold = $this->get_threshold();
		$is_vip    = in_array( $slug, (array) $current_user->roles, true );

		// Compatible total spent retrieval.
		$total = 0.0;
		if ( function_exists( 'wc_get_customer_total_spent' ) ) {
			$total = wc_get_customer_total_spent( $current_user->ID );
		}

		echo '<div class="woocommerce-vip-club">';

		if ( $is_vip ) {
			echo '<div class="vip-status vip-active">';
			printf(
				'<p class="vip-status-label">%s <strong>%s</strong></p>',
				esc_html__( 'VIP Status:', 'vip-club' ),
				esc_html__( 'Active', 'vip-club' )
			);
			printf(
				/* translators: %s: Customer's lifetime spending amount. */
				'<p class="vip-lifetime-spent">%s</p>',
				sprintf(
					// translators: %s: The customer's total lifetime spending amount.
					esc_html__( 'Lifetime spending: %s', 'vip-club' ),
					'<strong>' . wp_kses_post( wc_price( $total ) ) . '</strong>'
				)
			);
			echo '</div>';
		} else {
			$remaining = max( 0, $threshold - $total );

			echo '<div class="vip-status vip-inactive">';
			printf(
				'<p class="vip-status-label">%s <strong>%s</strong></p>',
				esc_html__( 'VIP Status:', 'vip-club' ),
				esc_html__( 'Inactive', 'vip-club' )
			);

			if ( $remaining > 0 ) {
				printf(
					/* translators: 1: Amount remaining to reach VIP status, 2: Total threshold amount required. */
					'<p class="vip-progress">%s</p>',
					sprintf(
						// translators: 1: The amount remaining to reach VIP status, 2: The total threshold amount.
						esc_html__( 'Spend %1$s more to join VIP (threshold: %2$s)', 'vip-club' ),
						'<strong>' . wp_kses_post( wc_price( $remaining ) ) . '</strong>',
						wp_kses_post( wc_price( $threshold ) )
					)
				);

				$percentage = $threshold > 0 ? min( 100, ( $total / $threshold ) * 100 ) : 0;
				printf(
					'<div class="vip-progress-bar"><div class="vip-progress-fill" style="width: %s%%;"></div></div>',
					esc_attr( number_format( $percentage, 2 ) )
				);
				printf(
					/* translators: %s: Progress percentage. */
					'<p class="vip-progress-percent">%s</p>',
					sprintf(
						// translators: %s: The customer's progress percentage toward VIP status.
						esc_html__( 'Progress: %s%%', 'vip-club' ),
						esc_html( number_format( $percentage, 1 ) )
					)
				);
			}
			echo '</div>';
		}

		echo '</div>';
	}

	/**
	 * Load textdomain.
	 *
	 * Loads translation files for internationalization.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'vip-club',
			false,
			dirname( plugin_basename( WC_VIP_CLUB_PLUGIN_FILE ) ) . '/languages'
		);
	}

	/**
	 * Get instance.
	 *
	 * Returns the singleton instance of the plugin.
	 *
	 * @return WC_VIP_Club Singleton instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}
}
