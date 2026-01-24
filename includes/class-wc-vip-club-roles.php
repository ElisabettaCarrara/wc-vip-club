<?php
/**
 * WC VIP Club Roles handler.
 *
 * Handles VIP role creation and updates.
 *
 * @package WC_VIP_Club
 */

defined( 'ABSPATH' ) || exit;

/**
 * WC VIP Club Roles class.
 *
 * Responsible for creating the VIP role, updating its name or slug, 
 * and migrating users when slugs change.
 */
final class WC_VIP_Club_Roles {

	/**
	 * Constructor.
	 *
	 * Registers hooks.
	 */
	public function __construct() {
		$this->register_hooks();
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	private function register_hooks(): void {
		// Create VIP role on init.
		add_action( 'init', array( $this, 'maybe_create_role' ) );

		// Update role name when option changes.
		add_action(
			'update_option_wc_vip_club_role_name',
			array( $this, 'update_role_name_handler' ),
			10,
			2
		);

		// Update role slug when option changes.
		add_action(
			'update_option_wc_vip_club_role_slug',
			array( $this, 'update_role_slug_handler' ),
			10,
			2
		);
	}

	/**
	 * Create VIP role if it doesn't exist.
	 *
	 * @return void
	 */
	public function maybe_create_role(): void {
		$slug = get_option( WC_VIP_Club::OPTION_ROLE_SLUG, 'vip_customer' );
		$name = get_option( WC_VIP_Club::OPTION_ROLE_NAME, 'VIP Customer' );

		$customer_role = get_role( 'customer' );

		if ( null === get_role( $slug ) && $customer_role ) {
			add_role( $slug, $name, $customer_role->capabilities );
		}
	}

	/**
	 * Handle role name change only.
	 *
	 * @param string $old Old value.
	 * @param string $new_value New value.
	 *
	 * @return void
	 */
	public function update_role_name_handler( $old, $new_value ): void {
		$slug = get_option( WC_VIP_Club::OPTION_ROLE_SLUG, 'vip_customer' );
		$this->update_role_name( $slug, (string) $new_value );
	}

	/**
	 * Handle role slug change only.
	 *
	 * @param string $old_slug Old slug.
	 * @param string $new_slug New slug.
	 *
	 * @return void
	 */
	public function update_role_slug_handler( $old_slug, $new_slug ): void {
		$old_slug_option = get_option( '_wc_vip_club_old_slug', $old_slug );
		$new_slug        = (string) $new_slug;

		if ( $old_slug_option !== $new_slug ) {
			$name = get_option( WC_VIP_Club::OPTION_ROLE_NAME, 'VIP Customer' );
			$this->update_role_slug( (string) $old_slug_option, $new_slug, $name );
			update_option( '_wc_vip_club_old_slug', $new_slug );
		}
	}

	/**
	 * Update role display name.
	 *
	 * @param string $slug Role slug.
	 * @param string $new_name New display name.
	 *
	 * @return void
	 */
	private function update_role_name( string $slug, string $new_name ): void {
		$role = get_role( $slug );

		if ( $role ) {
			global $wp_roles;

			if ( isset( $wp_roles->roles[ $slug ] ) ) {
				$wp_roles->roles[ $slug ]['name'] = $new_name;
				$wp_roles->role_names[ $slug ]   = $new_name;
			}
		}
	}

	/**
	 * Update role slug and migrate users.
	 *
	 * @param string $old_slug Old slug.
	 * @param string $new_slug New slug.
	 * @param string $new_name Display name for new slug.
	 *
	 * @return void
	 */
	private function update_role_slug( string $old_slug, string $new_slug, string $new_name ): void {
		if ( empty( $old_slug ) || empty( $new_slug ) || $old_slug === $new_slug ) {
			return;
		}

		$old_role      = get_role( $old_slug );
		$customer_role = get_role( 'customer' );

		$caps = $old_role
			? $old_role->capabilities
			: ( $customer_role ? $customer_role->capabilities : array( 'read' => true ) );

		// Add new role if it does not exist.
		if ( ! get_role( $new_slug ) ) {
			add_role( $new_slug, $new_name, $caps );
		}

		// Migrate users to new role.
		$users = get_users( array( 'role' => $old_slug ) );
		foreach ( $users as $user ) {
			$user->remove_role( $old_slug );
			$user->add_role( $new_slug );
		}

		// Remove old role.
		if ( $old_role ) {
			remove_role( $old_slug );
		}
	}
}
