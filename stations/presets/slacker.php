<?php

class Spiff_Station_Preset_Slacker extends Spiff_Station_Default{
    
    var $slacker_slug;

    function populate_url($feed_url){
        
        //init
        $this->slacker_slug = null;
        
        $patterns = array(
            '~^(?:http(?:s)?://(?:www\.)?slacker.com/station/)([^/]*)(?:/?)$~'
        );

        foreach ($patterns as $pattern){
            preg_match($pattern, $feed_url, $matches);

            if ( isset($matches[1]) ){
                $this->slacker_slug = $matches[1];
                return true;
            }
        }
    }
    
    function get_presets(){
        return array(
            'tracks_order' => 'desc',
            'selectors' => array(
                    'tracks'            => 'track',
                    'track_artist'      => 'byArtist name',
                    'track_title'       => 'name',
                    'track_album'       => 'inAlbum name',
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
            'name'      => __('Slacker Station Slug','spiff'),
            'value'     => $this->slacker_slug,
        );

        $station_slug_var = $this->variables->make_single_var('slacker_station_slug',$station_slug_args);
        $variables[] = $station_slug_var;

        return $variables;
    }

    function get_preset_variable_form_values(){
        
        $values = parent::get_preset_variable_form_values();
        
        if (!isset($values['slacker_station_url'])) return $values;
        
        $this->populate_url($values['slacker_station_url']);
        $values['slacker_station_slug'] = $this->slacker_slug;

        return $values;
    }
    
    function preset_filter_transition_variables($vars){

        //user & playlist ID vars are shown frontend
        if (!isset($vars['slacker_station_slug']) || !$vars['slacker_station_slug']->show_form ) return $vars;

        $vars['slacker_station_slug']->show_form = false;

        //add a new form field for a slacker URL
        $slacker_url_args = array(
            'name'              => __('Slacker Station URL','spiff'),
            'show_form'         => true
        );

        $slacker_url_var = $this->variables->make_single_var('slacker_station_url',$slacker_url_args);

        $vars[] = $slacker_url_var;

        return $vars;
    }

    function preset_filter_page_content_pre($content){
        libxml_use_internal_errors(true);

        //QueryPath
        try{
            $node = htmlqp( $content, 'head script[type="application/ld+json"]', $this->datas_remote->querypath_options );
            if ($node_content = $node->innerHTML()){
                $node_content = spiff_stations_sanitize_cdata_string($node_content);
                $content = preg_replace('/&(?!#?[a-z0-9]+;)/', '&amp;', $node_content); //http://stackoverflow.com/questions/7604436/xmlparseentityref-no-name-warnings-while-loading-xml-into-a-php-file
                $this->datas_remote->response_type = 'application/json'; //set content
            }
            
        }catch(Exception $e){
            return new WP_Error( 'querypath', sprintf(__('QueryPath Error [%1$s] : %2$s','spiff'),$e->getCode(),$e->getMessage()) );
        }

        libxml_clear_errors();

        return $content;
    }
    /*
    function get_preset_title(){
        $title = parent::get_preset_title();

        //QueryPath
        try{
            if ( $page_title = qp( $this->datas_remote->page_node, 'title', $this->datas_remote->querypath_options )->text() ){

                $pattern = '~^(.*?)(?:\s\|\s)(?:.*)$~';
                preg_match($pattern, $page_title, $matches);

                //title
                if ( isset($matches[1]) ){
                    $title = $matches[1];
                }

            }

        }catch(Exception $e){

        }

        return $title;
        
    }
     * 
     */

}

spiff_stations()->register_preset('Spiff_Station_Preset_Slacker');
