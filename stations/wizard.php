<?php

/**
 * Wizard class.  Used to build a playlist.  Actually only in the backend, will maybe be extended to the frontend someday.
 */

class Spiff_Station_Wizard {
    
    var $station = null;

    function __construct() {
        self::setup_globals();
        self::includes();
        self::setup_actions();
    }
    
    function setup_globals() {
    }

    function includes(){
        
    }

    function setup_actions(){

        
        add_action( 'admin_init',  array( $this, 'wizard_settings_init' ) );
        add_action( 'admin_init', array( $this, 'reduce_settings_errors' ) );

        add_action( 'admin_enqueue_scripts',  array( $this, 'scripts_styles' ) );

        //TO FIX should run BEFORE wizard_settings_init
        add_action( 'save_post',  array( $this, 'save' ) );
        
        add_action( 'add_meta_boxes',  array( $this, 'register_meta_boxes' ) );

    }

    
    function scripts_styles(){
        
        //TO FIX makes the wizard extremly slow
        /*
        wp_enqueue_style('prismjs','http://prismjs.com/themes/prism.css');
        wp_enqueue_script('prismjs','http://prismjs.com/prism.js');
        */
        
        wp_enqueue_style( 'spiff-stations-wizard', spiff()->plugin_url .'_inc/css/spiff-stations-wizard.css', array(), spiff()->version );
        //wp_enqueue_script( 'spiff-stations-wizard', spiff()->plugin_url .'_inc/js/spiff-stations-wizard.js', array( 'jquery', 'jquery-ui-tabs' ), spiff()->version );
    }

    function register_meta_boxes(){
        add_meta_box( 'spiff-station-wizard-metabox', __('Station Wizard','spiff'), array(&$this,'wizard_metabox'), spiff_stations()->station_post_type,'normal','high');
    }
    
    function dummy_sanitize( $input ){
        /*
         * Do nothing here.  We use our own hooked function save_step() at init, this one is not necessary.
         */
        return false;
    }
    
    function can_show_step($slug){

        switch ($slug){
            case 'base_urls':
                if ( $this->station->get_options('cache_persistent') ) break;
                return true;
            break;
            case 'tracks_selector':
                
                if ( $this->station->get_options('cache_persistent') ) break;
                
                if ( !$this->station->datas_remote ) break;
                if ( !$this->station->datas_remote->page_node ) break;
                
                return true;
            break;
            case 'variables':
                if ( !$this->station->post_id ) break;
                if ( $this->station->get_options('cache_persistent') ) break;
                return true;
                
            break;
            case 'track_details':
                
                if ( $this->station->get_options('cache_persistent') ) break;
                
                if ( !$this->can_show_step('tracks_selector') ) break;
                
                //$selectors = $this->station->get_options('selectors');
                //if ( !$selectors['tracks'] ) break;
                //if ( !$this->station->datas_remote->track_nodes ) break;
                
                return true;
                
            break;
            case 'tracklist':
                
                $selectors = $this->station->get_options('selectors');

                if ( !isset($selectors['tracks']) ) break;
                if ( !isset($selectors['track_title']) && !isset($selectors['track_artist']) ) break;

                return true;
                
            break;
            
            case 'playlist_options':
                if ( !$this->station->post_id ) break;
                return true;
            break;
            
        }
        return false;
    }
    
