<?php
// This is global bootstrap for autoloading
if ( ! defined('ABSPATH') )
    define('ABSPATH', realpath(dirname(__FILE__) . '/../../../../'));

if ( ! defined('_DATA') )
    define('_DATA', realpath(dirname(__FILE__) . '/_data/'));

if ( ! defined('DB_NAME') ) {
    define('DB_NAME', ''); #don't forget to add your db name here
    define('DB_HOST', 'localhost');
    define('DB_USER', ''); #don't forget to add your username here
    define('DB_PASSWORD', ''); #don't forget to add your password here
}

require_once(ABSPATH . '/wp-content/plugins/wpdemo/Generator.php');
require_once(ABSPATH . '/wp-content/plugins/wpdemo/Instance.php');
require_once(ABSPATH . '/wp-content/plugins/wpdemo/Config.php');
