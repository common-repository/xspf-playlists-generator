<?php

abstract class Spiff_Station_Datas{
    protected $station;
    public function __construct($station){ //Formater $formater
      $this->station = $station;
    }
    
    public function populate(){
        /*override me*/
    }
    
}

class Spiff_Station_Datas_Remote extends Spiff_Station_Datas{
    
    public $page_node;
    public $track_nodes;
    public $response_type;
    
    var $querypath_options = array(
        'omit_xml_declaration'      => true,
        'ignore_parser_warnings'    => true,
        'convert_from_encoding'     => 'auto',
        'convert_to_encoding'       => 'ISO-8859-1'
    );
    
    var $remote_get_options = array(
        'User-Agent'        => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML => like Gecko) Iron/31.0.1700.0 Chrome/31.0.1700.0'
    );
    
    public function __construct($playlist){
        parent::__construct($playlist);
    }
    
    public function populate(){
        $this->populate_track_nodes(); //must be before the selectors check as requesting the response can modify get_options

        $empty_artist_selector = ( !$this->station->get_options(array('selectors','track_artist')) && !$this->station->get_options(array('selectors_regex','track_artist')) );
        $empty_title_selector = ( !$this->station->get_options(array('selectors','track_title')) && !$this->station->get_options(array('selectors_regex','track_title')) );

        if ($empty_artist_selector){

            $error = new WP_Error( 'empty_artist_selector', __('You need to define an Artist Selector (css selector, regex, or both)','spiff') );
            if ( is_admin() ){
                    add_settings_error( 'wizard-step-track_details', 'empty_artist_selector', $error->get_error_message(),'error inline' );
            }
            if (!$this->station->is_wizard){
                return false;
            }
        }
        
        if ($empty_title_selector){
            $error = new WP_Error( 'empty_title_selector', __('You need to define a Title Selector (css selector, regex, or both)','spiff') );
            if ( is_admin() ){
                    add_settings_error( 'wizard-step-track_details', 'empty_title_selector', $error->get_error_message(),'error inline' );
            }
            if (!$this->station->is_wizard){
                return false;
            }
        }

        if ( !$this->track_nodes ) return false;

        // Get all tracks
        $tracks_arr = array();
        foreach($this->track_nodes as $key=>$track_node) {

            $args = array(
                'artist'    => $this->get_track_node_content($track_node,'artist'),
                'title'     => $this->get_track_node_content($track_node,'title'),
                'album'     => $this->get_track_node_content($track_node,'album'),
                'location'  => $this->get_track_node_content($track_node,'location'),
                'image'     => $this->get_track_node_content($track_node,'image')
            );

            $tracks_arr[] = $args;

        }

        $this->station->tracklist->array_load( $tracks_arr );
        $this->station->response_time = current_time( 'timestamp' );

        //sort
        if ($this->station->get_options('tracks_order') == 'asc'){
            $this->station->tracklist->reverse();
        }

        //lookup
        if ( ($this->station->get_options('musicbrainz')) && ( !$this->station->is_wizard ) ){
            $this->station->tracklist->musicbrainz();
        }
        
        do_action( 'spiff_get_remote_tracks',$this->station );

    }
    
    function populate_track_nodes(){
        
        $items = array();
        $error = null;

        if ( !$this->page_node ){
            $this->populate_feed();
        }
        

        if ( $this->page_node ){

            $selector = $this->station->get_options( array('selectors','tracks') );
            if ( !$selector ) return;
       
            //QueryPath
            try{

                $track_nodes = qp( $this->page_node, null, $this->querypath_options  )->find($selector);

                if ($track_nodes->length == 0){
                    $error = new WP_Error( 'no_track_nodes', __('Either the tracks selector is invalid, or there is actually no tracks in the playlist – you may perhaps try again later.','spiff') );
                }

            }catch(Exception $e){
                $error = new WP_Error( 'querypath', sprintf(__('QueryPath Error [%1$s] : %2$s','spiff'),$e->getCode(),$e->getMessage()) );
            }
     
            if ( $error ) {
                if ( is_admin() ){
                        add_settings_error( 'wizard-step-tracks_selector', 'no_track_nodes', $error->get_error_message(),'error inline' );
                }
            }else{

                if ($track_nodes->length > 0){
                    $this->track_nodes = $track_nodes;
                }
                

            }

        }

    }
    
    function populate_feed(){
        $error = $page = $page_node = null;

        if ( !$this->station->is_station_ready() ) return false;
        
        $feed_url = $this->station->get_feed_url_redirect();

        $remote_args = apply_filters('spiff_get_response_args',$this->remote_get_options,$feed_url );
        $response = wp_remote_get( $feed_url, $remote_args );

        if ( !is_wp_error($response) ){
            
            $this->populate_feed_type($response);
            
            $response_code = wp_remote_retrieve_response_code( $response );

            if ($response_code && $response_code != 200){

                $response_message = wp_remote_retrieve_response_message( $response );
                $error = new WP_Error( 'http_response_code', sprintf('[%1$s] %2$s',$response_code,$response_message ) );
                
            }else{

                $page = wp_remote_retrieve_body( $response ); 

                if ( !is_wp_error($page) ){

                    $page_node = $this->get_page_node($page);

                    if ( is_wp_error($page_node ) ){
                        $error = $page_node;
                    }
                    
                }

            }

        }else{
            $error = $response;
        }

        if ($error){
            if ( is_admin() ){
                $error_msg = sprintf(__('Error while trying to reach %1$s : %2$s','spiff'),'<em>'.$feed_url.'</em>','<strong>'.$error->get_error_message().'</strong>' );
                add_settings_error( 'wizard-header', 'no_response', $error_msg,'error inline' );
            }
        }else{
            
            $this->page_node = $page_node;

            if ( is_admin() ){
                add_settings_error( 'wizard-step-base-response', 'grabbed_from', $feed_url,'updated inline' );
            }
        }

    }
    
    /**
     * Get response content-type, filtered by us
     * @return type
     */
    
    function populate_feed_type($response){

        $type = wp_remote_retrieve_header( $response, 'content-type' );

        //is JSON
        if ( substr(trim(wp_remote_retrieve_body( $response )), 0, 1) === '{' ){ // is JSON
            $type = 'application/json';
        }

        //remove charset if any
        $split = explode(';',$type);

        $this->response_type = $split[0];

    }
    
    function get_page_node($content){
        
        $content = $this->station->preset_filter_page_content_pre($content);

        //$content = apply_filters('xspf_get_page_node_pre',$content,$this->station);

        $error = null;
        
        libxml_use_internal_errors(true);
        
        switch ($this->response_type){
            
            case 'application/xspf+xml':
            case 'text/xspf+xml':
            case 'application/xml':
            case 'text/xml':
                
                //check for XSPF
                if ($this->response_type=='application/xml' || $this->response_type=='text/xml'){
                    //QueryPath
                    //should be "trackList" instead of "tracklist", but it does not work.
                    try{
                        if ( qp( $content, 'playlist tracklist track', $this->querypath_options )->length > 0 ){
                            $this->response_type = 'text/xspf+xml';
                        }
                    }catch(Exception $e){

                    }
                }

                $xml = simplexml_load_string($content);
                
                //do not set the $error var as this would abord the process.
                //maybe libxml will output error but still have it working.
                $xml_errors = libxml_get_errors();
                foreach( $xml_errors as $xml_error_obj ) {
                    $xml_error = new WP_Error( 'simplexml', sprintf(__('simplexml Error [%1$s] : %2$s','spiff'),$xml_error_obj->code,$xml_error_obj->message) );
                    
                    if (is_admin()){
                        add_settings_error( 'wizard-step-base', 'simplexml', $xml_error->get_error_message(),'error inline' );
                    }
                    
                }

                //QueryPath
                try{
                    $result = qp( $xml, null, $this->querypath_options );
                }catch(Exception $e){
                    $error = new WP_Error( 'querypath', sprintf(__('QueryPath Error [%1$s] : %2$s','spiff'),$e->getCode(),$e->getMessage()) );
                }

            break;

            case 'application/json':

                try{
                    $data = json_decode($content, true);

                    $dom = Array2XML::createXML($data,'root','element');
                    $xml = $dom->saveXML($dom);
                    $this->response_type = 'text/xml';
                    
                    if ( is_admin() ){
                        add_settings_error( 'wizard-row-feed_content_type', 'json2xml', __("The json input has been converted to XML.",'spiff'),'updated inline');
                    }

                    $result = $this->get_page_node($xml);

                }catch(Exception $e){
                    $error = WP_Error( 'XML2Array', sprintf(__('XML2Array Error [%1$s] : %2$s','spiff'),$e->getCode(),$e->getMessage()) );
                }

            break;

            case 'text/html': 

                //QueryPath
                try{
                    $result = htmlqp( $content, null, $this->querypath_options );
                }catch(Exception $e){
                    $error = WP_Error( 'querypath', sprintf(__('QueryPath Error [%1$s] : %2$s','spiff'),$e->getCode(),$e->getMessage()) );
                }

            break;
        
            //TO FIX seems to put a wrapper around our content + bad content type
        
            default: //text/plain
                //QueryPath
                try{
                    $result = qp( $content, 'body', $this->querypath_options );
                }catch(Exception $e){
                    $error = WP_Error( 'querypath', sprintf(__('QueryPath Error [%1$s] : %2$s','spiff'),$e->getCode(),$e->getMessage()) );
                }
                
            break;
        
        }
        
        libxml_clear_errors();
        
        if ( !$error && (!$result || ($result->length == 0)) ){
            $error = new WP_Error( 'querypath', __('We were unable to populate the page node') );
        }
        
        if ($error){
            if (is_admin()){
                add_settings_error( 'wizard-step-base', 'no_page_html', $error->get_error_message(),'error inline' );
            }
            
            return false;
        }

        return $result;
        
    }
    
    function get_track_node_content($track_node,$slug){
        
        $node = $track_node;
        $result = null;
        $pattern = null;
        $string = null;
        
        $selector_slug = 'track_'.$slug;
        $selector_css = $this->station->get_options(array('selectors',$selector_slug));
        $selector_regex = $this->station->get_options(array('selectors_regex',$selector_slug));

        //abord
        if ( !$selector_css && !$selector_regex ){
            return false;
        }

        //QueryPath
        try{
            if ($selector_css) $node = $track_node->find($selector_css);
            $string = $node->innerHTML();
            
        }catch(Exception $e){
            return new WP_Error( 'querypath', sprintf(__('QueryPath Error [%1$s] : %2$s','spiff'),$e->getCode(),$e->getMessage()) );
        }
        
        if (!$string = trim($string)) return;

        if( ($slug == 'image' ) || ($slug == 'location' ) ){

            if ($url = $node->attr('src')){ //is an image or audio tag
                $string = $url;
            }elseif ($url = $node->attr('href')){ //is a link
                $string = $url;
            }

            if (filter_var((string)$string, FILTER_VALIDATE_URL) === false) {
                $string = '';
            }
            
        }

        //CDATA fix
        $string = spiff_stations_sanitize_cdata_string($string);

        //regex pattern
        if ( $selector_regex ){
            $pattern = $selector_regex;
        }

        if(!$pattern) {
            $result = $string;
        }else{
            //flags
            $flags = 'm';
            //add delimiters
            $pattern = '~'.$pattern.'~'.$flags;
            //add beginning slash
            //$pattern = strrev($pattern); //reverse string
            //$pattern = trailingslashit($pattern);
            //$pattern = strrev($pattern);
            
            preg_match($pattern, $string, $matches);
            if (isset($matches[1])){
                $result = strip_tags($matches[1]);
            }
                
        }
        
        return $result;
    }
    
    function feed_is_xspf(){

        if ( !$this->response_type ) return null;

        switch ($this->response_type){

            case 'application/xspf+xml':
            case 'text/xspf+xml':
                return true;
            break;
        }

        return false;

    }
    
}