    function wizard_settings_init(){

        if ( !spiff_stations_is_wizard() ) return;

        $this->station = spiff_stations_get();
        $this->station->populate_tracklist(); //we need the tracklist to be populated before registering options

        register_setting(
             'spiff', // Option group
             'spiff_stations_wizard', // Option name
             array( $this, 'dummy_sanitize' ) // Sanitize
         );
        
        if ($this->can_show_step('base_urls')){
            
            add_settings_section(
                 'settings_general', // ID
                 __('Base URLs','spiff'), // Title
                 array( $this, 'section_general_desc' ), // Callback
                 'spiff-station-wizard-step-base' // Page
            );

            //Allow to unfreeze playlist as other tabs are not available if frozen

            add_settings_field(
                'feed_url', 
                __('Tracks feed URL','spiff'), 
                array( $this, 'feed_url_callback' ), 
                'spiff-station-wizard-step-base', 
                'settings_general'
            );

            add_settings_field(
                'website_url', 
                __('Website','spiff'), 
                array( $this, 'website_url_callback' ), 
                'spiff-station-wizard-step-base', // Page
                'settings_general' // Section
            ); 
            
            if ($this->can_show_step('tracks_selector')){

                add_settings_field(
                    'playlist_content_type', 
                    __('Feed Content Type','spiff'), 
                    array( $this, 'feed_content_type_callback' ), 
                    'spiff-station-wizard-step-base', 
                    'settings_general'
                );

                add_settings_field(
                    'playlist_raw_content', 
                    __('Feed Raw Content','spiff'), 
                    array( $this, 'feed_raw_content_callback' ), 
                    'spiff-station-wizard-step-base', 
                    'settings_general'
                );
                
            }

        }

        if ($this->can_show_step('variables')){

            add_settings_section(
                 'settings_playlist_variables', // ID
                 __('Variables','spiff'), // Title
                 array( $this, 'section_variables_desc' ), // Callback
                 'spiff-station-wizard-step-playlist-variables' // Page
            );
            
            if ( $this->station->variables->get() ){
                
                add_settings_field(
                    'playlist_variables', 
                    __('Variables','spiff'), 
                    array( $this, 'variables_callback' ), 
                    'spiff-station-wizard-step-playlist-variables', 
                    'settings_playlist_variables'
                );
                
            }
            
            add_settings_section(
                 'settings_playlist_dynamic_title', // ID
                 __('Dynamic title','spiff'), // Title
                 array( $this, 'section_dynamic_title_desc' ), // Callback
                 'spiff-station-wizard-step-playlist-dynamic-title' // Page
            );

            add_settings_field(
                'playlist_dynamic_title', 
                __('Dynamic Title','spiff'), 
                array( $this, 'dynamic_title_callback' ), 
                'spiff-station-wizard-step-playlist-dynamic-title', 
                'settings_playlist_dynamic_title'
            );
   
        }

        if ($this->can_show_step('tracks_selector')){


            add_settings_section(
                'playlist_track_selector',
                __('Tracks Selector','spiff'),
                array( $this, 'section_tracks_selector_desc' ),
                'spiff-station-wizard-step-tracks-selector'
            );

            add_settings_field(
                'playlist_track_selector', 
                __('Tracks Selector','spiff'), 
                array( $this, 'selector_tracks_callback' ), 
                'spiff-station-wizard-step-tracks-selector', 
                'playlist_track_selector'
            );

            add_settings_field(
                'tracklist_raw_content', 
                __('Tracks Raw Content','spiff'), 
                array( $this, 'tracklist_raw_content_callback' ), 
                'spiff-station-wizard-step-tracks-selector', 
                'playlist_track_selector'
            );

        }
        
        if ($this->can_show_step('track_details')){

            add_settings_section(
                'track_details',
                __('Tracks Selector','spiff'),
                array( $this, 'section_tracks_selector_desc' ),
                'spiff-station-wizard-step-track-details'
            );


            add_settings_section(
                'track_details',
                __('Track Details','spiff'),
                array( $this, 'section_track_details_desc' ),
                'spiff-station-wizard-step-track-details'
            );

            add_settings_field(
                'track_artist_selector', 
                __('Artist Selector','spiff').'* '.$this->regex_link(),
                array( $this, 'track_artist_selector_callback' ), 
                'spiff-station-wizard-step-track-details', 
                'track_details'
            );

            add_settings_field(
                'track_title_selector', 
                __('Title Selector','spiff').'* '.$this->regex_link(), 
                array( $this, 'track_title_selector_callback' ), 
                'spiff-station-wizard-step-track-details', 
                'track_details'
            );

            add_settings_field(
                'track_album_selector', 
                __('Album Selector','spiff').' '.$this->regex_link(), 
                array( $this, 'track_album_selector_callback' ), 
                'spiff-station-wizard-step-track-details', 
                'track_details'
            );
            
            add_settings_field(
                'track_location_selector', 
                __('File Selector','spiff').' '.$this->regex_link(), 
                array( $this, 'track_location_selector_callback' ), 
                'spiff-station-wizard-step-track-details', 
                'track_details'
            );

            add_settings_field(
                'track_image_selector', 
                __('Image Selector','spiff'), 
                array( $this, 'track_image_selector_callback' ), 
                'spiff-station-wizard-step-track-details', 
                'track_details'
            );
            
        }
        
        //Found Tracks
        
        if ($this->can_show_step('tracklist')){
            
            $tracks_count = 0;
            if ( $this->station->tracklist->tracks ){
                $tracks_count = count( $this->station->tracklist->tracks );
            }

            add_settings_section(
                'found_tracks',
                sprintf( __('Found Tracks : %d','spiff'),$tracks_count ),
                array( $this, 'section_found_tracks_desc' ),
                'spiff-station-wizard-step-tracklist'
            );
            
        }
        
        if ($this->can_show_step('playlist_options')){

            //Stations Options
            add_settings_section(
                'playlist_options',
                __('Stations Options','spiff'),
                array( $this, 'section_station_options_desc' ),
                'spiff-station-wizard-step-playlist-options'
            );
            
            if ( !$this->station->get_options('cache_persistent') ){
  
                add_settings_field(
                    'cache_duration', 
                    __('Enable Cache','spiff'), 
                    array( $this, 'cache_callback' ), 
                    'spiff-station-wizard-step-playlist-options', 
                    'playlist_options'
                );

                add_settings_field(
                    'enable_musicbrainz', 
                    __('Use Musicbrainz','spiff'), 
                    array( $this, 'musicbrainz_callback' ), 
                    'spiff-station-wizard-step-playlist-options', 
                    'playlist_options'
                );

                add_settings_field(
                    'tracks_order', 
                    __('Tracks Order','spiff'), 
                    array( $this, 'tracks_order_callback' ), 
                    'spiff-station-wizard-step-playlist-options', 
                    'playlist_options'
                );
                
            }

            add_settings_field(
                'cache_persistent', 
                __('Persistent cache','spiff'), 
                array( $this, 'cache_persistent_callback' ), 
                'spiff-station-wizard-step-playlist-options', 
                'playlist_options'
            );
            
        }

    }
    
