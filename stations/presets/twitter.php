<?php

class Spiff_Station_Preset_Twitter extends Spiff_Station_Default{
    
    var $twitter_apikey;
    var $username;

    function populate_url($feed_url){
        
        //init
        $this->username = null;
        //$this->twitter_apikey = spiff_stations()->get_options('twitter_apikey');

        $patterns = array(
            '~^(?:http(?:s)?://(?:www\.)?twitter.com/)([^/]+)(?:/?)$~'
        );

        foreach ($patterns as $pattern){
            preg_match($pattern, $feed_url, $matches);

            if ( isset($matches[1]) ){
                $this->twitter_username = $matches[1];
                return true;
            }
            return false;
        }
    }
    
    function get_presets(){
        return array(
            'tracks_order'  => 'desc',
            'selectors'     => array(
                    'tracks'        => '#timeline .tweet-text',
                    'track_artist'      => false,
                    'track_title'       => false,
                    'track_ablum'       => false,
                    'track_location'    => false
            )
        );
    }
    
    function prepare_preset_variables(){
        $variables = parent::prepare_preset_variables();
        
       //username
        $username_args = array(
            'value'         => $this->twitter_username,
            'name'          => __('Twitter username','spiff'),
            'private'       => true,
            'show_form'     => false,
        );
        
        $username_var = $this->variables->make_single_var('twitter_username',$username_args);
        $variables[] = $username_var;

        return $variables;
        
    }


}

spiff_stations()->register_preset('Spiff_Station_Preset_Twitter');
