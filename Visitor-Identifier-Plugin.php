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
add_action( 'wp', 'add_tracking_to_page');
wp_register_style( 'bootstrap', plugins_url('/bootstrap.css', __FILE__), false, '1.0.0', 'all');

//One time installation actions
function setup_visitor_identifier_database(){
    global $wpdb;
    global $visitor_identifier_db_version;

    $table_name = $wpdb->prefix . "visitoridentifierlogs"; 

    $sql = "CREATE TABLE $table_name (
      ip BINARY(16) NOT NULL,
      time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
      orgname tinytext,
      fullxml text,
      header text,
      UNIQUE KEY ip (ip)
    );";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );

    add_option( "visitor_identifier_db_version", $visitor_identifier_db_version );
}


//On page tracking code
function add_tracking_to_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . "visitoridentifierlogs"; 

    $userIp = get_user_ip();

    $noRowsForUser = $wpdb->get_var( 
        $wpdb->prepare( 
            "SELECT time 
            FROM $table_name 
            WHERE ip = CAST(%s AS BINARY(16))", 
            $userIp
        )
    );

    $newVisitor = $noRowsForUser == NULL;

    if($newVisitor){
        $headers = get_request_headers();
        $serializedHeaders = serialize($headers);
        $rows_affected = $wpdb->insert( $table_name, array( 'ip' => $userIp, 'time' => current_time('mysql'), 'header' => $serializedHeaders ) );
    } else {
        //TODO: update time? 
    }

    //TODO: pages accessed stuff?

}

function get_request_headers(){
    foreach ($_SERVER as $name => $value) { 
       if (substr($name, 0, 5) == 'HTTP_') 
       { 
           $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5))))); 
           $headers[$name] = $value; 
       } else if ($name == "CONTENT_TYPE") { 
           $headers["Content-Type"] = $value; 
       } else if ($name == "CONTENT_LENGTH") { 
           $headers["Content-Length"] = $value; 
       } 
   } 
   
   return $headers; 
}


//Page creation functions
function visitor_identifier_plugin_menu() {
	add_menu_page( 'Visitor Identifier', 'Visitor Identifier', 'manage_options', 'Visitor-Identifier-Plugin', 'visitor_identifier_page', plugins_url( "Visitor-Identifier-Plugin/magnifying-glass.png" ), 100 );
}

function visitor_identifier_page() {
	if ( !current_user_can( 'manage_options' ) )  {
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
	}
    global $wpdb;

    //Add bootstrap to page
    wp_enqueue_style( 'bootstrap' );

    perform_whios();

    $table_name = $wpdb->prefix . "visitoridentifierlogs";
	$visitorinfo = $wpdb->get_results( "SELECT * FROM $table_name" );
    //TODO: better html with {var} in echo
    echo '<table class="table table-bordered"><tr><td>IP</td><td>TIME (First Access)</td><td>ORGNAME</td><td>USER AGENT</td><td>FULL XML</td></tr>';
    foreach ($visitorinfo as $row) {
        $simpleXml = simplexml_load_string($row->fullxml);
        $serializedHeaders = $row->header;
        $headers =  unserialize($serializedHeaders);
        if(!is_crawler($headers["User-Agent"])) {
            echo "<tr>";
            echo "<td>";
            echo $row->ip;
            echo "</td>";
            echo "<td>";
            echo $row->time;
            echo "</td>";
            echo "<td>";
            echo $simpleXml->registrant->organization;
            echo "</td>";
            echo "<td>";
            echo $headers["User-Agent"];
            echo "</td>";
            echo "<td>";
            echo "<div style='height: 100px; overflow: scroll;'>";
            echo htmlentities($row->fullxml);
            echo "</div>";
            echo "</td>";
            echo "</tr>";
        }
    }
    echo "</table>";
}

function perform_whios(){
    global $wpdb;

    $table_name = $wpdb->prefix . "visitoridentifierlogs";
    $visitorinfo = $wpdb->get_results( 
        "SELECT * 
        FROM $table_name
        WHERE 
            fullxml IS NULL 
            OR fullxml = '' 
            OR fullxml LIKE '%<ErrorMessage>%'" );
    foreach ($visitorinfo as $row) {
        $xml = wp_remote_get( 'http://www.whoisxmlapi.com/whoisserver/WhoisService?domainName='.$row->ip );
        $rows_affected = $wpdb->update( $table_name, array( 'fullxml' => $xml["body"] ), array( 'ip' => $row->ip ) );
    }
}

function is_crawler($userAgent){
    $crawlers = array(
        'Google',
        'msnbot',
        'Rambler',
        'Yahoo',
        'AbachoBOT',
        'accoona',
        'AcoiRobot',
        'ASPSeek',
        'CrocCrawler',
        'Dumbot',
        'FAST-WebCrawler',
        'GeonaBot',
        'Gigabot',
        'Lycos',
        'MSRBOT',
        'Scooter',
        'AltaVista',
        'IDBot',
        'eStyle',
        'Scrubby',
        'SiteUptime.com',
        'Baiduspider',
        'bingbot',
        'MJ12bot',
        'Ezoom'
        );
    foreach ($crawlers as $crawler) {
        if(strpos($userAgent, $crawler) !== FALSE)  {
            return true;
        }
    }
    return false;
}


//Utility functions
function get_user_ip() {
    $ip = $_SERVER['REMOTE_ADDR'];
    return $ip;
}

?>