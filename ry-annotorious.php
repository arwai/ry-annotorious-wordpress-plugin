<?php
/*
    Plugin Name: OpenSeadragon image viewer with Annotorious (annotations)
    Plugin URI: arwai.me
    Description: Extends WordPress to manage and annotate image collections via OpenSeadragon and Annotorious.
    Version: 1.1.1
    Author: Arwai (based on Annotorius-wp by Ben Johnston)
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



/**
 * Activation Hook
 * Creates custom database tables on plugin activation.
 */
function ry_annotorious_activate() {
    global $wpdb;

    $table_name_data = $wpdb->prefix . 'annotorious_data';
    $table_name_history = $wpdb->prefix . 'annotorious_history'; // New table name

    $charset_collate = $wpdb->get_charset_collate();

    // SQL for annotorious_data table (Existing table)
    $sql_data = "CREATE TABLE $table_name_data (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        annotation_id_from_annotorious VARCHAR(255) NOT NULL,
        attachment_id BIGINT(20) UNSIGNED NOT NULL,
        annotation_data LONGTEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY annotorious_id (annotation_id_from_annotorious),
        KEY attachment_id (attachment_id)
    ) $charset_collate;";

    // SQL for annotorious_history table (NEW table for edit history)
    $sql_history = "CREATE TABLE $table_name_history (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        annotation_id_from_annotorious VARCHAR(255) NOT NULL, -- The UUID of the annotation being modified
        attachment_id BIGINT(20) UNSIGNED NOT NULL, -- The image attachment ID the annotation belongs to
        action_type VARCHAR(50) NOT NULL, -- 'created', 'updated', 'deleted'
        annotation_data_snapshot LONGTEXT NOT NULL, -- Full JSON snapshot of the annotation at the time of the action
        user_id BIGINT(20) UNSIGNED, -- ID of the user who performed the action (0 for guests/anonymous)
        action_timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY annotorious_id_idx (annotation_id_from_annotorious),
        KEY attachment_id_idx (attachment_id),
        KEY user_id_idx (user_id)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql_data );
    dbDelta( $sql_history ); // Crucial: Run dbDelta for the new history table
}
register_activation_hook( __FILE__, 'ry_annotorious_activate' );


/**  Optional: Uninstall hook to clean up the table when the plugin is deleted
*function ry_annotorious_uninstall() {
*    global $wpdb;
*    $table_name_data = $wpdb->prefix . 'annotorious_data';
*    $table_name_history = $wpdb->prefix . 'annotorious_history';
*    // This will drop the table. Use with caution!
*    // Consider adding an option in plugin settings for users to choose if they want to delete data.
*    // $wpdb->query( "DROP TABLE IF EXISTS $table_name" );
*}
*register_uninstall_hook( __FILE__, 'ry_annotorious_uninstall' );
**/