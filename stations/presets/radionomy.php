<?php

//add_filter('spiff_get_track_image',array(&$this,'unset_default_images'),10,2);

class Spiff_Station_Preset_Radionomy_Station extends Spiff_Station_Default{

    var $radionomy_slug;
    var $radionomy_id;
    var $radionomy_title;

    function populate_url($feed_url){
        
        //init
        $this->radionomy_slug = null;
        $this->radionomy_id = null;

        $patterns = array(
            '~^(?:http(?:s)?://(?:www\.)?radionomy.com/.*?/radio/)([^/]+)~',
            '~^(?:http(?:s)?://listen.radionomy.com/)([^/]+)~',
            '~^(?:http(?:s)?://streaming.radionomy.com/)([^/]+)~',
        );

        foreach ($patterns as $pattern){
            preg_match($pattern, $feed_url, $matches);

            if ( isset($matches[1]) ){
                $this->radionomy_slug = $matches[1];

                return true;
            }
        }
    }
    
    function get_presets(){
        return array(
            'tracks_order'  => 'desc',
            'selectors' => array(
                    'tracks'            => 'div.titre',
                    'track_artist'      => 'table td',
                    'track_title'       => 'table td i',
                    'track_album'       => null,
                    'track_location'    => null,
                    'track_image'       => 'img'
            ),
            'selectors_regex' => array(
                'track_artist'      => '^(.*?)(?:<br ?/?>)',
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
            'name'      => __('Station Slug','spiff'),
            'value'     => $this->radionomy_slug,
        );

        
        $station_slug_var = $this->variables->make_single_var('radionomy_station_slug',$station_slug_args);
        $variables[] = $station_slug_var;

        return $variables;
    }

    function get_preset_variable_form_values(){
        
        $values = parent::get_preset_variable_form_values();
        
        if (!isset($values['radionomy_url'])) return $values;
        
        $this->populate_url($values['radionomy_url']);
        $values['radionomy_station_slug'] = $this->radionomy_slug;

        return $values;
    }

    function preset_filter_transition_variables($vars){

        //is the station slug dynamic ?
        if (!isset($vars['radionomy_station_slug']) || !$vars['radionomy_station_slug']->show_form) return $vars;

        //hide & unset previous ones
        foreach((array)$vars as $var){
            $var->show_form = false;
            $var->value = '';
        }

        //add a new form field for a spotify URL
        $radionomy_url_args = array(
            'name'      => __('Radionomy URL','spiff'),
            'show_form' => true,
        );

        $radionomy_url_var = $this->variables->make_single_var('radionomy_url',$radionomy_url_args);
        $vars[] = $radionomy_url_var;

        return $vars;
    }

    function get_preset_feed_url_redirect(){

        if (!$station_id_var = $this->variables->get('station_id')){

            $station_slug_var = $this->variables->get('radionomy_station_slug');

            //station ID var
            $station_id_args = array(
                'name'              => __('Station ID','spiff'),
                'private'           => true,
                'show_form'         => false,
                'store_value'       => (!$station_slug_var->show_form) //store if station slug is NOT dynamic
            );

            $station_id_var = $this->variables->make_single_var('station_id',$station_id_args);

            //station ID value
            if ( !$station_id_var->value && ($this->radionomy_id = $this->get_station_id()) ){
                $station_id_var->value = $this->radionomy_id;
            }

            $this->variables->items[] = $station_id_var;
        }

        return sprintf('http://radionomy.letoptop.fr/ajax/ajax_last_titres.php?radiouid=%s',$station_id_var->value);

    }

    function unset_default_images($image){

        $unset_images = array(
            'http://radionomy.letoptop.fr/images/none.jpg'
        );

        if (in_array($image,$unset_images)){
            $image = null;
        }

        return $image;
    }

    function get_station_id(){

        if (!$this->radionomy_slug) return false;

        $station_url = sprintf('http://www.radionomy.com/en/radio/%1$s',$this->radionomy_slug);

        $response = wp_remote_get( $station_url );

        if ( is_wp_error($response) ) return;

        $response_code = wp_remote_retrieve_response_code( $response );
        if ($response_code != 200) return;

        $content = wp_remote_retrieve_body( $response );

        libxml_use_internal_errors(true);

        //QueryPath
        try{
            $title = htmlqp( $content, 'head meta[property="og:title"]', $this->datas_remote->querypath_options )->attr('content');
            if ($title) $this->radionomy_title = $title;
        }catch(Exception $e){
        }

        //QueryPath
        try{
            $imagepath = htmlqp( $content, 'head meta[property="og:image"]', $this->datas_remote->querypath_options )->attr('content');
        }catch(Exception $e){
            return false;
        }

        libxml_clear_errors();

        $image_file = basename($imagepath);

        $pattern = '~^([^.]+)~';
        preg_match($pattern, $image_file, $matches);

        //title
        if ( !isset($matches[1]) ) return false;

        return $matches[1];

    }

}

spiff_stations()->register_preset('Spiff_Station_Preset_Radionomy_Station');
