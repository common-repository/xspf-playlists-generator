<?php

class Spiff_Station_Preset_LastFM_User extends Spiff_Station_Default{
    
    var $lastfm_username;
    var $lastfm_apikey;
    var $lastfm_api_method;

    function populate_url($feed_url){
        
        //init
        $this->lastfm_username = null;
        $this->lastfm_api_method = 'user.getRecentTracks';
        $this->lastfm_apikey = spiff()->get_options('lastfm_apikey');

        $patterns = array(
            '~http(?:s)?://(?:www\.)?last.fm/(?:[a-zA-Z]{2}/)?(?:user/([^/]+))(?:/([^/]+))?~'
        );

        foreach ($patterns as $pattern){
            preg_match($pattern, $feed_url, $matches);

            if ( isset($matches[2]) ){
                switch ($matches[2]){
                    case 'loved':
                        $this->lastfm_api_method = 'user.getLovedTracks';
                    break;
                    case 'library':
                        $this->lastfm_api_method = 'user.getTopTracks';
                    break;
                }
            }
            
            if ( isset($matches[1]) ){
                $this->lastfm_username = $matches[1];
                return true;
            }

        }
    }
    
    function get_presets(){
        
        $presets = array();
        
        if ($this->lastfm_apikey){

            $options_new = array(
                'tracks_order'  => 'desc',
                'selectors_regex' => array(
                    'track_artist'      => false,
                    'track_title'       => false,
                    'track_album'       => false,
                )
            );

            $options_method = array();

            switch ($this->lastfm_api_method){
                case 'user.getRecentTracks':
                    $options_method = array(
                        'selectors'     => array(
                            'tracks'        => 'track',
                            'track_artist'  => 'artist',
                            'track_title'   => 'name',
                            'track_album'   => 'album',
                            'track_image'   => 'image[size="small"]'
                        )
                    );
                break;
                default:
                    $options_method = array(
                        'selectors'     => array(
                                'tracks'        => 'track',
                                'track_artist'  => 'artist name',
                                'track_title'   => 'name',
                                'track_album'   => null,
                                'track_image'   => 'image[size="small"]'
                        )
                    );
                break;
            }

            $presets = array_merge( $options_new,$options_method );
            
        }
        
        return $presets;
    }
    
    function prepare_preset_variables(){
        
        $variables = parent::prepare_preset_variables();
        
        //username
        $username_args = array(
            'value' => $this->lastfm_username,
            'name'  => __('Last.fm username','spiff')
        );
        
        $username_var = $this->variables->make_single_var('lastfm_username',$username_args);
        $variables[] = $username_var;

        //API key
        $apikey_args = array(
            'value'             => $this->lastfm_apikey,
            'name'              => __('Last.fm API key','spiff'),
            'private'           => true,
            'show_form'         => false,
        );

        $apikey_var = $this->variables->make_single_var('lastfm_apikey',$apikey_args);
        $variables[] = $apikey_var;

        $method_args = array(
            'value'             => $this->lastfm_api_method,
            'name'              => __('Last.fm API method','spiff'),
            'private'           => true,
            'show_form'         => false,
        );

        $method_var = $this->variables->make_single_var('lastfm_method',$method_args);
        $variables[] = $method_var;
        
        return $variables;
    }
    
    function get_preset_feed_url_redirect(){
        
        $feed_url = parent::get_preset_feed_url_redirect();
        
        if ($this->lastfm_apikey){
            $api_args = array(
                'user'      => $this->lastfm_username,
                'api_key'   => $this->lastfm_apikey,
                'method'    => $this->lastfm_api_method,
            );

            $feed_url = add_query_arg($api_args,'http://ws.audioscrobbler.com/2.0/');
        }
        
        return $feed_url;
    }
    
    function get_preset_author(){
        return $this->lastfm_username;
    }
    
}

spiff_stations()->register_preset('Spiff_Station_Preset_LastFM_User');
