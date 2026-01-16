<?php
/**
 * Plugin Name: VIP Club for WooCommerce
 * Description: Automatically upgrades customers to a configurable VIP role based on total spending and displays progress in My Account.
 * Version: 1.3.0
 * Author: Your Name
 * Text Domain: vip-club
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WooCommerce' ) ) {
	return;
}

final class WC_VIP_Club {

	public const OPTION_THRESHOLD = 'vip_club_threshold';
	public const OPTION_ROLE_NAME = 'vip_club_role_name';
	public const OPTION_ROLE_SLUG = 'vip_club_role_slug';
	public const ENDPOINT         = 'vip-club';

	public function __construct() {
		register_activation_hook( __FILE__, [ $this, 'activate' ] );

		add_action( 'plugins_loaded', [ $this, 'load_textdomain' ] );
		add_action( 'init', [ $this, 'register_endpoint' ] );

		add_filter( 'woocommerce_account_menu_items', [ $this, 'add_account_tab' ] );
		add_action(
			'woocommerce_account_' . self::ENDPOINT . '_endpoint',
			[ $this, 'render_account_tab' ]
		);

		add_action( 'woocommerce_order_status_completed', [ $this, 'maybe_upgrade_customer' ] );
		add_action( 'woocommerce_order_status_processing', [ $this, 'maybe_upgrade_customer' ] );

		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );

		// Settings
		add_filter( 'woocommerce_settings_tabs_array', [ $this, 'add_settings_tab' ], 50 );
		add_action( 'woocommerce_settings_tabs_vip_club', [ $this, 'render_settings_tab' ] );
		add_action( 'woocommerce_update_options_vip_club', [ $this, 'save_settings' ] );
		add_action( 'admin_notices', [ $this, 'settings_preview_notice' ] );
	}

	/* ---------------------------------------------------------
	 * Activation
	 * ------------------------------------------------------ */

	public function activate(): void {
		add_option( self::OPTION_THRESHOLD, '1000' );
		add_option( self::OPTION_ROLE_NAME, __( 'VIP Customer', 'vip-club' ) );
		add_option( self::OPTION_ROLE_SLUG, '' );

		$this->sync_vip_role();
		flush_rewrite_rules();
	}

	/* ---------------------------------------------------------
	 * Role helpers
	 * ------------------------------------------------------ */

	public function get_role_name(): string {
		return apply_filters(
			'vip_club_role_name',
			get_option( self::OPTION_ROLE_NAME, __( 'VIP Customer', 'vip-club' ) )
		);
	}

	public function get_role_slug(): string {
		$override = get_option( self::OPTION_ROLE_SLUG );

		$slug = $override
			? sanitize_key( $override )
			: sanitize_key( $this->get_role_name() );

		return apply_filters( 'vip_club_role_slug', $slug );
	}

	public function get_threshold(): float {
		return (float) apply_filters(
			'vip_club_threshold',
			get_option( self::OPTION_THRESHOLD, 1000 )
		);
	}

	private function sync_vip_role(): void {
		$customer = get_role( 'customer' );
		if ( ! $customer ) {
			return;
		}

		$slug = $this->get_role_slug();

		remove_role( $slug );

		add_role(
			$slug,
			$this->get_role_name(),
			$customer->capabilities
		);

		do_action( 'vip_club_role_synced', $slug, $this->get_role_name() );
	}

	/* ---------------------------------------------------------
	 * Settings
	 * ------------------------------------------------------ */

	public function add_settings_tab( array $tabs ): array {
		$tabs['vip_club'] = __( 'VIP Club', 'vip-club' );
		return $tabs;
	}

	private function get_settings_fields(): array {
		return [
			[
				'name' => __( 'VIP Club Settings', 'vip-club' ),
				'type' => 'title',
				'id'   => 'vip_club_section',
			],
			[
				'name'    => __( 'VIP role name', 'vip-club' ),
				'type'    => 'text',
				'id'      => self::OPTION_ROLE_NAME,
				'default' => __( 'VIP Customer', 'vip-club' ),
			],
			[
				'name'    => __( 'Advanced: role slug override', 'vip-club' ),
				'type'    => 'text',
				'id'      => self::OPTION_ROLE_SLUG,
				'desc'    => __( 'Optional. Leave empty to auto-generate from role name.', 'vip-club' ),
			],
			[
				'name'              => __( 'Spending threshold', 'vip-club' ),
				'type'              => 'number',
				'id'                => self::OPTION_THRESHOLD,
				'default'           => '1000',
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

	public function render_settings_tab(): void {
		woocommerce_admin_fields( $this->get_settings_fields() );
	}

	public function save_settings(): void {
		woocommerce_update_options( $this->get_settings_fields() );
		$this->sync_vip_role();
	}

	public function settings_preview_notice(): void {
		if ( ! isset( $_GET['page'], $_GET['tab'] ) || $_GET['tab'] !== 'vip_club' ) {
			return;
		}

		printf(
			'<div class="notice notice-info"><p><strong>%s</strong><br>%s<br>%s</p></div>',
			esc_html__( 'VIP Role Preview', 'vip-club' ),
			esc_html( sprintf( 'Name: %s | Slug: %s', $this->get_role_name(), $this->get_role_slug() ) ),
			esc_html( sprintf( 'Threshold: %s', wc_price( $this->get_threshold() ) ) )
		);
	}

	/* ---------------------------------------------------------
	 * My Account
	 * ------------------------------------------------------ */

	public function register_endpoint(): void {
		add_rewrite_endpoint( self::ENDPOINT, EP_ROOT | EP_PAGES );
	}

	public function add_account_tab( array $items ): array {
		$new = [];
		foreach ( $items as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'dashboard' === $key ) {
				$new[ self::ENDPOINT ] = __( 'VIP Club', 'vip-club' );
			}
		}
		return $new;
	}

	public function render_account_tab(): void {
		$user = wp_get_current_user();
		if ( ! $user->ID ) {
			return;
		}

		$is_vip = apply_filters(
			'vip_club_is_user_vip',
			in_array( $this->get_role_slug(), (array) $user->roles, true ),
			$user
		);

		$total_spent = wc_get_customer_total_spent( $user->ID );
		$threshold   = $this->get_threshold();

		$percentage = min( 100, ( $total_spent / max( 1, $threshold ) ) * 100 );
		$remaining  = max( 0, $threshold - $total_spent );
		?>

		<div class="wc-vip-wrapper">
			<h2><?php echo esc_html( $this->get_role_name() ); ?></h2>

			<?php if ( $is_vip ) : ?>
				<p class="wc-vip-success"><?php esc_html_e( 'You are a VIP member.', 'vip-club' ); ?></p>
			<?php else : ?>
				<div class="wc-vip-progress">
					<div class="wc-vip-progress-bar">
						<span style="width: <?php echo esc_attr( $percentage ); ?>%"></span>
					</div>
					<div class="wc-vip-meta">
						<span><?php echo esc_html( round( $percentage ) ); ?>%</span>
						<span><?php echo wc_price( $remaining ); ?></span>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/* ---------------------------------------------------------
	 * Upgrade logic
	 * ------------------------------------------------------ */

	public function maybe_upgrade_customer( int $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$user_id = (int) $order->get_user_id();
		if ( ! $user_id ) {
			return;
		}

		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			return;
		}

		if ( ! apply_filters( 'vip_club_should_upgrade_user', true, $user, $order ) ) {
			return;
		}

		if ( wc_get_customer_total_spent( $user_id ) >= $this->get_threshold() ) {
			$user->add_role( $this->get_role_slug() );
			do_action( 'vip_club_user_upgraded', $user_id, $user );
		}
	}

	/* ---------------------------------------------------------
	 * Assets
	 * ------------------------------------------------------ */

	public function enqueue_assets(): void {
		if ( is_account_page() ) {
			wp_enqueue_style(
				'wc-vip-club',
				plugins_url( 'assets/vip-club.css', __FILE__ ),
				[ 'woocommerce-general' ],
				'1.3.0'
			);
		}
	}

	public function load_textdomain(): void {
		load_plugin_textdomain( 'vip-club', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}
}

new WC_VIP_Club();
