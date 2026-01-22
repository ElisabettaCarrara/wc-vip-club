<?php
/**
 * Main WC VIP Club class.
 *
 * Handles VIP Club role, thresholds, WooCommerce settings, and front-end display.
 *
 * @package WC_VIP_Club
 */

defined( 'ABSPATH' ) || exit;

/**
 * Main VIP Club plugin class.
 */
class WC_VIP_Club {

	/**
	 * Option name for role name.
	 */
	public const OPTION_ROLE_NAME = 'wc_vip_club_role_name';

	/**
	 * Option name for role slug.
	 */
	public const OPTION_ROLE_SLUG = 'wc_vip_club_role_slug';

	/**
	 * Option name for spending threshold.
	 */
	public const OPTION_THRESHOLD = 'wc_vip_club_threshold';

	/**
	 * Singleton instance.
	 *
	 * @var WC_VIP_Club|null
	 */
	private static ?WC_VIP_Club $instance = null;

	/**
	 * Get the singleton instance.
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
	 * Constructor.
	 */
	private function __construct() {
		$this->register_hooks();
	}

	/**
	 * Register all WordPress/WooCommerce hooks.
	 */
	private function register_hooks(): void {
		// Initialization.
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'init', array( $this, 'load_textdomain' ) );

		// Settings.
		/**
		 * Filters the WooCommerce settings tabs array.
		 *
		 * @since 1.0.0
		 */
		add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_settings_tab' ), 50 );
		add_action( 'woocommerce_settings_vip_club', array( $this, 'render_settings_tab' ) );
		add_action( 'woocommerce_update_options_vip_club', array( $this, 'save_settings' ) );
		add_action( 'admin_notices', array( $this, 'settings_preview_notice' ) );

		// My Account.
		/**
		 * Filters the WooCommerce account menu items.
		 *
		 * @since 1.0.0
		 */
		add_filter( 'woocommerce_account_menu_items', array( $this, 'add_account_tab' ), 10 );
		add_action( 'woocommerce_account_vip_club_endpoint', array( $this, 'render_account_tab' ) );

		// Promotion logic.
		/**
		 * Fires when an order status is changed to completed.
		 *
		 * @since 1.0.0
		 */
		add_action( 'woocommerce_order_status_completed', array( $this, 'maybe_promote_customer_to_vip' ), 20 );

