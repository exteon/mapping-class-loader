<?php

namespace Exteon\Loader\MappingClassLoader\StaticInitializer;

use Exteon\Loader\MappingClassLoader\IStaticInitializer;
use ReflectionClass;
use ReflectionException;

class ClassInitMethodInitializer implements IStaticInitializer
{
    /** @array<string,null> */
    protected static $initClasses = [];

    /**
     * @param string $class
     * @throws ReflectionException
     */
    public function init(string $class): void
    {
        if (!array_key_exists($class, self::$initClasses)) {
            if (
                class_exists($class, false) &&
                is_a($class, IClassInitMethodInitializable::class, true)
            ) {
                $reflection = new ReflectionClass($class);
                if ($reflection->hasMethod('classInit')) {
                    $method = $reflection->getMethod('classInit');
                    if ($method->class == $class) {
                        $class::classInit();
                    }
                }
                self::$initClasses[$class] = null;
            }
        }
    }
}