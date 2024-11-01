<?php

/**
 * Admin Options Page
 */

class Spiff_Stations_Admin_Options{
    
    var $options_page;

    /**
     * Start up
     */
    function __construct(){
        
        add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
        add_action( 'admin_init', array( $this, 'page_init' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts_styles' ) );
    }

    function enqueue_scripts_styles($hook){
        global $post_type;
        if( spiff_stations()->station_post_type != $post_type ) return;
        wp_enqueue_script('spiff-stations-settings', spiff()->plugin_url.'_inc/js/spiff-stations-settings.js', array('jquery','jquery-ui-tabs'),spiff()->version);
        
    }

    /**
     * Add options page
     */
    function add_plugin_page()
    {
        // This page will be under "Settings"
        $this->options_page = add_submenu_page(
                'edit.php?post_type='.spiff_stations()->station_post_type,
                __('Options'),
                __('Options'),
                'manage_options',
                'spiff-options',
                array( $this, 'options_page' )
        );
    }

    /**
     * Options page callback
     */
    function options_page(){
        // Set class property
        
        ?>
        <div class="wrap">
            <?php screen_icon(); ?>
            <h2><?php _e('Spiff Stations','spiff');?></h2>  
            <?php settings_errors('spiff_option_group'); ?>
            
            <form method="post" action="options.php">
            <?php
                // This prints out all hidden setting fields
                settings_fields( 'spiff_option_group' );   
                do_settings_sections( 'spiff-settings-admin' );
                submit_button(); 
            ?>
            </form>
        </div>
        <?php
    }

    /**
     * Register and add settings
     */
    function page_init(){        
        
        if ( !Spiff_Station_Datas_Cache::is_installed() ){
            add_settings_error( 'spiff_option_group', 'cache_disabled', sprintf(__("The directory %s does not exists.  Cache cannot be enabled.",'spiff'), '<em>'.Spiff_Station_Datas_Cache::$cache_dir.'</em>') );
        }

        register_setting(
            'spiff_option_group', // Option group
            Spiff::$meta_key_options, // Option name
            array( $this, 'sanitize' ) // Sanitize
        );

        add_settings_section(
            'settings_general', // ID
            __('General Options','spiff'), // Title
            array( $this, 'section_general_desc' ), // Callback
            'spiff-settings-admin' // Page
        );  
        
        add_settings_section(
            'settings_playlist', // ID
            __('Stations Options','spiff'), // Title
            array( $this, 'section_playlist_desc' ), // Callback
            'spiff-settings-admin' // Page
        );
        
        add_settings_field(
            'cache_tracks_intval', 
            __('Playlist cache duration','spiff'), 
            array( $this, 'playlist_cache_callback' ), 
            'spiff-settings-admin', 
            'settings_playlist'
        );

        add_settings_field(
            'playlist_link', 
            __('Embed links','spiff'), 
            array( $this, 'playlist_link_callback' ), 
            'spiff-settings-admin', 
            'settings_playlist'
        );
        
        add_settings_field(
            'tracklist_embed', 
            __('Embed Tracklist','spiff'), 
            array( $this, 'playlist_embed_callback' ), 
            'spiff-settings-admin', 
            'settings_playlist'
        );
        /*
        add_settings_field(
            'enable_hatchet', 
            __('Enable Hatchet','spiff'), 
            array( $this, 'enable_hatchet_callback' ), 
            'spiff-settings-admin', 
            'settings_playlist'
        );
         * 
         */
        
        add_settings_section(
            'settings_api', // ID
            __('API Options','spiff'), // Title
            array( $this, 'section_api_desc' ), // Callback
            'spiff-settings-admin' // Page
        );
        
        add_settings_field(
            'lastfm_apikey', 
            __('LAST.fm API key','spiff'), 
            array( $this, 'lastfm_apikey_callback' ), 
            'spiff-settings-admin', 
            'settings_api'
        );
        
        add_settings_field(
            'soundcloud_client_id', 
            __('Soundcloud Client ID','spiff'), 
            array( $this, 'soundcloud_client_id_callback' ), 
            'spiff-settings-admin', 
            'settings_api'
        );
        
        /*
        add_settings_field(
            'twitter_apikey', 
            __('Twitter API key','spiff'), 
            array( $this, 'twitter_apikey_callback' ), 
            'spiff-settings-admin', 
            'settings_api'
        );
         */
        
        add_settings_section(
            'settings_system', // ID
            __('System Options','spiff'), // Title
            array( $this, 'section_system_desc' ), // Callback
            'spiff-settings-admin' // Page
        );

        add_settings_field(
            'reset_options', 
            __('Reset Options','spiff'), 
            array( $this, 'reset_options_callback' ), 
            'spiff-settings-admin', 
            'settings_system'
        );
        
    }

    /**
     * Sanitize each setting field as needed
     *
     * @param array $input Contains all settings fields as array keys
     */
    function sanitize( $input ){

        $new_input = array();

        if( isset( $input['reset_options'] ) ){
            
            $new_input = spiff_stations()->options_default;
            
        }else{ //sanitize values
            
            if ( isset ($input['cache_tracks_intval']) && ctype_digit($input['cache_tracks_intval']) ){
                $new_input['cache_tracks_intval'] = $input['cache_tracks_intval'];
            }

            if( isset( $input['playlist_link'] ) ){
                $new_input['playlist_link'] = $input['playlist_link'];
            }

            if( isset( $input['tracklist_embed'] ) ){
                $new_input['tracklist_embed'] = $input['tracklist_embed'];
            }
            
            if( isset( $input['enable_hatchet'] ) ){
                $new_input['enable_hatchet'] = $input['enable_hatchet'];
            }
            
            if ( isset ($input['lastfm_apikey']) && ctype_alnum($input['lastfm_apikey']) ){
                $new_input['lastfm_apikey'] = $input['lastfm_apikey'];
            }
            
            if ( isset ($input['soundcloud_client_id']) && ctype_alnum($input['soundcloud_client_id']) ){
                $new_input['soundcloud_client_id'] = $input['soundcloud_client_id'];
            }
            
            if ( isset ($input['twitter_apikey']) && ctype_alnum($input['twitter_apikey']) ){
                $new_input['twitter_apikey'] = $input['twitter_apikey'];
            }

        }

        //remove default values
        foreach($input as $slug => $value){
            $default = spiff_stations()->get_default_option($slug);
            if ($value == $default) unset ($input[$slug]);
        }

        $new_input = array_filter($new_input);

        return $new_input;
       
    }

    /** 
     * Print the Section text
     */
    function section_general_desc(){
    }
    
    function section_playlist_desc(){
    }
    
    function playlist_cache_callback(){
        $option = (int)spiff_stations()->get_options('cache_tracks_intval');
        $disabled = (!Spiff_Station_Datas_Cache::is_installed()); 

        $help = '<small>'.__('Number of seconds a playlist is cached before requesting the remote page again. 0 = Disabled.','spiff').'</small>';
        
        printf(
            '<input type="number" name="%1$s[cache_tracks_intval]" size="4" min="0" value="%2$s" %3$s/><br/>%4$s',
            Spiff::$meta_key_options,
            $option,
            disabled( $disabled , true, false),
            $help
        );
        
    }
    
    function playlist_link_callback(){
        $option = spiff_stations()->get_options('playlist_link');

        $checked = checked( (bool)$option, true, false );
        $desc = __('Automatically embed the playlist links.','spiff');
        $help = '<small>'.sprintf(__('You might want to disable this and use function %s instead, in your templates.','spiff'),'<code>spiff_station_form()</code>').'</small>';
                
        printf(
            '<input type="checkbox" name="%1$s[playlist_link]" value="on" %2$s/> %3$s<br/>%4$s',
            Spiff::$meta_key_options,
            $checked,
            $desc,
            $help
        );
    }
    
    function playlist_embed_callback(){
        $option = spiff_stations()->get_options('tracklist_embed');

        $checked = checked( (bool)$option, true, false );
        $desc = __('Automatically embed the tracklist.','spiff');
        $help = '<small>'.sprintf(__('You might want to disable this and use function %s instead, in your templates.','spiff'),'<code>$playlist->display()</code>').'</small>';
                
        printf(
            '<input type="checkbox" name="%1$s[tracklist_embed]" value="on" %2$s/> %3$s<br/>%4$s',
            Spiff::$meta_key_options,
            $checked,
            $desc,
            $help
        );
    }
    
    function enable_hatchet_callback(){
        $option = spiff_stations()->get_options('enable_hatchet');
        $help = null;

        $checked = checked( (bool)$option, true, false );
        $disabled = disabled(class_exists('Hatchet'), false, false); 
 
        $desc = __('Embeds the hatchet widgets in the tracklist.','spiff');
      
        if ($disabled){
            $help = '<small><strong>'.sprintf(__('The plugin %1$s is needed to enable this feature.','spiff'),'<a href="https://wordpress.org/plugins/wp-hatchet/" target="_blank">Hatchet</a>').'</strong></small>';
        }
        
        printf(
            '<input type="checkbox" name="%1$s[enable_hatchet]" value="on" %2$s %3$s/> %4$s<br/>%5$s',
            Spiff::$meta_key_options,
            $checked,
            $disabled,
            $desc,
            $help
        );
    }
    
    function section_api_desc(){
        
        _e('Some presets are relying on music services API to fetch various informations.','spiff').'</small>';
        
    }
    
    function lastfm_apikey_callback(){
        $option = spiff_stations()->get_options('lastfm_apikey');

        printf(
            '<input type="text" name="%1$s[lastfm_apikey]" value="%2$s"/>',
            Spiff::$meta_key_options,
            $option
        );
    }
    
    function soundcloud_client_id_callback(){
        $option = spiff_stations()->get_options('soundcloud_client_id');
        
        printf(
            '<input type="text" name="%1$s[soundcloud_client_id]" value="%2$s"/>',
            Spiff::$meta_key_options,
            $option
        );
    }
    
    function twitter_apikey_callback(){
        $option = spiff_stations()->get_options('twitter_apikey');

        printf(
            '<input type="text" name="%1$s[twitter_apikey]" value="%2$s"/>',
            Spiff::$meta_key_options,
            $option
        );
    }
    

    function section_system_desc(){
    }

    
    function reset_options_callback(){
        printf(
            '<input type="checkbox" name="%1$s[reset_options]" value="on"/> %2$s',
            Spiff::$meta_key_options,
            __("Reset options to their default values.","ari")
        );
    }
    
}

new Spiff_Stations_Admin_Options();