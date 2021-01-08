<?php

    namespace Exteon\Loader\MappingClassLoader;

    class LoadAction
    {
        /** @var string */
        protected $class;

        /** @var string|null */
        protected $file;

        /** @var string|null */
        protected $source;

        /** @var string */
        protected $hintCode;

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
