<?php

function spiff_stations_get_website_url($post_id=false){
    $playlist = spiff_stations_get($post_id);
    return $playlist->get_feed_website_url();
}

function spiff_station_form(){
    echo spiff_station_get_form();
}

function spiff_station_get_form($post_id=false){
    global $post;
    if (!$post_id) $post_id = $post->ID;
    $playlist = spiff_stations_get($post_id);

    $output = null;
    $output.= $playlist->variables->get_frontend_form();
    return $output;
}

function spiff_stations_is_cache_persistent($post_id = false){
    
    $playlist = spiff_stations_get($post_id);
    return $playlist->get_options('cache_persistent');
}

/**
 * Returns the last track
 * @param type $post_id
 * @return type
 */

function spiff_stations_get_last_track($post_id = false, $cache_only = true){
    
    global $post;
    if (!$post_id) $post_id = $post->ID;
    $playlist = spiff_stations_get($post_id);
    $playlist->populate_tracklist($cache_only);

    if ( !$playlist->tracklist->tracks ) return;

    $track = array_shift($playlist->tracklist->tracks);

    if ( $track->album ){
        
        return sprintf(
            __('%1$s by %2$s on %3$s','spiff'),
            '<em>'.$track->title.'</em>',
            $track->artist,
            '"'.$track->album.'"'
        );
        
    }else{
        
        return sprintf(
            __('%1$s by %2$s','spiff'),
            '<em>'.$track->title.'</em>',
            $track->artist
        );
        
    }
}


/**
 * Get the number of time tracks have been requested
 * @global type $post
 * @param type $post_id
 * @return boolean
 */

function spiff_stations_get_request_count($post_id = false){
    
    global $post;
    if (!$post_id) $post_id = $post->ID;
    $playlist = spiff_stations_get($post_id);
    
    $count = null;
    
    if ( get_post_status($post->ID) == 'publish') {
        $count = get_post_meta($post->ID, XSPFPL_Station_Stats::$meta_key_requests, true);
    }

    return (int)$count;
}

function spiff_stations_get_monthly_request_count($post_id = false){
    
    global $post;
    if (!$post_id) $post_id = $post->ID;
    $playlist = spiff_stations_get($post_id);
    
    $count = null;
    
    if ( get_post_status($post->ID) == 'publish') {
        $count = get_post_meta($post->ID, XSPFPL_Station_Stats::$meta_key_monthly_requests, true);
    }

    return (int)$count;
}

/**
 * Checks if the playlist is still alive : each time tracks are populated,
 * A "health" meta is added with the time and number of tracks found.
 * If health fell to zero, maybe the playlist is no more alive.
 * @return boolean
 */

function spiff_stations_get_health($post_id = false){
    
    global $post;
    if (!$post_id) $post_id = $post->ID;
    $playlist = spiff_stations_get($post_id);
    
    if ( get_post_status($post->ID) != 'publish') return false;
    
    //no health for frozen playlists
    if ( spiff_stations_is_cache_persistent() ) return false;

    $metas = get_post_meta($post->ID, XSPFPL_Station_Stats::$meta_key_health, true);

    //no entries
    if ( empty($metas) ) return false;

    $total = count($metas);
    $health = 0;

    foreach ($metas as $meta){
        if ($meta['tracks'] == 0) continue;
        $health++;
    }

    $percent = ($health / $total)*100;

    return $percent;     

}

?>
