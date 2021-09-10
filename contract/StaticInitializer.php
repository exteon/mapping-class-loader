<?php

    namespace Exteon\Loader\MappingClassLoader;

    interface StaticInitializer
    {
        public function init(string $class): void;
    }