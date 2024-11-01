<?php
/*
 * Plugin Name: Spiff
 * Plugin URI: http://radios.pencil2d.org
 * Description: Extracts a tracklist from a remote feed (radio stations websites, music services, xml/rss/json/...); and generate a [XSPF](http://en.wikipedia.org/wiki/XSPF/) playlist that stays synced with your source.
 * Author: G.Breant
 * Version: 0.6.0
 * Author URI: http://radios.pencil2d.org
 * License: GPL2+
 * Text Domain: spiff
 * Domain Path: /languages/
 */

/**
 * Plugin Main Class
 */

class Spiff {
    
    public $name = 'Spiff Stations';
    public $author = 'G.Breant';

    /** Version ***************************************************************/

    /**
    * @public string plugin version
    */
    public $version = '0.6.0';

    /**
    * @public string plugin DB version
    */
    public $db_version = '114';

    /** Paths *****************************************************************/

    public $file = null;

    /**
    * @public string Basename of the plugin directory
    */
    public $basename = null;

    /**
    * @public string Absolute path to the plugin directory
    */
    public $plugin_dir = null;


    /**
    * @var The one true Instance
    */
    private static $instance;

    var $options_default = array();
    var $options = null;

    public $station_post_type='station';
    public $tax_music_tag='music_tag';
    public $var_xspf='xspf';
    static $meta_key_db_version = 'spiff-db';
    static $meta_key_options = 'spiff-options';
    
    var $presets_names = array();
    
    public static function instance() {
            if ( ! isset( self::$instance ) ) {
                    self::$instance = new Spiff;
                    self::$instance->setup_globals();
                    self::$instance->includes();
                    self::$instance->setup_actions();
            }
            return self::$instance;
    }

    /**
        * A dummy constructor to prevent bbPress from being loaded more than once.
        *
        * @since bbPress (r2464)
        * @see bbPress::instance()
        * @see bbpress();
        */
    private function __construct() { /* Do nothing here */ }

    function setup_globals() {

            /** Paths *************************************************************/
            $this->file       = __FILE__;
            $this->basename   = plugin_basename( $this->file );
            $this->plugin_dir = plugin_dir_path( $this->file );
            $this->plugin_url = plugin_dir_url ( $this->file );

            //options
            $this->options_default = array(
                'playlist_link'         => 'on',
                'cache_tracks_intval'   => 60*5, //seconds - set to 0 to disable cache
                'tracklist_embed'       => 'on',
                //'enable_hatchet'        => 'on',
                'lastfm_apikey'         => null,
                'soundcloud_client_id'  => null,
                'twitter_apikey'         => null,
            );


    }

    function includes(){

        require_once($this->plugin_dir . '_inc/php/autoload.php');

        require( $this->plugin_dir . 'functions.php');
        require( $this->plugin_dir . 'templates.php');
        
        require( $this->plugin_dir . 'playlists/playlists-core.php');
        require( $this->plugin_dir . 'stations/stations-core.php');

        //admin
        if(is_admin()){
            //require( $this->plugin_dir . 'admin.php');
            //require( $this->plugin_dir . 'admin-options.php');
        }

    }

    function setup_actions(){    
        
        register_activation_hook( $this->file , array( $this, 'set_roles_capabilities' ) );//roles & capabilities

        add_action( 'init' , array($this, 'upgrade'));//install and upgrade

        add_action( 'all_admin_notices', array($this, 'migrate_notice') );

    }
    
    function migrate_notice(){

        $wpsstm_link = '<a href="https://wordpress.org/plugins/wp-soundsystem/" target="_blank" href="">WP SoundSystem</a>';
        ?>
        <div class="notice">
            <p>
                <?php printf(__('The Spiff plugin is no more maintened; but is now a module of the %s plugin.  Please make a backup of your database, and upgrade to the new plugin!  Your existing stations should be supported.','spiff'),$wpsstm_link);?>
            </p>
        </div>
        <?php

    }
    
    function upgrade_from_113(){
        global $wpdb;

        //rename options prefix
        $query_options = $wpdb->prepare( 
            "UPDATE `".$wpdb->prefix . "options` SET option_name = REPLACE(option_name, '%s', '%s')",
            'xspfpl',
            'spiff'
        );
        $wpdb->query($query_options);
        
        //rename post metas prefix
        $query_postmetas = $wpdb->prepare( 
            "UPDATE `".$wpdb->prefix . "postmeta` SET meta_key = REPLACE(meta_key, '%s', '%s')",
            'xspfpl',
            'spiff'
        );
        $wpdb->query($query_postmetas);
        
    }
    
    function upgrade_from_112(){
        global $wpdb;
        //install cache
        Spiff_Station_Datas_Cache::install();
        //rename meta_key_requests
        $query = $wpdb->prepare( 
            "UPDATE `".$wpdb->prefix . "postmeta` SET meta_key = '%s' WHERE meta_key = '%s'",
            XSPFPL_Station_Stats::$meta_key_requests,
            'xspfpl_xspf_requests'
        );
        $wpdb->query($query);
    }

    function upgrade(){
        global $wpdb;

        $old_name_version = get_option('xspfpl-db');
        $current_version = get_option(self::$meta_key_db_version);
        if ( $current_version==$this->db_version ) return;

        if ($old_name_version ){
            //> 112
            if ( $current_version < 113 ) {
                self::upgrade_from_112();
            }

            //> 113
            if ( $current_version < 114 ) {
                self::upgrade_from_113();
            }
        }
        


        //install
        if(!$current_version){
            //handle SQL
            //require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            //dbDelta($sql);
            //add_option($option_name,$this->get_default_settings()); // add settings
            
            Spiff_Station_Datas_Cache::install();
            
        }

        //upgrade DB version
        update_option(self::$meta_key_db_version, $this->db_version );//upgrade DB version
    }

    public function get_options($key = null){
        
        if ( !isset($this->options) ){
            $options = get_option( self::$meta_key_options, $this->options_default );
            $this->options = $options;
        }

        if (!$key) return $this->options;
        if (!isset($this->options[$key])) return false;
        return $this->options[$key];

    }
    
    public function get_default_option($name){
        if (!isset($this->options_default[$name])) return;
        return $this->options_default[$name];
    }

    function debug_log($message) {

        if (WP_DEBUG_LOG !== true) return false;

        $prefix = '[spiff] : ';

        if (is_array($message) || is_object($message)) {
            error_log($prefix.print_r($message, true));
        } else {
            error_log($prefix.$message);
        }
    }
    


}

/**
 * The main function responsible for returning the one Instance
 * to functions everywhere.
 *
 * Use this function like you would a global variable, except without needing
 * to declare the global.
 *
 */

function spiff() {
	return Spiff::instance();
}

spiff();



?>
