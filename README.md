# MappingClassLoader

## Abstract

When loading classes, there are occasions where we need to do source code
manipulation before loading, mainly for weaving classes in the context of AOP or
creating class proxies for other purposes. One of the most common operations is
rewriting class ancestry before loading the class.

One of the problems with this approach is maintaining debugging capabilities
with Xdebug, so that the modified interpreted source file is step debugged
against the original source.

`MappingClassLoader` is an advanced class loader framework providing the
following functionalities:

* Class resolvers with the ability to provide modified source code for the
  classes being loaded
* Caching for the modified source files
* Enabling debug via mapping of the modified sources to the original source
  files using stream wrappers
* Static class initializers that allow implementing static constructors or
  static dependency injection

## Requirements

* PHP 7.2

## Usage

### Installing with `composer`

```shell script
composer require exteon/mapping-class-loader
```

## Class resolvers

`MappingClassLoader` has a modular design allowing the implementation of
multiple resolvers; a resolver is the first thing to implement in order to load
classes.

Resolvers implement `IclassResolver` to resolve a requested class name to one or
more `LoadAction`'s. A `LoadAction` is the identification of a source code to
load, which can be one of the three types:

- Pure code: only provides the `source` property containing the code to be
  evaluated
- Source file: only provides the `file` property
- Modified, mappable source file: provides both a `file` property to identify
  the original source file and a `source` property containing the modified
  source code to be evaluated

**Example**

*A.php*

```php
<?php
    class A {
        public function doSomething(string $what): void
        {
            // ...
        }
    }
```

*Resolver.php*

```php
<?php
    use Exteon\Loader\MappingClassLoader\IClassResolver;
    use Exteon\Loader\MappingClassLoader\LoadAction;

    class Resolver implements IClassResolver {
        function resolveClass(string $class) : array{
            $loadActions = [];
            $sourceFile = $class . '.php';
            $sourceCode = file_get_contents($sourceFile);
            
            // Rename i.e. A to A_proxied
            $proxiedClass = $class.'_proxied';
            $modifiedCode = preg_replace(
                '(/class\\s+)('.preg_quote($class,'/').')(\\s)/', 
                '$1'.$proxiedClass.'$3',
                $sourceCode
            );
            
            $loadActions[] = new LoadAction(
                $proxiedClass, 
                $sourceFile, 
                $modifiedCode
            );
            
            $proxyCode = '
<?php
class ' . $class . ' extends ' . $proxiedClass. '
{
    // ... specific generated proxy code ...
}
            ';
            
            $loadActions[] = new LoadAction(
                $class,
                null,
                $proxyCode
            ); 
        }
    }
```
*main.php*

```php
<?php
    use Exteon\Loader\MappingClassLoader\MappingClassLoader;
    use Exteon\Loader\MappingClassLoader\StreamWrapLoader;
    
    $loader = new MappingClassLoader(
        [],
        [new Resolver()],
        [],
        new StreamWrapLoader([])
    );

    $loader->register();

    (new A())->doSomething('anything');
```
As you can see in the example above, the `Resolver` returns 2 `LoadAction`s, one
for the modified proxied class, and one for the proxying class.

**Note** that because of this, and for caching (see below), every `LoadAction` 
the resolver returns must specify the fully qualified class the `LoadAction` 
applies to (the first constructor parameter). 

### `LoadAction`'s

`LoadAction`'s are immutables returned by resolvers specifying what to load for
the searched class. The `LoadAction` constructor is as follows:

```php
  public function __construct(
    string $class, 
    ?string $file, 
    ?string $source = null, 
    ?string $hintCode = null
  );
```

The fields have the following meanings:
- `$class` must always be provided, even if it's just an echo of the searched 
  class (if a single `LoadAction` is generated)
- `$file` the source file the class is to be loaded from or the file upon which 
  the modified source is based, if `$source` is also specified. When both 
  `$file` and `$source` are specified, the modified code is mapped to the source 
  file for debugging purposes.
  
  `$file` can be null; in this case `$source` must be present and this setup
  signifies we are loading purely generated code.
- `$source` is the generated or modified source code to be loaded. If this is 
  null, `$file` must be specified and the meaning is that `$file` will be loaded
  without further processing (or, in other words, conventional loader behavior).
- `$hintCode` : for generated code, there is sometimes the need to generate some
  hint classes for the development tools (i.e. developer's GUI or static 
  analysers). This property provides that code, which can be dumped to a 
  directory using `MappingClassLoader::dumpHintClasses()`. 
  
### `IClassScanner`

Resolvers can (not required but desirable) implement the `IClassScanner` 
interface to enable functions such as cache pregeneration and hint file dumping.

The interface has one method, `scanClasses()` which needs to return an array
of the class names that can be resolved by the resolver.

## Caching

As the code modification/generation might be expensive, `MappingClassLoader`
provides a caching mechanism for the source files. To enable the caching 
mechanism, the `enableCaching` and `cacheDir` parameters need to be passed to
the `MappingClassLoader` constructor, like this:

```php
    use Exteon\Loader\MappingClassLoader\MappingClassLoader;
    use Exteon\Loader\MappingClassLoader\StreamWrapLoader;
    
    $loader = new MappingClassLoader(
            [
            'enableCaching' => true,
            'cacheDir' => '/tmp/caching'
        ],
        [new Resolver()],
        [],
        new StreamWrapLoader([])
    );

    $loader->register();
```