    /**
     * Removes duplicate settings errors (based on their messages)
     * @global type $wp_settings_errors
     */
    
    function reduce_settings_errors(){
        //remove duplicates errors
        global $wp_settings_errors;

        if (empty($wp_settings_errors)) return;
        $wp_settings_errors = array_values(array_unique($wp_settings_errors, SORT_REGULAR));

    }
    
    function regex_link(){
        return sprintf(
            '<a href="#" title="%1$s" class="regex-link">[...^]</a>',
            __('Use Regular Expression','spiff')
        );
    }
    
    function css_selector_block($field_name){
        
        ?>
        <div class="wizard-selector">
            <?php


            //css
            $option = $this->station->get_options(array('selectors',$field_name));
            $option = ( $option ? htmlentities($option) : null);
            $is_preset = $this->station->is_preset_value(array('selectors',$field_name));

            //build info

            switch($field_name){
                    case 'tracks':
                        $info = sprintf(
                            __('Enter a <a href="%1$s" target="_blank">jQuery selector</a> to target each track item from the tracklist page, for example: %2$s.','spiff'),
                            'http://www.w3schools.com/jquery/jquery_ref_selectors.asp',
                            '<code>#content #tracklist .track</code>'
                        );
                    break;
                    case 'track_artist':
                        $info = sprintf(
                            __('eg. %s','spiff'),
                            '<code>h4 .artist strong</code>'
                        );
                    break;
                    case 'track_title':
                        $info = sprintf(
                            __('eg. %1$s','spiff'),
                            '<code>span.track</code>'
                        );
                    break;
                    case 'track_album':
                        $info = sprintf(
                            __('eg. %1$s','spiff'),
                            '<code>span.album</code>'
                        );
                    break;
                    case 'track_image':
                        $info = sprintf(
                            __('eg. %1$s','spiff'),
                            '<code>.album-art img</code> '.__('or an url','spiff')
                        );
                    break;
                    case 'track_location':
                        $info = sprintf(
                            __('eg. %1$s','spiff'),
                            '<code>audio</code> '.__('or an url','spiff')
                        );
                    break;
            }
            
            if ($field_name!='tracks'){
                echo $this->get_track_detail_selector_prefix();
            }
            
            printf(
                '<input type="text" name="%1$s[selectors][%2$s]" value="%3$s" %4$s/><span class="wizard-field-desc">%5$s</span>',
                'spiff_stations_wizard',
                $field_name,
                $option,
                spiff_readonly( $is_preset , true, false),
                $info
            );

            //regex
            //uses a table so the style matches with the global form (WP-core styling)
            ?>
            <table class="form-table regex-row">
                <tbody>
                    <tr>
                        <th scope="row"><?php _e('Regex pattern','spiff');?></th>
                        <td>        
                            <div>
                                <?php

                                //regex
                                $option_regex = $this->station->get_options(array('selectors_regex',$field_name));
                                $option_regex = ( $option_regex ? htmlentities($option_regex) : null);
                                $is_preset_regex = $this->station->is_preset_value(array('selectors_regex',$field_name));

                                printf(
                                    '<span class="regex-field"><input class="regex" name="%1$s[selectors_regex][%2$s]" type="text" value="%3$s" %4$s/></span>',
                                    'spiff_stations_wizard',
                                    $field_name,
                                    $option_regex,
                                    spiff_readonly( $is_preset_regex , true, false)
                                );
                                ?>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php
        
    }
    
