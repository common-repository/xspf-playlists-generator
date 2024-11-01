<?php

class Spiff_Station_Preset_Soundcloud_User extends Spiff_Station_Default{
    var $soundcloud_username;
    var $soundcloud_api_subresource;
    var $soundcloud_api_client_id;
    
    function populate_url($feed_url){
        
        //init
        $this->soundcloud_username = null;
        $this->soundcloud_api_subresource = 'tracks';
        $this->soundcloud_api_client_id = spiff()->get_options('soundcloud_client_id');

        $patterns = array(
            '~^(?:http(?:s)?://(?:www\.)?soundcloud.com/)([^/]+)/([^/]+)~'
        );

        foreach ($patterns as $pattern){
            preg_match($pattern, $feed_url, $matches);

            if ( !isset($matches[1]) ) return false;

            $this->soundcloud_username = $matches[1];

            if ( isset($matches[2]) ){
                switch ($matches[2]){
                    case 'likes':
                        $this->soundcloud_api_subresource = 'favorites';
                    break;
                }
            }

            return true;
        }
    }

    function get_presets(){
        
        $presets = array();
        
        if ($this->soundcloud_api_client_id){
            $presets = array(
                'tracks_order'  => 'desc',
                'selectors'     => array(
                        'tracks'            => 'element',
                        'track_artist'      => 'user username',
                        'track_title'       => 'title',
                        'track_album'       => false,
                        'track_location'    => false,
                        'track_image'       => 'artwork_url'
                ),
                'selectors_regex' => array(
                    'track_artist'      => false,
                    'track_title'       => false,
                    'track_album'       => false,
                    'track_location'    => false,
                )
            );
        }
        
        return $presets;

    }
    
    function prepare_preset_variables(){
        $variables = parent::prepare_preset_variables();
        
        //username
        $username_args = array(
            'value' => $this->soundcloud_username,
            'name'  => __('Soundcloud username','spiff')
        );
        
        $username_var = $this->variables->make_single_var('soundcloud_username',$username_args);
        $variables[] = $username_var;

        //Client ID
        $client_id_args = array(
            'value'             => $this->soundcloud_api_client_id,
            'name'              => __('Soundcloud API client ID','spiff'),
            'private'           => true,
            'show_form'         => false,
        );

        $client_id_var = $this->variables->make_single_var('soundcloud_client_id',$client_id_args);
        $variables[] = $client_id_var;

        //subresource
        $subresource_args = array(
            'value'         => $this->soundcloud_api_subresource,
            'name'          => __('Soundcloud API Subresource','spiff'),
            'private'       => true,
            'show_form'         => false,
        );

        $subresource_var = $this->variables->make_single_var('soundcloud_subresource',$subresource_args);
        $variables[] = $subresource_var;

        return $variables;
        
    }
    
    function get_preset_feed_url_redirect(){
        if ($this->soundcloud_api_client_id){
            $api_url = sprintf('http://api.soundcloud.com/users/%1$s/%2$s',$this->soundcloud_username,$this->soundcloud_api_subresource);
            $api_url = add_query_arg(array('client_id'=>$this->soundcloud_api_client_id),$api_url);

            return $api_url;
        }
    }

    function get_preset_author(){
        return $this->soundcloud_username;
    }

}

spiff_stations()->register_preset('Spiff_Station_Preset_Soundcloud_User');
