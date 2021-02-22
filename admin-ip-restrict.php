<?php
/*
* Plugin Name: Admin IP Restrict
* Description: Restrict WP-Admin access to a list of allowed IP addresses.
* Version:     1.0.1
* Author:      Chris Budd
* License:     GPLv2 or later
* License URI: http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
* Text Domain: admin-ip-restrict
*/

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

class Admin_IP_Restrict {
	const ADMIN_IP_RESTRICT_OPTIONS = 'admin-ip-restrict';
	const ADMIN_IP_RESTRICT_LIST = 'admin-ip-restrict-list';
	const ADMIN_IP_RESTRICT_ACTIVE = 'admin-ip-restrict-active';

	private $plugin_name;
	private $allow_list;
	private $required_ips;
	private $user_ip;
	private $active;

	public function __construct() {
		$this->plugin_name = get_file_data( __FILE__, ['Name' => 'Plugin Name'] )['Name'];

		$this->user_ip = $this->get_user_ip();

		$this->active = get_option( self::ADMIN_IP_RESTRICT_ACTIVE, '' );
		if ( '' === $this->active ) {
			// If option doesn't already exist, create it. 
			$this->active = '0';
			update_option( self::ADMIN_IP_RESTRICT_ACTIVE, $this->active );
		}

		$this->allow_list = get_option( self::ADMIN_IP_RESTRICT_LIST );
		if ( ! $this->allow_list ) {
			// If option doesn't already exist, create it
			$this->allow_list = [];
			update_option( self::ADMIN_IP_RESTRICT_LIST, $this->allow_list );
		}

		add_action( 'login_init', [$this, 'check_access'] );
		add_action( 'admin_init', [$this, 'check_access'] );
		add_action( 'admin_init', [$this, 'adminSettings'] );
		add_action( 'admin_menu', [$this, 'menu'] );

		register_activation_hook( __FILE__, [$this, 'activation'] );
		add_filter( sprintf( 'plugin_action_links_%s', plugin_basename( __FILE__ ) ), [$this, 'action_links'] );
	}

	/**
	 * Get User's IP Address
	 *
	 * @return string|false $ip   User's IP Address or false
	 */
	private function get_user_ip() {
		// Ignoring PHPCS because we are validating the user input using `validate_ip_address`. We also only need this to function on uncached pages (login and admin).
		if ( isset( $_SERVER['REMOTE_ADDR'] ) ) { // phpcs:ignore WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders
			$remote_addr = $this->validate_ip_address( $_SERVER['REMOTE_ADDR'] ); // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___SERVER__REMOTE_ADDR__, WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		} else {
			$remote_addr = false;
		}
		return $remote_addr;
	}

	/**
	 * Check if User's IP is on the allow or required lists
	 * 
	 * @return bool
	 */
	private function is_user_ip_allowed()
	{
		if ( ! $this->user_ip ) {
			return false;
		}

		$allowed_ips = array_merge( $this->allow_list, $this->required_ips );

		foreach ( $allowed_ips as $allowed_ip ) {
			// If IP is range, then check if user IP is in range
			if ( strpos( $allowed_ip, '/' ) !== false ) {
				if ( $this->is_ip_in_range( $this->user_ip, $allowed_ip ) ) {
					return true;
				}
			}

			// If not, then do a direct comparison
			if ( $this->user_ip === $allowed_ip ) {
				return true;
			}
		}

		// User IP not allowed
		return false;
	}

	/**
	 * Get list of Required IPs
	 * 
	 * @return array
	 */
	private function getRequiredIPs()
	{
		$required_ips = apply_filters( 'admin-ip-restrict-required-ips', $this->required_ips );
		if ( $required_ips ) {
			$required_ips = $this->sanitize_ip_addresses( $required_ips );
		} else {
			$required_ips = [];
		}

		return $required_ips;
	}

