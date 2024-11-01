<?php

//TO FIX TO IMPROVE !
function spiff_stations_get($post_id = null){

    global $post;
    $post_id = null;

    $is_wizard = (bool)spiff_stations_is_wizard();

    //get wizard post ID
    if ( $is_wizard && !$post_id ){
        if ( isset($_GET['post']) ){
            $post_id = $_GET['post'];
        }elseif( isset($_POST['post_ID']) ){
            $post_id = $_POST['post_ID'];
        }
    }

    //get current post
    if (!$post_id && $post) $post_id = $post->ID;

    //check post type
    if ( $post_id && ( !$post || ($post_id != $post->ID) ) ){
            if ( get_post_type($post_id) != spiff_stations()->station_post_type ) return false;
            $post = get_post($post_id);
    }

    //populate station, only if it doesn't exists yet
    if ( $post_id ){
        if ( !property_exists($post,'station') ){
            $station = spiff_stations_get_preset($post->ID,$is_wizard);
            $post->station = $station;
            $post->station->populate();
        }
    }else{
        $post->station = spiff_stations_get_preset(null,$is_wizard);
        
    }
    
    if ($post->station){
        return $post->station;
    }
    

}

function spiff_stations_get_preset($post_id,$is_wizard){
    $station = new Spiff_Station_Default($post_id);
    foreach(spiff_stations()->presets_names as $preset_name){
        $potential_station = new $preset_name($post_id);
        if ($potential_station->populate_url( $potential_station->options['feed_url'] )){
            $station = $potential_station;
        }
    }

    return $station;
}

function spiff_stations_is_wizard(){
    global $pagenow;
    
    $station_wizard_new = ( ($pagenow == 'post-new.php') && isset($_GET['post_type']) && $_GET['post_type']==spiff_stations()->station_post_type );
    $station_wizard_edit = ( ($pagenow == 'post.php') && isset($_GET['post']) && (get_post_type($_GET['post'])==spiff_stations()->station_post_type) );
    $station_wizard_update = ( ($pagenow == 'post.php') && isset($_POST['post_ID']) && (get_post_type($_POST['post_ID'])==spiff_stations()->station_post_type) );

    if ( $station_wizard_new || $station_wizard_edit || $station_wizard_update ){
            return true;
    }

}

function spiff_stations_sanitize_cdata_string($string){
    $string = str_replace("//<![CDATA[","",$string);
    $string = str_replace("//]]>","",$string);
    
    $string = str_replace("<![CDATA[","",$string);
    $string = str_replace("]]>","",$string);

    return trim($string);
}

/**
 * Extract %patterns% from an array of strings
 * @return type
 */

function spiff_stations_get_patterns($arr){

    $variables = array();
    $regex = '/\%(.*?)\%/';

    foreach ((array)$arr as $string){
        preg_match_all($regex, $string, $matches);

        if (isset($matches[1])){
            $variables = array_merge($variables,$matches[1]);
        }
    }

    return array_unique($variables);
}   

class Array2XML {
    
    /* based (but tweaked) on
     * https://github.com/easydigitaldownloads/Easy-Digital-Downloads/blob/master/includes/libraries/array2xml.php
     */
    
