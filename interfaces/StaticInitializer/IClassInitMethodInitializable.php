<?php

namespace Exteon\Loader\MappingClassLoader\StaticInitializer;

/**
 * When using the @see ClassInitMethodInitializer initializer, if the loaded
 * classa implements this interface and it has an own classInit() method (not
 * inherited), the class's method will be called.
 */
interface IClassInitMethodInitializable
{
    public static function classInit(): void;
}