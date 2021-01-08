<?php

    namespace Exteon\Loader\MappingClassLoader;

    interface IStaticInitializer
    {
        public function init(string $class): void;
    }