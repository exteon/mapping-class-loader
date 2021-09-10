<?php
    namespace Exteon\Loader\MappingClassLoader\StaticInitializer;

    use Exteon\Loader\MappingClassLoader\StaticInitializer;

    class MultiStaticInitializer implements StaticInitializer
    {
        /** @var StaticInitializer[] */
        private $initializers;

        /**
         * @param StaticInitializer[] $initializers
         */
        public function __construct(array $initializers){
            $this->initializers = $initializers;
        }

        public function init(string $class): void
        {
            foreach($this->initializers as $initializer){
                $initializer->init($class);
            }
        }
    }