    function section_general_desc(){
        settings_errors('wizard-step-base');
        
        _e('Those are the settings required to reach the remote feed.','spiff');
        echo"<br/>";
        
        printf(
            __('You can eventually use variables in your URLs; using %1$s. Eg %2$s.  This will bring a new tab.','spiff'),
            '<em>%variable%</em>',
            '<code>https://soundcloud.com/%username%/likes</code>'
        );
        
    }
    
    function website_url_callback(){

        $option = $this->station->get_options('website_url');
        $is_preset = $this->station->is_preset_value('website_url');
        printf(
            '<input type="text" name="%1$s[website_url]" value="%2$s" %3$s style="min-width:100%%" /><p class="wizard-field-desc">%4$s</p>',
            'spiff_stations_wizard',
            $option,
            spiff_readonly( $is_preset , true, false),
            __('Optional.  If you want the station source link (used as info in the XSPF) to refer to another URL than the feed.','spiff')
        );
        
    }
    
    function feed_url_callback(){
        
        $option = $this->station->get_options('feed_url');
        
        printf(
            '<input type="text" name="%1$s[feed_url]" value="%2$s" style="min-width:100%%"/><p class="wizard-field-desc">%3$s</p>',
            'spiff_stations_wizard',
            $option,
            __('Where should we get the data from ?','spiff')
        );

    }
    
    function feed_content_type_callback(){
        
        settings_errors('wizard-row-feed_content_type');

        $output = "—";

        if ( $this->station->datas_remote->response_type ){
            $output = $this->station->datas_remote->response_type;
        }
        
        echo $output;

    }
    
    function section_variables_desc(){
        settings_errors('wizard-step-playlist-service');
        ?>
        <p><?php _e('Some stations presets do populate variables that are used to reach the feed (eg. to communicate with an API). It will be listed here.','spiff');?></p>
        <p><?php _e('For some of them, a "Form" checkbox is available.  Checking that input will make the variable dynamic!  Eg. If you create a station here with a Last.FM user URL, you can check the "username" variable.  On the station page, <strong>a form will then prompt the visitor to set the "username" value</strong>, making that station work with any Last.FM username.','spiff');?></p>
        <p>
        <?php
        _e('You can also <strong>define new variable</strong> directly within this wizard, by using <em>%patterns%</em> inside the URLs defined in the first tab.','spiff');
        echo '<br/>'.sprintf(__('Eg: %s','spiff'),'<code>http://www.my-url.fm/?station=<em>%station_id%</em></code>');
        ?>
        </p>
        <p>
            <?php printf(__('<strong>API settings</strong> (if needed) should be defined in the %s.','spiff'),'<a href="'.admin_url('edit.php?post_type='.spiff_stations()->station_post_type.'&page=spiff-options').'">'.__('Options').'</a>');?>
        </p>
       <?php
    }
    
    function variables_callback(){

        $variables = $this->station->variables->get();

        ?>
        <table id="spiff-urls-variables">
          <tr>
            <th><?php _e('Slug','spiff');?></th>
            <th><?php _e('Name','spiff');?></th>
            <th><?php _e('Value','spiff');?></th>
            <th><?php _e('Form','spiff');?></th>
          </tr>
          <?php

            foreach ((array)$variables as $variable){

                $name_field = $desc_field = $value_field = null;
                $has_value_preset = $has_name_preset = $has_desc_preset = null;

                //value
                if ($variable->store_value){
                    //$has_value_preset = (bool)$this->station->get_options( array('variables',$slug,'value'),true );
                    $value_field = sprintf(
                        '<input type="text" name="%1$s[variables][%2$s][value]" value="%3$s" %4$s/>',
                        'spiff_stations_wizard',
                        $variable->slug,
                        $variable->value,
                        spiff_readonly( $has_value_preset , true, false)
                    );
                }else{
                    if (!$value = $variable->value){
                        $value = '&ndash;';
                    }else{
                        $value_hidden_field = sprintf(
                            '<input type="hidden" name="%1$s[variables][%2$s][value]" value="%3$s"/>',
                            'spiff_stations_wizard',
                            $variable->slug,
                            $variable->value
                        );
                        $value.=$value_hidden_field;
                    }
                    $value_field = $value;
                }

                //name
                if ($variable->can_edit_name){
                    //$has_name_preset = (bool)$this->station->get_options( array('variables',$slug,'name'),true );
                    $name_field = sprintf(
                        '<input type="text" name="%1$s[variables][%2$s][name]" value="%3$s" %4$s/>',
                        'spiff_stations_wizard',
                        $variable->slug,
                        $variable->name,
                        spiff_readonly( $has_name_preset , true, false)
                    );
                }else{
                    $name_field = $variable->name;
                }

                //dynamic
                $dynamic_field = sprintf(
                    '<input type="checkbox" name="%1$s[variables][%2$s][show_form]" value="on" %3$s %4$s/>',
                    'spiff_stations_wizard',
                    $variable->slug,
                    checked( (bool)$variable->show_form, true, false),
                    disabled( (bool)$variable->private , true, false)
                );

                printf(
                    '<tr><td><em>%1$s</em></td><td>%2$s</td><td>%3$s</td><td>%4$s</td>',
                    $variable->slug,
                    $name_field,
                    $value_field,
                    $dynamic_field
                );
            }
          ?>
        </table>
        <?php
    }
    
