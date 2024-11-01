<?php

require( spiff()->plugin_dir . 'stations/presets/bbc.php');
require( spiff()->plugin_dir . 'stations/presets/lastfm.php');
require( spiff()->plugin_dir . 'stations/presets/radionomy.php');
require( spiff()->plugin_dir . 'stations/presets/slacker.php');
require( spiff()->plugin_dir . 'stations/presets/somafm.php');
require( spiff()->plugin_dir . 'stations/presets/soundcloud.php');
require( spiff()->plugin_dir . 'stations/presets/spotify.php');
require( spiff()->plugin_dir . 'stations/presets/twitter.php');
require( spiff()->plugin_dir . 'stations/presets/xspf.php');

class Spiff_Station_Default extends XSPFPL_Station{
    var $presets = null;
    
    /**
     * Function that checks if the preset should be loaded
     * + Populate custom variables.
     * @param type $feed_url
     */
    
    function populate_url($feed_url){
        return true;
    }

    /**
     * Array of presets that would override the playlist options
     * @return type
     */
    function get_presets(){
        return array();
    }
    
    /**
     * Prepare the variables that will be used in this preset.
     * They will be filtered in XSPFPL_Station_Variables::populate().
     * @return type
     */
    
    function prepare_preset_variables(){
        return array();
    }
    
    /**
     * Retrieve the values sent through the form.
     * They will be filtered in XSPFPL_Station_Variables::get_form_values().
     * @return type
     */
    
    function get_preset_variable_form_values(){
        if (!isset($_REQUEST[spiff_stations()->var_station_variables])) return;
        return $_REQUEST[spiff_stations()->var_station_variables];
    }
    
    /**
     * Prepare the variables that will be used to display the frontend form.
     * They will be filtered in XSPFPL_Station_Variables::get_for_form().
     * @return type
     */
    
    function preset_filter_transition_variables($vars){
        return $vars;
    }
    
    /**
     * "Real" feed URL (called to get the remote playlist)
     * @return type
     */

    function get_preset_feed_url_redirect(){
        return $this->get_feed_url();
    }
    
    /**
     * Get the playlist title
     * @return type
     */
    function get_preset_title(){

        if ( !$this->is_wizard && $this->is_station_ready() && ($dynamic_title = $this->get_options('dynamic_title')) ){
            return $this->variables->string_replace_patterns($dynamic_title,$this);
        }else{
            return get_post_field( 'post_title', $this->post_id );
        }

    }
    
    /**
     * Get the playlist author
     * @return type
     */
    function get_preset_author(){
        $author_id = get_post_field( 'post_author', $this->post_id );
        return get_the_author_meta( 'user_nicename', $author_id );
    }
    
    /**
     * Filter the page content before it's being parsed
     * @param type $content
     * @return type
     */
    
    function preset_filter_page_content_pre($content){
        return $content;
    }

}

/**
 * All presets but this one should be registered like this :
 */

//spiff_stations()->register_preset('Spiff_Station_Default');
