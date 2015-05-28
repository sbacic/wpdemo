<?php
    
namespace WPDemo;

    class Config {
        public $defaultPrefix   = 'wp_';
        public $instancePrefix  = 'wpdemo_';
        public $uploadDir       = 'wp-content/uploads';
        public $cloneDir        = 'wp-content';
        public $lifetime        = 15; //Instance lifetime, in minutes
        public $limit           = 10; //Max instances
    }

