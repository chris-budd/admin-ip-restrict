<?php

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

/**
 * Class Admin_IP_Restrict_Uninstall
 */
class Admin_IP_Restrict_Uninstall
{
    public function __construct()
    {
        delete_option( 'admin-ip-restrict-list' );
        delete_option( 'admin-ip-restrict-active' );
    }
}

new Admin_IP_Restrict_Uninstall();
