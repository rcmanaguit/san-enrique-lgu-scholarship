<?php declare(strict_types = 1);

// osfsl-C:/xampp/htdocs/san-enrique-lgu-scholarship/vendor/composer/../phpoffice/phpword/src/PhpWord/Element/Row.php-PHPStan\BetterReflection\Reflection\ReflectionClass-PhpOffice\PhpWord\Element\Row
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-1e00dbf5dd3f9848436636fab78e1f684ca7c3bf466488b46b338201e4b6d2f4-8.2.12-6.65.0.9',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'PhpOffice\\PhpWord\\Element\\Row',
        'filename' => 'C:/xampp/htdocs/san-enrique-lgu-scholarship/vendor/composer/../phpoffice/phpword/src/PhpWord/Element/Row.php',
      ),
    ),
    'namespace' => 'PhpOffice\\PhpWord\\Element',
    'name' => 'PhpOffice\\PhpWord\\Element\\Row',
    'shortName' => 'Row',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Table row element.
 *
 * @since 0.8.0
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 28,
    'endLine' => 109,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'PhpOffice\\PhpWord\\Element\\AbstractElement',
    'implementsClassNames' => 
    array (
    ),
    'traitClassNames' => 
    array (
    ),
    'immediateConstants' => 
    array (
    ),
    'immediateProperties' => 
    array (
      'height' => 
      array (
        'declaringClassName' => 'PhpOffice\\PhpWord\\Element\\Row',
        'implementingClassName' => 'PhpOffice\\PhpWord\\Element\\Row',
        'name' => 'height',
        'modifiers' => 4,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * Row height.
 *
 * @var ?int
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 35,
        'endLine' => 35,
        'startColumn' => 5,
        'endColumn' => 20,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'style' => 
      array (
        'declaringClassName' => 'PhpOffice\\PhpWord\\Element\\Row',
        'implementingClassName' => 'PhpOffice\\PhpWord\\Element\\Row',
        'name' => 'style',
        'modifiers' => 4,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * Row style.
 *
 * @var ?RowStyle
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 42,
        'endLine' => 42,
        'startColumn' => 5,
        'endColumn' => 19,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'cells' => 
      array (
        'declaringClassName' => 'PhpOffice\\PhpWord\\Element\\Row',
        'implementingClassName' => 'PhpOffice\\PhpWord\\Element\\Row',
        'name' => 'cells',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 49,
            'endLine' => 49,
            'startTokenPos' => 52,
            'startFilePos' => 1049,
            'endTokenPos' => 53,
            'endFilePos' => 1050,
          ),
        ),
        'docComment' => '/**
 * Row cells.
 *
 * @var Cell[]
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 49,
        'endLine' => 49,
        'startColumn' => 5,
        'endColumn' => 24,
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
      '__construct' => 
      array (
        'name' => '__construct',
        'parameters' => 
        array (
          'height' => 
          array (
            'name' => 'height',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 57,
                'endLine' => 57,
                'startTokenPos' => 68,
                'startFilePos' => 1203,
                'endTokenPos' => 68,
                'endFilePos' => 1206,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 57,
            'endLine' => 57,
            'startColumn' => 33,
            'endColumn' => 46,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'style' => 
          array (
            'name' => 'style',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 57,
                'endLine' => 57,
                'startTokenPos' => 75,
                'startFilePos' => 1218,
                'endTokenPos' => 75,
                'endFilePos' => 1221,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 57,
            'endLine' => 57,
            'startColumn' => 49,
            'endColumn' => 61,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Create a new table row.
 *
 * @param int $height
 * @param mixed $style
 */',
        'startLine' => 57,
        'endLine' => 61,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'PhpOffice\\PhpWord\\Element',
        'declaringClassName' => 'PhpOffice\\PhpWord\\Element\\Row',
        'implementingClassName' => 'PhpOffice\\PhpWord\\Element\\Row',
        'currentClassName' => 'PhpOffice\\PhpWord\\Element\\Row',
        'aliasName' => NULL,
      ),
      'addCell' => 
      array (
        'name' => 'addCell',
        'parameters' => 
        array (
          'width' => 
          array (
            'name' => 'width',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 71,
                'endLine' => 71,
                'startTokenPos' => 127,
                'startFilePos' => 1501,
                'endTokenPos' => 127,
                'endFilePos' => 1504,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 71,
            'endLine' => 71,
            'startColumn' => 29,
            'endColumn' => 41,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'style' => 
          array (
            'name' => 'style',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 71,
                'endLine' => 71,
                'startTokenPos' => 134,
                'startFilePos' => 1516,
                'endTokenPos' => 134,
                'endFilePos' => 1519,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 71,
            'endLine' => 71,
            'startColumn' => 44,
            'endColumn' => 56,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add a cell.
 *
 * @param int $width
 * @param mixed $style
 *
 * @return Cell
 */',
        'startLine' => 71,
        'endLine' => 78,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'PhpOffice\\PhpWord\\Element',
        'declaringClassName' => 'PhpOffice\\PhpWord\\Element\\Row',
        'implementingClassName' => 'PhpOffice\\PhpWord\\Element\\Row',
        'currentClassName' => 'PhpOffice\\PhpWord\\Element\\Row',
        'aliasName' => NULL,
      ),
      'getCells' => 
      array (
        'name' => 'getCells',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get all cells.
 *
 * @return Cell[]
 */',
        'startLine' => 85,
        'endLine' => 88,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'PhpOffice\\PhpWord\\Element',
        'declaringClassName' => 'PhpOffice\\PhpWord\\Element\\Row',
        'implementingClassName' => 'PhpOffice\\PhpWord\\Element\\Row',
        'currentClassName' => 'PhpOffice\\PhpWord\\Element\\Row',
        'aliasName' => NULL,
      ),
      'getStyle' => 
      array (
        'name' => 'getStyle',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get row style.
 *
 * @return ?RowStyle
 */',
        'startLine' => 95,
        'endLine' => 98,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'PhpOffice\\PhpWord\\Element',
        'declaringClassName' => 'PhpOffice\\PhpWord\\Element\\Row',
        'implementingClassName' => 'PhpOffice\\PhpWord\\Element\\Row',
        'currentClassName' => 'PhpOffice\\PhpWord\\Element\\Row',
        'aliasName' => NULL,
      ),
      'getHeight' => 
      array (
        'name' => 'getHeight',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get row height.
 *
 * @return ?int
 */',
        'startLine' => 105,
        'endLine' => 108,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'PhpOffice\\PhpWord\\Element',
        'declaringClassName' => 'PhpOffice\\PhpWord\\Element\\Row',
        'implementingClassName' => 'PhpOffice\\PhpWord\\Element\\Row',
        'currentClassName' => 'PhpOffice\\PhpWord\\Element\\Row',
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