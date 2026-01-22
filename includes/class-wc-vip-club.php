<?php
/**
 * VIP Club main class.
 *
 * Handles settings, role synchronization, My Account tab, and automatic
 * promotion of customers to VIP role when they reach the spending threshold.
 *
 * @package WC_VIP_Club
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Main WC_VIP_Club class.
 *
 * This class implements a singleton pattern to manage VIP customer functionality
 * within WooCommerce/ClassicCommerce, including:
 * - Custom VIP role management
 * - Spending threshold tracking
 * - Automatic customer promotion
 * - My Account integration with visual status indicators
 *
 * @since 1.0.0
 */
class WC_VIP_Club {

	/**
	 * Option name for VIP role display name.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	public const OPTION_ROLE_NAME = 'wc_vip_club_role_name';

	/**
	 * Option name for VIP role slug override.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	public const OPTION_ROLE_SLUG = 'wc_vip_club_role_slug';

	/**
	 * Option name for VIP spending threshold.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	public const OPTION_THRESHOLD = 'wc_vip_club_threshold';

	/**
	 * Singleton instance.
	 *
	 * @since 1.0.0
	 * @var   WC_VIP_Club|null
	 */
	private static ?WC_VIP_Club $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * Implements the singleton pattern to ensure only one instance
	 * of the class exists throughout the application lifecycle.
	 *
	 * @since 1.0.0
	 *
	 * @return WC_VIP_Club The singleton instance.
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
	 * Private constructor to prevent direct instantiation.
	 * Use get_instance() to retrieve the singleton instance.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		$this->register_hooks();
	}

	/**
	 * Register all WordPress and WooCommerce hooks.
	 *
	 * Sets up all action and filter hooks required for the plugin to function,
	 * including settings integration, My Account tabs, and automatic promotions.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function register_hooks(): void {
		// Core initialization hooks.
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'init', array( $this, 'load_textdomain' ) );

		// Settings integration (WooCommerce / ClassicCommerce compatible).
		add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_settings_tab' ), 50, 1 );
		add_action( 'woocommerce_settings_vip_club', array( $this, 'render_settings_tab' ) );
		add_action( 'woocommerce_update_options_vip_club', array( $this, 'save_settings' ) );
		add_action( 'admin_notices', array( $this, 'settings_preview_notice' ) );

		// My Account integration.
		add_filter( 'woocommerce_account_menu_items', array( $this, 'add_account_tab' ), 10, 1 );
		add_action( 'woocommerce_account_vip_club_endpoint', array( $this, 'render_account_tab' ) );

		// Automatic VIP promotion on order completion.
		add_action( 'woocommerce_order_status_completed', array( $this, 'maybe_promote_customer_to_vip' ), 20, 1 );

		// Enqueue frontend styles for star icons.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_styles' ) );
	}

	/**
	 * Initialize plugin logic.
	 *
	 * Registers rewrite endpoint and synchronizes the VIP role.
	 * Runs on the 'init' hook.
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
	 * Load plugin textdomain for internationalization.
	 *
	 * Allows the plugin to be translated into different languages.
	 * Translation files should be placed in the /languages directory.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'wc-vip-club',
			false,
			dirname( plugin_basename( __FILE__ ) ) . '/languages'
		);
	}

	/**
	 * Register VIP Club endpoint for My Account page.
	 *
	 * Creates a custom rewrite endpoint 'vip_club' that will be used
	 * to display VIP status information in the My Account section.
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
	 * Retrieves the customizable display name for the VIP role.
	 * Defaults to 'VIP Customer' if not configured.
	 *
	 * @since 1.0.0
	 *
	 * @return string The VIP role display name.
	 */
	public function get_role_name(): string {
		$default = __( 'VIP Customer', 'wc-vip-club' );

		/**
		 * Filter VIP role display name.
		 *
		 * Allows developers to programmatically modify the VIP role name.
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
	 * Retrieves the role slug used in WordPress user roles.
	 * Uses override if set in settings, otherwise generates from role name.
	 *
	 * @since 1.0.0
	 *
	 * @return string The sanitized VIP role slug.
	 */
	public function get_role_slug(): string {
		$override = get_option( self::OPTION_ROLE_SLUG );
		$slug     = $override ? sanitize_key( (string) $override ) : sanitize_key( $this->get_role_name() );

		/**
		 * Filter VIP role slug.
		 *
		 * Allows developers to programmatically modify the VIP role slug.
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
	 * Retrieves the minimum spending amount required for VIP status.
	 * Defaults to 1000 if not configured.
	 *
	 * @since 1.0.0
	 *
	 * @return float The spending threshold amount.
	 */
	public function get_threshold(): float {
		/**
		 * Filter VIP spending threshold.
		 *
		 * Allows developers to programmatically modify the spending threshold.
		 *
		 * @since 1.0.0
		 *
		 * @param float $threshold Spending threshold amount.
		 */
		return (float) apply_filters(
			'vip_club_threshold',
			get_option( self::OPTION_THRESHOLD, 1000 )
		);
	}

	/**
	 * Synchronize VIP role with customer capabilities.
	 *
	 * The VIP role is recreated to mirror the customer role capabilities.
	 * This ensures VIP users have the same permissions as regular customers
	 * plus any additional benefits configured elsewhere.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function sync_vip_role(): void {
		$customer_role = get_role( 'customer' );

		// Bail if customer role doesn't exist.
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
		 * Allows developers to hook into role synchronization events.
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
	 * Inserts a new 'VIP Club' tab in the WooCommerce settings page.
	 * The tab is placed before the 'Advanced' tab when present.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string,string> $tabs Existing settings tabs.
	 *
	 * @return array<string,string> Modified settings tabs array.
	 */
	public function add_settings_tab( array $tabs ): array {
		$new_tabs = array();

		foreach ( $tabs as $key => $label ) {
			// Insert VIP tab immediately before the 'advanced' tab.
			if ( 'advanced' === $key ) {
				$new_tabs['vip_club'] = __( 'VIP Club', 'wc-vip-club' );
			}

			$new_tabs[ $key ] = $label;
		}

		// If there is no 'advanced' tab, ensure VIP tab is added at the end.
		if ( ! isset( $new_tabs['vip_club'] ) ) {
			$new_tabs['vip_club'] = __( 'VIP Club', 'wc-vip-club' );
		}

		return $new_tabs;
	}

	/**
	 * Get settings fields definition.
	 *
	 * Defines all configuration fields for the VIP Club settings tab.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int,array<string,mixed>> Array of settings field definitions.
	 */
	private function get_settings_fields(): array {
		return array(
			array(
				'name' => __( 'VIP Club Settings', 'wc-vip-club' ),
				'type' => 'title',
				'desc' => __( 'Configure VIP role name, slug, and spending threshold for automatic promotion.', 'wc-vip-club' ),
				'id'   => 'vip_club_section',
			),
			array(
				'name'    => __( 'VIP role name', 'wc-vip-club' ),
				'desc'    => __( 'Display name for the VIP role shown throughout the site.', 'wc-vip-club' ),
				'type'    => 'text',
				'id'      => self::OPTION_ROLE_NAME,
				'default' => __( 'VIP Customer', 'wc-vip-club' ),
			),
			array(
				'name' => __( 'Role slug override', 'wc-vip-club' ),
				'desc' => __( 'Optional: Custom slug for the VIP role. Leave empty to auto-generate from role name.', 'wc-vip-club' ),
				'type' => 'text',
				'id'   => self::OPTION_ROLE_SLUG,
			),
			array(
				'name'              => __( 'Spending threshold', 'wc-vip-club' ),
				'desc'              => __( 'Minimum lifetime spending required for automatic VIP promotion.', 'wc-vip-club' ),
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
	 *
	 * Outputs the settings fields for the VIP Club tab using
	 * WooCommerce's built-in settings rendering function.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_settings_tab(): void {
		woocommerce_admin_fields( $this->get_settings_fields() );
	}

	/**
	 * Save settings and resynchronize VIP role.
	 *
	 * Processes form submission from the VIP Club settings tab
	 * and triggers role synchronization to apply changes.
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
	 * Display settings preview notice when on VIP Club tab.
	 *
	 * Shows an informational notice displaying current VIP configuration
	 * values (role name, slug, and threshold) for administrator reference.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function settings_preview_notice(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only display logic.
		if ( ! isset( $_GET['tab'] ) || 'vip_club' !== sanitize_text_field( wp_unslash( $_GET['tab'] ) ) ) {
			return;
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$role_name           = $this->get_role_name();
		$role_slug           = $this->get_role_slug();
		$threshold           = $this->get_threshold();
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
	 * Add VIP Club tab to My Account menu with star icon.
	 *
	 * Inserts the VIP Club tab as the first item in the My Account navigation
	 * menu and includes a visual star icon indicating VIP status.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string,string> $items Account menu items.
	 *
	 * @return array<string,string> Modified account menu items.
	 */
	public function add_account_tab( array $items ): array {
		$user = wp_get_current_user();

		// Default to empty star if user check fails.
		$star_icon = $this->get_star_icon_html( 0.0 );

		if ( $user instanceof WP_User && $user->exists() ) {
			$is_vip    = in_array( $this->get_role_slug(), (array) $user->roles, true );
			$total     = 0.0;
			$threshold = $this->get_threshold();

			if ( function_exists( 'wc_get_customer_total_spent' ) ) {
				$total = (float) wc_get_customer_total_spent( (int) $user->ID );
			}

			// Calculate progress percentage for star display.
			$progress  = $threshold > 0 ? ( $total / $threshold ) * 100 : 0.0;
			$star_icon = $this->get_star_icon_html( $progress );
		}

		// Add VIP Club tab with star icon if not already present.
		if ( ! isset( $items['vip_club'] ) ) {
			$items['vip_club'] = $star_icon . ' ' . __( 'VIP Club', 'wc-vip-club' );
		}

		// Store the VIP label with icon.
		$vip_label = $items['vip_club'];
		unset( $items['vip_club'] );

		// Rebuild array with VIP Club as first entry.
		$reordered = array(
			'vip_club' => $vip_label,
		);

		foreach ( $items as $key => $label ) {
			$reordered[ $key ] = $label;
		}

		return $reordered;
	}

	/**
	 * Get HTML for star icon based on progress percentage.
	 *
	 * Returns appropriate star icon based on VIP progress:
	 * - Empty star: 0-49%
	 * - Half star: 50-99%
	 * - Full star: 100%+
	 *
	 * @since 1.0.0
	 *
	 * @param float $progress Progress percentage (0-100+).
	 *
	 * @return string HTML for star icon.
	 */
	private function get_star_icon_html( float $progress ): string {
		$star_class = 'wc-vip-club-star';

		if ( $progress >= 100.0 ) {
			// Full star for VIP members.
			$star_type  = 'full';
			$star_title = __( 'VIP Member', 'wc-vip-club' );
		} elseif ( $progress >= 50.0 ) {
			// Half star for 50-99% progress.
			$star_type  = 'half';
			$star_title = __( 'Halfway to VIP', 'wc-vip-club' );
		} else {
			// Empty star for less than 50% progress.
			$star_type  = 'empty';
			$star_title = __( 'Not yet VIP', 'wc-vip-club' );
		}

		return sprintf(
			'<span class="%s %s-%s" title="%s" aria-label="%s"></span>',
			esc_attr( $star_class ),
			esc_attr( $star_class ),
			esc_attr( $star_type ),
			esc_attr( $star_title ),
			esc_attr( $star_title )
		);
	}

	/**
	 * Enqueue frontend styles for star icons.
	 *
	 * Adds inline CSS for displaying star icons in the My Account menu.
	 * Only loads on My Account pages to minimize overhead.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function enqueue_frontend_styles(): void {
		// Only load on My Account pages.
		if ( ! is_account_page() ) {
			return;
		}

		$inline_css = '
			.wc-vip-club-star {
				display: inline-block;
				width: 1em;
				height: 1em;
				vertical-align: middle;
				margin-right: 0.25em;
			}
			.wc-vip-club-star::before {
				content: "★";
				font-size: 1.2em;
				line-height: 1;
			}
			.wc-vip-club-star-empty::before {
				color: #ddd;
			}
			.wc-vip-club-star-half::before {
				background: linear-gradient(90deg, #ffc107 50%, #ddd 50%);
				-webkit-background-clip: text;
				-webkit-text-fill-color: transparent;
				background-clip: text;
				color: transparent;
			}
			.wc-vip-club-star-full::before {
				color: #ffc107;
			}
		';

		wp_register_style( 'wc-vip-club-stars', false, array(), '1.0.0' );
		wp_enqueue_style( 'wc-vip-club-stars' );
		wp_add_inline_style( 'wc-vip-club-stars', $inline_css );
	}

	/**
	 * Render My Account VIP Club tab content.
	 *
	 * Displays comprehensive VIP status information including:
	 * - Current VIP status with visual star indicator
	 * - Lifetime spending amount
	 * - Spending threshold requirement
	 * - Remaining amount needed (if not yet VIP)
	 * - Welcome message for VIP members
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_account_tab(): void {
		$user = wp_get_current_user();

		// Bail if user is not logged in.
		if ( ! $user instanceof WP_User || ! $user->exists() ) {
			return;
		}

		$is_vip = in_array( $this->get_role_slug(), (array) $user->roles, true );

		// Get customer's lifetime spending.
		$total = 0.0;
		if ( function_exists( 'wc_get_customer_total_spent' ) ) {
			$total = (float) wc_get_customer_total_spent( (int) $user->ID );
		}

		// Get threshold and format amounts.
		$threshold           = $this->get_threshold();
		$total_formatted     = function_exists( 'wc_price' ) ? wc_price( $total ) : (string) $total;
		$threshold_formatted = function_exists( 'wc_price' ) ? wc_price( $threshold ) : (string) $threshold;

		// Calculate progress for star display.
		$progress  = $threshold > 0 ? ( $total / $threshold ) * 100 : 0.0;
		$star_icon = $this->get_star_icon_html( $progress );

		// Output tab content.
		echo '<h2>' . wp_kses_post( $star_icon ) . ' ' . esc_html__( 'VIP Club', 'wc-vip-club' ) . '</h2>';

		echo '<p>';
		printf(
			/* translators: %s: VIP status (Active/Inactive). */
			esc_html__( 'VIP Status: %s', 'wc-vip-club' ),
			$is_vip
				? '<strong>' . esc_html__( 'Active', 'wc-vip-club' ) . '</strong>'
				: '<strong>' . esc_html__( 'Inactive', 'wc-vip-club' ) . '</strong>'
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
					'<strong>' . wp_kses_post( $remaining_formatted ) . '</strong>'
				);
				echo '</p>';
			}
		} else {
			echo '<p class="wc-vip-club-welcome">';
			echo '<strong>' . esc_html__( 'Welcome to the VIP Club! Enjoy your exclusive benefits.', 'wc-vip-club' ) . '</strong>';
			echo '</p>';
		}
	}

	/**
	 * Maybe promote a customer to VIP on order completion.
	 *
	 * Automatically upgrades eligible customers to VIP status when their
	 * lifetime spending reaches or exceeds the configured threshold.
	 *
	 * Process:
	 * - Only runs for logged-in customers
	 * - Checks lifetime spending against threshold
	 * - Switches user to VIP role, removing other roles
	 * - Does not handle downgrades (downgrades are manual by design)
	 *
	 * @since 1.0.0
	 *
	 * @param int $order_id Order ID of the completed order.
	 *
	 * @return void
	 */
	public function maybe_promote_customer_to_vip( int $order_id ): void {
		// Validate order ID.
		if ( $order_id <= 0 || ! function_exists( 'wc_get_order' ) ) {
			return;
		}

		$order = wc_get_order( $order_id );

		// Bail if order object is invalid.
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$user_id = $order->get_user_id();

		// Guest order - nothing to do.
		if ( ! $user_id ) {
			return;
		}

		$user = get_user_by( 'id', $user_id );

		// Bail if user doesn't exist.
		if ( ! $user instanceof WP_User ) {
			return;
		}

		// Only promote standard customers (not admins, shop managers, etc.).
		if ( ! in_array( 'customer', (array) $user->roles, true ) ) {
			return;
		}

		$threshold = $this->get_threshold();

		// Bail if threshold is not configured or is zero.
		if ( $threshold <= 0 ) {
			return;
		}

		// Calculate customer's lifetime spending.
		$total = 0.0;
		if ( function_exists( 'wc_get_customer_total_spent' ) ) {
			$total = (float) wc_get_customer_total_spent( (int) $user_id );
		}

		// Customer hasn't reached threshold yet.
		if ( $total < $threshold ) {
			return;
		}

		$vip_role_slug = $this->get_role_slug();

		// Assign VIP role and remove other roles (single-role model).
		$user->set_role( $vip_role_slug );

		/**
		 * Fires after a customer is promoted to VIP.
		 *
		 * Allows developers to trigger additional actions when a customer
		 * achieves VIP status, such as sending welcome emails or granting
		 * special discounts.
		 *
		 * @since 1.0.0
		 *
		 * @param int    $user_id       User ID of promoted customer.
		 * @param string $vip_role_slug VIP role slug assigned.
		 * @param float  $total         Customer's lifetime spending amount.
		 * @param float  $threshold     Threshold amount used for promotion.
		 */
		do_action( 'vip_club_customer_promoted', $user_id, $vip_role_slug, $total, $threshold );
	}
}

// Bootstrap the singleton instance.
WC_VIP_Club::get_instance();