The `cacheDir` must point to a directory that the script can create or write to.
The sources for the `LoadAction`s that specify a `source` property are stored
in files under this directory following PSR-4 structure.

To clear the cache, you can use one of the methods 
`MappingClassLoader::clearCache()` or 
`MappingClassLoader::clearSpecificClasses()`.

### Pregenerating cache (priming)

Using `MappingClassLoader::primeCache()`, the cache can be generated. The cache
will be generated only for the resolvers that implement the `IClassScanner`
interface, for the classes returned by the resolver's `scanClasses()` method.

## Debug mapping

To enable step debugging the modified files with XDebug, we use stream wrapping
to include the modified sources. The stream wrapper maps to the path of the 
original script.

*Note* This will only make sense if any modification you make to the original 
source file preserves the line numbers and the source is largely similar. There 
is no full mapping of the modified file to the source file, that is not 
possible, only the file name is mapped. When step debugging you will actually 
see the original source file, and any modifications will be hidden; so this is 
only ideal if you are making small changes in your resolver, like modifying the 
class hierarchy.

To enable this feature, you must pass the `enableMapping` config parameter
to the `StreamWrapLoader` constructor like so:

```php
    use Exteon\Loader\MappingClassLoader\MappingClassLoader;
    use Exteon\Loader\MappingClassLoader\StreamWrapLoader;
    
    $loader = new MappingClassLoader(
        [],
        [new Resolver()],
        [],
        new StreamWrapLoader([
            'enableMapping' => true
        ])
    );

    $loader->register();
```

## Static initializers

In order to provide static constructor, or static dependency injection behavior.

_(When you read "static" here it's in the context of static class properties and 
methods; the classes are initialized dynamically at load-time, it's not static 
in the sense of some immutable source code)_

The `MappingClassLoader`'s constructor has an `$initializers` parameter, where
you can load an array of class initializers. Class initializers must implement 
the `IStaticInitializer` interface, implementing the `init($class)` method. This
method will be called once the class is loaded and you can perform any 
initialisation on the class there.

There is a simple static initializer included, the `ClassInitMethodInitializer`.
This initializer calls the `classInit()` static method on any loaded class, 
provided it implements the `IClassMethodInitializable` interface. Therefore,
the static `classInit` method will act like a static parameterless constructor 
for the class.

**Example**

*A.php*

```php
<?php
    use Exteon\Loader\MappingClassLoader\StaticInitializer\IClassInitMethodInitializable;
    
    class A  implements IClassInitMethodInitializable {
        protected static $someClassStaticProperty;
        
        public static function classInit() : void{
            self::$someClassStaticProperty = 'I am initialized now';
        }
        
        function getStaticProperty(){
            return self::$someClassStaticProperty;
        }
    }
```

*main.php*

```php
<?php
    use Exteon\Loader\MappingClassLoader\MappingClassLoader;
    use Exteon\Loader\MappingClassLoader\StaticInitializer\ClassInitMethodInitializer;use Exteon\Loader\MappingClassLoader\StreamWrapLoader;
    
    $loader = new MappingClassLoader(
        [],
        [new Resolver()],
        [new ClassInitMethodInitializer()],
        new StreamWrapLoader([])
    );

    $loader->register();
    
    var_dump((new A())->getStaticProperty());
```

**Note** there will be no overriding behavior of the static `classInit()` 
method as this would be semantically inconsistent. This means, if you have:

```php
  class B extends A {
        protected static $someOtherStaticProperty;
  
        public static function classInit() : void{
            self::$someClassStaticProperty = 'Other property is initialized';
        }
  }
```

Then both `A::classInit()` and `B::classInit()` will be called (in this order, 
because `A` will always have to be loaded before `B`). Therefore, don't call
`parent::classInit()` in `B::classInit()`. The parent method will have already 
been called.

This also means that you cannot, for example, avoid calling `A::classInit()`
when `B` is loaded. This is because, on the one hand, multiple classes may 
inherit from `A` which all would be overriding the same static behavior, and 
also because classes descendant from `A` may never get to be loaded, but `A` 
expects to be initialized anyway. So override behavior makes no semantic sense 
here.

By implementing your own `IStaticInitializer`, you could introduce more advanced
features such as static dependency injection.

## Hint files

The `hintCode` property can be set for any `LoadAction` to define code that 
is not runtime code, but that serves auxiliary tools. Especially for generated
classes, this code can be used to provide a class hint about the class 
composition.

Class hints can be dumped to a directory using 
```php
MappingClassLoader::dumpHintClasses($dir);
```
Every class' hint code will be dumped to a separate file in $dir, using a PSR-4
structure.

In order for this functionality to work, resolvers that provide hint code must
also implement the `IClassResolver` interface, so that the loader knows which
classes the hint files must be generated for.

## More examples

To see how this class loader is used to implement a weaving class loader 
framework for PHP modular plugins providing class chaining, you can take a look
at 
[exteon/chaining-class-resolver](https://github.com/exteon/chaining-class-resolver).

You can see there an implementation of an advanced custom resolver making use of
most of the features of `mapping-class-loader`.