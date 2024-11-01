<?php

class Spiff_Track{
    
    public $title;
    public $artist;
    public $album;
    public $image;
    public $location;
    public $mbid = null;
    public $duration;
    public $did_lookup = false;
    
    function __construct($args){
        $defaults = self::get_default();
        $args = wp_parse_args($args,$defaults);
        
        foreach ((array)$defaults as $param=>$dummy){
            $this->$param = apply_filters('spiff_get_track_'.$param,$args[$param]);
        }
    }
    
    static function get_default(){
        return array(
            'title'     =>null,
            'artist'    =>null,
            'album'     =>null,
            'image'     =>null,
            'location'  =>null,
            'mbid'      =>null,
            'duration'  =>null,
        );
    }
    
    function array_export(){
        $defaults = self::get_default();
        $export = array();
        foreach ((array)$defaults as $param=>$dummy){
            $export[$param] = $this->$param;
        }
        return array_filter($export);
    }
    
    function musicbrainz(){
        //abord
        if( !$this->artist || !$this->title ) return;
        if( $this->mbid ) return;
        
        //query
        $mzb_args = '"'.rawurlencode($this->title).'"';
        $mzb_args .= rawurlencode(' AND artist:');
        $mzb_args .= '"'.rawurlencode($this->artist).'"';

        //TO FIX album is ignored for the moment.
        /*
        if(!empty($this->album)){
            $mzb_args .= rawurlencode(' AND release:');
            $mzb_args .= '"'.rawurlencode($this->album).'"';
        }
        */

        $mzb_url = add_query_arg(array('fmt'=>'json','query'=>$mzb_args),'http://www.musicbrainz.org/ws/2/recording');

        $request = wp_remote_get($mzb_url);
        $this->did_lookup = true;

        if (is_wp_error($request)) return;

        $response = wp_remote_retrieve_body( $request );
        if (is_wp_error($response)) return;

        $results = json_decode($response);

        
        if ($results->count && isset($results->recordings[0])){

            //WE'VE GOT A MATCH !!!
            $match = $results->recordings[0];

            //check score is high enough
            if($match->score>=70){

                $this->mbid = $match->id;

                //title
                $this->title = $match->title;

                //length
                if(isset($match->length))
                    $this->duration = $match->length;

                //artist
                $artists = $match->{'artist-credit'};

                foreach($artists as $artist){
                    $obj = $artist->artist;
                    $artists_names_arr[]=$obj->name;
                }
                $this->artist = implode(' & ',$artists_names_arr);

                //album
                if(isset($match->releases)){
                    $albums = $match->releases;

                    if(isset($albums[0])){
                        $this->album = $albums[0]->title;
                    }
                }

            }

        }
        
    }

}

add_filter('spiff_get_track_artist','strip_tags');
add_filter('spiff_get_track_artist','urldecode');
add_filter('spiff_get_track_artist','htmlspecialchars_decode');
add_filter('spiff_get_track_artist','trim');

add_filter('spiff_get_track_title','strip_tags');
add_filter('spiff_get_track_title','urldecode');
add_filter('spiff_get_track_title','htmlspecialchars_decode');
add_filter('spiff_get_track_title','trim');

add_filter('spiff_get_track_album','strip_tags');
add_filter('spiff_get_track_album','urldecode');
add_filter('spiff_get_track_album','htmlspecialchars_decode');
add_filter('spiff_get_track_album','trim');

add_filter('spiff_get_track_image','strip_tags');
add_filter('spiff_get_track_image','urldecode');
add_filter('spiff_get_track_image','trim');


add_filter('spiff_get_track_location','strip_tags');
add_filter('spiff_get_track_location','urldecode');
add_filter('spiff_get_track_location','trim');

?>
