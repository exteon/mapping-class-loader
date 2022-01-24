<?php

    namespace Test\Exteon\Loader\MappingClassLoader\Props;

    use Exception;
    use Exteon\Loader\MappingClassLoader\ClassResolver;
    use Exteon\Loader\MappingClassLoader\Data\LoadAction;

    class ChainingResolver implements ClassResolver
    {
        public const ROOT_NS =
            'Test\\Exteon\\Loader\\MappingClassLoader\\ChainPropsRoot';

        protected static function nsPathToFilePath(string $ns): string
        {
            return str_replace('\\', '/', $ns);
        }

        public static function classNameToCacheFilename(string $class): string
        {
            return
                static::getCacheDirectory() .
                '/' .
                static::nsPathToFilePath($class) .
                '.php';
        }

        public static function getCacheDirectory(): string
        {
            $tmpDir = sys_get_temp_dir();
            return
                $tmpDir .
                '/exteon/mapping-class-loader/test/' .
                substr(sha1(__FILE__), 0, 6);
        }

        function resolveClass(string $class): array
        {
            try {
                $unprefixedClass = static::getUnprefixedClass($class);
            } catch (UnknownNsException) {
                return [];
            }

            $ns = self::ROOT_NS;
            $ns1 = self::ROOT_NS.'\\Ns1';
            $ns2 = self::ROOT_NS.'\\Ns2';
            $class1 = $ns1.'\\'.$unprefixedClass;
            $class2 = $ns2.'\\'.$unprefixedClass;

            return [
                new LoadAction($class1,null,"<?php namespace $ns1; class $unprefixedClass {}",null),
                new LoadAction($class2,null,"<?php namespace $ns2; class $unprefixedClass {}",null),
                new LoadAction($class,null,"<?php namespace $ns; class $unprefixedClass {}",null)
            ];
        }

        /**
         * @throws UnknownNsException
         */
        protected static function getUnprefixedClass(string $class): ?string
        {
            if (
                !preg_match(
                    '`^' .
                    preg_quote(
                        self::ROOT_NS,
                        '`'
                    ) .
                    '\\\\(.*)`',
                    $class,
                    $matches
                )
            ) {
                throw new UnknownNsException(
                    "$class is not a suffix of " . self::ROOT_NS
                );
            }
            return $matches[1];
        }
    }