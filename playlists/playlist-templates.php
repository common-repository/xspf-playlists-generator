<?php

function spiff_playlist_get_links($post_id=false){
    
    global $post;
    if (!$post_id) $post_id = $post->ID;

    $links = array();
    $links_str = null;

    //links
    if ($xspf_link = spiff_get_xspf_link($post->ID)){
        
        $links[] = sprintf(
            '<a href="%1$s" %2$s>%3$s</a>',
            $xspf_link,
            spiff_get_classes(array('xspf-link')),
            __('Download XSPF','spiff')
        );

        //THK friendly
        $thk_link = add_query_arg( array('xspf' => $xspf_link), 'tomahawk://import/playlist' );

        $links[] = sprintf(
            '<a href="%1$s" %2$s>%3$s</a>',
            $thk_link,
            spiff_get_classes(array('xspf-link','thk-link')),
            __('Add to Tomahawk','spiff')
        );
    }

    if ($links){
        foreach($links as $link){
            $links_str .= '<li>'.$link.'</li>';
        }
        return '<ul class="station-links">'.$links_str.'</ul>';
    }

}

/**
 * Get the XSPF link for a post.
 * Don't user permalinks here as subscriptions will break if something changes about the permalinks on the blog.
 * Using raw URLs avoid it.
 * @global type $post
 * @param type $post_id
 * @return string 
 */
function spiff_get_xspf_link($post_id=false){
    
    global $post;
    if (!$post_id) $post_id = $post->ID;

    $args = array(
        'p'                 => $post->ID,
        Spiff_Playlists_Core::$var_xspf   => 1
    );

    $url = add_query_arg ( $args,get_bloginfo('url') );
    return apply_filters('spiff_get_xspf_link',$url,$post->ID);
}

?>