    function section_dynamic_title_desc(){
        $variables = $this->station->variables->get_for_form();
        $available = array();
        foreach ($variables as $var){
            $available[] = sprintf('<code>%s</code>','%'.$var->slug.'%');
        }
        $patterns_str = _n( 'this pattern:', 'those patterns:', count($available), 'spiff' ).implode($available,', ');
        ?>
        <p><?php _e("If a playlist has variables and that the visitor can set their values frontend (some <em>Form</em> inputs above are checked), the station becomes <strong>dynamic</strong>.",'spiff');?></p>
        <p>
            <?php _e("A <strong>dynamic station</strong> can have a dynamic title that will take into acount the user's values and override the default title.",'spiff');?><br/>
            <?php 
            if ($available){
                printf(__("Just set a new title below, where you can use %s",'spiff'),$patterns_str);
            }
            ?>
        </p>
        <?php
    }
    
    function dynamic_title_callback(){
        $option = $this->station->get_options('dynamic_title');
        $has_variables = ( $this->station->variables->get_for_form() ) ? true : false;

        printf(
            '<input type="text" name="%1$s[dynamic_title]" value="%2$s" %3$s style="min-width:100%%"/>',
            'spiff_stations_wizard',
            $option,
            spiff_readonly( $has_variables , false, false)
        );
    }
    
    function feed_raw_content_callback(){
        
        settings_errors('wizard-step-base-response');
        
        
        $output = "—";

        if ( $page_node = $this->station->datas_remote->page_node ){

            $content = $page_node->html();
            
            //force UTF8
            $content = iconv("ISO-8859-1", "UTF-8", $content); //ISO-8859-1 is from QueryPath
            
            $content = esc_html($content);
            $output = '<pre class="spiff-raw"><code class="language-markup">'.$content.'</code></pre>';

        }
        
        echo $output;
        

    }
    
    function section_tracks_selector_desc(){
        settings_errors('wizard-step-tracks_selector');
    }
    
    function selector_tracks_callback(){  
        $this->css_selector_block('tracks');
    }
    
    function tracklist_raw_content_callback(){

        $output = "—"; //none
        $tracks_output = array();

        foreach ($this->station->datas_remote->track_nodes as $node){

            $node_content = $node->innerHTML();
            
            //force UTF8
            $node_content = iconv("ISO-8859-1", "UTF-8", $node_content); //ISO-8859-1 is from QueryPath
            
            $tracks_output[] = sprintf( '<pre class="spiff-raw xspf-track-raw"><code class="language-markup">%s</code></pre>',esc_html($node_content) );

        }
        if ($tracks_output){
            $output = sprintf('<div id="spiff-station-tracks-raw">%s</div>',implode(PHP_EOL,$tracks_output));
        }
        
        echo $output;

    }

    function section_track_details_desc(){
        
        settings_errors('wizard-step-track_details');
        
        _e('Enter a <a href="http://www.w3schools.com/jquery/jquery_ref_selectors.asp" target="_blank">jQuery selectors</a> to extract the artist, title, album (optional) and image (optional) for each track.','spiff');
        echo"<br/>";
        _e('Advanced users can eventually use <a href="http://regex101.com/" target="_blank">regular expressions</a> to refine your matches, using the links <strong>[...^]</strong>.','spiff');
    }
    