    private static $xml = null;
    private static $encoding = null;
    private static $element_name = null;
    /**
     * Initialize the root XML node [optional]
     * @param $version
     * @param $encoding
     * @param $format_output
     */
    public static function init($version = '1.0', $encoding = 'UTF-8', $element_name = 'element', $format_output = true) {
        self::$xml = new DomDocument($version, $encoding);
        self::$xml->formatOutput = $format_output;
        self::$encoding = $encoding;
        self::$element_name = $element_name;
    }
    /**
     * Convert an Array to XML
     * @param string $node_name - name of the root node to be converted
     * @param array $arr - aray to be converterd
     * @return DomDocument
     */
    public static function &createXML($arr=array(),$node_name) {
        $xml = self::getXMLRoot();
        $xml->appendChild(self::convert($arr,$node_name));
        self::$xml = null;    // clear the xml node in the class for 2nd time use.
        return $xml;
    }
    /**
     * Convert an Array to XML
     * @param string $node_name - name of the root node to be converted
     * @param array $arr - aray to be converterd
     * @return DOMNode
     */
    private static function &convert($arr=array(),$node_name) {
        //print_arr($node_name);
        $xml = self::getXMLRoot();
        $node = $xml->createElement($node_name);
        
        //convert nodes to attributes if their name starts with @
        if (is_array($arr)){
            foreach($arr as $key => $value) {

                if(substr($key,0,1) == '@'){
                    $clean_key = ltrim($key, '@'); //strip prefix
                    $arr['@attributes'][$clean_key] = $value;
                    unset($arr[$key]);
                }

            }
        }

        if(is_array($arr)){

            // get the attributes first.;
            if(isset($arr['@attributes'])) {
                foreach($arr['@attributes'] as $key => $value) {
                    if(!self::isValidTagName($key)) {
                        throw new Exception('[Array2XML] Illegal character in attribute name. attribute: '.$key.' in node: '.$node_name);
                    }
                    $node->setAttribute($key, self::bool2str($value));
                }
                unset($arr['@attributes']); //remove the key from the array once done.
            }
            // check if it has a value stored in @value, if yes store the value and return
            // else check if its directly stored as string
            if(isset($arr['@value'])) {
                $node->appendChild($xml->createTextNode(self::bool2str($arr['@value'])));
                unset($arr['@value']);    //remove the key from the array once done.
                //return from recursion, as a note with value cannot have child nodes.
                return $node;
            } else if(isset($arr['@cdata'])) {
                $node->appendChild($xml->createCDATASection(self::bool2str($arr['@cdata'])));
                unset($arr['@cdata']);    //remove the key from the array once done.
                //return from recursion, as a note with cdata cannot have child nodes.
                return $node;
            }
        }
        //create subnodes using recursion
        if(is_array($arr)){
            // recurse to get the node for that key
            foreach($arr as $key=>$value){
                
                if (is_int($key)){ //dealing with <0/>..<n/> issues
                    //$node->setAttribute('index', self::bool2str($key)); //TO FIX TO CHECK
                    $key = self::$element_name;
                }
                
                if(!self::isValidTagName($key)) {
                    throw new Exception('[Array2XML] Illegal character in tag name. tag: '.$key.' in node: '.$node_name);
                }
                
                if(is_array($value) && is_numeric(key($value))) {
                    // MORE THAN ONE NODE OF ITS KIND;
                    // if the new array is numeric index, means it is array of nodes of the same kind
                    // it should follow the parent key name
                    foreach($value as $k=>$v){
                        $node->appendChild(self::convert($v,$key));
                    }
                } else {
                    // ONLY ONE NODE OF ITS KIND
                    $node->appendChild(self::convert($value,$key));
                }
                unset($arr[$key]); //remove the key from the array once done.
            }
        }
        // after we are done with all the keys in the array (if it is one)
        // we check if it has any text value, if yes, append it.
        if(!is_array($arr)) {
            $node->appendChild($xml->createTextNode(self::bool2str($arr)));
        }
        return $node;
    }
    /*
     * Get the root XML node, if there isn't one, create it.
     */
    private static function getXMLRoot(){
        if(empty(self::$xml)) {
            self::init();
        }
        return self::$xml;
    }
    /*
     * Get string representation of boolean value
     */
    private static function bool2str($v){
        //convert boolean to text value.
        $v = $v === true ? 'true' : $v;
        $v = $v === false ? 'false' : $v;
        return $v;
    }
    /*
     * Check if the tag name or attribute name contains illegal characters
     * Ref: http://www.w3.org/TR/xml/#sec-common-syn
     */
    private static function isValidTagName($tag){
        $pattern = '/^[a-z_]+[a-z0-9\:\-\.\_]*[^:]*$/i';
        return preg_match($pattern, $tag, $matches) && $matches[0] == $tag;
    }
}