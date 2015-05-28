<?php

namespace WPDemo;

    require_once(dirname(__FILE__) . '/Generator.php');
    require_once(dirname(__FILE__) . '/Instance.php');
    require_once(dirname(__FILE__) . '/Config.php');

    class Manager
    {
        protected $config;
        protected static $init;

        public function init()
        {
            if (Manager::$init != true) {
                Manager::$init = true;

                add_filter( 'plugin_action_links_wpdemo/wpdemo.php', array($this, 'addLinks') );

                add_action( 'admin_notices', array( $this, 'addNotices' ) );

                add_action( 'admin_post_wpdemo_remove_instances', array( $this, 'removeInstancesNow' ) );
            } 
        }

        public function __construct($config) {
            $this->config = $config;
        }

        /**
         * Checks that the current MySQL user has all the required permissions.
         * @return boolean True if user has all the permissions needed, false otherwise.
         */
        public static function hasPermissions()
        {
            $sql       = "SHOW GRANTS for test;";
            $generator = new Generator();
            $pdo       = $generator->getPDO();
            $query     = $pdo->query($sql);
            $results   = array_pop(($query->fetch(\PDO::FETCH_NUM)));

            return 
                strpos($results, 'GRANT ALL PRIVILEGES ON') !== false || 
                strpos($results, 'SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, INDEX, ALTER ON') !== false;
        }

        /**
         * Run when the plugin is activated.
         * @return bool Returns true.
         */
        public function setup()
        {
            $standin = $this;
            
            //try to create the uploads dir automatically
            $dir = explode('/', $this->config->uploadDir);
            @mkdir (ABSPATH . '/' . $dir);

            add_action('WPDemo.cleanupInstances', function() use ($standin) {
                $standin->cleanup();
            });

            register_activation_hook(__FILE__, function() {
                wp_schedule_event(time(), 'hourly', 'WPDemo.cleanupInstances');
            });

            return true;
        }

        /**
         * Adds the remove instances link to the Plugins page. This wipes all existing demo instances.
         * @param array $links Merged links array.
         */
        public function addLinks($links)
        {
            $generator = new Generator();
            $count     = $generator->countInstances();
            $myLinks   = array();
            $nonce     = wp_create_nonce('wpdemo_remove_instances');

            $myLinks = array(
                vsprintf('<a href="%s">Remove Instances (%s)</a>', array(admin_url('admin-post.php?action=wpdemo_remove_instances&_wpnonce='.$nonce), $count))
            );

            return array_merge($links, $myLinks);
        }

        /**
         * Adds notices in case of insufficient file and database permissions to the Plugins page.
         */
        public function addNotices()
        {
            $cloneDir         = ABSPATH . $this->config->cloneDir;
            $folderExists     = '<div class="updated"><p><strong>WPDemo could not create folder "%s" which is needed for storing clones of uploads dir. Please create it manually.</strong></p></div>';
            $filePermissions  = '<div class="updated"><p><strong>WPDemo may not function properly because the "%s" directory is not writable.</strong></p></div>';
            $tablePermissions = '<div class="updated"><p><strong>WPDemo may not function properly because the current database user ("%s") does not have all the required MySQL permissions ("grants").</strong></p></div>';

            if (file_exists($cloneDir) === false) {
                echo vsprintf($folderExists, array($cloneDir));
            }

            if (file_exists($cloneDir) === true && is_writable($cloneDir) === false) {
                echo vsprintf($filePermissions, array($cloneDir));
            }

            if (Manager::hasPermissions() === false) {
                echo vsprintf($tablePermissions, array(DB_USER));
            }
        }

        /**
         * Run when the plugin is deactivated. Removes hooks and destroys all demo instances.
         * @return bool Returns true.
         */
        public function remove()
        {
            $this->cleanup(0);

            return true;
        }

        public function removeInstancesNow()
        {
            $nonce = $_GET['_wpnonce'];

            if (current_user_can('activate_plugins') === true && wp_verify_nonce($nonce, 'wpdemo_remove_instances') === 1 ) {
                $this->cleanup(0);
                $location = admin_url('plugins.php');
                wp_redirect($location);
            } else {
                //do nothing
            }            
        }

        /**
         * Removes stale demo instances - instances that are older than the allowed lifetime.
         * @param  int $lifetime The max lifetime, in minutes. Default is null (so the value in Config is used). A value of zero means that all instances are destroyed immediately.
         * @return int Number of instances destroyed.
         */
        public function cleanup($lifetime = null)
        {
            $generator = new Generator();
            $start     = $generator->countInstances();
            $lifetime  = $lifetime === null ? $generator->getConfig()->lifetime : $lifetime;

            $generator->destroyInstances(
                $lifetime,
                $generator->getConfig()->instancePrefix,
                $generator->getConfig()->cloneDir . '/' . $generator->getConfig()->instancePrefix
            );

            $end = $generator->countInstances();

            return abs($start-$end);
        }

        /**
         * Handles exceptions, either outputting them to the browers or storing them in a debug log.
         * @param  Exception $e The exception raised.
         * @return void    
         */
        public static function writeLog($e)
        {
            $message = vsprintf("WPDemo: %s \n", array($e->getMessage()));

            if ( defined('WP_DEBUG_LOG') && WP_DEBUG_LOG === true ) {
                error_log($message, 3, ABSPATH . '/wp-content/debug.log');
                exit();
            } else {
                exit($message);
            }               
        }
    }


