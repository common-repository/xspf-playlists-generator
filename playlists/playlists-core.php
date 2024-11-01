<?php

/**
 * Plugin Main Class
 */

class Spiff_Playlists_Core {
    
    /**
    * @var The one true Instance
    */
    private static $instance;
    
    static $var_xspf='xspf';
    
    public static function instance() {
            if ( ! isset( self::$instance ) ) {
                    self::$instance = new Spiff_Playlists_Core;
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

    }

    function includes(){
        
        require( spiff()->plugin_dir . 'playlists/playlist-templates.php');
        require( spiff()->plugin_dir . 'playlists/playlist-track-class.php');
        require( spiff()->plugin_dir . 'playlists/playlist-tracklist-class.php');
        require( spiff()->plugin_dir . 'playlists/playlist-tracklist-table-class.php' );
        require( spiff()->plugin_dir . 'playlists/playlist-class.php');
        
        //admin
        if(is_admin()){
            //require( spiff()->plugin_dir . 'playlists/admin.php');
            //require( spiff()->plugin_dir . 'playlists/admin-options.php');
        }

    }

    function setup_actions(){   
        add_action( 'init', array(&$this,'add_xspf_endpoint' ));    
    }

    
    function register_query_vars($vars) {
        $vars[] = self::$var_xspf;
        return $vars;
    }
    
    /**
     * Add endpoint for the "/xspf" posts links 
     */

    function add_xspf_endpoint(){
        add_rewrite_endpoint(self::$var_xspf, EP_PERMALINK );
    }
    
}

function spiff_playlists() {
	return Spiff_Playlists_Core::instance();
}

spiff_playlists();



?>
