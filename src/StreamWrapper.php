<?php

    namespace Exteon\Loader\MappingClassLoader;

    use ErrorException;
    use Exception;

    class StreamWrapper implements PHPStreamWrapper
    {
        public const
            URL_SCHEME_EVAL = 'exteon-loader-eval',
            URL_SCHEME_INCLUDE = 'exteon-loader-include',
            URL_EVAL_INLINE_HOST = 'INLINE';

        private const
            MODE_INCLUDE = 1,
            MODE_EVAL = 2;

        /** @var int */
        private $pos;

        /** @var string */
        private $fileRef;

        /** @var string */
        private $realFileRef;

        /** @var int */
        private $mode;

        /** @var string */
        private $file;

        /** @var resource */
        private $handle;

        /** @var null|array {
         *      dev: int,
         *      ino: int,
         *      mode: int,
         *      nlink:int,
         *      uid: int,
         *      gid: int,
         *      rdev: int,
         *      size: int,
         *      atime: int,
         *      mtime: int,
         *      ctime: int,
         *      blksize: int,
         *      blocks: int
         *  }
         */
        private $stat = null;

        /** @var array<string,string> */
        private static $fragments = [];

        /** @var int */
        private static $uid;

        /**
         * @param string $path
         * @param string $mode
         * @param int $options
         * @param $opened_path
         * @return bool
         * @throws Exception
         */
        public function stream_open(
            string $path,
            string $mode,
            int $options,
            &$opened_path
        ): bool {
            $parsed = parse_url($path);
            if (
                $parsed['scheme'] === self::URL_SCHEME_EVAL
            ) {
                $this->mode = self::MODE_EVAL;
                $this->fileRef = static::getFullPath($parsed);
                if (
                    $parsed['host'] !== self::URL_EVAL_INLINE_HOST
                ) {
                    $this->realFileRef = $this->fileRef;
                }
            } elseif (
                $parsed['scheme'] === self::URL_SCHEME_INCLUDE
            ) {
                $fullPath = static::getFullPath($parsed);
                $components = explode(',', $fullPath, 2);
                if (count($components) !== 2) {
                    throw new Exception('Invalid web3EvalWrapper URL');
                }
                $this->mode = self::MODE_INCLUDE;
                $this->fileRef = $this->realFileRef = $components[1];
                $this->file = $components[0];
            } else {
                throw new Exception('Invalid web3EvalWrapper URL');
            }
            if ($this->fileRef) {
                $opened_path = realPath($this->fileRef);
            }
            switch ($this->mode) {
                case self::MODE_EVAL:
                    if (!array_key_exists($this->fileRef, self::$fragments)) {
                        throw new Exception('Invalid web3EvalWrapper URL');
                    }
                    $this->pos = 0;
                    break;
                case self::MODE_INCLUDE:
                    $this->handle = fopen($this->file, 'r');
                    break;
                default:
                    throw new ErrorException('Unknown operation mode');
            }
            return true;
        }

        /**
         * @param int $count
         * @return string
         * @throws ErrorException
         */
        public function stream_read(int $count): string
        {
            switch ($this->mode) {
                case self::MODE_EVAL:
                    $ret = substr(
                        self::$fragments[$this->fileRef],
                        $this->pos,
                        $count
                    );
                    $this->pos += strlen($ret);
                    return $ret;
                case self::MODE_INCLUDE:
                    return fread($this->handle, $count);
                default:
                    throw new ErrorException('Unknown operation mode');
            }
        }

        /**
         * @return bool
         * @throws ErrorException
         */
        public function stream_eof(): bool
        {
            switch ($this->mode) {
                case self::MODE_EVAL:
                    return $this->pos >=
                        strlen(self::$fragments[$this->fileRef]);
                case self::MODE_INCLUDE:
                    return feof($this->handle);
                default:
                    throw new ErrorException('Unknown operation mode');
            }
        }

        /**
         * @throws ErrorException
         */
        private function initStat()
        {
            if ($this->stat === null) {
                switch ($this->mode) {
                    case self::MODE_INCLUDE:
                        $this->stat = stat($this->file);
                        break;
                    case self::MODE_EVAL:
                        if ($this->realFileRef) {
                            $this->stat = stat($this->realFileRef);
                        } else {
                            $this->stat = [];
                        }
                        $this->stat[7] = $this->stat['size'] = strlen(
                            self::$fragments[$this->fileRef]
                        );
                        break;
                    default:
                        throw new ErrorException('Unknown operation mode');
                }
            }
        }

        /**
         * @param string $path
         * @param $flags
         * @return array {
         *      dev: int,
         *      ino: int,
         *      mode: int,
         *      nlink:int,
         *      uid: int,
         *      gid: int,
         *      rdev: int,
         *      size: int,
         *      atime: int,
         *      mtime: int,
         *      ctime: int,
         *      blksize: int,
         *      blocks: int
         *  }
         * @throws ErrorException
         */
        public function url_stat(string $path, $flags): array
        {
            $this->initStat();
            return $this->stat;
        }

        /**
         * @return array {
         *      dev: int,
         *      ino: int,
         *      mode: int,
         *      nlink:int,
         *      uid: int,
         *      gid: int,
         *      rdev: int,
         *      size: int,
         *      atime: int,
         *      mtime: int,
         *      ctime: int,
         *      blksize: int,
         *      blocks: int
         *  }
         * @throws ErrorException
         */
        public function stream_stat(): array
        {
            $this->initStat();
            return $this->stat;
        }

        /**
         * @throws ErrorException
         */
        public function stream_close(): void
        {
            switch ($this->mode) {
                case self::MODE_EVAL:
                    unset(self::$fragments[$this->fileRef]);
                    break;
                case self::MODE_INCLUDE:
                    fclose($this->handle);
                    break;
                default:
                    throw new ErrorException('Unknown operation mode');
            }
        }

        /**
         * @return int
         */
        public static function getUid(): int
        {
            return self::$uid++;
        }

        /**
         * @param string $name
         * @param string $contents
         * @throws Exception
         */
        public static function setFragment(string $name, string $contents): void
        {
            if (array_key_exists($name, self::$fragments)) {
                throw new Exception("Fragment $name is already set");
            }
            self::$fragments[$name] = $contents;
        }

        /**
         * @param array {
         *      scheme: string,
         *      host: string,
         *      port: int,
         *      user: string,
         *      pass: string,
         *      path: string,
         *      query: string,
         *      fragment: string
         * } $parsedUrl
         * @return string
         */
        private static function getFullPath(array $parsedUrl): string
        {
            $path = substr($parsedUrl['path'], 1);
            if (isset($parsedUrl['query'])) {
                $path .= '?' . $parsedUrl['query'];
            }
            if (isset($parsedUrl['fragment'])) {
                $path .= '#' . $parsedUrl['fragment'];
            }
            return $path;
        }

        public function stream_set_option(int $option, int $arg1, int $arg2): bool
        {
            return false;
        }
    }
