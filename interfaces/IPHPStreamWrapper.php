<?php

namespace Exteon\Loader\MappingClassLoader;

/**
 * Based on https://www.php.net/manual/en/class.streamwrapper.php,
 * this is the minimal interface that needs to be implemented for PHP's
 * include() operation on a stream.
 */
interface IPHPStreamWrapper
{
    /**
     * @param string $path
     * @param string $mode
     * @param int $options
     * @param $opened_path
     * @return bool
     */
    public function stream_open(
        string $path,
        string $mode,
        int $options,
        &$opened_path
    );

    /**
     * @param int $count
     * @return string
     */
    public function stream_read(int $count): string;

    /**
     * @return bool
     */
    public function stream_eof(): bool;

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
     */
    public function url_stat(string $path, $flags): array;

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
     */
    public function stream_stat(): array;

    public function stream_close(): void;
}