<?php
/**
 * @package WPDemo
 */
/*
Plugin Name: Wordpress Demo
Plugin URI: www.google.com
Description: Wordpress Demo lets you showcase the admin backend of your plugin or theme! WPDemo creates a new site for each visitor on the fly, based on a "master copy". Each demo instance is separate from the others and changes in one do not affect the others. 
Version: 1.0
Author: Slaven Bačić
Author URI: www.66volts.com
License: GPLv2 or later
Text Domain: wpdemo
*/

/**
 * A brief run through of the architecture for this plugin; 
 * 
 * Manager is taksed with managing the plugin within Wordpress (activate, deactivate, regular cleanups of stale instances). 
 * Generator is a factory that produces demo instances when a user visits the site and deletes them in bulk once they expire. 
 * Instance is tasked with the heavy lifting when creating demos - copying tables and fixing prefixes, copying the media folder, etc. 
 * Configuration settings are placed in Config. Be very careful about changing these. Generally no changes should be needed.
 * Generate.php is there to keep most of the plugin's code out of wp-config.
 * Finally, this file is here to satisfy Wordpress itself.
 */

//Security precaution, in case somebody calls the file directly.
if ( !function_exists( 'add_action' ) ) {
    exit;
}

require_once(plugin_dir_path(__FILE__) . '/Manager.php');

register_activation_hook(__FILE__, function() {
    (new WPDemo\Manager(new \WPDemo\Config()))->setup();
});

register_deactivation_hook(__FILE__, function() {
    (new WPDemo\Manager(new \WPDemo\Config()))->remove();
});

(new WPDemo\Manager(new \WPDemo\Config()))->init();