	/**
	 * Check whether current request should have access
	 */
	public function check_access() { 
		$this->required_ips = $this->getRequiredIPs();
		$this->active = apply_filters( 'admin-ip-restrict-active', $this->active );

		if ( ! $this->active ) {
			return;
		}

		if (
			( function_exists( 'is_proxied_request' ) && is_proxied_request() ) || // Allow VIP access
			( function_exists( 'vip_is_jetpack_request' ) && vip_is_jetpack_request() ) || // Allow Jetpack access
			$this->is_user_ip_allowed() // Check if User IP is allowed
		) {
			return;
		}
		
		wp_die( 'Sorry, you are not allowed to access this page.', 'Not allowed', 403 );
	}

	/**
	 * Add action links to plugin page
	 */
	public function action_links( $links ) {
		return array_merge( ['settings' => sprintf( '<a href="%s%s">%s</a>', admin_url( 'options-general.php?page=' ), self::ADMIN_IP_RESTRICT_OPTIONS, __( 'Settings', 'admin-ip-restrict' ) ) ], $links );
	}

	/**
	 * Set up Options Page
	 */
	public function optionsPage() {
		?>
		<div class="wrap">
			<div id="icon-options-general" class="icon32"><br /></div>
			<h2><?php esc_html_e( 'Admin IP Restrict', 'admin-ip-restrict' ) ?></h2>
			<form method="post" action="options.php">
				<?php
				settings_fields( self::ADMIN_IP_RESTRICT_OPTIONS );
				do_settings_sections( self::ADMIN_IP_RESTRICT_OPTIONS );
				?>
				<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="<?php esc_attr_e( 'Save Changes', 'admin-ip-restrict' ) ?>"></p>
			</form>
		</div>
		<?php
	}

	/**
	 * Add item to admin menu
	 */
	public function menu() {
		add_options_page( $this->plugin_name, $this->plugin_name, 'manage_options', self::ADMIN_IP_RESTRICT_OPTIONS, array( $this, 'optionsPage' ) );
	}

	/**
	 * Register admin settings
	 */
	public function adminSettings() {
		add_settings_section( self::ADMIN_IP_RESTRICT_OPTIONS, null,'', self::ADMIN_IP_RESTRICT_OPTIONS );

		add_settings_field(  
			self::ADMIN_IP_RESTRICT_ACTIVE,  
			'Restrict Access',  
			array( $this, 'active_checkbox' ),  
			self::ADMIN_IP_RESTRICT_OPTIONS,  
			self::ADMIN_IP_RESTRICT_OPTIONS 
		);

		add_settings_field(
			'admin-ip-restrict-required-list',
			__( 'Required IP Addresses', 'admin-ip-restrict' ),
			array( $this, 'required_ips_element' ),
			self::ADMIN_IP_RESTRICT_OPTIONS,
			self::ADMIN_IP_RESTRICT_OPTIONS,
			[ 'label_for' => 'required-ips' ]
		);

		add_settings_field(
			self::ADMIN_IP_RESTRICT_LIST,
			__( 'Allowed IP Addresses', 'admin-ip-restrict' ),
			array( $this, 'allowed_ips_element' ),
			self::ADMIN_IP_RESTRICT_OPTIONS,
			self::ADMIN_IP_RESTRICT_OPTIONS,
			[ 'label_for' => self::ADMIN_IP_RESTRICT_LIST ]
		);

		register_setting( self::ADMIN_IP_RESTRICT_OPTIONS, self::ADMIN_IP_RESTRICT_LIST, array( $this, 'handle_ip_allow_form' ) );
		register_setting( self::ADMIN_IP_RESTRICT_OPTIONS, self::ADMIN_IP_RESTRICT_ACTIVE );
	}

	/**
	 * Create the Active checkbox
	 */
	public function active_checkbox() {
		// If filter in use, then lock checkbox
		$locked = apply_filters( 'admin-ip-restrict-active', null );

		echo sprintf( '<input type="checkbox" id="%1$s" name="%1$s" value="1" %2$s %3$s/><label for="%1$s"> Check box to restrict access</label>',
			esc_attr( self::ADMIN_IP_RESTRICT_ACTIVE ),
			checked( 1, $this->active, false ),
			disabled( isset( $locked ), true, false )
		);
	}

