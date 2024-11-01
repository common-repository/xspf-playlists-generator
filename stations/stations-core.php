<?php

class Spiff_Stations_Core {

    /**
    * @var The one true Instance
    */
    private static $instance;

    var $options_default = array();
    var $options = null;

    public $station_post_type='station';
    public $tax_music_tag='music_tag';
    public $var_station_variables='station_vars';
    public $var_station_sortby='sort_station';

    var $presets_names = array();
    
    public static function instance() {
            if ( ! isset( self::$instance ) ) {
                    self::$instance = new Spiff_Stations_Core;
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

            //options
            $this->options_default = array(
                'playlist_link'         => 'on',
                'cache_tracks_intval'   => 60*5, //seconds - set to 0 to disable cache
                'tracklist_embed'       => 'on',
                'lastfm_apikey'         => null,
                'soundcloud_client_id'  => null,
                'twitter_apikey'         => null,
            );


    }

    function includes(){

        require( spiff()->plugin_dir . 'stations/functions.php');
        require( spiff()->plugin_dir . 'stations/templates.php');
        require( spiff()->plugin_dir . 'stations/station-class.php');
        require( spiff()->plugin_dir . 'stations/presets-class.php');
        require( spiff()->plugin_dir . 'stations/datas-class.php');
        require( spiff()->plugin_dir . 'stations/variables-class.php');
        require( spiff()->plugin_dir . 'stations/stats-class.php');

        //admin
        if(is_admin()){
            require( spiff()->plugin_dir . 'stations/wizard.php' );
            require( spiff()->plugin_dir . 'stations/admin.php');
            require( spiff()->plugin_dir . 'stations/admin-options.php');
        }

    }

    function setup_actions(){    
        
        register_activation_hook( spiff()->file , array( $this, 'set_roles_capabilities' ) );//roles & capabilities

        add_action( 'init', array(&$this,'register_station_post_type' ));
        add_action( 'init', array(&$this,'register_taxonomy' ));
        add_filter('query_vars', array(&$this,'register_query_vars' ));
 
        add_filter( 'request', array($this, 'filter_request'));
        add_action( 'pre_get_posts', array($this, 'sort_stations'));
        add_action( 'template_redirect', array($this, 'render_xspf'), 5);
        add_action( 'wp_enqueue_scripts', array($this, 'scripts_styles'));

        add_filter('spiff_get_xspf_link', array($this, 'xspf_link_append_variables'), 10, 2);

        //filter regular the_title & the_author functions so we can still use them as usual instead of using our custom functions
        add_filter( 'the_title', array(&$this,'filter_playlist_title' ), 10, 2);
        add_filter( 'the_author', array(&$this,'filter_station_author' ));
        add_filter( 'the_excerpt', array(&$this,'append_station_links' ));
        add_filter( 'the_content', array(&$this,'append_station' ));

    }
    
    function xspf_link_append_variables($link,$post_id){
        if ( get_post_type($post_id) != $this->station_post_type ) return $link;
        
        $station = spiff_stations_get($post_id);
        if ( !$station->is_station_ready() ) return false;
        
        $args = array();
        $variables = $station->variables->get_for_url();

        foreach ($variables as $variable){
            $args[$this->var_station_variables][$variable->slug] = $variable->value;
        }
        
        $args = array_filter($args);

        return add_query_arg ( $args,$link );

    }

    
    function register_query_vars($vars) {
        $vars[] = $this->var_station_variables;
        $vars[] = $this->var_station_sortby;
        return $vars;
    }

    function scripts_styles(){
        wp_register_style( 'spiff-stations', spiff()->plugin_url .'_inc/css/spiff-stations-style.css',false,spiff()->version);
        wp_enqueue_style( 'spiff-stations' );
    }

    /**
    * Set xspf as true, see http://wordpress.stackexchange.com/questions/42279/custom-post-type-permalink-endpoint
    * @param type $vars 
    */

    function filter_request($vars){
        if( isset( $vars[Spiff_Playlists_Core::$var_xspf] ) ) $vars[Spiff_Playlists_Core::$var_xspf] = true;
        return $vars;
    }
    
    function sort_stations( $query ) {

        if ( !$query->is_main_query() || ( !$order = $query->get( $this->var_station_sortby ) ) || $query->get('post_type')!=$this->station_post_type ) return $query;
        
        switch ($order){
            case 'popular':

                $query->set('meta_key', XSPFPL_Station_Stats::$meta_key_requests );
                $query->set('orderby','meta_value_num');
                $query->set('order', 'DESC');
                
            break;
        
            //TO FIX
            case 'trending':
                $query->set('meta_key', XSPFPL_Station_Stats::$meta_key_monthly_requests );
                $query->set('orderby','meta_value_num');
                $query->set('order', 'DESC');
            break;
        }
        
        return $query;
        
    }
    
    /**
     * (Do apply filter only if page_content is set)
     * @global type $post
     * @param type $title
     * @return type
     */
    
    function filter_playlist_title($title,$post_id){
        global $post;

        if ( is_admin() ) return $title;
        if ($post->ID != $post_id) return $title; //menu title fix
        if ( !is_single() ) return $title;
        if ( !$station = spiff_stations_get() ) return $title;

        return $station->title;
        
    }
    
    /**
     * (Do apply filter only if page_content is set)
     * @global type $post
     * @param type $author
     * @return type
     */
    
    function filter_station_author($author){
        
        global $post;

        if ( is_admin() ) return $author;
        if ( !is_single() ) return $author;
        if ( !$station = spiff_stations_get() ) return $author;
        
        return $post->playlist->author;
        
    }

    /**
     * Render the XSPF file
     * @return boolean 
     */

    function render_xspf($download = false){
        global $post;
        
        if (!is_singular($this->station_post_type)) return false;
        if (!get_query_var( Spiff_Playlists_Core::$var_xspf )) return false;
        
        if (!$playlist = spiff_stations_get()) return false;
        if (!$playlist->is_station_ready()) return false;
        


        $xspf = $playlist->get_xspf();
        
        if (!is_wp_error($xspf)){
            
            if ($download){
                $filename = $post->post_name;
                $filename = sprintf('%1$s.xspf',$filename);
                header("Content-Type: application/xspf+xml");
                header('Content-disposition: attachment; filename="'.$filename.'"');
            }else{
                header("Content-Type: text/xml");
            }

            echo $xspf;
            
        }else{
            echo $xspf->get_error_message();
        }
        
        die();
    }
    
    function append_station_links($content){
        global $post;

        if( get_post_type()==$this->station_post_type && spiff()->get_options('playlist_link') ){
            
            $station = spiff_stations_get();
            
            if ( $station->is_station_ready() ){
                $content .= spiff_playlist_get_links();
            }else{
                $content .= spiff_station_get_form();
            }

        }
        return $content;

    }
    
    function append_station($content){

        if( is_single() && get_post_type()==$this->station_post_type && spiff()->get_options('tracklist_embed') ){

            $station = spiff_stations_get();
            $station->populate_tracklist();

            if ( $station->is_station_ready() ){
                $content .= $station->get_display();
            }

        }

        return $content;
        
    }

    function register_station_post_type() {

        $labels = array( 
            'name' => _x( 'Stations', 'spiff' ),
            'singular_name' => _x( 'Station', 'spiff' ),
            'add_new' => _x( 'Add New', 'spiff' ),
            'add_new_item' => _x( 'Add New Station', 'spiff' ),
            'edit_item' => _x( 'Edit Station', 'spiff' ),
            'new_item' => _x( 'New Station', 'spiff' ),
            'view_item' => _x( 'View Station', 'spiff' ),
            'search_items' => _x( 'Search Stations', 'spiff' ),
            'not_found' => _x( 'No stations found', 'spiff' ),
            'not_found_in_trash' => _x( 'No stations found in Trash', 'spiff' ),
            'parent_item_colon' => _x( 'Parent Station:', 'spiff' ),
            'menu_name' => _x( 'Spiff Stations', 'spiff' ),
        );

        $args = array( 
            'labels' => $labels,
            'hierarchical' => false,

            'supports' => array( 'title', 'editor','author','thumbnail', 'comments' ),
            'taxonomies' => array( $this->tax_music_tag ),
            'public' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_nav_menus' => true,
            'publicly_queryable' => true,
            'exclude_from_search' => false,
            'has_archive' => true,
            'query_var' => true,
            'can_export' => true,
            'rewrite' => true,
            //http://justintadlock.com/archives/2013/09/13/register-post-type-cheat-sheet
            'capability_type' => 'station',
            'map_meta_cap'        => true,
            'capabilities' => array(

                // meta caps (don't assign these to roles)
                'edit_post'              => 'edit_station',
                'read_post'              => 'read_station',
                'delete_post'            => 'delete_station',

                // primitive/meta caps
                'create_posts'           => 'create_stations',

                // primitive caps used outside of map_meta_cap()
                'edit_posts'             => 'edit_stations',
                'edit_others_posts'      => 'manage_stations',
                'publish_posts'          => 'manage_stations',
                'read_private_posts'     => 'read',

                // primitive caps used inside of map_meta_cap()
                'read'                   => 'read',
                'delete_posts'           => 'manage_stations',
                'delete_private_posts'   => 'manage_stations',
                'delete_published_posts' => 'manage_stations',
                'delete_others_posts'    => 'manage_stations',
                'edit_private_posts'     => 'edit_stations',
                'edit_published_posts'   => 'edit_stations'
            ),
        );

        register_post_type( $this->station_post_type, $args );
    }
    
    function register_taxonomy() {
        
        $labels = array(
                'name'                       => _x( 'Tags', 'taxonomy general name' ),
                'singular_name'              => _x( 'Tag', 'taxonomy singular name' ),
                'search_items'               => __( 'Search Tags' ),
                'popular_items'              => __( 'Popular Tags' ),
                'all_items'                  => __( 'All Tags' ),
                'parent_item'                => null,
                'parent_item_colon'          => null,
                'edit_item'                  => __( 'Edit Tag' ),
                'update_item'                => __( 'Update Tag' ),
                'add_new_item'               => __( 'Add New Tag' ),
                'new_item_name'              => __( 'New Tag Name' ),
                'separate_items_with_commas' => __( 'Separate tags with commas' ),
                'add_or_remove_items'        => __( 'Add or remove tags' ),
                'choose_from_most_used'      => __( 'Choose from the most used tags' ),
                'not_found'                  => __( 'No tags found.' ),
                'menu_name'                  => __( 'Tags' ),
        );

        $args = array(
                'hierarchical'          => false,
                'labels'                => $labels,
                'show_ui'               => true,
                'show_admin_column'     => true,
                'update_count_callback' => '_update_post_term_count',
                'query_var'             => true,
                'rewrite'               => array( 'slug' => $this->tax_music_tag ),
                'capabilities' => array(
                    'manage_terms' => 'manage_playlists',
                    'edit_terms' => 'edit_playlists',
                    'delete_terms' => 'edit_playlists',
                    'assign_terms' => 'edit_playlists'
                )
        );

        register_taxonomy( $this->tax_music_tag, $this->station_post_type, $args );
    }
    
        function set_roles_capabilities(){
            
            global $wp_roles;
            if ( ! isset( $wp_roles ) ) $wp_roles = new WP_Roles();

            //create a new role, based on the subscriber role 
            $role_name = 'station_author';
            $subscriber = $wp_roles->get_role('subscriber');
            $wp_roles->add_role($role_name,__('Station Author','spiff'), $subscriber->capabilities);

            //list of custom capabilities and which role should get it
            $wiki_caps=array(
                'manage_stations'=>array('administrator','editor'),
                'edit_stations'=>array('administrator','editor',$role_name),
                'create_stations'=>array('administrator','editor',$role_name),
            );

            foreach ($wiki_caps as $wiki_cap=>$roles){
                foreach ($roles as $role){
                    $wp_roles->add_cap( $role, $wiki_cap );
                }
            }

        }
        
        function register_preset($class_name){
            if (!class_exists($class_name)) return false;
            spiff_stations()->presets_names[] = $class_name;
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

function spiff_stations() {
	return Spiff_Stations_Core::instance();
}

spiff_stations();



?>
