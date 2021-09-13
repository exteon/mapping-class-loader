### 3.0.1

#### Bugfixes

* Fix static class initializations

# 3.0.0

#### Changes

* `MappingClassLoader` signature changed: it now allows a single 
  `StaticInitializer` instead of an array. Use `MultiStaticInitializer` to
  multiplex to multiple initializers.
* Contract interfaces renamed: no more leading `I`
* Proper encapsulation: what was `protected` is now `private`
* `LoadAction` moved to `Data` sub-namespace

#### Bugfixes

* `MappingClassLoader::clearSpecificClasses()` now deletes the entire resolved 
  chain, not just the final class

### 2.1.2

#### Bugfixes

* Make `MappingClassLoader::clearCache()` work even when not caching

## 2.1.0

#### New functionality
* `IClassScanner` functionality
*  Class code hints using `MappingClassLoader::dumpHintClasses()`
*  Cache priming using `MappingClassLoader::primeCache()`
