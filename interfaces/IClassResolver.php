<?php

    namespace Exteon\Loader\MappingClassLoader;

    /**
     * Class resolvers are used to resolve a requested class to a series of load
     * actions to be processed by MappingClassLoader
     */
    interface IClassResolver
    {
        /**
         * Returns an array of LoadAction for the cache loader to load the
         * requested $class
         *
         * @param string $class
         * @return LoadAction[]
         */
        function resolveClass(string $class): array;
    }