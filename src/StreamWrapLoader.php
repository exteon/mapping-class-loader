<?php

    namespace Exteon\Loader\MappingClassLoader;

    use Exception;

    class StreamWrapLoader implements MappingFileLoader
    {
        /**
         * @var array {
         *      enableMapping: bool
         * }
         */
        private $config;

        /**
         * StreamWrapLoader constructor.
         * @param array {
         *      enableMapping: bool
         * } $config
         */
        public function __construct(array $config)
        {
            $this->config = $config;
            stream_wrapper_register(
                StreamWrapper::URL_SCHEME_EVAL,
                StreamWrapper::class
            );
            stream_wrapper_register(
                StreamWrapper::URL_SCHEME_INCLUDE,
                StreamWrapper::class
            );
        }

        /**
         * @param string $code
         * @param string|null $mapToFile
         * @throws Exception
         */
        public function eval(string $code, string $mapToFile = null)
        {
            if (
                $mapToFile &&
                ($this->config['enableMapping'] ?? false)
            ) {
                if (!$mapToFile) {
                    $mapToFile =
                        StreamWrapper::URL_EVAL_INLINE_HOST .
                        '/' .
                        StreamWrapper::getUid();
                }
                StreamWrapper::setFragment($mapToFile, $code);
                include(StreamWrapper::URL_SCHEME_EVAL . '://-/' . $mapToFile);
            } else {
                $includePath = set_include_path(
                    get_include_path() .
                    PATH_SEPARATOR .
                    dirname($mapToFile)
                );
                if (preg_match('`\\?>\\s*$`s', $code)) {
                    eval('?>' . $code . '<?php ');
                } else {
                    eval('?>' . $code);
                }
                set_include_path($includePath);
            }
        }

        /**
         * @param string $file
         * @param string $mapToFile
         */
        public function includeOnce(string $file, string $mapToFile)
        {
            if (
                $mapToFile &&
                ($this->config['enableMapping'] ?? false)
            ) {
                include(
                    StreamWrapper::URL_SCHEME_INCLUDE .
                    '://-/' .
                    $file .
                    ',' .
                    $mapToFile
                );
            } else {
                $includePath = set_include_path(
                    get_include_path() . PATH_SEPARATOR . dirname($file)
                );
                include_once($file);
                set_include_path($includePath);
            }
        }
    }
