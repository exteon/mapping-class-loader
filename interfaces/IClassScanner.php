<?php

    namespace Exteon\Loader\MappingClassLoader;

    interface IClassScanner
    {
        /**
         * @return string[]
         */
        public function scanClasses(): array;
    }