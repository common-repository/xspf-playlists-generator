<?php

class Spiff_Stations_Options {

    function __construct() {
        self::setup_globals();
        self::includes();
        self::setup_actions();
    }

    function setup_globals() {
        $this->column_name_synced = 'spiff_station_synced';
        $this->column_name_health = 'spiff_station_health';
        $this->column_name_last_track = 'spiff_station_last_track';
        $this->column_name_requests_count = 'spiff_station_requests';
    }

    function includes(){
        
    }

    function setup_actions(){

        add_action( 'admin_enqueue_scripts',  array( $this, 'scripts_styles' ) );
        add_filter('manage_posts_columns', array(&$this,'post_column_register'), 5);
        add_action('manage_posts_custom_column', array(&$this,'post_column_content'), 5, 2);

    }

    /*
     * Scripts for backend
     */
    public function scripts_styles($hook) {
        if( ( get_post_type()!=spiff_stations()->station_post_type ) && ($hook != 'playlist_page_spiff-options') ) return;
        wp_enqueue_style( 'spiff-stations-admin', spiff()->plugin_url .'_inc/css/spiff-stations-admin.css', array(), spiff()->version );
    }
    

    function post_column_register($defaults){

        if ( get_post_type() != spiff_stations()->station_post_type) return $defaults;
        
        //split at title
        
        $before = array();
        $after = array();
        
        $after[$this->column_name_last_track] = __('Last track','spiff');
        $after[$this->column_name_health] = __('Live','spiff');
        $after[$this->column_name_requests_count] = __('Requests','spiff').'<br/><small>'.__('month','spiff').'/'.__('total','spiff').'</small>';
        $after[$this->column_name_synced] = '';
        
        $defaults = array_merge($before,$defaults,$after);
        
        return $defaults;
    }
    function post_column_content($column_name, $post_id){
        
        if ( get_post_type() != spiff_stations()->station_post_type) return;
        
        $output = '';
        
        switch ($column_name){
            
            //health
            case $this->column_name_health:
                $percentage = spiff_stations_get_health();
                if ($percentage === false){

                }else{
                    $output = sprintf('%d %%',$percentage);
                }
            break;
            
            //last track
            case $this->column_name_last_track:

                if ( $last_track = spiff_stations_get_last_track() ){
                    $output = $last_track;
                }
                
            break;
            
            //loaded
            case $this->column_name_requests_count:
                $total = spiff_stations_get_request_count();
                $month = spiff_stations_get_monthly_request_count();
                
                $output = $month.'/'.$total;
                
            break;
        
            //live icon
            case $this->column_name_synced:
                if ( !$is_persistent = spiff_stations_is_cache_persistent() ){
                    $output = '<div class="dashicons dashicons-rss"></div>';
                }else{
                    $output = '<div class="dashicons dashicons-rss is-frozen"></div>';
                }
            break;
        
        
        }
        
        echo $output;
    }

}

new Spiff_Stations_Options();

?>
