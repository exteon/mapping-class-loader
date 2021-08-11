<?php

    namespace Exteon\Loader\MappingClassLoader;

    use ErrorException;
    use Exception;
    use Exteon\FileHelper;
    use InvalidArgumentException;
    use ReflectionException;

    class MappingClassLoader implements IClassScanner
    {
        protected const
            MAP_FILE_SUFFIX = '.map',
            PHP_FILE_SUFFIX = '.php';

        /** @var array<string,null> */
        protected static $initClasses = [];

        /** @var IClassResolver[] */
        protected $resolvers;

        /** @var string */
        protected $cacheDir;

        /** @var bool */
        protected $isCaching;

        /** @var IMappingFileLoader */
        protected $mappingFileLoader;
        /**
         * @var IStaticInitializer[]
         */
        protected $initializers;

        /**
         * @param array $config {
         *      enableCaching: bool,
         *      cacheDir: string
         * }
         * @param IClassResolver[] $resolvers
         * @param IStaticInitializer[] $initializers
         * @param IMappingFileLoader $mappingFileLoader
         * @throws ErrorException
         */
        public function __construct(
            array $config,
            array $resolvers,
            array $initializers,
            IMappingFileLoader $mappingFileLoader
        ) {
            $this->resolvers = $resolvers;
            $this->initializers = $initializers;
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
         * @return bool
         */
        public function clearSpecificClasses(array $classes): bool
        {
            $result = true;
            foreach ($classes as $class) {
                $spec = $this->getCacheFilePath($class);
                if (file_exists($spec)) {
                    $result = $result && unlink($spec);
                }
            }
            return $result;
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
         * @throws ReflectionException
         */
        public function loadClass(string $class): void
        {
            $loaded = false;
            if ($this->isCaching) {
                $cachedFile = self::getCached($class);
                if ($cachedFile) {
                    $mapFileName =
                        FileHelper::getFileName($cachedFile) .
                        self::MAP_FILE_SUFFIX;
                    $mapFilePath = FileHelper::getDescendPath(
                        FileHelper::getAscendPath($cachedFile),
                        $mapFileName
                    );
                    if (file_exists($mapFilePath)) {
                        $file = file_get_contents($mapFilePath);
                        $this->mappingFileLoader->includeOnce(
                            $cachedFile,
                            $file
                        );
                    } else {
                        require_once($cachedFile);
                    }
                    $loaded = true;
                }
            }
            if (!$loaded) {
                $loadActions = $this->getLoadActions($class);
                if (!$loadActions) {
                    return;
                }
                foreach ($loadActions as $loadAction) {
                    $this->doAction($loadAction);
                }
            }
            $this->classInit($class);
        }

        /**
         * @param string $class
         * @throws ReflectionException
         */
        protected function classInit(string $class): void
        {
            foreach ($this->initializers as $initializer) {
                $initializer->init($class);
            }
        }

        /**
         * @param LoadAction $action
         * @throws ErrorException
         * @throws ReflectionException
         */
        protected function doAction(LoadAction $action): void
        {
            if (!$action->getClass()) {
                throw new InvalidArgumentException(
                    'class must be specified'
                );
            }
            if ($action->getSource()) {
                if ($this->isCaching) {
                    $cachePath = $this->cache($action);
                    if ($action->getFile()) {
                        $this->mappingFileLoader->includeOnce(
                            $cachePath,
                            $action->getFile()
                        );
                    } else {
                        require_once($cachePath);
                    }
                } else {
                    if ($action->getFile()) {
                        $this->mappingFileLoader->eval(
                            $action->getSource(),
                            $action->getFile()
                        );
                    } else {
                        $this->mappingFileLoader->eval($action->getSource());
                    }
                }
            } elseif ($action->getFile()) {
                require_once($action->getFile());
            } else {
                throw new InvalidArgumentException(
                    'source or file must be specified'
                );
            }
            $this->classInit($action->getClass());
        }

        /**
         * @param LoadAction $action
         * @return string The cached file path
         * @throws ErrorException
         */
        protected function cache(LoadAction $action): string
        {
            $filePath = $this->getCacheFilePath($action->getClass());
            $this->cacheToFile($filePath, $action);
            return $filePath;
        }

        /**
         * @param string $class
         * @return string|null
         */
        protected function getCached(string $class): ?string
        {
            $filePath = $this->getCacheFilePath($class);
            if (file_exists($filePath)) {
                return $filePath;
            }
            return null;
        }

        public function addResolver(IClassResolver $resolver): void
        {
            $this->resolvers[] = $resolver;
        }

        public function addInitializer(IStaticInitializer $initializer): void
        {
            $this->initializers[] = $initializer;
        }

        /**
         * @param string $class
         * @return string
         */
        protected function getCacheFilePath(string $class): string
        {
            return $this->getPathInDir($this->cacheDir, $class);
        }

        /**
         * @param string $dir
         * @param string $class
         * @return string
         */
        protected function getPathInDir(string $dir, string $class): string
        {
            $classPath = static::classNameToPath($class);
            $classPath .= self::PHP_FILE_SUFFIX;
            return FileHelper::getDescendPath($dir, $classPath);
        }

        /**
         * @param string $class
         * @return string
         */
        protected static function classNameToPath(string $class): string
        {
            return str_replace('\\', '/', $class);
        }

        /**
         * @return string[]
         */
        public function scanClasses(): array
        {
            $flippedClasses = [];
            foreach ($this->resolvers as $resolver) {
                if ($resolver instanceof IClassScanner) {
                    $flippedClasses = array_merge(
                        $flippedClasses,
                        array_flip($resolver->scanClasses())
                    );
                }
            }
            return array_keys($flippedClasses);
        }

        /**
         * @return LoadAction[]
         */
        protected function getPrefetchActions(): array
        {
            $classes = $this->scanClasses();
            $prefetchActions = [];
            foreach ($classes as $class) {
                $prefetchActions = array_merge(
                    $prefetchActions,
                    $this->getLoadActions($class)
                );
            }
            return $prefetchActions;
        }

        /**
         * @param string $class
         * @return array
         */
        protected function getLoadActions(string $class): array
        {
            $loadActions = [];
            foreach ($this->resolvers as $resolver) {
                $loadActions = array_merge(
                    $loadActions,
                    $resolver->resolveClass($class)
                );
            }
            return $loadActions;
        }

        /**
         * @param string $filePath
         * @param LoadAction $action
         * @throws ErrorException
         */
        protected function cacheToFile(
            string $filePath,
            LoadAction $action
        ): void {
            $this->cacheContentToFile($filePath, $action->getSource());
            if ($action->getFile()) {
                $mapName =
                    FileHelper::getFileName($filePath) . self::MAP_FILE_SUFFIX;
                $mapPath = FileHelper::getDescendPath(
                    FileHelper::getAscendPath($filePath),
                    $mapName
                );
                if (!file_put_contents($mapPath, $action->getFile())) {
                    throw new ErrorException("Cannot write file");
                }
            }
        }

        /**
         * @param string $filePath
         * @param string $contents
         * @throws ErrorException
         */
        protected function cacheContentToFile(
            string $filePath,
            string $contents
        ) {
            if (!FileHelper::preparePath($filePath, true)) {
                throw new ErrorException("Cannot create directory");
            }
            if (!file_put_contents($filePath, $contents)) {
                throw new ErrorException("Cannot write file");
            }
        }

        /**
         * @param string $targetDir
         * @throws ErrorException
         */
        public function dumpHintClasses(string $targetDir): void
        {
            $prefetchActions = $this->getPrefetchActions();
            foreach ($prefetchActions as $action) {
                $mockCode = $action->getHintCode();
                if ($mockCode !== null) {
                    $filePath = $this->getPathInDir(
                        $targetDir,
                        $action->getClass()
                    );
                    $this->cacheContentToFile(
                        $filePath,
                        $mockCode
                    );
                }
            }
        }

        /**
         * @throws ErrorException
         */
        public function primeCache()
        {
            $prefetchActions = $this->getPrefetchActions();
            foreach ($prefetchActions as $action) {
                $this->cache($action);
            }
        }
    }
