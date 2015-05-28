<?php

//Security precaution, in case somebody calls the file directly or inserts it in the wrong place in wp-config
if ( !defined('ABSPATH') ) {
    exit ('This script may not be called directly.');
}

if (defined('DOING_CRON') === false) { //We only want to create a new demo instance when an actual user visits
    require_once(dirname(__FILE__) . '/Generator.php');
    require_once(dirname(__FILE__) . '/Manager.php');
    require_once(dirname(__FILE__) . '/Instance.php');
    require_once(dirname(__FILE__) . '/Config.php');

    try {
        (new WPDemo\Generator())->instantiateSession();
    } catch (\Exception $e) {
        if ( defined('WP_DEBUG') && WP_DEBUG === true) {
            \WPDemo\Manager::writeLog($e);
        } else {
            exit ('Sorry, couldn\'t create a new demo session. Please try again later.');
        }
    }
    
}
