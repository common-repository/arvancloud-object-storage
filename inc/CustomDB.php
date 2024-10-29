<?php

namespace WP_Arvan\OBS;

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * This class is responsible for custom database table instruction
 * Such as create, update, delete,...
 * when plugin activates, class checks `coin_info` table is exists or not
 * if there is no table it will create table
 */
class CustomDB
{


    /**
     * @var null current instance of database class
     */
    private static $instance = null;
    private const TABLE_NAME = 'obs_operations';

    public function __construct(){
        //Default constructor
    }


    /**
     * Get the database instance
     * @return CustomDB|null
     */
    public static function get_instance()
    {

        if ( !self::$instance ) {
            self::$instance = new CustomDB();
        }
        return self::$instance;

    }

    public function create_memory_table()
    {

        if (false == $this->check_table_exists())
            $this->create_table();

    }

    private function check_table_exists(): bool
    {

        global $wpdb;

        if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->base_prefix}" . self::TABLE_NAME . "'")
            != $wpdb->base_prefix . self::TABLE_NAME)
            return false;

        return true;

    }


    /**
     * Create database table
     * @return void
     */
    private function create_table()
    {

        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE `{$wpdb->base_prefix}" . self::TABLE_NAME . "` (
                  `id` INT NOT NULL AUTO_INCREMENT,
                  `operation` varchar(100) NOT NULL,
                  `source` varchar(1024),
                  `destination` varchar(1024),
                  `key` varchar(1024),
                  `status` varchar(50),
                  PRIMARY KEY  (id)
                ) ; $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');




        dbDelta($sql);



    }


    /**
     * Return true if option exists
     * this will be used for insert new record to table or update it
     * @param $option_name
     * @return bool
     */
    public function is_operation_exist($name)
    {

        global $wpdb;
        $name = $name;

        $sql_query = $wpdb->prepare(
            'SELECT * FROM ' .
            $wpdb->base_prefix . self::TABLE_NAME .
            " WHERE `operation` = '%s'",
            $name);

        return !empty($wpdb->get_row($sql_query));
    }


    /**
     * find option  by option id
     * @param $optionID
     * @return array|object|\stdClass|void|null
     */
    public function get_option_by_id($optionID){
        global $wpdb;

        $sql_query = $wpdb->prepare(
            'SELECT * FROM ' .
            $wpdb->base_prefix . self::TABLE_NAME .
            " WHERE `id` = '%d'",
            $optionID);

        return $wpdb->get_row($sql_query);
    }

    /**
     * Get option by name
     * @param $name
     * @return array|object|\stdClass[]|null
     */

    public function get_option_by_operation($name){
        global $wpdb;
        $name = $name;
        $sql_query = $wpdb->prepare(
            'SELECT * FROM ' .
            $wpdb->base_prefix . self::TABLE_NAME .
            " WHERE `operation` = '%s' ORDER BY `id` DESC",
            $name);

        return $wpdb->get_row($sql_query);
    }


    /**
     * Get option by value
     * @param $fields
     * @return array|object|\stdClass[]|null
     */
    public function get_option_by_fields($fields, $limitation = 0){
        global $wpdb;

        if( !is_array( $fields ) )
            return $fields;

        $by_operation = isset( $fields['operation'] ) ? " AND `operation`='%s'" :'';
        $by_source = isset( $fields['source'] ) ? " AND `source`='%s'" :'';
        $by_destination = isset( $fields['destination'] ) ? " AND `destination`='%s'" :'';
        $by_key = isset( $fields['key'] ) ? " AND `key`='%s'" :'';
        $by_status = isset( $fields['status'] ) ? " AND `status`='%s'" :'';
        $limited = !empty($limitation) ? " LIMIT $limitation" : '';
        $values = array();

        if( isset($fields['operation']) )
            $values[] = $fields['operation'];

        if( isset($fields['source']) )
            $values[] = $fields['source'];

        if( isset($fields['destination']) )
            $values[] = $fields['destination'];

        if( isset($fields['key']) )
            $values[] = $fields['key'];

        if( isset($fields['status']) )
            $values[] = $fields['status'];


        $sql_query = 'SELECT * FROM ' . $wpdb->base_prefix . self::TABLE_NAME . " WHERE 1=1 $by_operation $by_source $by_destination $by_key $by_status ORDER BY `id` DESC $limited";
        $sql_query = $wpdb->prepare($sql_query, $fields);

        return $wpdb->get_results($sql_query, ARRAY_A);
    }


    public static function get_single_item($items){
        if( is_array($items) && (count($items) > 0 ) )
        {
            return $items[0];
        }
        return null;
    }

    /**
     *
     * Insert option
     *
     * @param $name
     * @param $value
     * @param $status
     *
     * @return void
     */
    public function create_operation( $operation )
    {

        global $wpdb;

        $operation_name = $operation['operation'] ?? '';
        $source = $operation['source'] ?? '';
        $destination = $operation['destination'] ?? '';
        $key = maybe_serialize($operation['key'] ?? '');
        $status =  $operation['status'] ?? '';

        $sql_query = $wpdb->prepare(
            'INSERT INTO ' .
            $wpdb->base_prefix . self::TABLE_NAME .
            ' (`operation`, `source`, `destination`,`key`, `status` ) ' .
            " VALUES ('%s', '%s', '%s', '%s', '%s')", [$operation_name, $source, $destination, $key, $status]
        );

        $wpdb->query($sql_query);
    }


    /**
     *
     * Update existing option
     *
     * @param $name
     * @param $value
     * @param $status
     * @return void
     */
    public function update_option($fields, $where){

        global $wpdb;


        $sets = array();


        if( isset( $fields['operation'] )   )
            $sets[] =  " `operation`='%s'" ;

        if( isset( $fields['source'] ) )
            $sets[] = " `source`='%s'";

        if( isset( $fields['destination'] ) )
            $sets[] =  " `destination`='%s'" ;

        if( isset( $fields['key'] ) )
            $sets[] =  " `key`='%s'" ;

        if( isset( $fields['status'] ) )
            $sets[] =  " `status`='%s'" ;


        $sets_string = implode(',', $sets);

        $by_id = isset( $where['id'] ) ? " AND `id`='%d'" :'';
        $by_operation = isset( $where['operation'] ) ? " AND `operation`='%s'" :'';
        $by_source = isset( $where['source'] ) ? " AND `source`='%s'" :'';
        $by_destination = isset( $where['destination'] ) ? " AND `destination`='%s'" :'';
        $by_key = isset( $where['key'] ) ? " AND `key`='%s'" :'';

        $set_values = array();

        /*
        * % Parameters for prepare, SET section
        */
        if( isset($fields['operation']) )
            $set_values[] = $fields['operation'];

        if( isset($fields['source']) )
            $set_values[] = $fields['source'];

        if( isset($fields['destination']) )
            $set_values[] = $fields['destination'];

        if( isset($fields['key']) )
            $set_values[] = $fields['key'];

        if( isset($fields['status']) )
            $set_values[] = $fields['status'];

        /*
         * % Parameters for prepare, WHERE section
         */

        if( isset($where['id']) )
            $set_values[] = $where['id'];

        if( isset($where['operation']) )
            $set_values[] = $where['operation'];

        if( isset($where['source']) )
            $set_values[] = $where['source'];

        if( isset($where['destination']) )
            $set_values[] = $where['destination'];

        if( isset($where['key']) )
            $set_values[] = $where['key'];




        $sql_query = $wpdb->prepare(
            'UPDATE ' .
            $wpdb->base_prefix . self::TABLE_NAME .
            " SET $sets_string WHERE 1=1 $by_id $by_operation $by_source $by_destination $by_key",
            $set_values
        );

        $wpdb->query($sql_query);

    }


    /**
     *
     * Delete existing option by ID
     *
     * @param $id

     * @return void
     */
    public function delete_operation( $fields )
    {
        global $wpdb;

        if( !is_array( $fields ) )
            return $fields;

        $by_operation = isset( $fields['operation'] ) ? " AND `operation`='%s'" :'';
        $by_source = isset( $fields['source'] ) ? " AND `source`='%s'" :'';
        $by_destination = isset( $fields['destination'] ) ? " AND `destination`='%s'" :'';
        $by_key = isset( $fields['key'] ) ? " AND `key`='%s'" :'';
        $by_status = isset( $fields['status'] ) ? " AND `status`='%s'" :'';

        $values = array();

        if( isset($fields['operation']) )
            $values[] = $fields['operation'];

        if( isset($fields['source']) )
            $values[] = $fields['source'];

        if( isset($fields['destination']) )
            $values[] = $fields['destination'];

        if( isset($fields['key']) )
            $values[] = $fields['key'];
        if( isset($fields['status']) )
            $values[] = $fields['status'];

        $sql_query = 'DELETE FROM ' . $wpdb->base_prefix . self::TABLE_NAME . " WHERE 1=1 $by_operation $by_source $by_destination $by_key $by_status";
        $sql_query = $wpdb->prepare($sql_query, $fields);
        $wpdb->query($sql_query);
    }

    /**
     * Drop the table when plugin is deleted
     * @return void
     */
    public function dropTable()
    {
        global $wpdb;

        $sql_query = 'DROP TABLE ' . $wpdb->base_prefix . self::TABLE_NAME . ';';

        $wpdb->query($sql_query);

    }

}
