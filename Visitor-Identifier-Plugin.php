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


//SETUP

//Global vars
global $visitor_identifier_db_version;
$visitor_identifier_db_version = "0.1";
//Create DB on first use
register_activation_hook( __FILE__, 'setup_visitor_identifier_database' );
//Add tracking code to every page
add_action( 'admin_menu', 'visitor_identifier_plugin_menu' );
//Crate admin page
add_action( 'wp_after_admin_bar_render', 'add_tracking_to_page');


//One time installation actions
function setup_visitor_identifier_database(){
    global $wpdb;
    global $visitor_identifier_db_version;

    $table_name = $wpdb->prefix . "visitoridentifierlogs"; 

    $sql = "CREATE TABLE ".$table_name." (
      ip BINARY(16) NOT NULL,
      time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
      orgname tinytext,
      fullxml text NOT NULL,
      UNIQUE KEY ip (ip)
    );";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );

    add_option( "visitor_identifier_db_version", $visitor_identifier_db_version );
}


//On page tracking code
function add_tracking_to_page() {
    $userIp = get_user_ip();

    $xml = wp_remote_get( 'http://www.whoisxmlapi.com/whoisserver/WhoisService?domainName='.$userIp );

    $simpleXml = simplexml_load_string($xml["body"]);
    var_dump($simpleXml);
    echo "MADE IT!";
    echo $simpleXml["registrant"]["orgainization"];
}


//Page creation functions
function visitor_identifier_plugin_menu() {
	add_menu_page( 'Visitor Identifier', 'Visitor Identifier', 'manage_options', 'Visitor-Identifier-Plugin', 'visitor_identifier_page'. "", 100 );
}

function visitor_identifier_page() {
	if ( !current_user_can( 'manage_options' ) )  {
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
	}
    global $wpdb;

    $table_name = $wpdb->prefix . "visitoridentifierlogs";
	$visitorinfo = $wpdb->get_results( "SELECT * FROM ".$table_name );
	var_dump($visitorinfo);
}


//Utility functions
function get_user_ip() {
    $ip = $_SERVER['REMOTE_ADDR'];
    return $ip;
}

?>