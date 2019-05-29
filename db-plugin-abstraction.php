<?php
/*
Plugin Name: DB Plugin Abstraction
Version: 1.0
Description: A plugin that implements the database abstraction layer stuff with the same pet data
Author: Jorie Sieck
Author URI: https://my.thinkeracademy.com
*/

global $db_version;
$db_version = '1.0';

// Call create_table on plugin activation.
register_activation_hook(__FILE__,'create_table');
/*
 * Creates the table "wp_catalogue_pets" in the database.
 */
function create_table() {
    global $wpdb;
    global $db_version;
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    $table_name = $wpdb->prefix . 'catalogue_pets';

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) UNSIGNED NOT NULL AUTO_INCREMENT,
		name tinytext NOT NULL,
        type tinytext NOT NULL,
        description longtext NOT NULL,
        price bigint(20) NOT NULL,
        PRIMARY KEY (id)
	) $charset_collate;";

    dbDelta($sql);
    $success = empty( $wpdb->last_error );
    update_option($table_name . '_db_version',$db_version);
    return $success;
}