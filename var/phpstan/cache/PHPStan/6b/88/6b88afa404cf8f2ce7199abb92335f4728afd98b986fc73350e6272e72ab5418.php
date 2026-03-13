<?php declare(strict_types = 1);

// osfsl-C:/xampp/htdocs/san-enrique-lgu-scholarship/vendor/composer/../phpoffice/phpspreadsheet/src/PhpSpreadsheet/Writer/BaseWriter.php-PHPStan\BetterReflection\Reflection\ReflectionClass-PhpOffice\PhpSpreadsheet\Writer\BaseWriter
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-441212698f8cb22c7d6a2ecab1112bf6fc398340433fd62d2efd1e927f34c33c-8.2.12-6.65.0.9',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'PhpOffice\\PhpSpreadsheet\\Writer\\BaseWriter',
        'filename' => 'C:/xampp/htdocs/san-enrique-lgu-scholarship/vendor/composer/../phpoffice/phpspreadsheet/src/PhpSpreadsheet/Writer/BaseWriter.php',
      ),
    ),
    'namespace' => 'PhpOffice\\PhpSpreadsheet\\Writer',
    'name' => 'PhpOffice\\PhpSpreadsheet\\Writer\\BaseWriter',
    'shortName' => 'BaseWriter',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 64,
    'docComment' => NULL,
    'attributes' => 
    array (
    ),
    'startLine' => 5,
    'endLine' => 142,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => NULL,
    'implementsClassNames' => 
    array (
      0 => 'PhpOffice\\PhpSpreadsheet\\Writer\\IWriter',
    ),
    'traitClassNames' => 
    array (
    ),
    'immediateConstants' => 
    array (
    ),
    'immediateProperties' => 
    array (
      'includeCharts' => 
      array (
        'declaringClassName' => 'PhpOffice\\PhpSpreadsheet\\Writer\\BaseWriter',
        'implementingClassName' => 'PhpOffice\\PhpSpreadsheet\\Writer\\BaseWriter',
        'name' => 'includeCharts',
        'modifiers' => 2,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 11,
            'endLine' => 11,
            'startTokenPos' => 29,
            'startFilePos' => 322,
            'endTokenPos' => 29,
            'endFilePos' => 326,
          ),
        ),
        'docComment' => '/**
 * Write charts that are defined in the workbook?
 * Identifies whether the Writer should write definitions for any charts that exist in the PhpSpreadsheet object.
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 11,
        'endLine' => 11,
        'startColumn' => 5,
        'endColumn' => 42,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'preCalculateFormulas' => 
      array (
        'declaringClassName' => 'PhpOffice\\PhpSpreadsheet\\Writer\\BaseWriter',
        'implementingClassName' => 'PhpOffice\\PhpSpreadsheet\\Writer\\BaseWriter',
        'name' => 'preCalculateFormulas',
        'modifiers' => 2,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'default' => 
        array (
          'code' => 'true',
          'attributes' => 
          array (
            'startLine' => 18,
            'endLine' => 18,
            'startTokenPos' => 42,
            'startFilePos' => 640,
            'endTokenPos' => 42,
            'endFilePos' => 643,
          ),
        ),
        'docComment' => '/**
 * Pre-calculate formulas
 * Forces PhpSpreadsheet to recalculate all formulae in a workbook when saving, so that the pre-calculated values are
 * immediately available to MS Excel or other office spreadsheet viewer when opening the file.
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 18,
        'endLine' => 18,
        'startColumn' => 5,
        'endColumn' => 48,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'useDiskCaching' => 
      array (
        'declaringClassName' => 'PhpOffice\\PhpSpreadsheet\\Writer\\BaseWriter',
        'implementingClassName' => 'PhpOffice\\PhpSpreadsheet\\Writer\\BaseWriter',
        'name' => 'useDiskCaching',
        'modifiers' => 4,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 23,
            'endLine' => 23,
            'startTokenPos' => 55,
            'startFilePos' => 738,
            'endTokenPos' => 55,
            'endFilePos' => 742,
          ),
        ),
        'docComment' => '/**
 * Use disk caching where possible?
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 23,
        'endLine' => 23,
        'startColumn' => 5,
        'endColumn' => 41,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'diskCachingDirectory' => 
      array (
        'declaringClassName' => 'PhpOffice\\PhpSpreadsheet\\Writer\\BaseWriter',
        'implementingClassName' => 'PhpOffice\\PhpSpreadsheet\\Writer\\BaseWriter',
        'name' => 'diskCachingDirectory',
        'modifiers' => 4,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'default' => 
        array (
          'code' => '\'./\'',
          'attributes' => 
          array (
            'startLine' => 28,
            'endLine' => 28,
            'startTokenPos' => 68,
            'startFilePos' => 836,
            'endTokenPos' => 68,
            'endFilePos' => 839,
          ),
        ),
        'docComment' => '/**
 * Disk caching directory.
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 28,
        'endLine' => 28,
        'startColumn' => 5,
        'endColumn' => 48,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'fileHandle' => 
      array (
        'declaringClassName' => 'PhpOffice\\PhpSpreadsheet\\Writer\\BaseWriter',
        'implementingClassName' => 'PhpOffice\\PhpSpreadsheet\\Writer\\BaseWriter',
        'name' => 'fileHandle',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * @var resource
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 33,
        'endLine' => 33,
        'startColumn' => 5,
        'endColumn' => 26,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'shouldCloseFile' => 
      array (
        'declaringClassName' => 'PhpOffice\\PhpSpreadsheet\\Writer\\BaseWriter',
        'implementingClassName' => 'PhpOffice\\PhpSpreadsheet\\Writer\\BaseWriter',
        'name' => 'shouldCloseFile',
        'modifiers' => 4,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 35,
        'endLine' => 35,
        'startColumn' => 5,
        'endColumn' => 34,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
    ),
    'immediateMethods' => 
    array (
      'getIncludeCharts' => 
      array (
        'name' => 'getIncludeCharts',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 37,
        'endLine' => 40,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'PhpOffice\\PhpSpreadsheet\\Writer',
        'declaringClassName' => 'PhpOffice\\PhpSpreadsheet\\Writer\\BaseWriter',
        'implementingClassName' => 'PhpOffice\\PhpSpreadsheet\\Writer\\BaseWriter',
        'currentClassName' => 'PhpOffice\\PhpSpreadsheet\\Writer\\BaseWriter',
        'aliasName' => NULL,
      ),
      'setIncludeCharts' => 
      array (
        'name' => 'setIncludeCharts',
        'parameters' => 
        array (
          'includeCharts' => 
          array (
            'name' => 'includeCharts',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'bool',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 42,
            'endLine' => 42,
            'startColumn' => 38,
            'endColumn' => 56,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'self',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 42,
        'endLine' => 47,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'PhpOffice\\PhpSpreadsheet\\Writer',
        'declaringClassName' => 'PhpOffice\\PhpSpreadsheet\\Writer\\BaseWriter',
        'implementingClassName' => 'PhpOffice\\PhpSpreadsheet\\Writer\\BaseWriter',
        'currentClassName' => 'PhpOffice\\PhpSpreadsheet\\Writer\\BaseWriter',
        'aliasName' => NULL,
      ),
      'getPreCalculateFormulas' => 
      array (
        'name' => 'getPreCalculateFormulas',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 49,
        'endLine' => 52,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'PhpOffice\\PhpSpreadsheet\\Writer',
        'declaringClassName' => 'PhpOffice\\PhpSpreadsheet\\Writer\\BaseWriter',
        'implementingClassName' => 'PhpOffice\\PhpSpreadsheet\\Writer\\BaseWriter',
        'currentClassName' => 'PhpOffice\\PhpSpreadsheet\\Writer\\BaseWriter',
        'aliasName' => NULL,
      ),
      'setPreCalculateFormulas' => 
      array (
        'name' => 'setPreCalculateFormulas',
        'parameters' => 
        array (
          'precalculateFormulas' => 
          array (
            'name' => 'precalculateFormulas',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'bool',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 54,
            'endLine' => 54,
            'startColumn' => 45,
            'endColumn' => 70,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'self',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 54,
        'endLine' => 59,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'PhpOffice\\PhpSpreadsheet\\Writer',
        'declaringClassName' => 'PhpOffice\\PhpSpreadsheet\\Writer\\BaseWriter',
        'implementingClassName' => 'PhpOffice\\PhpSpreadsheet\\Writer\\BaseWriter',
        'currentClassName' => 'PhpOffice\\PhpSpreadsheet\\Writer\\BaseWriter',
        'aliasName' => NULL,
      ),
      'getUseDiskCaching' => 
      array (
        'name' => 'getUseDiskCaching',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 61,
        'endLine' => 64,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'PhpOffice\\PhpSpreadsheet\\Writer',
        'declaringClassName' => 'PhpOffice\\PhpSpreadsheet\\Writer\\BaseWriter',
        'implementingClassName' => 'PhpOffice\\PhpSpreadsheet\\Writer\\BaseWriter',
        'currentClassName' => 'PhpOffice\\PhpSpreadsheet\\Writer\\BaseWriter',
        'aliasName' => NULL,
      ),
      'setUseDiskCaching' => 
      array (
        'name' => 'setUseDiskCaching',
        'parameters' => 
        array (
          'useDiskCache' => 
          array (
            'name' => 'useDiskCache',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'bool',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 66,
            'endLine' => 66,
            'startColumn' => 39,
            'endColumn' => 56,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'cacheDirectory' => 
          array (
            'name' => 'cacheDirectory',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 66,
                'endLine' => 66,
                'startTokenPos' => 233,
                'startFilePos' => 1651,
                'endTokenPos' => 233,
                'endFilePos' => 1654,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionUnionType',
              'data' => 
              array (
                'types' => 
                array (
                  0 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'string',
                      'isIdentifier' => true,
                    ),
                  ),
                  1 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'null',
                      'isIdentifier' => true,
                    ),
                  ),
                ),
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 66,
            'endLine' => 66,
            'startColumn' => 59,
            'endColumn' => 88,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'self',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 66,
        'endLine' => 79,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'PhpOffice\\PhpSpreadsheet\\Writer',
        'declaringClassName' => 'PhpOffice\\PhpSpreadsheet\\Writer\\BaseWriter',
        'implementingClassName' => 'PhpOffice\\PhpSpreadsheet\\Writer\\BaseWriter',
        'currentClassName' => 'PhpOffice\\PhpSpreadsheet\\Writer\\BaseWriter',
        'aliasName' => NULL,
      ),
      'getDiskCachingDirectory' => 
      array (
        'name' => 'getDiskCachingDirectory',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 81,
        'endLine' => 84,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'PhpOffice\\PhpSpreadsheet\\Writer',
        'declaringClassName' => 'PhpOffice\\PhpSpreadsheet\\Writer\\BaseWriter',
        'implementingClassName' => 'PhpOffice\\PhpSpreadsheet\\Writer\\BaseWriter',
        'currentClassName' => 'PhpOffice\\PhpSpreadsheet\\Writer\\BaseWriter',
        'aliasName' => NULL,
      ),
      'processFlags' => 
      array (
        'name' => 'processFlags',
        'parameters' => 
        array (
          'flags' => 
          array (
            'name' => 'flags',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 86,
            'endLine' => 86,
            'startColumn' => 37,
            'endColumn' => 46,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 86,
        'endLine' => 94,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'PhpOffice\\PhpSpreadsheet\\Writer',
        'declaringClassName' => 'PhpOffice\\PhpSpreadsheet\\Writer\\BaseWriter',
        'implementingClassName' => 'PhpOffice\\PhpSpreadsheet\\Writer\\BaseWriter',
        'currentClassName' => 'PhpOffice\\PhpSpreadsheet\\Writer\\BaseWriter',
        'aliasName' => NULL,
      ),
      'openFileHandle' => 
      array (
        'name' => 'openFileHandle',
        'parameters' => 
        array (
          'filename' => 
          array (
            'name' => 'filename',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 101,
            'endLine' => 101,
            'startColumn' => 36,
            'endColumn' => 44,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Open file handle.
 *
 * @param resource|string $filename
 */',
        'startLine' => 101,
        'endLine' => 124,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'PhpOffice\\PhpSpreadsheet\\Writer',
        'declaringClassName' => 'PhpOffice\\PhpSpreadsheet\\Writer\\BaseWriter',
        'implementingClassName' => 'PhpOffice\\PhpSpreadsheet\\Writer\\BaseWriter',
        'currentClassName' => 'PhpOffice\\PhpSpreadsheet\\Writer\\BaseWriter',
        'aliasName' => NULL,
      ),
      'tryClose' => 
      array (
        'name' => 'tryClose',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 126,
        'endLine' => 129,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'PhpOffice\\PhpSpreadsheet\\Writer',
        'declaringClassName' => 'PhpOffice\\PhpSpreadsheet\\Writer\\BaseWriter',
        'implementingClassName' => 'PhpOffice\\PhpSpreadsheet\\Writer\\BaseWriter',
        'currentClassName' => 'PhpOffice\\PhpSpreadsheet\\Writer\\BaseWriter',
        'aliasName' => NULL,
      ),
      'maybeCloseFileHandle' => 
      array (
        'name' => 'maybeCloseFileHandle',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Close file handle only if we opened it ourselves.
 */',
        'startLine' => 134,
        'endLine' => 141,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'PhpOffice\\PhpSpreadsheet\\Writer',
        'declaringClassName' => 'PhpOffice\\PhpSpreadsheet\\Writer\\BaseWriter',
        'implementingClassName' => 'PhpOffice\\PhpSpreadsheet\\Writer\\BaseWriter',
        'currentClassName' => 'PhpOffice\\PhpSpreadsheet\\Writer\\BaseWriter',
        'aliasName' => NULL,
      ),
    ),
    'traitsData' => 
    array (
      'aliases' => 
      array (
      ),
      'modifiers' => 
      array (
      ),
      'precedences' => 
      array (
      ),
      'hashes' => 
      array (
      ),
    ),
  ),
));