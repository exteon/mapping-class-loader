<?php

    namespace Test\Exteon\Loader\MappingClassLoader\PropsRoot;

    use Exteon\Loader\MappingClassLoader\StaticInitializer\IClassInitMethodInitializable;

    class Foo implements IClassInitMethodInitializable
    {
        /** @var array<string,int> */
        protected static $isClassInit = [];

        public static function classInit(): void
        {
            static::$isClassInit[get_called_class()] =
                (static::$isClassInit[get_called_class()] ?? 0) + 1;
        }

        public function testXDebug(): bool
        {
            /*
             * Run the test with an IDE listening for xDebug; following the next
             * instruction, the debugger should break in the original class file
             * (test/Props/Classes/Foo.php), although the executed class is the
             * changed one
             */
            $result = xdebug_break();
            return $result;
        }

        public function getValue(): string
        {
            /*
             * The following syntax ~~~[...]~~~ is used in a regexp parser to
             * replace the value here
             */
            return '~~~[value]~~~';
        }

        public function getXdebugFilePath(): string
        {
            return xdebug_call_file(0);
        }

        public static function isClassInit(): int
        {
            return self::$isClassInit[get_called_class()] ?? 0;
        }
    }