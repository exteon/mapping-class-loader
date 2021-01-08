<?php

    namespace Exteon\Loader\MappingClassLoader;

    interface IMappingFileLoader
    {
        /**
         * @param string $code
         * @param string|null $mapToFile
         */
        public function eval(string $code, string $mapToFile = null);

        /**
         * @param string $file
         * @param string $mapToFile
         */
        public function includeOnce(string $file, string $mapToFile);
    }