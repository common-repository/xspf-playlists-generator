<?php

class Spiff_Tracklist{
    var $tracks = array();
    var $is_wizard = false;
    
    function __construct(){
    }
    
    function add(Spiff_Track $track){
        $this->tracks[] = $track;
        $this->clean();
        
    }
    
    function reverse(){
        $this->tracks = array_reverse($this->tracks);
    }
    
    function musicbrainz(){
        foreach ($this->tracks as $key=>$track){
            $track->musicbrainz();
            
            //API requires a pause between two requests.
            if ( (count($this->tracks) > 1) && $track->did_lookup ) sleep(1);
        }
    }
    
    function clean(){
        
        //array unique
        $this->tracks = array_unique($this->tracks, SORT_REGULAR);
        
        if (!$this->is_wizard){
            //keep only tracks having artist AND title
            $this->tracks = array_filter(
                $this->tracks,
                function ($e) {
                    return ($e->artist && $e->title);
                }
            );
        }else{
            //keep only tracks having artist OR title (Wizard)
            $this->tracks = array_filter(
                $this->tracks,
                function ($e) {
                    return ($e->artist || $e->title);
                }
            );
        }
    }
    
    function array_load(array $array){
        $this->tracks = null;
        foreach ($array as $item){
            $this->add( new Spiff_Track($item) );
        }
    }
    function array_export(){
        $export = array();
        foreach ($this->tracks as $track){
            $export[] = $track->array_export();
        }

        return $export;
    }
    
    function display(){
        echo $this->get_display();
    }
    
    function get_display(){

        $tracks_table = new Spiff_Tracklist_Table($this->tracks);
        $tracks_table->prepare_items();

        ob_start();
        //$tracks_table->views();
        $tracks_table->display();
        $table = ob_get_clean();

        return $table;
    }
   
}

?>
