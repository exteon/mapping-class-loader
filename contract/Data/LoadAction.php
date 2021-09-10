<?php

    namespace Exteon\Loader\MappingClassLoader\Data;

    class LoadAction
    {
        /** @var string */
        private $class;

        /** @var string|null */
        private $file;

        /** @var string|null */
        private $source;

        /** @var string */
        private $hintCode;

        public function __construct(
            string $class,
            ?string $file,
            ?string $source = null,
            ?string $hintCode = null
        ) {
            $this->class = $class;
            $this->file = $file;
            $this->source = $source;
            $this->hintCode = $hintCode;
        }

        /**
         * @return string
         */
        public function getClass(): string
        {
            return $this->class;
        }

        /**
         * @return string|null
         */
        public function getFile(): ?string
        {
            return $this->file;
        }

        /**
         * @return string|null
         */
        public function getSource(): ?string
        {
            return $this->source;
        }

        public function getHintCode(): ?string
        {
            return $this->hintCode;
        }
    }
