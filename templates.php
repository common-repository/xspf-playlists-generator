<?php

function spiff_classes($array){
    echo spiff_get_classes($array);
}

function spiff_get_classes($classes){
    if (empty($classes)) return;
    return' class="'.implode(' ',$classes).'"';
}

?>