abstract class Spiff_Station_Datas_Cache extends Spiff_Station_Datas{
    
    protected $cache_dir;
    protected $cache_url;
    protected $cache;

    public function __construct($playlist){
        parent::__construct($playlist);
        $this->cache_dir  = WP_CONTENT_DIR . '/spiff-cache';
        $this->cache_url  = WP_CONTENT_URL . '/spiff-cache';
    }
    
    static function install(){

        return; //we have disabled write cache files for the moment, keep the function if needed later.
        
        if(!is_dir(self::$cache_dir)){
          return mkdir(self::$cache_dir);
        }
    }
    
    static function is_installed(){
        
        return true; //we have disabled write cache files for the moment, keep the function if needed later.
        
        return is_dir(self::$cache_dir);
    }
    
    function populate(){ 
        
        if ( !self::is_installed() ) return false;
        if ( !$cache = $this->get_cache() ) return false;

        if ( is_admin() ){
            add_settings_error( 'wizard-header', 'cache_tracks_loaded', sprintf(__('%1$s tracks were found in cache (at %2$s); but are ignored within the wizard.','spiff'),count($cache['tracks']),gmdate(DATE_ISO8601,$cache['response_time'])),'updated inline' );
        }

        if ( !$this->station->is_wizard ){
            $this->station->response_time = $cache['response_time'];
            $this->station->tracklist->array_load($cache['tracks']);
            $this->station->title = $cache['title'];
            $this->station->author = $cache['author'];
        }

    }
    
