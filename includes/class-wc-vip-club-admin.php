<?php
/**
 * Admin settings handler.
 *
 * @package WC_VIP_Club
 */

defined( 'ABSPATH' ) || exit;

final class WC_VIP_Club_Admin {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->register_hooks();
	}

	private function register_hooks(): void {
	add_filter(
		'woocommerce_settings_tabs_array',
		array( $this, 'add_settings_tab' ),
		50
	);

	add_action(
		'woocommerce_settings_tabs_wc_vip_club',
		array( $this, 'render_settings' )
	);

	add_action(
		'woocommerce_update_options_wc_vip_club',
		array( $this, 'save_settings' )
	);

  add_filter(
	'woocommerce_admin_settings_sanitize_option_wc_vip_club_role_name',
	array( $this, 'sanitize_role_name' ),
	10,
	3
);

add_filter(
	'woocommerce_admin_settings_sanitize_option_wc_vip_club_role_slug',
	array( $this, 'sanitize_role_slug' ),
	10,
	3
);

add_filter(
	'woocommerce_admin_settings_sanitize_option_wc_vip_club_threshold',
	array( $this, 'sanitize_threshold' ),
	10,
	3
);
    
}

/**
 * Add VIP Club settings tab.
 *
 * @param array<string, string> $tabs Existing WooCommerce settings tabs.
 * @return array<string, string>
 */
public function add_settings_tab( array $tabs ): array {
	$tabs['wc_vip_club'] = esc_html__(
		'VIP Club',
		'wc-vip-club'
	);

	return $tabs;
}

/**
 * Render VIP Club settings.
 *
 * @return void
 */
public function render_settings(): void {
	woocommerce_admin_fields( $this->get_settings() );
}

/**
 * Get VIP Club settings fields.
 *
 * @return array<int, array<string, mixed>>
 */
private function get_settings(): array {
	return array(
		array(
			'title' => esc_html__( 'VIP Club Settings', 'wc-vip-club' ),
			'type'  => 'title',
			'id'    => 'wc_vip_club_settings_title',
		),

		array(
			'title'       => esc_html__( 'VIP role name', 'wc-vip-club' ),
			'desc'        => esc_html__( 'Display name for the VIP user role.', 'wc-vip-club' ),
			'id'          => 'wc_vip_club_role_name',
			'type'        => 'text',
			'default'     => esc_html__( 'VIP Customer', 'wc-vip-club' ),
			'desc_tip'    => true,
		),

		array(
			'title'       => esc_html__( 'VIP role slug', 'wc-vip-club' ),
			'desc'        => esc_html__( 'Internal role identifier (lowercase, no spaces).', 'wc-vip-club' ),
			'id'          => 'wc_vip_club_role_slug',
			'type'        => 'text',
			'default'     => 'vip_customer',
			'desc_tip'    => true,
		),

		array(
			'title'       => esc_html__( 'Spending threshold', 'wc-vip-club' ),
			'desc'        => esc_html__( 'Lifetime spending required to become VIP.', 'wc-vip-club' ),
			'id'          => 'wc_vip_club_threshold',
			'type'        => 'number',
			'default'     => 1000,
			'custom_attributes' => array(
				'min'  => '0',
				'step' => '1',
			),
		),

		array(
			'type' => 'sectionend',
			'id'   => 'wc_vip_club_settings_end',
		),
	);
}

/**
 * Save VIP Club settings.
 *
 * @return void
 */
public function save_settings(): void {
	woocommerce_update_options( $this->get_settings() );
}

/**
 * Sanitize VIP role name.
 *
 * @param mixed  $value Raw value.
 * @param array  $option Option data.
 * @param mixed  $raw_value Raw submitted value.
 * @return string
 */
public function sanitize_role_name( mixed $value, array $option, mixed $raw_value ): string {
	$value = trim( (string) $raw_value );

	if ( '' === $value ) {
		$value = esc_html__( 'VIP Customer', 'wc-vip-club' );
	}

	return $value;
}

/**
 * Sanitize VIP role slug.
 *
 * @param mixed $value Sanitized value.
 * @param array $option Option data.
 * @param mixed $raw_value Raw submitted value.
 * @return string
 */
public function sanitize_role_slug( mixed $value, array $option, mixed $raw_value ): string {
	$slug = sanitize_key( (string) $raw_value );

	if ( '' === $slug ) {
		$slug = 'vip_customer';
	}

	return $slug;
}

/**
 * Sanitize VIP spending threshold.
 *
 * @param mixed $value Sanitized value.
 * @param array $option Option data.
 * @param mixed $raw_value Raw submitted value.
 * @return int
 */
public function sanitize_threshold( mixed $value, array $option, mixed $raw_value ): int {
	$threshold = absint( $raw_value );

	if ( 0 > $threshold ) {
		$threshold = 0;
	}

	return $threshold;
}