	/**
	 * Create the Required IPs textarea
	 */
	public function required_ips_element() {
		echo sprintf( '<textarea name="required-ips" id="required-ips" disabled cols="50" rows="5">%s</textarea>',
			esc_textarea( implode( "\n", $this->required_ips ) )
		);
	}

	/**
	 * Create the IP Allow list textarea
	 */
	public function allowed_ips_element() {
		echo sprintf( '<textarea name="%1$s" id="%1$s" cols="50" rows="10">%2$s</textarea><p>%3$s</p>',
			esc_attr( self::ADMIN_IP_RESTRICT_LIST ),
			esc_textarea( implode( "\n", $this->sanitize_ip_addresses( $this->allow_list ) ) ),
			esc_html__( 'Enter one IP address or range per line.', 'admin-ip-restrict' ),
		);
	}

	/**
	 * Fire on plugin activation
	 */
	public function activation() {
		$this->allow_list = get_option( self::ADMIN_IP_RESTRICT_LIST );

		if ( ! $this->allow_list ) {
			$this->allow_list = [ $this->user_ip ];
		} else {
			if ( ! in_array( $this->user_ip, $this->allow_list ) ) {
				array_push( $this->allow_list, $this->user_ip);
			}
		}

		update_option( self::ADMIN_IP_RESTRICT_LIST, $this->allow_list );
		update_option( self::ADMIN_IP_RESTRICT_ACTIVE, '0' );
	}

	/**
	 * Handle IP input data
	 *
	 * @param   string  $input  IP addresses from input.
	 * @return  array           Sanitized and verfified IP addresses.
	 */
	public function handle_ip_allow_form( $input ) {
		$input_ip_addresses = explode( "\n", $input );
		return $this->sanitize_ip_addresses( $input_ip_addresses );
	}

	/**
	 * Check if a given ip is in a network
	 * @param  string $ip    IP to check in IPV4 format eg. 127.0.0.1
	 * @param  string $range IP/CIDR netmask eg. 127.0.0.0/24
	 * @return boolean true if the ip is in this range / false if not.
	 */
	private function is_ip_in_range( $ip, $range ) {
		// $range is in IP/CIDR format eg 127.0.0.1/24
		list( $range, $netmask ) = explode( '/', $range, 2 );
		$range_decimal = ip2long( $range );
		$ip_decimal = ip2long( $ip );
		$wildcard_decimal = pow( 2, ( 32 - $netmask ) ) - 1;
		$netmask_decimal = ~ $wildcard_decimal;
		return ( ( $ip_decimal & $netmask_decimal ) == ( $range_decimal & $netmask_decimal ) );
	}

	/**
	 * Sanitize IP Addresses
	 *
	 * @param   array  $ips     IP addresses from input.
	 * @return  array           Sanitized and verfified IP addresses.
	 */
	private function sanitize_ip_addresses( $ips ) {
		$verified_ip_addresses = [];

		foreach ( $ips as $ip_address ) {
			// Sanitize input
			$ip_address = sanitize_text_field( $ip_address );
			// Remove commas if present
			$ip_address = trim( $ip_address, ',' );
			// Check IP is valid
			$ip_address = $this->validate_ip_address( $ip_address );
			if ( $ip_address ) {
				array_push( $verified_ip_addresses, $ip_address );
			}
		}

		return $verified_ip_addresses;
	}

	/**
	 * Validate IP Address
	 *
	 * @param   string  $ip_address  IP Address.
	 * @return  string|false         Validated IP Address or false.
	 */
	private function validate_ip_address( $ip_address ) {
		// If IP is range, validate 
		if ( strpos( $ip_address, '/' ) !== false ) {
			$ip_parts = explode( '/', $ip_address );
			$valid_ip = filter_var( $ip_parts[0], FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );

			if ( $valid_ip && is_numeric( $ip_parts[1] ) ) {
				return $valid_ip . '/' . $ip_parts[1];
			}
		}

		return filter_var( $ip_address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
	}

}

new Admin_IP_Restrict();
