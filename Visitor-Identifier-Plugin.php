<?php
/**
 * Plugin Name: Visitor Identifier
 * Plugin URI: https://github.com/gbleaney/Visitor-Identifier-Plugin
 * Description: A Wordpress plugin to track the blog's visitors and perform a whois on them. Specifically geared to tracking when potential co-op employers visit your site.
 * Version: 0.1
 * Author: Graham Bleaney
 * Author URI: http://bleaney.ca
 * License: GPL2
 */
/*  Copyright 2013  Graham Bleaney  (email : graham@bleaney.ca)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

add_action( 'admin_menu', 'visitor_identifier_plugin_menu' );


function visitor_identifier_plugin_menu() {
	add_menu_page( 'Visitor Identifier', 'Visitor Identifier', 'manage_options', 'Visitor-Identifier-Plugin', 'visitor_identifier_page'. "", 100 );
}

function visitor_identifier_page() {
	if ( !current_user_can( 'manage_options' ) )  {
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
	}
	$userIp = get_user_ip();

	$xml = wp_remote_get( 'http://www.whoisxmlapi.com/whoisserver/WhoisService?domainName='.$userIp );

	$simpleXml = simplexml_load_string($xml["body"]);
	var_dump($simpleXml);
	echo $simpleXml["registrant"]["orgainization"];
}

function get_user_ip() {
    $ip = $_SERVER['REMOTE_ADDR'];
    return $ip;
}

?>