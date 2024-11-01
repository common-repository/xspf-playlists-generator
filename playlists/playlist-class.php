<?php

class Spiff_Playlist{
    
    var $tracklist = null;
    
    var $title = null;
    var $author = null;
    
    function __construct(){
        $this->tracklist = new Spiff_Tracklist();
    }

    function get_xspf(){
        
        error_reporting(E_ERROR | E_PARSE); //ignore warnings & errors

        $this->populate_tracklist(); //need to be up, at least to get response time

        ///RENDER XSPF
        $dom = new DOMDocument('1.0', get_bloginfo('charset') );
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;

        // create playlist element
        $playlist_el = $dom->createElement("playlist");
        $playlist_el->setAttribute('xmlns', 'http://xspf.org/ns/0/');
        $playlist_el->setAttribute('version', '1');
        $dom->appendChild($playlist_el);

        // playlist title

        if ( $pl_title = $this->title ){

            $pl_title_el = $dom->createElement("title");
            $playlist_el->appendChild($pl_title_el);
            $pl_title_txt_el = $dom->createTextNode($pl_title);
            $pl_title_el->appendChild($pl_title_txt_el);
        }

        // playlist author
        if ( $author =  $this->author ){
            $pl_author_el = $dom->createElement("creator");
            $playlist_el->appendChild($pl_author_el);
            $pl_author_txt_el = $dom->createTextNode($author);
            $pl_author_el->appendChild($pl_author_txt_el);
        }

        //playlist info
        if ( $info = get_permalink($this->post_id) ){
            $pl_info_el = $dom->createElement("info");
            $playlist_el->appendChild($pl_info_el);
            $pl_info_txt_el = $dom->createTextNode($info);
            $pl_info_el->appendChild($pl_info_txt_el);
        }
        //playlist date            
        $pl_date_txt_el = $dom->createTextNode( gmdate(DATE_ISO8601,$this->response_time) );
        $pl_date_el = $dom->createElement("date");
        $pl_date_el->appendChild($pl_date_txt_el);
        $playlist_el->appendChild($pl_date_el);

        //playlist location
        $pl_loc_txt_el = $dom->createTextNode( $this->get_feed_website_url() );
        $pl_loc_el = $dom->createElement("location");
        $pl_loc_el->appendChild($pl_loc_txt_el);
        $playlist_el->appendChild($pl_loc_el);

        //playlist annotation
        $pl_annot_el = $dom->createElement("annotation");
        $pl_annot_txt_el = $dom->createTextNode(sprintf(__('Station generated with the %1s Plugin by %2s.','spiff'),spiff_stations()->name,spiff_stations()->author));
        $pl_annot_el->appendChild($pl_annot_txt_el);
        $playlist_el->appendChild($pl_annot_el);

        // tracklist
        $pl_tracklist_el = $dom->createElement("trackList");
        $playlist_el->appendChild($pl_tracklist_el);

        //tracks

        foreach ((array)$this->tracklist->tracks as $key=>$track){

            $track_el = $dom->createElement("track");

            //title
            $track_title_el = $dom->createElement("title");
            $track_el->appendChild($track_title_el);
            $track_title_txt_el = $dom->createTextNode($track->title);
            $track_title_el->appendChild($track_title_txt_el);

            //artist
            $track_artist_el = $dom->createElement("creator");
            $track_el->appendChild($track_artist_el);
            $track_artist_txt_el = $dom->createTextNode($track->artist);
            $track_artist_el->appendChild($track_artist_txt_el);

            //album
            if ( $track->album ){
                $track_album_el = $dom->createElement("album");
                $track_el->appendChild($track_album_el);
                $track_album_txt_el = $dom->createTextNode($track->album);
                $track_album_el->appendChild($track_album_txt_el);
            }

            //image
            if ( $track->image ){
                $track_img = $dom->createElement("image");
                $track_el->appendChild($track_img);
                $track_img_txt = $dom->createTextNode($track->image);
                $track_img->appendChild($track_img_txt);
            }

            //location
            if ( $track->location ){
                $track_img = $dom->createElement("location");
                $track_el->appendChild($track_img);
                $track_img_txt = $dom->createTextNode($track->location);
                $track_img->appendChild($track_img_txt);
            }

            //mbid
            if ( $track->mbid ){
                $track_mbid_el = $dom->createElement("mbid");
                $track_el->appendChild($track_mbid_el);
                $track_mbid_txt_el = $dom->createTextNode( $track->mbid );
                $track_mbid_el->appendChild($track_mbid_txt_el);
            }

            //duration
            if ( $track->duration ){
                $track_duration_el = $dom->createElement("duration");
                $track_el->appendChild($track_duration_el);
                $track_duration_txt_el = $dom->createTextNode( $track->duration );
                $track_duration_el->appendChild($track_duration_txt_el);
            }

            $pl_tracklist_el->appendChild($track_el);
        }

        //TO FIX 
        //we should remove the root XML node using
        //LIBXML_NOXMLDECL
        //but seems it is not supported yet or buggy
        //$rendered = $dom->saveXML(null,LIBXML_NOXMLDECL);
        //so we use this trick :

        return $dom->saveXML($dom->documentElement);
        
    }
    
    function display(){
        echo $this->get_display();
        
    }
    
    function get_display(){
        $station_links = spiff_playlist_get_links();
        $tracklist = $this->tracklist->get_display();
        return $station_links.$tracklist.$station_links;
    }

}

?>
