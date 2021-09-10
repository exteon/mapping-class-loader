<?php

    namespace Exteon\Loader\MappingClassLoader;

    interface ClassScanner
    {
        /**
         * @return string[]
         */
        public function scanClasses(): array;
    }