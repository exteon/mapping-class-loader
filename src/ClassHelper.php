<?php

    namespace Exteon\Loader\MappingClassLoader;

    use Exteon\FileHelper;

    abstract class ClassHelper
    {
        public static function getClassFilePath(
            string $class,
            string $dir,
            string $suffix
        ): string {
            $classPath = static::classNameToPath($class);
            $classPath .= $suffix;
            return FileHelper::getDescendPath($dir, $classPath);
        }

        /**
         * @param string $class
         * @return string
         */
        private static function classNameToPath(string $class): string
        {
            return str_replace('\\', '/', $class);
        }
    }