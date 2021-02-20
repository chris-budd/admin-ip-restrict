=== Admin IP Restrict ===
Contributors: chrisbudd1
Tags: login, protect, ip, admin, restrict
Requires at least: 4.6
Tested up to: 5.6
Stable tag: 1.0.1
Requires PHP: 5.6
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/old-licenses/gpl-2.0.html

Restrict access to WP-Admin to a list of allowed IP Addresses.

== Description ==
A plugin to restrict access to your WordPress admin interface to a list of allowed IP Addresses.

== Installation ==
1. Install plugin
2. Activate plugin
3. Enter allowed IPs on the 'Settings > Admin IP Restrict' screen
4. Check the 'Restrict Access' box to start restricting admin access

== Usage ==
The IP Address of the user who activates the plugin is automatically added as the first IP on the allow list.

You can also require IPs via the `admin-ip-restrict-required-ips` filter. This allows you to hardcode IP Addresses with access to the admin. You can still add and remove other "Allowed IP Addresses" from the admin, but the "Required IP Addresses" can only be edited through the code. For example:

```
add_filter( 'admin-ip-restrict-required-ips', 'add_required_ips' );

function add_required_ips( $ips ) {
	$required_ips = [ '127.0.0.1', '127.0.0.1/24' ];
	return $required_ips;
}
```

You can use the `admin-ip-restrict-active` filter to lock restricted access on or off via code. For example:

```
 add_filter( 'admin-ip-restrict-active', '__return_true' );
```

== Changelog ==
= 1.0.1 =
* Added additional escaping
* Added labels to textareas for accessibility
* Added additional IP Validation for reserved/private ranges
* Fixed incorrect filter/action calls

= 1.0 =
* Initial release
