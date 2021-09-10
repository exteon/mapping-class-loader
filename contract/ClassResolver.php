<?php

    namespace Exteon\Loader\MappingClassLoader;

    use Exteon\Loader\MappingClassLoader\Data\LoadAction;

    /**
     * Class resolvers are used to resolve a requested class to a series of load
     * actions to be processed by MappingClassLoader
     */
    interface ClassResolver
    {
        /**
         * Returns an array of LoadAction for the cache loader to load the
         * requested $class
         *
         * @param string $class
         * @return LoadAction[]
         */
        public function resolveClass(string $class): array;
    }