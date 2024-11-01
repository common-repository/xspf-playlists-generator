<?php

function spiff_get_bbc_station_slug($url){
    $pattern = '~^(?:http(?:s)?://(?:www\.)?bbc.co.uk/)([^/]*)~';
    
    preg_match($pattern, $url, $matches);

    if (!isset($matches[1])) return false;

    return $matches[1];
}

function spiff_get_bbc_program_id($station_url){
    
    //only when displaying single station, or would be potentially requested too often (eg. in posts loops)
    if ( !is_singular(spiff_stations()->station_post_type) && !spiff_stations_is_wizard() ) return false;
    
    if (!$slug = spiff_get_bbc_station_slug($station_url)) return;
    
    $station_url = sprintf('http://www.bbc.co.uk/%s',$slug);

    $response = wp_remote_get( $station_url );
    if ( is_wp_error($response) ) return;

    $response_code = wp_remote_retrieve_response_code( $response );
    if ($response_code != 200) return;
    
    $content = wp_remote_retrieve_body( $response );
        
    libxml_use_internal_errors(true);
    
    //QueryPath

    try{
        $music_played_link = htmlqp( $content, '.t1-live-title a', $this->datas_remote->querypath_options )->attr('href');
    }catch(Exception $e){
        return false;
    }

    libxml_clear_errors();

    $pattern = '~(?:/programmes/)([^/#]*)~';
    preg_match($pattern, $music_played_link, $matches);

    //title
    if ( !isset($matches[1]) ) return false;

    return $matches[1];
    
}

function spiff_bbc_get_presets($options,$playlist){

    if ( $slug = spiff_get_bbc_station_slug($options['feed_url']) ){
        
        $options_new = array(
            'feed_url'      => 'http://www.bbc.co.uk/programmes/%program_id%/segments.inc',
            'tracks_order'  => 'desc',
            'selectors' => array(
                    'tracks'        => 'ul li',
                    'track_artist'  => 'span[property=byArtist] a span',
                    'track_title'   => 'span[property=name]',
                    'track_album'   => 'li.inline em',
                    'track_location'    => null,
                    'track_image'   => 'img'
            ),
            'selectors_regex' => array(
                'track_artist'      => false,
                'track_title'       => false,
                'track_album'       => false,
                'track_location'    => false
            )
        );

        $playlist->options['website_url'] = sprintf('http://www.bbc.co.uk/%s',$slug);

        $options = array_merge( (array)$options,$options_new );
        
    }

    return $options;

}

function spiff_bbc_get_feed_url_redirect($feed_url,$playlist){

         //radio ID
        $had_bbc_id = false;
        
        if (!$playlist->is_form){
           if ($bbc_id = get_post_meta($playlist->post_id,'spiff_setting_bbc_id', true)){
               $had_bbc_id = true;
           }
        }

        if ( !$bbc_id ){
            $bbc_id = spiff_get_bbc_program_id($website_url);;
        }
        
        if ( !$playlist->is_form && $bbc_id && !$had_bbc_id ){
            update_post_meta($playlist->post_id, 'spiff_setting_bbc_id', $bbc_id);
        }
        
        $playlist->populate_variable('bbc_id',__('BBC Program ID'),$bbc_id);


}
