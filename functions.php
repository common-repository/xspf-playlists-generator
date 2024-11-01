<?php

/**
 * Get a value in a multidimensional array
 * http://stackoverflow.com/questions/1677099/how-to-use-a-string-as-an-array-index-path-to-retrieve-a-value
 * @param type $keys
 * @param type $array
 * @return type
 */

function spiff_get_array_value($keys = null, $array){

    if (!$keys) return $array;
    
    $keys = (array)$keys;
    $first_key = $keys[0];

    if(count($keys) > 1) {
        if ( isset($array[$keys[0]]) ){
            return spiff_get_array_value(array_slice($keys, 1), $array[$keys[0]]);
        }
    }elseif (isset($array[$first_key])){
        return $array[$first_key];
    }
    
    return false;

}

/**
 * Outputs the html readonly attribute.  Inspired by core function disabled().
 *
 * Compares the first two arguments and if identical marks as readonly
 *
 * @since 3.0.0
 *
 * @param mixed $readonly One of the values to compare
 * @param mixed $current  (true) The other value to compare if not just true
 * @param bool  $echo     Whether to echo or just return the string
 * @return string html attribute or empty string
 */
function spiff_readonly( $readonly, $current = true, $echo = true ) {
	return __checked_selected_helper( $readonly, $current, $echo, 'readonly' );
}

?>