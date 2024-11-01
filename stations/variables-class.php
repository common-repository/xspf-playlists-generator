<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

class XSPFPL_Station_Variables {
    var $station;
    var $items = array();
    var $form;
    var $missing; //required variables slugs that have not been filled
    
    function __construct($station){
        $this->station = $station;
    }
    
    function populate(){
        $variables = array();
        $required_variables_slugs = array();
        $variables = $this->populate_patterns_variables();

        if ($presets_variables = $this->station->prepare_preset_variables()){
            $presets_variables = self::reorder_with_slugs($presets_variables);
            $variables = array_merge($variables,$presets_variables);
        }
        
        $required_variables_slugs = array_keys($variables);

        //populate transition variables only when displaying the form
        if (!$this->station->is_wizard && !$this->station->is_xspf){
            $variables = $this->station->preset_filter_transition_variables($variables);
            $variables = self::reorder_with_slugs($variables);
        }

        if (!$this->station->is_wizard){

            //popuplate frontend values (only for non-private variables)
            $form_values = array_filter( (array)$this->station->get_preset_variable_form_values($variables) );

            foreach ($variables as $slug=>$variable){
                if ( $variable->private ) continue;
                $variable->value = (isset($form_values[$slug]) ? $form_values[$slug] : null);
            }

        }
        
        //frontend variables : set private as true so get_for_url() will exclude them
        foreach ($variables as $slug=>$var){
            if ( !in_array($slug, $required_variables_slugs) ){
                $var->private = true;
            }
        }

        $this->items = $variables; //populate them

        //register missing  variables
        foreach ($variables as $slug=>$var){
            if ($var->value) continue;
            if ( in_array($slug, $required_variables_slugs) && !$var->show_form ) continue;
            $this->missing[] = $slug;
        }

        if ( $this->missing && is_admin() ){
            add_settings_error( 'wizard-header', 'playlist_variables_missing', __('Some required variables have not been filled, check the Variables tab.','spiff'),'error inline' );
        }

    }
    
    /**
     * Update string by replacing wizard value with %pattern%.
     * @param type $string
     * @return type
     */
    
    static function string_replace_wizard_value($string,$playlist){
        
        if ( !is_object($playlist->variables) ) return $string; //not populated yet

        if (!$variables = $playlist->variables->get()) return $string;

        foreach ($variables as $var){
            if (!$var->pattern) continue;
            if (!$var->wizard_value) continue;
            
            $string = str_replace($var->wizard_value,$var->pattern,$string);
        }

        return $string;
    }

    /**
     * Update string by replacing %variables% with form values.
     * @param type $string
     * @return type
     */
    
    static function string_replace_patterns($string,$playlist){
        
        if ( !is_object($playlist->variables) ) return $string; //not populated yet

        if (!$variables = $playlist->variables->get()) return $string;

        foreach ($variables as $var){
            if (!$var->pattern) continue;
            if (!$var->value) continue;
            
            $string = str_replace($var->pattern,$var->value,$string);
        }

        return $string;
    }
    
    function make_single_var($slug,$args = null){
        return new XSPFPL_Variable($slug,$this->station->post_id,$args);
        
    }
    
    /**
     * Extract patterns from the station URL,
     * And make variables out of it.
     * @return type
     */

    function populate_patterns_variables(){
        
        $variables = array();

        //URL masks variables
        $pattern_strings = array(
            $this->station->get_options('feed_url'),//no filters here !
            $this->station->get_options('website_url')//no filters here !,
        );
        $slugs = spiff_stations_get_patterns($pattern_strings);

        foreach ((array)$slugs as $slug){

                $args = array(
                    'show_form'         => true,
                    'store_value'       => true,
                    'can_edit_name'     => true,
                    'pattern'           => '%'.$slug.'%',
                );
                $variables[] = $this->make_single_var($slug,$args);
        }
        
        return self::reorder_with_slugs($variables);

    }
    
    /*
     * Reorder the input variables to put their slug as array key
     */
    
    static function reorder_with_slugs($vars){
        $keyed = array();
        //set slug as key
        foreach ((array)$vars as $variable){
            $keyed[$variable->slug] = $variable;
        }
        return $keyed;
    }

    
    function get($path = null){
        return spiff_get_array_value($path,$this->items);
    }
    
    /**
     * Keep only the variables that are not computed
     */
    
