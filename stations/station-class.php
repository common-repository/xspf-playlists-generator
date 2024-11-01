<?php

use PHPHtmlParser\Dom;
use SoapBox\Formatter\Formatter;

abstract class XSPFPL_Station extends Spiff_Playlist{

    static $meta_key_settings = 'spiff_settings';

    var $post_id = null;
    var $is_wizard = false; //is set to true when playlist is called by wizard
    var $is_xspf = false; //is XSPF rendering

    var $options = null; //playlist options as they have been saved
    
    var $variables = null;
    
    var $datas_remote = null;
    var $datas_cache = null;
    var $is_cache = false;

    var $response_time = null;
    var $track_nodes = array();

    var $stats;

    function __construct($post_id = null){
        
        parent::__construct();
        
        $this->is_wizard = spiff_stations_is_wizard();
        $this->is_xspf = ( isset($_REQUEST[Spiff_Playlists_Core::$var_xspf]) ? true : false);

        $this->post_id = $post_id;        
        $this->options = self::get_default_options();

        if ($option_db = get_post_meta($this->post_id, self::$meta_key_settings, true)){
            $this->options = array_replace_recursive($this->options, $option_db);
        }

    }
    
    function populate(){

        //variables engine
        $this->variables = new XSPFPL_Station_Variables($this);
        $this->variables->populate();

        //repopulate URL now that the variables are set
        $this->populate_url( $this->get_feed_url() );
        
        //datas engines
        $this->datas_remote = new Spiff_Station_Datas_Remote($this);
        
        if ( $this->get_options('cache_persistent') ){
            $this->datas_cache = new Spiff_Station_Datas_Cache_Persistent($this);
        }else{
            $this->datas_cache = new Spiff_Station_Datas_Cache_Temporary($this);
        }

        $this->populate_presets();
        
        //author & title will be repopulated if we fetch a remote feed later

        $this->title = $this->get_station_title();
        $this->author = $this->get_station_author();
        $this->tracklist->is_wizard = $this->is_wizard;

        $this->stats = new XSPFPL_Station_Stats($this);

    }
    
    function populate_presets(){

        $this->presets = (array)$this->get_presets();
        //$this->presets = apply_filters('spiff_get_presets',$this->presets,$this);
        
        if ( $this->presets ){
            $this->options = array_replace_recursive($this->options, $this->presets);
        }

    }

    function get_options($path = null){
        return spiff_get_array_value($path,$this->options);
    }

    static function get_default_options($path = null){
        $default = array(
            'website_url'               => null, //url to parse
            'feed_url'                  => null,
            'selectors' => array(
                'tracks'            => null,
                'track_artist'      => null,
                'track_title'       => null,
                'track_album'       => null,
                'track_location'    => null,
                'track_image'       => null
            ),
            'selectors_regex' => array(
                'track_artist'      => null,
                'track_title'       => null,
                'track_album'       => null,
                'track_location'    => null
            ),
            'tracks_order'              => 'desc',
            'cache_persistent'          => false,                               //wheter or not playlist should be only parsed one time
            'cache_duration'            => spiff()->get_options('cache_tracks_intval'),
            'max_tracks'                => 20,                                  //max titles (if playlist is not frozen)
            'musicbrainz'               => false,                               //check tracks with musicbrainz
            'dynamic_title'             => null
        );

        return spiff_get_array_value($path,$default);
        
    }
    
    /**
     * Checks if the requested option matches the preset
     * @param type $keys
     * @return type
     */
    
    function is_preset_value($path = null){
        $option = spiff_get_array_value($path,$this->options);
        $preset = spiff_get_array_value($path,$this->presets);
        $is_preset = (!is_null($option) && ($option === $preset) );
        
        return $is_preset;
    }

    function is_station_ready(){
        if (!$this->post_id) return false;
        if ( $this->variables->missing ) return false;
        return true;
    }
    
    function get_feed_website_url(){
        $url = $this->get_options('website_url');

        $url = XSPFPL_Station_Variables::string_replace_wizard_value($url,$this);
        $url = XSPFPL_Station_Variables::string_replace_patterns($url,$this);
        //$url = apply_filters('spiff_playlist_website_url',$url,$this);
        return $url;
    }

    function get_feed_url(){
        $url = $this->get_options('feed_url');

        $url = XSPFPL_Station_Variables::string_replace_wizard_value($url,$this);
        $url = XSPFPL_Station_Variables::string_replace_patterns($url,$this);

        //$url = apply_filters('spiff_playlist_feed_url',$url,$this);
        return $url;
    }
    
    /**
     * "Real" feed URL (called to get the remote playlist)
     * @return type
     */

    function get_feed_url_redirect(){
        $url = $this->get_preset_feed_url_redirect();

        $url = XSPFPL_Station_Variables::string_replace_wizard_value($url,$this);

        $url = XSPFPL_Station_Variables::string_replace_patterns($url,$this);
        //$url = apply_filters('spiff_playlist_feed_url_redirect',$url,$this);
        return $url;
    }
    
    function get_station_title(){
        return $this->get_preset_title();
        //return apply_filters('spiff_playlist_get_title',$title,$this);
    }
    
    function get_station_author(){
        return $this->get_preset_author();
        //return apply_filters('spiff_playlist_get_author',$author,$this);
    }

    function populate_tracklist($cache_only = false){

        if ( !$this->is_station_ready() )return;
 
        if ( $this->tracklist->tracks !==null ){
            
            // set to 'null' at init so we won't try tro repopulate it again if no tracks are found.
            // default at init was (array)
            $this->tracklist->tracks = null;
            
            //try to get cache first
            $this->datas_cache->populate();

            //successfully populated
            if ($this->tracklist->tracks){
                $this->is_cache = true;
            }
            
            //get remote data when no cache is found (except if cache_only is set)
            if ( !$this->tracklist->tracks && (!$cache_only) ){
                $this->datas_remote->populate();
                
                //repopulate author & title as we might change them depending of the page content
                $this->title = $this->get_station_title();
                $this->author = $this->get_station_author();
                
            }
            
            if ( $this->tracklist->tracks && $this->datas_remote && !$this->is_cache){
                $this->datas_cache->set_cache();
            }
            
            $this->stats->update_health_status();

            if (!$cache_only){ //used among others to get last track
                $this->stats->update_track_request_count();
                $this->stats->update_track_request_monthly_count();
            }
            do_action('spiff_get_tracks',$this);

        }

    }

}

?>
