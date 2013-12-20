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
//Register the URL parameter that we want to use
add_filter('query_vars', 'register_visitor_identifier_query_vars' );
//Crate admin page
add_action( 'wp', 'add_tracking_to_page');
wp_register_style( 'bootstrap', plugins_url('/bootstrap.css', __FILE__), false, '1.0.0', 'all');

//One time installation actions
function setup_visitor_identifier_database(){
    global $wpdb;
    global $visitor_identifier_db_version;

    $visitor_info_table_name = $wpdb->prefix . "visitor_identifier_visitor_info"; 
    $visitor_pages_table_name = $wpdb->prefix . "visitor_identifier_visitor_pages"; 

    //Create table for storing of visitor ip, whois, etc and Create table for storing user pages visited, the source of that page, etc
    $sql = 
        "CREATE TABLE $visitor_info_table_name (
            ip tinytext NOT NULL,
            time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            orgname tinytext,
            fullxml text,
            header text
        );
        CREATE TABLE $visitor_pages_table_name (
            ip tinytext NOT NULL,
            time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            url tinytext NOT NULL,
            source tinytext
        );
    ";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );

    add_option( "visitor_identifier_db_version", $visitor_identifier_db_version );
}


//On page tracking code
function add_tracking_to_page() {
    $headers = get_request_headers();
    if(!is_crawler($headers["User-Agent"])) {

        global $wpdb;
        global $wp_query;
        $visitor_info_table_name = $wpdb->prefix . "visitor_identifier_visitor_info"; 
        $visitor_pages_table_name = $wpdb->prefix . "visitor_identifier_visitor_pages"; 
        $userIp = get_user_ip();

        //Get URL parameters and store
        $source = "";
        if (isset($wp_query->query_vars['source'])) {
            $source = $wp_query->query_vars['source'];
        }
        $wpdb->insert( $visitor_pages_table_name, array( 'ip' => $userIp, 'time' => current_time('mysql'), 'url' => get_current_page_url(), 'source' => $source  ) );

        //Check if new user
        $noRowsForUser = $wpdb->get_var( 
            $wpdb->prepare( 
                "SELECT time 
                FROM $visitor_info_table_name 
                WHERE ip = %s", 
                $userIp
            )
        );
        $newVisitor = $noRowsForUser == NULL;

        //Store IP if new year
        if($newVisitor){
            $serializedHeaders = serialize($headers);
            $rows_affected = $wpdb->insert( $visitor_info_table_name, array( 'ip' => $userIp, 'time' => current_time('mysql'), 'header' => $serializedHeaders ) );
        } else {
            //TODO: update time? 
        }
    }
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


//URL parameter setup
function register_visitor_identifier_query_vars( $qvars ) {
    $qvars[] = 'source';
    return $qvars;
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
    $visitor_info_table_name = $wpdb->prefix . "visitor_identifier_visitor_info"; 
    $visitor_pages_table_name = $wpdb->prefix . "visitor_identifier_visitor_pages"; 

    //Add bootstrap to page
    wp_enqueue_style( 'bootstrap' );

    perform_whios();

	$visitorinfo = $wpdb->get_results( "SELECT * FROM $visitor_info_table_name" );
    //TODO: better html with {var} in echo
    echo '<table class="table table-bordered"><tr><td width="10%">IP</td><td width="10%">TIME (First Access)</td><td width="15%">ORGNAME</td><td width="10%">USER AGENT</td><td width="10%">SOURCE(S)</td><td width="30%">PAGES VISITED</td><td width="15"%>FULL XML</td></tr>';
    foreach ($visitorinfo as $row) {
        $simpleXml = simplexml_load_string($row->fullxml);
        $serializedHeaders = $row->header;
        $headers =  unserialize($serializedHeaders);
        
        $pagesVisited = $wpdb->get_results( 
            $wpdb->prepare( 
                "SELECT * 
                FROM $visitor_pages_table_name
                WHERE ip = %s", 
                $row->ip
            )
        );

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
        echo "<ul>";
        foreach ($pagesVisited as $pagesVisitedRow) {
            if($pagesVisitedRow->source!=null) {
                echo "<li>".$pagesVisitedRow->source."</li>";
            }
        }
        echo "</ul>";
        echo "</td>";
        echo "<td>";
        echo "<ul>";
        foreach ($pagesVisited as $pagesVisitedRow) {
            echo "<li>".$pagesVisitedRow->url."</li>";
        }
        echo "</ul>";
        echo "</td>";
        echo "<td>";
        echo "<div style='height: 100px; overflow: auto;'>";
        echo htmlentities($row->fullxml);
        echo "</div>";
        echo "</td>";
        echo "</tr>";
        
    }
    echo "</table>";
}

function perform_whios(){
    global $wpdb;

    $visitor_info_table_name = $wpdb->prefix . "visitor_identifier_visitor_info";
    $visitorinfo = $wpdb->get_results( 
        "SELECT * 
        FROM $visitor_info_table_name
        WHERE 
            fullxml IS NULL 
            OR fullxml = '' 
            OR fullxml LIKE '%<ErrorMessage>%'" );
    foreach ($visitorinfo as $row) {
        $xml = wp_remote_get( 'http://www.whoisxmlapi.com/whoisserver/WhoisService?domainName='.$row->ip );
        $rows_affected = $wpdb->update( $visitor_info_table_name, array( 'fullxml' => $xml["body"] ), array( 'ip' => $row->ip ) );
    }
}

function is_crawler($userAgent){
    $crawlers = array(
        'Googlebot',
        'msnbot',
        'Rambler',
        'AbachoBOT',
        'accoona',
        'AcoiRobot',
        'ASPSeek',
        'CrocCrawler',
        'Dumbot',
        'FAST-WebCrawler',
        'GeonaBot',
        'Gigabot',
        'MSRBOT',
        'IDBot',
        'SiteUptime.com',
        'Baiduspider',
        'bingbot',
        'MJ12bot',
        'Ezoom',
        'niki-bot',
        'TweetmemeBot',
        'http://yandex.com/bots'
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
function get_current_page_url(){
    global $wp;
    $current_url = add_query_arg( $wp->query_string, '', home_url( $wp->request ) );
    return  $current_url;
}

?>