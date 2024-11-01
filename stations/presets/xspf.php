<?php

class Spiff_Station_Preset_XSPF{
    
    public function enable_preset($playlist){
        if ( $playlist->datas_remote && $playlist->datas_remote->feed_is_xspf() ){
            return true;
        }
        return false;
    }
    
    function __construct(){
        add_filter('spiff_get_presets',array(&$this,'get_presets'),10,2);
    }
    
    function get_presets($options,$playlist){

        if (!$this->enable_preset($playlist)) return $options; //should we load this filter ?

        $options_new = array(
            'tracks_order' => 'desc',
            'selectors' => array(
                    'tracks'            => 'trackList track',
                    'track_artist'      => 'creator',
                    'track_title'       => 'title',
                    'track_album'       => 'album',
                    'track_location'    => 'location',
                    'track_image'       => 'image'
            ),
            'selectors_regex' => array(
                'track_artist'      => false,
                'track_title'       => false,
                'track_album'       => false,
                'track_location'    => false,
            )
        );

        return array_merge( (array)$options,$options_new );

    }
}