    function get_track_detail_selector_prefix(){
        
        
        $selectors = $this->station->get_options('selectors');

        if (!$selectors['tracks']) return;
        return sprintf(
            '<span class="tracks-selector-prefix">%1$s</span>',
            $selectors['tracks']
        );
    }

    function track_artist_selector_callback(){
        $this->css_selector_block('track_artist');
    }

    function track_title_selector_callback(){
        $this->css_selector_block('track_title');
    }

    function track_album_selector_callback(){
        $this->css_selector_block('track_album');
    }
    
    function track_image_selector_callback(){
        $this->css_selector_block('track_image');
    }
    
    function track_location_selector_callback(){
        $this->css_selector_block('track_location');
    }

    
    function section_station_options_desc(){
        settings_errors('spiff-station-wizard-step-playlist-options');
    }
    
    function section_found_tracks_desc(){
        settings_errors('wizard-step-found_tracks');        
        $this->station->tracklist->display();

    }
    
    function cache_callback(){
        $option = $this->station->get_options('cache_duration');
        $is_preset = $this->station->is_preset_value('cache_duration');
        $is_disabled = null;

        printf(
            '<input type="number" name="%1$s[cache_duration]" size="4" min="0" value="%2$s" %3$s %4$s/><span class="wizard-field-desc">%5$s</span>',
            'spiff_stations_wizard',
            $option,
            disabled( $is_disabled , true, false),
            spiff_readonly( $is_preset , true, false),
            __('Time the remote page & tracks should be cached (in seconds).','spiff').'  <small>'.__('While using this wizard, only the page is cached, not the tracks.','spiff').'</small>'
        );

        
    }
    
    function musicbrainz_callback(){
        
        $option = $this->station->get_options('musicbrainz');
        $is_preset = $this->station->is_preset_value('musicbrainz');
        $is_disabled = null;
        
        printf(
            '<input type="checkbox" name="%1$s[musicbrainz]" value="on" %2$s %3$s %4$s/><span class="wizard-field-desc">%5$s</span>',
            'spiff_stations_wizard',
            checked((bool)$option, true, false),
            disabled( $is_disabled , true, false),
            spiff_readonly( $is_preset , true, false),
            sprintf(
                __('Try to fix tracks information using <a href="%1$s" target="_blank">MusicBrainz</a>.'),
                'http://musicbrainz.org/').'  <small>'.__('This makes the station render slower : each track takes about ~1 second to be checked!').'</small>'
        );

        
    }
    
    function tracks_order_callback(){
        
        $option = $this->station->get_options('tracks_order');
        $is_preset = $this->station->is_preset_value('tracks_order');
        
        printf(
            '<input type="radio" name="%1$s[tracks_order]" value="desc" %2$s %3$s /><span class="wizard-field-desc">%4$s</span>',
            'spiff_stations_wizard',
            checked($option, 'desc', false),
            spiff_readonly( $is_preset , true, false),
            __('Descending','spiff')
        );
        echo"<br/>";
        printf(
            '<input type="radio" name="%1$s[tracks_order]" value="asc" %2$s %3$s /><span class="wizard-field-desc">%4$s</span>',
            'spiff_stations_wizard',
            checked($option, 'asc', false),
            spiff_readonly( $is_preset , true, false),
            __('Ascending','spiff')
        );
        printf(
            '<p class="wizard-field-desc"><small>%1$s</small></p>',
            __('On the feed page, where is the most recent track ?  Choose "Descending" if it is on top, choose "Ascending" if it is in at the bottom.','spiff')
        );

        
    }
    
