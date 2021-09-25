<?php

    namespace Exteon\Loader\MappingClassLoader;

    use ErrorException;
    use Exception;
    use Exteon\FileHelper;
    use Exteon\Loader\MappingClassLoader\Data\LoadAction;

    class MappingClassLoader implements ClassScanner
    {
        private const
            HINT_FILE_SUFFIX = '.php';

        /** @var ClassResolver[] */
        private $resolvers;

        /** @var string */
        private $cacheDir;

        /** @var bool */
        private $isCaching;

        /** @var MappingFileLoader */
        private $mappingFileLoader;

        /** @var StaticInitializer|null */
        private $initializer;

        /**
         * @param array $config {
         *      enableCaching: bool,
         *      cacheDir: string
         * }
         * @param ClassResolver[] $resolvers
         * @param StaticInitializer|null $initializer
         * @param MappingFileLoader $mappingFileLoader
         * @throws ErrorException
         */
        public function __construct(
            array $config,
            array $resolvers,
            ?StaticInitializer $initializer,
            MappingFileLoader $mappingFileLoader
        ) {
            $this->resolvers = $resolvers;
            $this->initializer = $initializer;
            $this->isCaching = $config['enableCaching'] ?? false;
            $this->cacheDir = $config['cacheDir'] ?? null;
            if ($this->isCaching) {
                if (!$this->cacheDir) {
                    throw new ErrorException('cacheDir is not specified');
                }
            }
            $this->mappingFileLoader = $mappingFileLoader;
        }

        /**
         * @return bool
         * @throws ErrorException
         */
        public function clearCache(): bool
        {
            if (!$this->cacheDir) {
                throw new ErrorException('cacheDir is not specified');
            }
            return FileHelper::rmDir($this->cacheDir);
        }

        /**
         * @param string[] $classes
         * @throws Exception
         */
        public function clearSpecificClasses(array $classes): void
        {
            foreach ($classes as $class) {
                (new ClassCacheEntry(
                    $this->cacheDir,
                    $this->mappingFileLoader,
                    $this->initializer,
                    $class
                ))->purge();
            }
        }

        public function register(): void
        {
            spl_autoload_register([$this, 'loadClass']);
        }

        public function isCaching(): bool
        {
            return $this->isCaching;
        }

        /**
         * @param string $class
         * @throws ErrorException
         */
        public function loadClass(string $class): void
        {
            if ($this->isCaching) {
                $classCacheEntry = new ClassCacheEntry(
                    $this->cacheDir,
                    $this->mappingFileLoader,
                    $this->initializer,
                    $class
                );
                if (!$classCacheEntry->load()) {
                    $chain = $this->getLoadActions($class);
                    if ($chain) {
                        $classCacheEntry->cacheAndLoad($chain);
                    }
                }
            } else {
                $chain = $this->getLoadActions($class);
                if ($chain) {
                    $this->loadUncached($chain);
                }
            }
        }

        /**
         * @param string $class
         * @return array
         * @throws ErrorException
         */
        private function getLoadActions(string $class): array
        {
            foreach ($this->resolvers as $resolver) {
                $chain = $resolver->resolveClass($class);
                if ($chain) {
                    $this->validateChain($chain, $class);
                    return $chain;
                }
            }
            return [];
        }

        /**
         * @param LoadAction[] $chain
         * @throws ErrorException
         */
        private function validateChain(array $chain, string $class): void
        {
            $foundClass = false;
            foreach ($chain as $loadAction) {
                if (!$loadAction->getClass()) {
                    throw new ErrorException(
                        'Every LoadAction must specify class'
                    );
                }
                if (
                    !$loadAction->getSource() &&
                    !$loadAction->getFile()
                ) {
                    throw new ErrorException(
                        'Either source or file must be specified'
                    );
                }
                if($loadAction->getClass() === $class){
                    $foundClass = true;
                }
            }
            if (!$foundClass) {
                throw new ErrorException(
                    'Class mismatch'
                );
            }
        }

        /**
         * @param LoadAction[] $chain
         */
        private function loadUncached(array $chain): void
        {
            foreach ($chain as $loadAction) {
                $file = $loadAction->getFile();
                $source = $loadAction->getSource();
                if ($source) {
                    if ($file) {
                        $this->mappingFileLoader->eval(
                            $source,
                            $file
                        );
                    } else {
                        $this->mappingFileLoader->eval($source);
                    }
                } else {
                    require_once($file);
                }
                if($this->initializer){
                    $this->initializer->init($loadAction->getClass());
                }
            }
        }

        public function addResolver(ClassResolver $resolver): void
        {
            $this->resolvers[] = $resolver;
        }

        /**
         * @param string $targetDir
         * @throws ErrorException
         */
        public function dumpHintClasses(string $targetDir): void
        {
            $prefetchActions = $this->getPrefetchActions();
            foreach ($prefetchActions as $class => $classActions) {
                foreach ($classActions as $action) {
                    $mockCode = $action->getHintCode();
                    if ($mockCode !== null) {
                        file_put_contents(
                            $this->getHintFilePath(
                                $targetDir,
                                $action->getClass()
                            ),
                            $mockCode
                        );
                    }
                }
            }
        }

        /**
         * @return array<class-string,LoadAction[]>
         * @throws ErrorException
         */
        private function getPrefetchActions(): array
        {
            $classes = $this->scanClasses();
            $prefetchActions = [];
            foreach ($classes as $class) {
                $prefetchActions[$class] = $this->getLoadActions($class);
            }
            return $prefetchActions;
        }

        /**
         * @return string[]
         */
        public function scanClasses(): array
        {
            $flippedClasses = [];
            foreach ($this->resolvers as $resolver) {
                if ($resolver instanceof ClassScanner) {
                    $flippedClasses = array_merge(
                        $flippedClasses,
                        array_flip($resolver->scanClasses())
                    );
                }
            }
            return array_keys($flippedClasses);
        }

        /**
         * @param string $dir
         * @param string $class
         * @return string
         */
        private static function getHintFilePath(
            string $dir,
            string $class
        ): string {
            $classPath = static::classNameToPath($class);
            $classPath .= self::HINT_FILE_SUFFIX;
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

        /**
         * @throws ErrorException
         */
        public function primeCache()
        {
            $prefetchActions = $this->getPrefetchActions();
            foreach ($prefetchActions as $class => $classActions) {
                (new ClassCacheEntry(
                    $this->cacheDir,
                    $this->mappingFileLoader,
                    $this->initializer,
                    $class
                ))->cache($classActions);
            }
        }
    }
