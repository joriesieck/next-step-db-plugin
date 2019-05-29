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

global $table_postfix;
$table_postfix = 'catalogue_pets';

// Genesis activation hook - if statement in function has it run only on a given page
add_action('genesis_before_content','cp_save_data');
/*
 * Calls the insert function from the class cp_db to insert pet data into the table
 */
function cp_save_data() {
    $page_id = 30;
    if(is_page($page_id)) {
        $names = array('Unicorn', 'Pegasus', 'Pony','Asian dragon','Medieval dragon','Lion','Gryphon');
        $types = array('Horse','Horse','Horse','Dragon','Dragon','Cat','Cat');
        $descriptions = array('Spiral horn centered in forehead','Flying; wings sprouting from back',
            'Very small; half the size of standard horse','Serpentine body','Lizard-like body','Large; maned',
            'Lion body; eagle head; wings');
        $prices = array(10000,15000,500,30000,30000,2000,25000);

        $used_js = array();
        $db = new cp_db;
        for($i=0;$i<sizeof($names);$i++) {
            $j = rand(0,sizeof($names)-1);
            while(in_array($j,$used_js)) {
                $j = rand(0,sizeof($names)-1);
            }
            $array_to_insert = array(
                'name' => $names[$j],
                'type' => $types[$j],
                'description' => $descriptions[$j],
                'price' => $prices[$j],
            );
            $db->insert($array_to_insert);
            array_push($used_js,$j);
        }
    }
}

// Call create_table on plugin activation.
register_activation_hook(__FILE__,'create_table');
/*
 * Creates the table "wp_catalogue_pets" in the database.
 */
function create_table() {
    global $wpdb;
    global $db_version;
    global $table_postfix;
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    $table_name = $wpdb->prefix . $table_postfix;

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

/*
 * The class which defines the generic functions for working with the database
 */
class cp_db {
    static $primary_key = 'id';

    // Private methods
    /*
     * Returns the name of the table
     */
    private static function _table() {
        global $wpdb;
        global $table_postfix;
        return $wpdb->prefix . $table_postfix;
    }

    /*
     * Returns the row with the given key
     */
    private static function _fetch_sql($value) {
        global $wpdb;
        $sql = sprintf("SELECT * FROM %s WHERE %s = %%s",self::_table(),static::$primary_key);
        return $wpdb->prepare($sql,$value);
    }

    // Public methods
    /*
     * Returns the row with the given value
     */
    static function get($value) {
        global $wpdb;
        return $wpdb->get_row( self::_fetch_sql( $value ) );
    }

    /*
     * Inserts a row
     */
    static function insert($data) {
        global $wpdb;
        $wpdb->insert(self::_table(),$data);
    }

    /*
     * Updates the specified row
     */
    static function update($data,$where) {
        global $wpdb;
        $wpdb->update(self::_table(),$data,$where);
    }

    /*
     * Deletes the specified row
     */
    static function delete($value) {
        global $wpdb;
        $sql = sprintf('DELETE FROM %s WHERE %s = %%s',self::_table(),static::$primary_key);
        return $wpdb->query($wpdb->prepare($sql,$value));
    }

    /*
     * Retrieves the specified data
     */
    static function fetch($value) {
        global $wpdb;
        $value = intval($value);
        $sql   = 'SELECT * FROM ' . self::_table() . " WHERE 'id' = '{$value}'";
        return $wpdb->get_results( $sql );
    }

    /*
     * Returns an array of the columns and their formats
     */
    public function get_columns() {
        return array(
            'ID' => '$d',
            'name' => '%s',
            'type' => '%s',
            'description' => '%s',
            'price' => '%d',
        );
    }

    /*
     * Returns an array with all results from the database with the given parameter
     * If $count is set to true, just returns the number of results
     */
    public function get_pets($args=array(),$count=false) {
        global $wpdb;
        $defaults = array(
            'name' => '',
            'type' => '',
            'price' => 0,
            'offset' => 0,
            'order_by' => 'name',
            'order' => 'DESC',
            'number' => 1,
        );
        $args = wp_parse_args($args,$defaults);
        $where = '';
        if(!empty($args['name'])) {
            if(is_array($args['name'])) {
                $names = implode(',',$args['name']);
            } else {
                $names = $args['name'];
            }
            $where .= "WHERE 'name' IN({$names})";
        }
        if(!empty($args['type'])) {
            if(empty($where)) {
                $where .= " WHERE";
            } else {
                $where .= " AND";
            }
            if(is_array($args['type'])) {
                $types = implode(',',$args['type']);
            } else {
                $types = $args['type'];
            }
            $where .= " 'type' IN({$types})";
        }
        if(!empty($args['price'])) {
            if(empty($where)) {
                $where .= " WHERE";
            } else {
                $where .= " AND";
            }
            if(is_array($args['price'])) {
                $prices = implode(',',$args['price']);
            } else {
                $prices = $args['price'];
            }
            $where .= " 'price' IN({$prices})";
        }

        $args['order_by'] = ! array_key_exists($args['order_by'],$this->get_columns()) ? static::$primary_key :
            $args['order_by'];

        $cache_key = (true === $count) ? md5('pc_pets_count' . serialize($args)) :
            md5('pc_pets_' . serialize($args));

        $results = wp_cache_get($cache_key,'pets');
        if(false === $results) {
            if(true === $count) {
                $results = absint($wpdb->get_var("SELECT COUNT(" . static::$primary_key . ") FROM ". self::_table() .
                    "{$where};"));
            } else {
                $results = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM " . self::_table() . " {$where} ORDER BY {$args['order_by']} {$args['order']}
                    LIMIT %d,%d;", absint($args['offset']), absint($args['number'])
                ));
            }
        }

        wp_cache_set($cache_key,$results,'pets',3600);
        return $results;
    }
}