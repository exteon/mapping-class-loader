<?php
    namespace Exteon\Loader\MappingClassLoader;

    class CachedClassMeta
    {
        /** @var string|null */
        private $includeFile;

        /** @var string[] */
        private $classChain;

        /**
         * @param string|null $includeFile
         * @param string[] $classChain
         */
        public function __construct(?string $includeFile, array $classChain){
            $this->includeFile = $includeFile;
            $this->classChain = $classChain;
        }

        public static function fromArray(array $array): self {
            return new self(
                $array['include'] ?? null,
                $array['classChain'] ?? []
            );
        }

        public function toArray(): array {
            $result = [];
            if($this->includeFile !== null){
                $result['include'] = $this->includeFile;
            }
            if($this->classChain){
                $result['classChain'] = $this->classChain;
            }
            return $result;
        }

        /**
         * @return string|null
         */
        public function getIncludeFile(): ?string
        {
            return $this->includeFile;
        }

        /**
         * @return string[]
         */
        public function getClassChain(): array
        {
            return $this->classChain;
        }
    }