    /**
     * Format cache for export
     * @return type
     */
    
    function format_cache(){

        return array(
            'response_time'     => current_time( 'timestamp' ),
            'title'             => $this->station->title,
            'author'            => $this->station->author,
            'tracks'            => $this->station->tracklist->array_export(),
        );

    }
    

}

class Spiff_Station_Datas_Cache_Persistent extends Spiff_Station_Datas_Cache{
    
    static $meta_bake = 'spiff_tracks_baked'; //cache key name
  
    public function __construct($playlist){
        parent::__construct($playlist);
    }
    
    public function get_cache(){
        return get_post_meta($this->station->post_id, self::$meta_bake, true);
    }
    
    function set_cache(){
        $cache = $this->format_cache();
        update_post_meta($this->station->post_id, $this->meta_bake, $cache);
    }
    
    public function delete(){
        delete_post_meta($this->station->post_id, $this->meta_bake);
    }

}

class Spiff_Station_Datas_Cache_Temporary extends Spiff_Station_Datas_Cache{
    
    protected $duration;
    protected $id;
    protected $transient_name;
  
    public function __construct($playlist){
        parent::__construct($playlist);

        if ( $this->station->is_station_ready() ){
            $url = $this->station->get_feed_url_redirect();
            $this->id = md5( $url );
            $this->transient_name = 'spiff_'.$this->id; //WARNING this must be 40 characters max !  md5 returns 32 chars.
        }
        
        $this->duration = $this->station->get_options('cache_duration');
        
    }
    
    public function get_cache(){
        if ( !$this->duration ){
            if ( is_admin() ){
                add_settings_error( 'wizard-header', 'cache_disabled', __("The cache is currently disabled.  Once you're happy with your settings, it is recommanded to enable it (see the Station Settings tab).",'spiff'),'updated inline' );
            }
            return false;
        }

        return get_transient( $this->transient_name );

    }
    
    function set_cache(){

        if ( !$this->duration  ) return;

        $cache = $this->format_cache();
        set_transient( $this->transient_name, $cache, $this->duration );

    }

    function delete(){
        if ($this->transient_name){
            delete_transient( $this->transient_name );
        }
    }

}