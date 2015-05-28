<?php

namespace DemoGenerator;

    /**
     * The DemoGenerator class is tasked with dynamically creating demo instances of the default Wordpress
     * installation, complete with cloned tables and upload directories. 
     */
    class DemoGenerator
    {                       
        /**
        *   Demo instance prefix.
        */                    
        const TABLE_PREFIX    = 'wpdemo_';

        /**
        *   Demo instance prefix - used to identify demo instances in the database.
        */  
        const TEMPLATE_PREFIX = 'wp_';

        /**
        *   Upload directory prefix. This is used when creating copies of the default uploads dir.
        */
        const UPLOAD_PREFIX   = 'wp-content/wpdemo_';

        /**
         *  Default uploads folder, relative to Wordpress root.
         */
        const UPLOAD_FOLDER   = 'wp-content/uploads';

        /**
         * Maximum number of instances.
         */
        const MAX_INSTANCES   = 20;

        /**
        *   Demo instance lifetime, in minutes. Do note that if you're using the default Wordpress cron that it's set to run every hour (the minimum value). 
        */
        const LIFETIME        = 15;

        /**
         * Singleton object instance. Accessed via getInstance().
         */
        private static $instance = null;

        /**
         * Demo instance ID. This ID, consisting of the TABLE_PREFIX plus a random string, 
         * is used to uniquely identify the users session. The value is stored on the client side via cookies.
         */
        protected $instanceID    = null;

        /**
         * PHP Data Object, used for database operations.
         */
        protected $pdo           = null;

        public function getTablePrefix() {
            return self::TABLE_PREFIX . $this->getInstanceInteger() . '_';
        }

        public function getUploadDir()
        {
            return self::UPLOAD_PREFIX . $this->getInstanceInteger();
        }
        
        private function __construct()
        {
            
        }
        
        public static function getInstance()
        {
            if (self::$instance === null) {
                self::$instance = new DemoGenerator();
            }

            return self::$instance;
        }

        protected function getInstanceInteger() {
            return isset($this->instanceID) ? $this->instanceID : false;
        }

        /**
         * Instantiates the demo session - either by creating a new demo instance (if none exists for this user) or 
         * by setting the values from an existing one.
         * @return void
         */
        public function instantiateSession()
        {
            session_start();

            if (isset($_SESSION['instanceID']) === false && defined('DOING_CRON') === false) {
                $this->generateDemoInstance();
            } else {
                $this->instanceID = $_SESSION['instanceID'];
            }
        }

        /**
         * Create a demo instance. Generates the instance cookie, clones tables, 
         * updates prefixes in options and usermeta and clones the upload directory. 
         * @return void
         */
        public function generateDemoInstance()
        {
            if ($this->countInstances() > self::MAX_INSTANCES) {
                die('Too many users are using the demo. Please try again later.');
            }

            $this->instanceID       = $this->generateRandomString();
            $_SESSION['instanceID'] = $this->instanceID;
            $from                   = ABSPATH . self::UPLOAD_FOLDER;
            $to                     = ABSPATH . $this->getUploadDir();
            
            $this->importTablesFromTemplate();
            $this->cloneUploadsDirectory($from, $to);
        }

        /**
         * Generates a random, 10 character string.
         * @return string Random 10 character string.
         */
        protected function generateRandomString()
        {
            $chars  = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $length = strlen($chars);
            $result = '';

            for ($i = 0; $i < 10; $i++) {
                $result .= $chars[rand(0, $length - 1)];
            }
            return $result;
        }
        
        /**
         * Gets a list of all the tables that need to be cloned, based on the default prefix, copies them and fixes the prefixes 
         * in options and usermeta tables.
         * @return void
         */
        public function importTablesFromTemplate()
        {
            //Get a list of all the tables that need to be cloned
            $tables = $this->getTablesFromTemplate(self::TEMPLATE_PREFIX);

            //Clone the tables from the template into the demo instance
            $instancePrefix = $this->getTablePrefix();
            $defaultPrefix  = self::TEMPLATE_PREFIX;
            $this->cloneTables($tables, $defaultPrefix, $instancePrefix);
            $this->convertPrefixes($defaultPrefix, $instancePrefix);
        }
        
        /**
         * Converts the prefixes in the options and usermeta tables to work with the demo instance.
         * @param  string $defaultPrefix  The default database prefix (eg: wp_).
         * @param  string $instancePrefix The demo instance prefix (eg: wpdemo_abcd123456_)
         * @return void                 
         */
        protected function convertPrefixes($defaultPrefix, $instancePrefix)
        {
            $pdo    = $this->getPDO();
            $update = "";

            //Update options table
            $sql    = "SELECT option_name FROM ".$defaultPrefix."options WHERE option_name LIKE '".$defaultPrefix."%'";
            $query  = $pdo->query($sql);
            $fields = $query->fetchAll(\PDO::FETCH_COLUMN, 0);
            
            foreach ($fields as $field) {
                $replacement = str_replace($defaultPrefix, $instancePrefix, $field);
                $update     .= "UPDATE ".$instancePrefix."options SET option_name = '".$replacement."' WHERE option_name = '".$field."';";
            }

            //Update usermeta table
            $pdo    = $this->getPDO();
            $sql    = "SELECT meta_key FROM ".$defaultPrefix."usermeta WHERE meta_key LIKE '".$defaultPrefix."%'";
            $query  = $pdo->query($sql);
            $fields = $query->fetchAll(\PDO::FETCH_COLUMN, 0);

            foreach ($fields as $field) {
                $replacement = str_replace($defaultPrefix, $instancePrefix, $field);
                $update     .= "UPDATE ".$instancePrefix."usermeta SET meta_key = '".$replacement."' WHERE meta_key = '".$field."';";
            }

            $pdo->exec($update);
        }

        /**
         * Generates the PDO object (if it doesn't exist) and returns it.
         * @return PDO
         */
        protected function getPDO()
        {
            if ($this->pdo === null) {
                $dsn    = 'mysql:dbname='.DB_NAME.';host='.DB_HOST.';';
                $user   = DB_USER;
                $pass   = DB_PASSWORD;

                try {
                    $this->pdo = new \PDO($dsn, $user, $pass, array(\PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'));
                } catch (Exception $e) {
                    die ('Failed to access database, WPDemo cannot continue...');
                }
                
            }
            
            return $this->pdo;
        }

        /**
         * Clones the tables by name, copying the schema and populating them with data from the original.
         * @param  array $tables         A list of tables to be copied, sans prefix.
         * @param  string $defaultPrefix  The default table prefix (eg: wp_).
         * @param  string $instancePrefix The new table prefix (eg: wpdemo_abcd123456_)
         * @return void
         */
        protected function cloneTables($tables, $defaultPrefix, $instancePrefix)
        {
            $pdo    = $this->getPDO();
            $query  = '';

            foreach ($tables as $table) {
                $defaultTable  = $defaultPrefix . $table;
                $instanceTable = $instancePrefix . $table;
                $query         .= "CREATE TABLE $instanceTable LIKE $defaultTable; INSERT $instanceTable SELECT * FROM $defaultTable;";
            }

            $pdo->exec($query);
        }

        /**
         * Retrieve a list of tables to be cloned from the default installation and remove the default prefix from them.
         * @param  string $templatePrefix Default table prefix (eg: wp_)
         * @return array                  List of tables to be copied.
         */
        protected function getTablesFromTemplate($templatePrefix)
        {
            $pdo            = $this->getPDO();
            $escaped        = str_replace('_', '\_', $templatePrefix); //Underscores just happen to be a wildcard in SQL LIKE statements, so we need to work around this
            $sql            = "SHOW TABLES LIKE '$escaped%'";
            $query          = $pdo->query($sql);
            $tables         = $query->fetchAll(\PDO::FETCH_COLUMN, 0);
            $results        = array_map(function ($tableName) use ($templatePrefix) {return str_replace($templatePrefix, '', $tableName);}, $tables);

            return $results;
        }

        /**
         * Recursively clone the uploads directory, icluding all files and sub-directories.
         * @return void
         */
        protected function cloneUploadsDirectory($from, $to)
        {
            if (file_exists($from) === false) {
                return false;
            } else {
                mkdir($to, 0755);

                foreach (
                    $iterator = new \RecursiveIteratorIterator(
                        new \RecursiveDirectoryIterator($from, \RecursiveDirectoryIterator::SKIP_DOTS),
                        \RecursiveIteratorIterator::SELF_FIRST) as $item
                        ) {
                        if ($item->isDir()) {
                            mkdir($to . DIRECTORY_SEPARATOR . $iterator->getSubPathName());
                        } else {
                            copy($item, $to . DIRECTORY_SEPARATOR . $iterator->getSubPathName());
                    }
                }
            }
        }

        /**
         * Remove expired demo instances by dropping their tables from the database and deleting their uploads folder. This method doesn't really do
         * much - it just delegates to other methods.
         * @return void 
         */
        public function cleanupInstances($lifetime = self::LIFETIME)
        {
            $this->cleanupTables($lifetime, self::TABLE_PREFIX);
            $this->cleanupUploadDirs($lifetime, self::UPLOAD_PREFIX);
        }

        /**
         * Remove tables belonging to expired demo instances.
         * @param  int $olderThan   Lifetime, in minutes. Tables older than this will be deleted.   
         * @param  string $tablePrefix Table prefix. Only tables with the demo prefix will be deleted.  
         * @return void
         */
        public function cleanupTables($olderThan, $tablePrefix)
        {
            $pdo     = $this->getPDO();
            $escaped = str_replace('_', '\_', $tablePrefix); //Underscores just happen to be a wildcard in SQL LIKE statements, so we need to work around this
            $sql     = "SHOW TABLE STATUS WHERE name LIKE '$escaped%' AND Create_time < NOW() - INTERVAL $olderThan MINUTE;";
            $query   = $pdo->query($sql);
            $tables  = $query->fetchAll(\PDO::FETCH_COLUMN, 0);

            $toDrop  = array();

            foreach ($tables as $table) {
                $toDrop[] = $table;
            }

            $sql     = sprintf("DROP TABLE %s;", implode(', ', $toDrop));

            $pdo->exec($sql);
        }

        /**
         * Return the number of currently existing instances.
         * @return int The number of instances.
         */
        public function countInstances() {
            $sql       = "SHOW TABLES LIKE '" . self::TABLE_PREFIX . "%usermeta'";
            $pdo       = $this->getPDO();
            $query     = $pdo->query($sql);
            $instances = count( $query->fetch(\PDO::FETCH_NUM) );
            return $instances;
        }

        /**
         * Recursively delete upload dir, along with files and subdirectories, ignoring dots.   
         * @param  string $uploadPrefix Upload directory to delete.
         * @return void
         */
        public function cleanupUploadDirs($lifetime, $uploadPrefix)
        {
            $uploadDirs = glob(ABSPATH . $uploadPrefix . '*');
            $toDelete   = array_filter($uploadDirs, function($dir) {
                return filemtime($dir) < time() - $lifetime * 60 ? true : false;
            });

            foreach ($toDelete as $dir) {
                $files = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($dir . '/', \RecursiveDirectoryIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
                
                foreach ($files as $file)
                    $file->isDir() === true ? rmdir($file->getRealPath()) : unlink($file->getRealPath());

                rmdir($dir);
            }
        }
    }
