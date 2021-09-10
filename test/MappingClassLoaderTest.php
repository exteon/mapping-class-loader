<?php

    namespace Test\Exteon\Loader\MappingClassLoader;

    use ErrorException;
    use Exception;
    use Exteon\FileHelper;
    use Exteon\Loader\MappingClassLoader\StaticInitializer\ClassInitMethodInitializer;
    use Exteon\Loader\MappingClassLoader\MappingClassLoader;
    use Exteon\Loader\MappingClassLoader\StreamWrapLoader;
    use PHPUnit\Framework\TestCase;
    use Test\Exteon\Loader\MappingClassLoader\Props\SourceChangeResolver;
    use Test\Exteon\Loader\MappingClassLoader\PropsRoot\Bar;
    use Test\Exteon\Loader\MappingClassLoader\PropsRoot\Chain\Ns2\A;
    use Test\Exteon\Loader\MappingClassLoader\PropsRoot\Foo;

    /**
     * @runTestsInSeparateProcesses
     */
    class MappingClassLoaderTest extends TestCase
    {
        /** @var MappingClassLoader */
        protected $mappingClassLoader;

        /** @var SourceChangeResolver */
        protected $sourceChangeResolver;

        public function testInitialRun()
        {
            $this->makeClean();
            $foo = new Foo();
            self::assertTrue(
                $this->sourceChangeResolver->didResolveClass(Foo::class)
            );
            self::assertEquals('~~~[replaced-value]~~~', $foo->getValue());
            self::assertFileExists(
                $this->sourceChangeResolver::classNameToCacheFilename(
                    Foo::class
                )
            );
            $bar = new Bar();
            self::assertEquals(1, Foo::isClassInit());
            self::assertEquals(0, Bar::isClassInit());
        }

        protected function makeClean(): void
        {
            if (
                !FileHelper::rmDir(
                    $this->sourceChangeResolver::getCacheDirectory(),
                    false
                )
            ) {
                throw new Exception(
                    'Cannot delete directory ' .
                    $this->sourceChangeResolver::getCacheDirectory()
                );
            }
        }

        /**
         * @depends testInitialRun
         */
        public function testSubsequentRun()
        {
            $foo = new Foo();
            self::assertFalse(
                $this->sourceChangeResolver->didResolveClass(Foo::class)
            );
        }

        /**
         * @depends testSubsequentRun
         */
        public function testXdebug()
        {
            $this->checkXdebug();
            $foo = new Foo();
            $file = $foo->getXdebugFilePath();
            self::assertEquals(
                $this->sourceChangeResolver::classNameToSourceFilename(
                    Foo::class
                ),
                $file
            );
        }

        protected static function checkXdebug(): void
        {
            if (!extension_loaded('xdebug')) {
                self::markTestSkipped('Extension xdebug is not enabled');
            }
            $modeString = ini_get('xdebug.mode');
            $modes = explode(',', $modeString);
            if (
                !in_array('debug', $modes) ||
                !in_array('develop', $modes)
            ) {
                self::markTestSkipped(
                    "Extension xdebug must have setting\nxdebug.mode=debug,devel\nin order to test this functionality.\nMaybe set the following environment variable:\nXDEBUG_MODE=debug,develop"
                );
            }
        }

        /**
         * @depends testSubsequentRun
         * @doesNotPerformAssertions
         */
        public function testXdebugBreakpoint()
        {
            $this->checkXdebug();
            $foo = new Foo();
            if (!$foo->testXDebug()) {
                self::markTestSkipped(
                    "In order to run manual xdebug test, you must have a debugger listening for xdebug connections"
                );
            }
        }

        /**
         * @throws ErrorException
         */
        public function setUp(): void
        {
            parent::setUpBeforeClass();
            $this->sourceChangeResolver = new SourceChangeResolver();
            $this->mappingClassLoader = new MappingClassLoader(
                [
                    'enableCaching' => true,
                    'cacheDir' => $this->sourceChangeResolver::getCacheDirectory(
                    )
                ],
                [$this->sourceChangeResolver],
                new ClassInitMethodInitializer(),
                new StreamWrapLoader(
                    [
                        'enableMapping' => true
                    ]
                )
            );
            $this->mappingClassLoader->register();
        }
    }
