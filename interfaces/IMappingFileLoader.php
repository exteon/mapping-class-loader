<?php

namespace Exteon\Loader\MappingClassLoader;

interface IMappingFileLoader
{
    /**
     * @param string $code
     * @param string|null $mapToFile
     */
    public function doEval(string $code, string $mapToFile = null);

    /**
     * @param string $file
     * @param string $mapToFile
     */
    public function doIncludeOnce(string $file, string $mapToFile);
}