<?php

class Spiff_Station_Preset_SomaFM extends Spiff_Station_Default{
    
    var $somafm_slug;
    
    function populate_url($feed_url){
        
        //init
        $this->somafm_slug = null;

        $patterns = array(
            '~^(?:http(?:s)?://(?:www\.)?somafm.com/)([^/]+)(?:/?)$~'
        );

        foreach ($patterns as $pattern){
            preg_match($pattern, $feed_url, $matches);

            if ( isset($matches[1]) ){
                $this->somafm_slug = $matches[1];
                return true;
            }
        }
    }
    
    function get_presets(){
        return array(
            'tracks_order'  => 'desc',
            'selectors'     => array(
                    'tracks'            => 'song',
                    'track_artist'      => 'artist',
                    'track_title'       => 'title',
                    'track_album'       => 'album',
                    'track_location'    => null,
                    'track_image'       => null
            ),
            'selectors_regex' => array(
                'track_artist'      => false,
                'track_title'       => false,
                'track_album'       => false,
                'track_location'    => false,
            )
        );
    }
    
    function prepare_preset_variables(){
        
        $variables = parent::prepare_preset_variables();
        
        //station Slug
        $station_slug_args = array(
            'name'      => __('SomaFM Station Slug','spiff'),
            'value'     => $this->somafm_slug,
        );

        $station_slug_var = $this->variables->make_single_var('somafm_station_slug',$station_slug_args);
        $variables[] = $station_slug_var;

        return $variables;
    }

    function get_feed_url_redirect(){
        return sprintf('http://somafm.com/songs/%1$s.xml',$this->somafm_slug);
    }

}

spiff_stations()->register_preset('Spiff_Station_Preset_SomaFM');
