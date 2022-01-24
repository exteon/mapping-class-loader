<?php

    namespace Exteon\Loader\MappingClassLoader;

    use ErrorException;
    use Exception;
    use Exteon\FileHelper;
    use Exteon\Loader\MappingClassLoader\Data\LoadAction;
    use JetBrains\PhpStorm\Pure;

    class ClassCacheEntry
    {
        private const
            MAP_FILE_SUFFIX = '.map',
            PHP_FILE_SUFFIX = '.php',
            META_FILE_SUFFIX = '.meta.php';

        private string $cacheDir;
        private string $class;
        private MappingFileLoader $mappingFileLoader;
        private ?StaticInitializer $initializer;

        public function __construct(
            string $cacheDir,
            MappingFileLoader $mappingFileLoader,
            ?StaticInitializer $initializer,
            string $class
        ) {
            $this->cacheDir = $cacheDir;
            $this->class = $class;
            $this->mappingFileLoader = $mappingFileLoader;
            $this->initializer = $initializer;
        }

        /**
         * @param LoadAction[] $chain
         * @throws ErrorException
         */
        public function cacheAndLoad(array $chain): void
        {
            $this->internalCacheAndLoad($chain, true);
        }

        /**
         * @param LoadAction[] $chain
         * @throws ErrorException
         */
        public function internalCacheAndLoad(array $chain, bool $doLoad): void
        {
            $myLoadAction = array_pop($chain);
            $myChain = $chain;
            while ($next = array_shift($chain)) {
                $this->getClassCacheEntry(
                    $next->getClass()
                )->singleActionCacheAndLoad(
                    $next,
                    $chain,
                    $doLoad
                );
            }
            $this->singleActionCacheAndLoad($myLoadAction, $myChain, $doLoad);
        }

        /**
         * @param LoadAction[] $chain
         * @throws ErrorException
         * @throws Exception
         */
        private function singleActionCacheAndLoad(
            LoadAction $loadAction,
            array $chain,
            bool $doLoad
        ): void {
            if ($loadAction->getClass() !== $this->class) {
                throw new ErrorException('Class mismatch');
            }
            $file = $loadAction->getFile();
            if ($loadAction->getSource()) {
                $classFilePath = $this->getClassFilePath();
                $this->cacheContentToFile(
                    $classFilePath,
                    $loadAction->getSource()
                );
                if ($file) {
                    $this->cacheContentToFile($this->getMapFilePath(), $file);
                    if ($doLoad) {
                        $this->mappingFileLoader->includeOnce(
                            $classFilePath,
                            $file
                        );
                    }
                } else {
                    if ($doLoad) {
                        require_once($classFilePath);
                    }
                }
                $include = null;
            } else {
                if ($doLoad) {
                    require_once($file);
                }
                $include = $file;
            }
            if (
                $doLoad &&
                $this->initializer
            ) {
                $this->initializer->init($this->class);
            }
            $meta = new CachedClassMeta(
                $include,
                array_map(
                    function (LoadAction $loadAction): string {
                        return $loadAction->getClass();
                    },
                    $chain
                )
            );
            $metaArray = $meta->toArray();
            $this->cacheContentToFile(
                $this->getMetaFilePath(),
                '<?php return ' . var_export($metaArray, true) . ';'
            );
        }

        /**
         * @return string
         */
        private function getClassFilePath(): string
        {
            return ClassHelper::getClassFilePath(
                $this->class,
                $this->cacheDir,
                self::PHP_FILE_SUFFIX
            );
        }

        /**
         * @param string $filePath
         * @param string $contents
         * @throws Exception
         */
        private function cacheContentToFile(
            string $filePath,
            string $contents
        ) {
            if (!FileHelper::preparePath($filePath, true)) {
                throw new Exception("Cannot create directory");
            }
            if (!file_put_contents($filePath, $contents)) {
                throw new Exception("Cannot write file");
            }
        }

        /**
         * @return string
         */
        private function getMapFilePath(): string
        {
            return ClassHelper::getClassFilePath(
                $this->class,
                $this->cacheDir,
                self::MAP_FILE_SUFFIX
            );
        }

        /**
         * @return string
         */
        private function getMetaFilePath(): string
        {
            return ClassHelper::getClassFilePath(
                $this->class,
                $this->cacheDir,
                self::META_FILE_SUFFIX
            );
        }

        /**
         * @param string $class
         * @return ClassCacheEntry
         */
        #[Pure]
        private function getClassCacheEntry(string $class): ClassCacheEntry
        {
            return new self(
                $this->cacheDir,
                $this->mappingFileLoader,
                $this->initializer,
                $class
            );
        }

        /**
         * @param LoadAction[] $chain
         * @throws ErrorException
         */
        public function cache(array $chain): void
        {
            $this->internalCacheAndLoad($chain, false);
        }

        public function load(): bool
        {
            $classFilePath = $this->getClassFilePath();
            if (file_exists($classFilePath)) {
                $mapFilePath = $this->getMapFilePath();
                if (file_exists($mapFilePath)) {
                    $origFile = file_get_contents($mapFilePath);
                    $this->mappingFileLoader->includeOnce(
                        $classFilePath,
                        $origFile
                    );
                } else {
                    require_once($classFilePath);
                }
                $this->initializer?->init($this->class);
                return true;
            } else {
                $meta = $this->getMeta();
                if ($meta) {
                    $includeFile = $meta->getIncludeFile();
                    if ($includeFile) {
                        require_once($includeFile);
                        $this->initializer?->init($this->class);
                        return true;
                    }
                }
            }
            return false;
        }

        public function getMeta(): ?CachedClassMeta
        {
            $metaFilePath = $this->getMetaFilePath();
            if (file_exists($metaFilePath)) {
                $array = require($metaFilePath);
                return CachedClassMeta::fromArray($array);
            }
            return null;
        }

        /**
         * @throws Exception
         */
        public function purge(): void
        {
            $meta = $this->getMeta();
            // temporal coupling: do AFTER getMeta()
            $this->purgeSingle();

            if ($meta) {
                foreach ($meta->getClassChain() as $class) {
                    $this->getClassCacheEntry($class)->purgeSingle();
                }
            }
        }

        /**
         * @throws Exception
         */
        private function purgeSingle(): void
        {
            $classFilePath = $this->getClassFilePath();
            $mapFilePath = $this->getMapFilePath();
            $metaFilePath = $this->getMetaFilePath();
            if (
                (
                    file_exists($classFilePath) &&
                    !unlink($classFilePath)
                ) ||
                (
                    file_exists($mapFilePath) &&
                    !unlink($mapFilePath)
                ) ||
                (
                    file_exists($metaFilePath) &&
                    !unlink($metaFilePath)
                )
            ) {
                throw new Exception('Cannot delete file');
            }
        }

    }