<?php

class Spiff_Station_Preset_Spotify_Playlist extends Spiff_Station_Default{
    
    var $spotify_user;
    var $spotify_playlist_id;
    var $spotify_api_key;

    function populate_url($feed_url){
        
        //init
        $this->spotify_user = null;
        $this->spotify_playlist_id = null;
        //$this->spotify_api_key = spiff_stations()->get_options('lastfm_apikey');

        $patterns = array(
            '~^(?:http(?:s)?://embed.spotify.com/\?uri=)(?:spotify:user:)([^:]+):playlist:([^&]+)~',
            '~(?:http(?:s)?://play.spotify.com/user/)([^/]+)/playlist/([^?]+)~',
            '~(?:http(?:s)?://open.spotify.com/user/)([^/]+)/playlist/([^?]+)~',
            '~spotify:user:([^:]+):playlist:(.+)~'
        );

        foreach ($patterns as $pattern){
            preg_match($pattern, $feed_url, $matches);

            if ( isset($matches[1]) && isset($matches[2]) ){
                $this->spotify_user = $matches[1];
                $this->spotify_playlist_id = $matches[2];
                
                return true;
            }
        }
    }

    
    function get_presets(){
        return array(
            'website_url'   => sprintf('https://embed.spotify.com/?uri=spotify:user:%1$s:playlist:%2$s',$this->spotify_user,$this->spotify_playlist_id),
            'selectors' => array(
                    'tracks'            => '#mainContainer ul.track-info',
                    'track_artist'      => '.artist',
                    'track_title'       => '.track-title',
                    'track_album'       => null,
                    'track_location'    => null,
                    'track_image'       => null
            ),
            'selectors_regex' => array(
                'track_artist'      => false,
                'track_title'       =>  '^(?:\d+\W+)?(.*)$',
                'track_location'    => false,
                'track_album'       => false,
            ),
            'tracks_order'              => 'desc'
        );
    }

    function prepare_preset_variables(){
        
        $variables = parent::prepare_preset_variables();
        
       //user
        $user_args = array(
            'name'  => __('User','spiff'),
            'value' => $this->spotify_user,
        );
        $user_var = $this->variables->make_single_var('user',$user_args);
        $variables[] = $user_var;

        //playlist ID
        $this_id_args = array(
            'name'  => __('Playlist','spiff'),
            'value' => $this->spotify_playlist_id,
        );

        $station_playlist_id_var = $this->variables->make_single_var('playlist_id',$this_id_args);
        $variables[] = $station_playlist_id_var;

        return $variables;
    }
    
    function get_preset_variable_form_values(){
        
        $values = parent::get_preset_variable_form_values();
        
        if (!isset($values['spotify_url'])) return $values;
        
        $this->populate_url($values['spotify_url']);
        $values['user'] = $this->spotify_user;
        $values['playlist_id'] = $this->spotify_playlist_id;

        return $values;
        
    }

    function preset_filter_transition_variables($vars){
        
        //user & playlist ID vars are shown frontend
        if (!isset($vars['user']) || !isset($vars['playlist_id']) || !$vars['user']->show_form || !$vars['playlist_id']->show_form) return $vars;

        $vars['user']->show_form = false;
        $vars['playlist_id']->show_form = false;

        //add a new form field for a spotify URL
        $spotify_url_args = array(
            'name'              => __('Spotify Playlist URL','spiff'),
            'show_form'         => true
        );

        $spotify_url_var = $this->variables->make_single_var('spotify_url',$spotify_url_args);

        $vars[] = $spotify_url_var;

        return $vars;
        
    }
    
    function get_preset_feed_url_redirect(){
        if (!$this->spotify_api_key){
            return sprintf('https://embed.spotify.com/?uri=spotify:user:%1$s:playlist:%2$s',$this->spotify_user,$this->spotify_playlist_id);
        }else{
            $api_url = sprintf('https://api.spotify.com/v1/users/%1$s/playlists/%2$s',$this->spotify_user,$this->spotify_playlist_id);
            $api_url = add_query_arg(array('client_id'=>$this->client_id),$api_url);
            return $api_url;
        }
    }
    
    function get_preset_title(){
        $title = parent::get_preset_title();

        if ($this->datas_remote->page_node){ //we've got the page HTML
            try{
                if ( $page_title = qp( $this->datas_remote->page_node, 'title', $this->datas_remote->querypath_options )->text() ){

                    $pattern = '~^(.*)(?: by )(.*)$~';
                    preg_match($pattern, $page_title, $matches);

                    //title
                    if ( isset($matches[1]) ){
                        $title = $matches[1];
                    }

                }

            }catch(Exception $e){
            }
        }

        return $title;
    }
    
    /**
     * Get the playlist author
     * @return type
     */
    function get_preset_author(){
        return $this->spotify_user;
    }

}

spiff_stations()->register_preset('Spiff_Station_Preset_Spotify_Playlist');
