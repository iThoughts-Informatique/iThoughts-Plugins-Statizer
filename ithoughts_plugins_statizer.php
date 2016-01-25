<?php
/*
Plugin Name: iThoughts Plugins Statizer
Plugin URI:  
Description: 
Version:     0.0.1
Author:      Gerkin
License:     GPLv2 or later
Text Domain: ithoughts_plugin_statizer
Domain Path: /lang
*/
require_once( dirname(__FILE__) . '/class/ithoughts_plugins_statizer.class.php' );
new ithoughts_plugins_statizer( dirname(__FILE__) );

register_activation_hook( __FILE__, array( 'ithoughts_plugins_statizer', 'activationHook' ) );
register_deactivation_hook( __FILE__, array( 'ithoughts_plugins_statizer', 'deactivationHook' ) );