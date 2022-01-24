<?php

    namespace Test\Exteon\Loader\MappingClassLoader;

    use ErrorException;
    use Exception;
    use Exteon\FileHelper;
    use Exteon\Loader\MappingClassLoader\StaticInitializer\ClassInitMethodInitializer;
    use Exteon\Loader\MappingClassLoader\MappingClassLoader;
    use Exteon\Loader\MappingClassLoader\StreamWrapLoader;
    use PHPUnit\Framework\TestCase;
    use Test\Exteon\Loader\MappingClassLoader\Props\ChainingResolver;
    use Test\Exteon\Loader\MappingClassLoader\Props\SourceChangeResolver;
    use Test\Exteon\Loader\MappingClassLoader\PropsRoot\Bar;
    use Test\Exteon\Loader\MappingClassLoader\PropsRoot\Chain\Ns2\A;
    use Test\Exteon\Loader\MappingClassLoader\PropsRoot\Foo;

    /**
     * @runTestsInSeparateProcesses
     */
    class ChainingTest extends TestCase
    {
        protected MappingClassLoader $mappingClassLoader;
        protected ChainingResolver $resolver;

        /**
         * @throws ErrorException
         */
        public function setUp(): void
        {
            parent::setUpBeforeClass();
            $this->resolver = new ChainingResolver();
            $this->mappingClassLoader = new MappingClassLoader(
                [
                    'enableCaching' => true,
                    'cacheDir' => $this->resolver::getCacheDirectory()
                ],
                [$this->resolver],
                null,
                new StreamWrapLoader([])
            );
            $this->mappingClassLoader->register();
        }

        /**
         * @throws Exception
         */
        public function testClearSpecificClasses(): void
        {
            $this->makeClean();
            $aClass = ChainingResolver::ROOT_NS . '\\a';
            $a1Class = ChainingResolver::ROOT_NS . '\\Ns1\a';
            $a2Class = ChainingResolver::ROOT_NS . '\\Ns2\a';
            new $aClass;
            self::assertFileExists(
                $this->resolver::classNameToCacheFilename(
                    $aClass
                )
            );
            self::assertFileExists(
                $this->resolver::classNameToCacheFilename(
                    $a1Class
                )
            );
            self::assertFileExists(
                $this->resolver::classNameToCacheFilename(
                    $a2Class
                )
            );
            $this->mappingClassLoader->clearSpecificClasses([$aClass]);
            self::assertFileNotExists(
                $this->resolver::classNameToCacheFilename(
                    $aClass
                )
            );
            self::assertFileNotExists(
                $this->resolver::classNameToCacheFilename(
                    $a1Class
                )
            );
            self::assertFileNotExists(
                $this->resolver::classNameToCacheFilename(
                    $a2Class
                )
            );
        }

        /**
         * @throws Exception
         */
        protected function makeClean(): void
        {
            if (
                !FileHelper::rmDir(
                    $this->resolver::getCacheDirectory(),
                    false
                )
            ) {
                throw new Exception(
                    'Cannot delete directory ' .
                    $this->resolver::getCacheDirectory()
                );
            }
        }
    }