    function cache_persistent_callback(){
        
        
        $option = $this->station->get_options('cache_persistent');
        $desc = null;
        
        if ($option){
            
            $desc = __('The playlist is currently baked, so you cannot edit its settings.  Uncheck this to clear its cache.','spiff');

            $desc.= '<small></small>';
            
        }else{
            
            $desc = __("Bake this playlist",'spiff').'  <small>'.__("It will be stored in the cache and won't be updated unless you uncheck this.",'spiff').'</small>';
        }
        
        printf(
            '<input type="checkbox" name="%1$s[cache_persistent]" value="on" %2$s/><span class="wizard-field-desc">%3$s</span>',
            'spiff_stations_wizard',
            checked((bool)$option, true, false),
            $desc
        );
        
    }

    
    function tabs( $active_tab = '' ) {
        
        $tabs_html    = '';
        $idle_class   = 'nav-tab';
        $active_class = 'nav-tab nav-tab-active';
        
        $base_urls_tab = $variables_tab = $tracks_selector_tab = $track_details_tab = $options_tab = $tracklist_tab = array();
                    
        if ($this->can_show_step('base_urls')){
            $base_urls_tab = array(
                'title'  => __('Base URLs','spiff'),
                'href'  => '#spiff-station-wizard-step-base-content'
            );
        }
        
        if ($this->can_show_step('variables')){
            
            $variables_tab = array(
                'title'  => __('Variables','spiff'),
                'href'  => '#spiff-station-wizard-step-playlist-variables-content'
            );
        }

        if ($this->can_show_step('tracks_selector')){
            $tracks_selector_tab = array(
                'title'  => __('Tracks Selector','spiff'),
                'href'  => '#spiff-station-wizard-step-tracks-selector-content'
            );
        }
        
        if ($this->can_show_step('track_details')){
            $track_details_tab = array(
                'title'  => __('Track details','spiff'),
                'href'  => '#spiff-station-wizard-step-track-details-content'
            );
        }
        
        if ($this->can_show_step('playlist_options')){
            $options_tab = array(
                'title'  => __('Station Settings','spiff'),
                'href'  => '#spiff-station-wizard-step-playlist-options-content'
            );
        }
        
        if ($this->can_show_step('tracklist')){
            
            $tracks_count = 0;
            if ( $this->station->tracklist->tracks ){
                $tracks_count = count( $this->station->tracklist->tracks );
            }

            $tracklist_tab = array(
                'title'  => sprintf( __('Found Tracks : %d','spiff'),$tracks_count ),
                'href'  => '#spiff-station-wizard-step-tracklist-content'
            );
        }

        $tabs = array(
            $base_urls_tab,
            $variables_tab,
            $tracks_selector_tab,
            $track_details_tab,
            $options_tab,
            $tracklist_tab
        );
        
        $tabs = array_filter($tabs);

        // Loop through tabs and build navigation
        foreach ( array_values( $tabs ) as $key=>$tab_data ) {

                $is_current = (bool) ( $key == $active_tab );
                $tab_class  = $is_current ? $active_class : $idle_class;
                $tabs_html .= '<li><a href="' . $tab_data['href'] . '" class="' . esc_attr( $tab_class ) . '">' . esc_html( $tab_data['title'] ) . '</a></li>';
        }

        echo $tabs_html;
    }
    
    function wizard_metabox(){
    ?>
    <div id="spiff-station-wizard-tabs">
        <?php settings_errors('wizard-header');?>
        <ul id="spiff-station-wizard-tabs-header">
            <?php $this->tabs(); ?>
        </ul>
        
        <div id="spiff-station-wizard-step-base-content" class="spiff-station-wizard-step-content">
            <?php do_settings_sections( 'spiff-station-wizard-step-base' );?>
        </div>
        
        <?php
        if ($this->can_show_step('variables')){
            ?>
            <div id="spiff-station-wizard-step-playlist-variables-content" class="spiff-station-wizard-step-content">
                <?php do_settings_sections( 'spiff-station-wizard-step-playlist-variables' );?>
                <?php do_settings_sections( 'spiff-station-wizard-step-playlist-dynamic-title' );?>
            </div>
            <?php
        }
        ?>
        
        <?php         
        if ($this->can_show_step('tracks_selector')){
            ?>
            <div id="spiff-station-wizard-step-tracks-selector-content" class="spiff-station-wizard-step-content">
                <?php do_settings_sections( 'spiff-station-wizard-step-tracks-selector' );?>
            </div>
            <?php
        }
        ?>
        
        <?php         
        if ($this->can_show_step('track_details')){
            ?>
            <div id="spiff-station-wizard-step-track-details-content" class="spiff-station-wizard-step-content">
                <?php do_settings_sections( 'spiff-station-wizard-step-track-details' );?>
            </div>
            <?php
        }
        ?>
        
        <?php
        if ($this->can_show_step('playlist_options')){
            ?>
            <div id="spiff-station-wizard-step-playlist-options-content" class="spiff-station-wizard-step-content">
                <?php do_settings_sections( 'spiff-station-wizard-step-playlist-options' );?>
            </div>
            <?php
        }
        ?>
        
        <?php
        if ($this->can_show_step('tracklist')){
            ?>
            <div id="spiff-station-wizard-step-tracklist-content" class="spiff-station-wizard-step-content">
                <?php do_settings_sections( 'spiff-station-wizard-step-tracklist' );?>
            </div>
            <?php
        }
        ?>

    </div>
    <?php
    submit_button();
    wp_nonce_field(spiff()->basename,'spiff_stations_wizard_nonce',false);
    
    }
    
