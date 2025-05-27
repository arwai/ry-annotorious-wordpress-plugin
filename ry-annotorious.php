<?php
/*
    Plugin Name: ry Annotorious
    Plugin URI:
    Description: Extends WordPress to manage and annotate image collections via OpenSeadragon and Annotorious.
    Version: 1.1.1
    Author: Arwai (original by Ben Johnston)
*/

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// Define plugin constants for URL and path
if ( ! defined( 'RY_ANNOTORIOUS_URL' ) ) {
    define( 'RY_ANNOTORIOUS_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'RY_ANNOTORIOUS_PATH' ) ) {
    define( 'RY_ANNOTORIOUS_PATH', plugin_dir_path( __FILE__ ) );
}

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing hooks.
 */
require RY_ANNOTORIOUS_PATH . 'includes/class-annotorious.php'; // Use new constant for path

/**
 * Begins execution of the plugin.
 *
 * This function will simply be called to begin the execution of the plugin.
 */
function run_ry_annotorious_plugin() {
    new Annotorious();
}
run_ry_annotorious_plugin();
