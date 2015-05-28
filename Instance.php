<?php

namespace WPDemo;
    
    /**
     * The Instance class is tasked with most of the heavy lifting required when creating a new demo instance. Because destroying instances is done in bulk, the Generator class, rather than the Instance, is tasked with handling it.
     */
    class Instance
    {
        public $id;
        public $pdo;
        public $tablePrefix;
        public $uploadDir;

        public function __construct($pdo, $config, $id = null)
        {
            $this->pdo = $pdo;

            if ($id === null) {
                $this->id          = $this->generateRandomString();
                $this->tablePrefix = $config->instancePrefix . $this->id . '_';
                $this->uploadDir   = $config->cloneDir . '/' . $config->instancePrefix . $this->id;

                $this->importTables($config->defaultPrefix, $this->tablePrefix);
                $this->cloneUploadsDirectory($config->uploadDir, $this->uploadDir);
            } else {
                $this->id          = $id;
                $this->tablePrefix = $config->instancePrefix . $this->id . '_';
                $this->uploadDir   = $config->cloneDir . '/' . $config->instancePrefix . $this->id;
            }
        }

        /**
         * Generates a random string.
         * @param int $size Number of characers in the string.
         * @return string Random string.
         */
        protected function generateRandomString($size = 10)
        {
            $chars  = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $length = strlen($chars);
            $result = '';

            for ($i = 0; $i < $size; $i++) {
                $result .= $chars[rand(0, $length - 1)];
            }
            return $result;
        }

        /**
         * Recursively clone the uploads directory, including all files and sub-directories.
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
         * Gets a list of all the tables that need to be cloned, based on the default prefix, copies them and fixes the prefixes in options and usermeta tables.
         * @param  string $defaultPrefix  The default database prefix (eg: wp_).
         * @param  string $instancePrefix The demo instance prefix (eg: wpdemo_abcd123456_)
         * @return void
         */
        public function importTables($defaultPrefix, $instancePrefix)
        {
            //Get a list of all the tables that need to be cloned
            $tables = $this->getTablesFromTemplate($defaultPrefix);

            //Clone the tables from the template into the demo instance
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
            $pdo    = $this->pdo;
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
            $pdo    = $this->pdo;
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
         * Clones the tables by name, copying the schema and populating them with data from the original.
         * @param  array $tables         A list of tables to be copied, sans prefix.
         * @param  string $defaultPrefix  The default table prefix (eg: wp_).
         * @param  string $instancePrefix The new table prefix (eg: wpdemo_abcd123456_)
         * @return void
         */
        protected function cloneTables($tables, $defaultPrefix, $instancePrefix)
        {
            $pdo    = $this->pdo;
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
            $pdo            = $this->pdo;
            $escaped        = str_replace('_', '\_', $templatePrefix); //Underscores just happen to be a wildcard in SQL LIKE statements, so we need to work around this
            $sql            = "SHOW TABLES LIKE '$escaped%'";
            $query          = $pdo->query($sql);
            $tables         = $query->fetchAll(\PDO::FETCH_COLUMN, 0);
            $results        = array_map(function ($tableName) use ($templatePrefix) {return str_replace($templatePrefix, '', $tableName);}, $tables);

            return $results;
        }
    }