    /*
     * Sanitize wizard data
     */
    
    function sanitize_settings($post_id, $input){

        $previous_values = $this->station->get_options();
        $new_input = $previous_values;
        
        //TO FIX isset() check for boolean option - have a hidden field to know that settings are enabled ?

        //persistent cache
        $new_input['cache_persistent'] = ( isset($input['cache_persistent']) ) ? $input['cache_persistent'] : null;

        if ( isset($previous_values['cache_persistent']) && $previous_values['cache_persistent'] ){ //restore baked options
            
            $new_input = array_merge($previous_values, $new_input );
            
        }else{

            //cache
            if ( isset($input['cache_duration']) && is_numeric($input['cache_duration']) ){
                $new_input['cache_duration'] = $input['cache_duration'];
            }

            $feed_url = trim($input['feed_url']);
            $website_url = trim($input['website_url']);

            if ( !$feed_url ) $feed_url = $website_url;
            $new_input['feed_url'] = $feed_url;

            if ( !$website_url ) $website_url = $feed_url;
            $new_input['website_url'] = $website_url;

             //urls variables
             if ( isset($input['variables']) ) {
                 foreach ($input['variables'] as $var_slug=>$var_options){
                
                    //patterned var
                    if (!$var = $this->station->variables->get($var_slug)){
                        $var = $this->station->variables->make_single_var($var_slug);
                        $var->store_value = true;
                    }
                        
                    if (isset($var_options['name'])){
                        $var->name = $var_options['name'];
                    }
                    if (isset($var_options['value'])){
                        $var->value = $var_options['value'];
                    }

                    $var->show_form = ( isset($var_options['show_form']) ) ? true : false;

                    $saved = $var->save();

                 }

             }

            //TO FIX do not erase selectors and regex if they are not enabled
            //selectors
            if ( isset($input['selectors']) ){
                foreach ($input['selectors'] as $key=>$value){
                    $new_input['selectors'][$key] = trim($value);
                }
            }

            //regex
            if ( isset($input['selectors_regex']) ){
                foreach ($input['selectors_regex'] as $key=>$value){
                    $value = trim($value);
                    $new_input['selectors_regex'][$key] = $value;
                }
            }

             //order
             $new_input['tracks_order'] = ( isset($input['tracks_order']) ) ? $input['tracks_order'] : null;

             //musicbrainz
             $new_input['musicbrainz'] = ( isset($input['musicbrainz']) ) ? $input['musicbrainz'] : null;
             
             //dynamic title
             if ( $dynamic_title = trim($input['dynamic_title']) ){
                 $new_input['dynamic_title'] = $dynamic_title;
             }
             
            
        }
        
        //cache is not enabled or cache_persistent have been unselected, delete existing cache
        if ( 
            ( !isset($new_input['cache_duration']) && isset($previous_values['cache_duration']) ) || 
            ( ($new_input['cache_persistent'] == false) && isset($previous_values['cache_persistent']) ) 
        ) {
            $this->station->datas_cache->delete();
        }

        return $new_input;
    }

    function save_settings($post_id, $data){

        $data = $this->sanitize_settings($post_id, $data);

        $new_input = array();
        $default_args = Spiff_Station_Default::get_default_options();

        //ignore default values
        foreach ( $default_args as $slug => $default ){
            if ( !isset($data[$slug]) ) continue;
            if ($data[$slug]==$default) continue;
            $new_input[$slug] = $data[$slug];
        }

        if ($result = update_post_meta( $post_id, Spiff_Station_Default::$meta_key_settings, $new_input )){
            do_action('spiff_save_wizard_settings', $new_input, $post_id);
            return $result;
        }

        

    }

    function save($post_id){

        //check save status
        $is_autosave = wp_is_post_autosave( $post_id );
        $is_revision = wp_is_post_revision( $post_id );
        $is_valid_nonce = false;
        if ( isset($_POST[ 'spiff_stations_wizard_nonce' ]) && wp_verify_nonce( $_POST['spiff_stations_wizard_nonce'], spiff()->basename)) $is_valid_nonce=true;

        if ($is_autosave || $is_revision || !$is_valid_nonce) return;

        if( get_post_type($post_id)!=spiff_stations()->station_post_type ) return;
        
        $data = ( isset($_POST[ 'spiff_stations_wizard' ]) ) ? $_POST[ 'spiff_stations_wizard' ] : null;

        $this->save_settings( $post_id, $data );
    }
    
}

new Spiff_Station_Wizard();

?>