		// Frontend styles.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_styles' ) );
	}

	/**
	 * Plugin initialization logic.
	 */
	public function init(): void {
		$this->register_endpoint();
		$this->sync_vip_role();
	}

	/**
	 * Load plugin text domain.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'wc-vip-club',
			false,
			dirname( plugin_basename( __FILE__ ) ) . '/languages'
		);
	}

	/**
	 * Register the VIP Club endpoint.
	 */
	private function register_endpoint(): void {
		add_rewrite_endpoint( 'vip_club', EP_ROOT | EP_PAGES );
	}

	/**
	 * Get the VIP role name.
	 *
	 * @return string
	 */
	public function get_role_name(): string {
		$default = __( 'VIP Club', 'wc-vip-club' );
		return (string) apply_filters( 'vip_club_role_name', get_option( self::OPTION_ROLE_NAME, $default ) );
	}

	/**
	 * Get the VIP role slug.
	 *
	 * @return string
	 */
	public function get_role_slug(): string {
		$override = get_option( self::OPTION_ROLE_SLUG );
		$slug     = $override ? sanitize_key( (string) $override ) : sanitize_key( $this->get_role_name() );
		return (string) apply_filters( 'vip_club_role_slug', $slug );
	}

	/**
	 * Get the VIP spending threshold.
	 *
	 * @return float
	 */
	public function get_threshold(): float {
		return (float) apply_filters( 'vip_club_threshold', get_option( self::OPTION_THRESHOLD, 1000 ) );
	}

	/**
	 * Sync the VIP role with WooCommerce customer role capabilities.
	 */
	private function sync_vip_role(): void {
		$customer_role = get_role( 'customer' );

		if ( ! $customer_role instanceof WP_Role ) {
			return;
		}

		$slug = $this->get_role_slug();
		$name = $this->get_role_name();

		remove_role( $slug );
		add_role( $slug, $name, $customer_role->capabilities );

		/**
		 * Action triggered after VIP Club role is synced.
		 *
		 * @since 1.0.0
		 *
		 * @param string $slug Role slug.
		 * @param string $name Role name.
		 */
		do_action( 'vip_club_role_synced', $slug, $name );
	}

	/**
	 * Add the VIP Club tab to WooCommerce settings tabs array.
	 *
	 * @param array $tabs Existing tabs.
	 * @return array Modified tabs.
	 */
	public function add_settings_tab( array $tabs ): array {
		$new_tabs = array();

		foreach ( $tabs as $key => $label ) {
			if ( 'advanced' === $key ) {
				$new_tabs['vip_club'] = __( 'VIP Club', 'wc-vip-club' );
			}
			$new_tabs[ $key ] = $label;
		}

		if ( ! isset( $new_tabs['vip_club'] ) ) {
			$new_tabs['vip_club'] = __( 'VIP Club', 'wc-vip-club' );
		}

		return $new_tabs;
	}

	/**
	 * Get WooCommerce settings fields for VIP Club.
	 *
	 * @return array
	 */
	private function get_settings_fields(): array {
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
				'name'              => __( 'Spending threshold', 'wc-vip-club' ),
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
	 * Render WooCommerce settings tab for VIP Club.
	 */
	public function render_settings_tab(): void {
		woocommerce_admin_fields( $this->get_settings_fields() );
	}

	/**
	 * Save settings and sync VIP role.
	 */
	public function save_settings(): void {
		woocommerce_update_options( $this->get_settings_fields() );
		$this->sync_vip_role();
	}

	/**
	 * Show admin notice for VIP Club settings preview.
	 */
	public function settings_preview_notice(): void {
		// Nonce is not required for read-only display of settings.
		if ( ! isset( $_GET['tab'] ) || 'vip_club' !== sanitize_text_field( wp_unslash( $_GET['tab'] ) ) ) {
			return;
		}

		$threshold_formatted = function_exists( 'wc_price' ) ? wc_price( $this->get_threshold() ) : $this->get_threshold();

		echo '<div class="notice notice-info"><p>';
		/* translators: 1: Role name, 2: Role slug, 3: Spending threshold amount. */
		printf(
			esc_html__( 'Current Configuration: Role "%1$s" | Slug: %2$s | Threshold: %3$s', 'wc-vip-club' ),
			'<code>' . esc_html( $this->get_role_name() ) . '</code>',
			'<code>' . esc_html( $this->get_role_slug() ) . '</code>',
			wp_kses_post( $threshold_formatted )
		);
		echo '</p></div>';
	}

	/**
	 * Add VIP Club tab to My Account menu.
	 *
	 * @param array $items Existing menu items.
	 * @return array Modified menu items.
	 */
	public function add_account_tab( array $items ): array {
		$user     = wp_get_current_user();
		$progress = 0.0;

		if ( $user instanceof WP_User && $user->exists() ) {
			$threshold = $this->get_threshold();
			$total     = function_exists( 'wc_get_customer_total_spent' ) ? (float) wc_get_customer_total_spent( $user->ID ) : 0.0;
			$progress  = 0 < $threshold ? ( $total / $threshold ) * 100 : 0.0;
		}

		$star_icon = $this->get_star_icon_html( $progress );

		// Dynamic role name for tab label.
		$vip_label = $star_icon . ' ' . $this->get_role_name();

		$reordered = array( 'vip_club' => $vip_label );
		foreach ( $items as $key => $label ) {
			if ( $key !== 'vip_club' ) {
				$reordered[ $key ] = $label;
			}
		}

		return $reordered;
	}

	/**
	 * Generate star icon HTML based on progress.
	 *
	 * @param float $progress Percentage progress.
	 * @return string
	 */
	private function get_star_icon_html( float $progress ): string {
		$type = 'empty';

		if ( 100 <= $progress ) {
			$type = 'full';
		} elseif ( 50 <= $progress ) {
			$type = 'half';
		}

		return sprintf( '<span class="wc-vip-club-star wc-vip-club-star-%s"></span>', esc_attr( $type ) );
	}

	/**
	 * Enqueue front-end CSS for VIP Club.
	 */
	public function enqueue_frontend_styles(): void {
		if ( ! is_account_page() ) {
			return;
		}

		wp_enqueue_style(
			'wc-vip-club-styles',
			WC_VIP_CLUB_PLUGIN_URL . 'assets/css/style.css',
			array(),
			WC_VIP_CLUB_VERSION
		);
	}

	/**
	 * Render VIP Club tab content in My Account.
	 */
	public function render_account_tab(): void {
		$user = wp_get_current_user();
		if ( ! $user instanceof WP_User || ! $user->exists() ) {
			return;
		}

		$role_name = $this->get_role_name();
		$is_vip    = in_array( $this->get_role_slug(), (array) $user->roles, true );
		$total     = function_exists( 'wc_get_customer_total_spent' ) ? (float) wc_get_customer_total_spent( $user->ID ) : 0.0;
		$threshold = $this->get_threshold();
		$progress  = 0 < $threshold ? min( 100, ( $total / $threshold ) * 100 ) : 0.0;
		$star_icon = $this->get_star_icon_html( $progress );

		echo '<div class="wc-vip-wrapper">';
		echo '<h2>' . wp_kses_post( $star_icon ) . ' ' . esc_html( $role_name ) . '</h2>';

		if ( $is_vip ) {
			echo '<div class="wc-vip-success">';
			echo '<strong>' . sprintf(
				/* translators: %s is role name. */
				esc_html__( 'Welcome to the %s! Enjoy your exclusive benefits.', 'wc-vip-club' ),
				esc_html( $role_name )
			) . '</strong>';
			echo '</div>';
		}

		// Progress Section.
		echo '<div class="wc-vip-progress">';
		echo '<div class="wc-vip-progress-bar"><span style="width:' . esc_attr( $progress ) . '%;"></span></div>';
		echo '<div class="wc-vip-meta">';
		echo '<span>' . wp_kses_post( wc_price( $total ) ) . ' ' . esc_html__( 'spent', 'wc-vip-club' ) . '</span>';
		echo '<span>' . esc_html__( 'Goal:', 'wc-vip-club' ) . ' ' . wp_kses_post( wc_price( $threshold ) ) . '</span>';
		echo '</div>';

		// Motivational Message.
		if ( ! $is_vip && $threshold > $total ) {
			$remaining = $threshold - $total;
			echo '<p style="margin-top:1rem;font-style:italic;">';
			printf(
				/* translators: 1: Remaining amount, 2: Role name. */
				esc_html__( 'You are only %1$s away from unlocking your %2$s status! Keep going!', 'wc-vip-club' ),
				'<strong>' . wp_kses_post( wc_price( $remaining ) ) . '</strong>',
				esc_html( $role_name )
			);
			echo '</p>';
		}

		echo '</div>'; // End progress.
		echo '</div>'; // End wrapper.
	}

	/**
	 * Promote customer to VIP if threshold is reached.
	 *
	 * @param int $order_id WooCommerce order ID.
	 */
	public function maybe_promote_customer_to_vip( int $order_id ): void {
		if ( 0 >= $order_id || ! function_exists( 'wc_get_order' ) ) {
			return;
		}

		$order   = wc_get_order( $order_id );
		$user_id = $order ? $order->get_user_id() : 0;

		if ( ! $user_id ) {
			return;
		}

		$user = get_user_by( 'id', $user_id );

		if ( ! in_array( 'customer', (array) $user->roles, true ) ) {
			return;
		}

		$threshold = $this->get_threshold();
		$total     = (float) wc_get_customer_total_spent( $user_id );

		// Yoda condition.
		if ( 0 < $threshold && $total >= $threshold ) {
			$user->set_role( $this->get_role_slug() );
			/**
			 * Action triggered when customer is promoted to VIP.
			 *
			 * @since 1.0.0
			 *
			 * @param int    $user_id   Customer ID.
			 * @param string $role_slug VIP role slug.
			 * @param float  $total     Total spent.
			 * @param float  $threshold Threshold required.
			 */
			do_action( 'vip_club_customer_promoted', $user_id, $this->get_role_slug(), $total, $threshold );
		}
	}
}
