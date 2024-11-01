<?php

class XSPFPL_Station_Stats {
    
    var $station;
    
    static $meta_key_health = 'spiff_station_health';
    static $meta_key_requests = 'spiff_total_requests';
    static $meta_key_monthly_requests = 'spiff_monthly_requests';
    static $meta_key_monthly_requests_log = 'spiff_monthly_requests_log';
    
    function __construct($station){
        $this->station = $station;
    }

    /**
     * Each time the tracks are requested for this station, update the meta.
     * @return type
     */
    
    function update_track_request_count(){
        
        if ( get_post_status($this->station->post_id) != 'publish') return;
        
        //total count
        $count = (int)get_post_meta($this->station->post_id, self::$meta_key_requests, true);
        $count++;
        
        //

        return update_post_meta($this->station->post_id, self::$meta_key_requests, $count);
    }
    
    /**
     * Update the number of tracks requests for the month, in two metas :
     * self::$meta_key_monthly_requests_log (array of entries with the timestamp tracks where requested)
     * self::$meta_key_monthly_requests (total requests)
     * @return type
     */
    
    function update_track_request_monthly_count(){

        if ( get_post_status($this->station->post_id) != 'publish') return;

        $log = array();
        $time = current_time( 'timestamp' );
        $time_remove = strtotime('-1 month',$time); 
        
        if ($existing_log = get_post_meta($this->station->post_id, self::$meta_key_monthly_requests_log, true)){ //get month log
            $log = $existing_log;
        }

        //remove entries that are too old from log metas (multiple)
        foreach ((array)$log as $key=>$log_time){
            if ($log_time <= $time_remove){
                unset($log[$key]);
            }
        }
        
        //update log
        $log[] = $time;
        update_post_meta($this->station->post_id, self::$meta_key_monthly_requests_log, $log);
        
        //avoid duplicates
        $log = array_filter($log);

        //update requests count
        $count = count($log);
        return update_post_meta($this->station->post_id, self::$meta_key_monthly_requests, $count );
    }
    

    
    /**
     * Each time the tracks are requested for this station, log the number of tracks fetched.
     * Meta will be used in 
     * @param type $tracks
     * @return type
     */
    
    function update_health_status(){

        if ( get_post_status($this->station->post_id) != 'publish') return;

        $time = current_time( 'timestamp' );
        $log = get_post_meta($this->station->post_id, self::$meta_key_health, true);     //get health log
        $min_delay = 10 * MINUTE_IN_SECONDS; //save maximum once every 10 minutes

        if (!empty($log)){

            $last_previous_meta = end($log);
            $limit = $time - $min_delay;
            
            //do not save new entry if last one <10 minutes
            if ($last_previous_meta['time'] > $limit){
                return;
            }
            
        }
        
        //add new entry at the end of the log
        $log[] = array(
            'time'      => $time,
            'tracks'    => count( $this->station->tracklist->tracks )
        );
        
        //limit log length
        $max_entries = DAY_IN_SECONDS / $min_delay; 
        $log = array_slice($log, 0, $max_entries);

        update_post_meta($this->station->post_id, self::$meta_key_health, $log);
    }

}

?>