    function get_for_form($path = null){

        $form_items = $this->get();

        $form_variables = array_filter(
            (array)$form_items,
            function ($e){
                return ($e->show_form);
            }
        ); 

        return spiff_get_array_value($path,$form_variables);

    }
    
    
    
    /**
     * Variables that will be used to build the playlists URLs.
     * Exclude variables that do not appear in the form and private ones.
     * @param type $path
     * @return type
     */
    
    function get_for_url($path = null){

        $url_variables = array_filter(
            (array)$this->get(),
            function ($e){
                return (!$e->private);
            }
        ); 

        return spiff_get_array_value($path,$url_variables);

    }
    
    function get_frontend_form(){

        $variables = $this->get_for_form();

        if ( !$variables ) return false;

        $block = null;
        $message = null;
        $form_fields = array();
        
        $form_classes = array('spiff-station-form');

        if ( is_singular() && $this->missing ){
            $form_classes[] = 'error';
            $message = sprintf('<p class="message">%1$s</p>',__('Please complete the form','spiff'));
        }


        foreach ((array)$variables as $variable){

            $field = $field_label = $field_desc = $field_input = null;
            $field_classes = array();

            if (is_singular()){
                if ( $variable->value ){
                    $field_classes[] = 'has-value';
                }else{
                    $field_classes[] = 'error';
                }
            }

            $field_label = '<label>'.$variable->name.'</label>';

            $field_input = sprintf(
                '<input type="text" name="%1$s" value="%2$s" placeholder="%3$s">',
                spiff_stations()->var_station_variables.'['.$variable->slug.']',
                $variable->value,
                $variable->name
            );
            $field = sprintf('<fieldset %1$s>%2$s%3$s%4$s</fieldset>',spiff_get_classes($field_classes),$field_label,$field_desc,$field_input);

            $form_fields[] = $field;

        }

        if ($form_fields){

            $form = sprintf(
                '<form action="%1$s" method="get" %2$s>
                %3$s
                <button type="submit">%4$s</button>
                <input type="hidden" name="p" value="%5$s"/>
                </form>',
                get_bloginfo( 'url' ),
                spiff_get_classes($form_classes),
                $message.implode("\n",$form_fields),
                __('Load Station','spiff'),
                $this->station->post_id
            );

            return $form;

        }
    }

}

class XSPFPL_Variable {
    var $post_id;
    var $slug;
    var $name;
    var $value;
    var $wizard_value;
    var $pattern; //string to replace in get_feed_url
    var $metaname;
    var $private; //set to false if visitor cannot see this variable in the URL (as for API keys)
    var $show_form; //allow user to set this value frontend
    var $can_edit_name; //can we edit the variable  name (label) ?
    var $store_value;

    function __construct($slug,$post_id,$input_args = array()){

        $default = array(
            'slug'              => null,
            'name'              => null,
            'value'             => null,
            'wizard_value'      => null, //set frontend, in populate()
            'pattern'           => null,
            'private'           => false,
            'show_form'         => false,
            'can_edit_name'     => false,
            'store_value'       => false,
        );
        
        $args = wp_parse_args($input_args,$default);

        $this->post_id =        $post_id;
        $this->slug =           sanitize_title($slug);
        $this->metaname =       'spiff_var_'.$this->slug;
        $this->store_value =    $args['store_value'];
        $this->can_edit_name =  $args['can_edit_name'];
        $this->pattern =        $args['pattern'];
        $this->private =        (bool)$args['private'];
        
        //stored values
        if ( $db = get_post_meta($this->post_id, $this->metaname, true) ){ //override with stored options
            
            if (!$this->store_value) unset($db['value']); //double check.
            $args = wp_parse_args($db,$args);
        }

        $this->name = ($args['name'] ? $args['name'] : $this->slug);
        $this->value = $args['value'];
        $this->wizard_value = $args['value'];
        $this->show_form = $args['show_form'];

        
        if ( !spiff_stations_is_wizard() && $this->show_form){
            
            if ( !$this->pattern ){ //set pattern
                $this->pattern = '%'.$this->slug.'%';
            }

        }

    }


    //TO FIX TO CHECK do not save form values
    function save(){

        $store = array(
            'name'          => $this->name,
            'value'         => $this->value,
            'show_form'     => $this->show_form
        );

        if (!$this->store_value) $store['value'] = null;

        //remove NULL (but not false) values
        $store = array_filter($store, function($var){return !is_null($var);} );

        return update_post_meta($this->post_id, $this->metaname, $store);

    }

}
