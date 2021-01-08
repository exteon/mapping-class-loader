<?php

    namespace Test\Exteon\Loader\MappingClassLoader\Props;

    use Exception;
    use Exteon\Loader\MappingClassLoader\IClassResolver;
    use Exteon\Loader\MappingClassLoader\LoadAction;

    class SourceChangeResolver implements IClassResolver
    {
        protected const ROOT_NS =
            'Test\\Exteon\\Loader\\MappingClassLoader\\PropsRoot';

        /** @var array<string, bool> */
        protected $resolvedClasses = [];

        function resolveClass(string $class): array
        {
            try {
                $unprefixedClass = static::getUnprefixedClass($class);
            } catch (UnknownNsException $e) {
                return [];
            }

            $this->resolvedClasses[$unprefixedClass] = null;

            $classPath = static::classNameToSourceFilename($class);
            if (!file_exists($classPath)) {
                throw new Exception(
                    'Cannot load class ' .
                    $class .
                    ' : source file not found at ' .
                    $classPath
                );
            }

            $contents = file_get_contents($classPath);
            $replaced = preg_replace(
                '`~~~\\[(.*?)\\]~~~`s',
                '~~~[replaced-$1]~~~',
                $contents
            );

            $loadAction = new LoadAction($class, $classPath, $replaced);
            return [$loadAction];
        }

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

        protected static function nsPathToFilePath(string $ns): string
        {
            return str_replace('\\', '/', $ns);
        }

        public static function classNameToSourceFilename(string $class): string
        {
            $unprefixedClass = static::getUnprefixedClass($class);
            return
                __DIR__ .
                '/Classes/' .
                static::nsPathToFilePath($unprefixedClass) . '.php';
        }

        public static function getCacheDirectory(): string
        {
            $tmpDir = sys_get_temp_dir();
            return
                $tmpDir .
                '/exteon/mapping-class-loader/test/' .
                substr(sha1(__FILE__), 0, 6);
        }

        public static function classNameToCacheFilename(string $class): string
        {
            return
                static::getCacheDirectory() .
                '/' .
                static::nsPathToFilePath($class) .
                '.php';
        }

        public function didResolveClass(string $class): bool
        {
            $unprefixedClass = static::getUnprefixedClass($class);
            return array_key_exists($unprefixedClass, $this->resolvedClasses);
        }
    }