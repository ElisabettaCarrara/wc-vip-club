<?php
/**
 * VIP Club main class.
 *
 * Handles settings, role synchronization, My Account tab, and automatic
 * promotion of customers to VIP role when they reach the spending threshold.
 *
 * @package WC_VIP_Club
 */

defined( 'ABSPATH' ) || exit;

/**
 * WC_VIP_Club class.
 */
class WC_VIP_Club {

	/**
	 * Option name for VIP role display name.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public const OPTION_ROLE_NAME = 'wc_vip_club_role_name';

	/**
	 * Option name for VIP role slug override.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public const OPTION_ROLE_SLUG = 'wc_vip_club_role_slug';

	/**
	 * Option name for VIP spending threshold.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public const OPTION_THRESHOLD = 'wc_vip_club_threshold';

	/**
	 * Singleton instance.
	 *
	 * @since 1.0.0
	 *
	 * @var WC_VIP_Club|null
	 */
	private static ?WC_VIP_Club $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @since 1.0.0
	 *
	 * @return WC_VIP_Club
	 */
	public static function get_instance(): WC_VIP_Club {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * WC_VIP_Club constructor.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		$this->register_hooks();
	}

	/**
	 * Register all WordPress and WooCommerce hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function register_hooks(): void {
		add_action( 'init', [ $this, 'init' ] );
		add_action( 'init', [ $this, 'load_textdomain' ] );

		// Settings integration (WooCommerce / Classic Commerce compatible).
		add_filter( 'woocommerce_settings_tabs_array', [ $this, 'add_settings_tab' ], 50, 1 );
		add_action( 'woocommerce_settings_vip_club', [ $this, 'render_settings_tab' ] );
		add_action( 'woocommerce_update_options_vip_club', [ $this, 'save_settings' ] );
		add_action( 'admin_notices', [ $this, 'settings_preview_notice' ] );

		// My Account integration.
		add_filter( 'woocommerce_account_menu_items', [ $this, 'add_account_tab' ], 10, 1 );
		add_action( 'woocommerce_account_vip_club_endpoint', [ $this, 'render_account_tab' ] );

		// Automatic VIP promotion on order completion.
		add_action( 'woocommerce_order_status_completed', [ $this, 'maybe_promote_customer_to_vip' ], 20, 1 );
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
	public function init(): void {
		$this->register_endpoint();
		$this->sync_vip_role();
	}

	/**
	 * Load plugin textdomain.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain( 'wc-vip-club', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}

	/**
	 * Register VIP Club endpoint for My Account page.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function register_endpoint(): void {
		add_rewrite_endpoint( 'vip_club', EP_ROOT | EP_PAGES );
	}

	/**
	 * Get VIP role display name.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_role_name(): string {
		$default = __( 'VIP Customer', 'wc-vip-club' );

		/**
		 * Filter VIP role name.
		 *
		 * @since 1.0.0
		 *
		 * @param string $role_name Role display name.
		 */
		return (string) apply_filters(
			'vip_club_role_name',
			get_option( self::OPTION_ROLE_NAME, $default )
		);
	}

	/**
	 * Get VIP role slug.
	 *
	 * Uses override if set, otherwise it is generated from the role name.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_role_slug(): string {
		$override = get_option( self::OPTION_ROLE_SLUG );
		$slug     = $override ? sanitize_key( (string) $override ) : sanitize_key( $this->get_role_name() );

		/**
		 * Filter VIP role slug.
		 *
		 * @since 1.0.0
		 *
		 * @param string $slug Role slug.
		 */
		return (string) apply_filters( 'vip_club_role_slug', $slug );
	}

	/**
	 * Get VIP spending threshold.
	 *
	 * @since 1.0.0
	 *
	 * @return float
	 */
	public function get_threshold(): float {
		/**
		 * Filter VIP spending threshold.
		 *
		 * @since 1.0.0
		 *
		 * @param float $threshold Spending threshold.
		 */
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
	private function sync_vip_role(): void {
		$customer_role = get_role( 'customer' );
		if ( ! $customer_role instanceof WP_Role ) {
			return;
		}

		$slug = $this->get_role_slug();
		$name = $this->get_role_name();

		// Remove previous VIP role if present, then recreate it.
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
	 * Add VIP Club settings tab to WooCommerce settings.
	 *
	 * Places the tab before the "Advanced" tab when present.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string,mixed> $tabs Existing settings tabs.
	 *
	 * @return array<string,mixed>
	 */
	public function add_settings_tab( array $tabs ): array {
		$new_tabs = [];

		foreach ( $tabs as $key => $label ) {
			// Insert VIP tab immediately before the "advanced" tab.
			if ( 'advanced' === $key ) {
				$new_tabs['vip_club'] = __( 'VIP Club', 'wc-vip-club' );
			}

			$new_tabs[ $key ] = $label;
		}

		// If there is no "advanced" tab, ensure VIP is added.
		if ( ! isset( $new_tabs['vip_club'] ) ) {
			$new_tabs['vip_club'] = __( 'VIP Club', 'wc-vip-club' );
		}

		return $new_tabs;
	}

	/**
	 * Get settings fields definition.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function get_settings_fields(): array {
		return [
			[
				'name' => __( 'VIP Club Settings', 'wc-vip-club' ),
				'type' => 'title',
				'id'   => 'vip_club_section',
			],
			[
				'name'    => __( 'VIP role name', 'wc-vip-club' ),
				'type'    => 'text',
				'id'      => self::OPTION_ROLE_NAME,
				'default' => __( 'VIP Customer', 'wc-vip-club' ),
			],
			[
				'name' => __( 'Role slug override', 'wc-vip-club' ),
				'type' => 'text',
				'id'   => self::OPTION_ROLE_SLUG,
			],
			[
				/* translators: WooCommerce settings label for the spending threshold. */
				'name'    => __( 'Spending threshold', 'wc-vip-club' ),
				'type'    => 'number',
				'id'      => self::OPTION_THRESHOLD,
				'default' => '1000',
				'custom_attributes' => [
					'min'  => '0',
					'step' => '0.01',
				],
			],
			[
				'type' => 'sectionend',
				'id'   => 'vip_club_section_end',
			],
		];
	}

	/**
	 * Render settings tab.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_settings_tab(): void {
		woocommerce_admin_fields( $this->get_settings_fields() );
	}

	/**
	 * Save settings and resync role.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function save_settings(): void {
		woocommerce_update_options( $this->get_settings_fields() );
		$this->sync_vip_role();
	}

	/**
	 * Show settings preview notice when on VIP Club tab.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function settings_preview_notice(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['tab'] ) || 'vip_club' !== sanitize_text_field( wp_unslash( $_GET['tab'] ) ) ) {
			return;
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$role_name  = $this->get_role_name();
		$role_slug  = $this->get_role_slug();
		$threshold  = $this->get_threshold();
		$threshold_formatted = function_exists( 'wc_price' ) ? wc_price( $threshold ) : (string) $threshold;

		echo '<div class="notice notice-info"><p>';
		printf(
			/* translators: 1: VIP role name, 2: VIP role slug, 3: Spending threshold amount */
			esc_html__( 'VIP role: %1$s (slug: %2$s). Threshold: %3$s.', 'wc-vip-club' ),
			'<code>' . esc_html( $role_name ) . '</code>',
			'<code>' . esc_html( $role_slug ) . '</code>',
			wp_kses_post( $threshold_formatted )
		);
		echo '</p></div>';
	}

		/**
	 * Add VIP Club tab to My Account menu.
	 *
	 * Ensures the VIP Club tab appears first.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string,string> $items Account menu items.
	 *
	 * @return array<string,string>
	 */
	public function add_account_tab( array $items ): array {
		// Add the tab if it is not present yet.
		if ( ! isset( $items['vip_club'] ) ) {
			$items['vip_club'] = __( 'VIP Club', 'wc-vip-club' );
		}

		$vip_label = $items['vip_club'];
		unset( $items['vip_club'] );

		// Rebuild so vip_club is the first entry.
		$reordered = [
			'vip_club' => $vip_label,
		];

		foreach ( $items as $key => $label ) {
			$reordered[ $key ] = $label;
		}

		return $reordered;
	}

	/**
	 * Render My Account VIP Club tab.
	 *
	 * Shows VIP status, total spent, and threshold, plus welcome message when VIP.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_account_tab(): void {
		$user = wp_get_current_user();
		if ( ! $user instanceof WP_User || ! $user->exists() ) {
			return;
		}

		$is_vip = in_array( $this->get_role_slug(), (array) $user->roles, true );

		$total = 0.0;
		if ( function_exists( 'wc_get_customer_total_spent' ) ) {
			$total = (float) wc_get_customer_total_spent( (int) $user->ID );
		}

		$threshold          = $this->get_threshold();
		$total_formatted    = function_exists( 'wc_price' ) ? wc_price( $total ) : (string) $total;
		$threshold_formatted = function_exists( 'wc_price' ) ? wc_price( $threshold ) : (string) $threshold;

		echo '<h2>' . esc_html__( 'VIP Club', 'wc-vip-club' ) . '</h2>';

		echo '<p>';
		printf(
			/* translators: %s: VIP status (Active/Inactive). */
			esc_html__( 'VIP Status: %s', 'wc-vip-club' ),
			$is_vip ? esc_html__( 'Active', 'wc-vip-club' ) : esc_html__( 'Inactive', 'wc-vip-club' )
		);
		echo '</p>';

		echo '<p>';
		printf(
			/* translators: %s: Formatted lifetime spending amount. */
			esc_html__( 'Lifetime spending: %s', 'wc-vip-club' ),
			wp_kses_post( $total_formatted )
		);
		echo '</p>';

		echo '<p>';
		printf(
			/* translators: %s: Formatted spending threshold amount. */
			esc_html__( 'VIP threshold: %s', 'wc-vip-club' ),
			wp_kses_post( $threshold_formatted )
		);
		echo '</p>';

		if ( ! $is_vip ) {
			$remaining = max( 0.0, $threshold - $total );
			if ( $remaining > 0 ) {
				$remaining_formatted = function_exists( 'wc_price' ) ? wc_price( $remaining ) : (string) $remaining;

				echo '<p>';
				printf(
					/* translators: %s: Remaining amount to reach VIP threshold. */
					esc_html__( 'You need %s more to enter the VIP Club.', 'wc-vip-club' ),
					wp_kses_post( $remaining_formatted )
				);
				echo '</p>';
			}
		} else {
			echo '<p>';
			echo esc_html__( 'Welcome to the VIP Club! Enjoy your exclusive benefits.', 'wc-vip-club' );
			echo '</p>';
		}
	}

	/**
	 * Maybe promote a customer to VIP on order completion.
	 *
	 * This:
	 * - Only runs for logged-in customers.
	 * - Checks lifetime spending against the threshold.
	 * - Switches the user to the VIP role, removing other roles, once they reach/exceed the threshold.
	 * - Does not handle downgrades (downgrades are manual by design).
	 *
	 * @since 1.0.0
	 *
	 * @param int $order_id Order ID.
	 *
	 * @return void
	 */
	public function maybe_promote_customer_to_vip( int $order_id ): void {
		if ( $order_id <= 0 || ! function_exists( 'wc_get_order' ) ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$user_id = $order->get_user_id();
		if ( ! $user_id ) {
			// Guest order, nothing to do.
			return;
		}

		$user = get_user_by( 'id', $user_id );
		if ( ! $user instanceof WP_User ) {
			return;
		}

		// Only promote standard customers.
		if ( ! in_array( 'customer', (array) $user->roles, true ) ) {
			return;
		}

		$threshold = $this->get_threshold();
		if ( $threshold <= 0 ) {
			return;
		}

		$total = 0.0;
		if ( function_exists( 'wc_get_customer_total_spent' ) ) {
			$total = (float) wc_get_customer_total_spent( (int) $user_id );
		}

		if ( $total < $threshold ) {
			return;
		}

		$vip_role_slug = $this->get_role_slug();

		// Assign VIP role and remove other roles (single-role model).
		$user->set_role( $vip_role_slug );

		/**
		 * Fires after a customer is promoted to VIP.
		 *
		 * @since 1.0.0
		 *
		 * @param int    $user_id       User ID.
		 * @param string $vip_role_slug VIP role slug.
		 * @param float  $total         Lifetime spent.
		 * @param float  $threshold     Threshold used for promotion.
		 */
		do_action( 'vip_club_customer_promoted', $user_id, $vip_role_slug, $total, $threshold );
	}
}

// Bootstrap the singleton.
WC_VIP_Club::get_instance();
