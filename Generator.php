<?php

namespace WPDemo;
    
    /**
     * The Generator is tasked with creating new demo instances and deleting stale ones.
     */
    class Generator
    {
        private $pdo;

        public function getPDO()
        {
            if ($this->pdo === null) {
                $dsn    = 'mysql:dbname='.DB_NAME.';host='.DB_HOST.';';
                $user   = DB_USER;
                $pass   = DB_PASSWORD;

                try {
                    $this->pdo = new \PDO($dsn, $user, $pass, array(\PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'));
                    $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                } catch (\Exception $e) {
                    throw new \Exception('Failed to access database, WPDemo cannot continue...');
                }   
            }
            
            return $this->pdo;
        }

        public function getConfig()
        {
            return new Config();
        }

        /**
         * Return the number of currently existing instances.
         * @return int The number of instances.
         */
        public function countInstances() {
            $sql     = "SHOW TABLES LIKE '" . $this->getConfig()->instancePrefix . "%usermeta'";
            $pdo     = $this->getPDO();
            $query   = $pdo->query($sql);
            $results = $query->fetchAll(\PDO::FETCH_NUM);
            
            return $results === false ? 0 : count($results);
        }
        
        public function hasInstance()
        {
            if (session_id() == '') {
                session_start();
            }

            return isset($_SESSION['instanceID']) == true; 
        }

        /**
         * Create a new session or instantiate an existing one.
         * @return bool Return true.
         */
        public function instantiateSession()
        {
            //Do not create a new instance if we're already at the limit
            if ($this->countInstances() >= $this->getConfig()->limit)
                throw new \Exception('Could not create a new demo, too many demos are already running.');

            //Grab the instance
            $instance = $this->hasInstance() === true? 
                new Instance( $this->getPDO(), $this->getConfig(), $_SESSION['instanceID']) : 
                new Instance( $this->getPDO(), $this->getConfig() );

            //Set the WP globals
            $GLOBALS['table_prefix'] = $instance->tablePrefix;
            $_SESSION['instanceID']  = $instance->id;

            if (defined('UPLOADS') === false)
                define('UPLOADS', $instance->uploadDir);

            return true;
        }

        /**
         * Destroys existing instances. Wiped database and file upload dirs based on the parameters provided.
         * @param  int $olderThan    Age of instances, in minutes. Zero means delete all.
         * @param  string $tablePrefix  All tables with this prefix are deleted. This is used as a wildcard by the SQL LIKE operation.
         * @param  string $uploadPrefix Directory prefix.
         * @return void               
         */
        public function destroyInstances($olderThan, $tablePrefix, $uploadPrefix)
        {
            $this->cleanupTables($olderThan, $tablePrefix);
            $this->cleanupUploadDirs($olderThan, $uploadPrefix);
        }

        /**
         * Recursively delete upload dir, along with files and subdirectories, ignoring dots.   
         * @param  int $lifetime Lifetime, in minutes. 
         * @param  string $uploadPrefix Upload directory to delete.
         * @return void
         */
        public function cleanupUploadDirs($lifetime, $uploadPrefix)
        {
            $uploadDirs = glob(ABSPATH . $uploadPrefix . '*');
            $toDelete   = array_filter($uploadDirs, function($dir) use ($lifetime) {
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

        /**
         * Remove tables belonging to expired demo instances.
         * @param  int $olderThan Lifetime, in minutes. Tables older than this will be deleted.   
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

            if (count($toDrop) === 0)
                return true;

            $sql     = sprintf("DROP TABLE %s;", implode(', ', $toDrop));

            $pdo->exec($sql);
        }
    }