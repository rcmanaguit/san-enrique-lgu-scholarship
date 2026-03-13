<?php declare(strict_types = 1);

// osfsl-C:/xampp/htdocs/san-enrique-lgu-scholarship/vendor/composer/../phpoffice/phpword/src/PhpWord/Element/Cell.php-PHPStan\BetterReflection\Reflection\ReflectionClass-PhpOffice\PhpWord\Element\Cell
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-3a6aed83f9e4f2f26de98ac36f50a1e0e62fd4f8b57c2a7fb9a04c184bfd6a99-8.2.12-6.65.0.9',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'PhpOffice\\PhpWord\\Element\\Cell',
        'filename' => 'C:/xampp/htdocs/san-enrique-lgu-scholarship/vendor/composer/../phpoffice/phpword/src/PhpWord/Element/Cell.php',
      ),
    ),
    'namespace' => 'PhpOffice\\PhpWord\\Element',
    'name' => 'PhpOffice\\PhpWord\\Element\\Cell',
    'shortName' => 'Cell',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Table cell element.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 26,
    'endLine' => 78,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'PhpOffice\\PhpWord\\Element\\AbstractContainer',
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
      'container' => 
      array (
        'declaringClassName' => 'PhpOffice\\PhpWord\\Element\\Cell',
        'implementingClassName' => 'PhpOffice\\PhpWord\\Element\\Cell',
        'name' => 'container',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'Cell\'',
          'attributes' => 
          array (
            'startLine' => 31,
            'endLine' => 31,
            'startTokenPos' => 38,
            'startFilePos' => 867,
            'endTokenPos' => 38,
            'endFilePos' => 872,
          ),
        ),
        'docComment' => '/**
 * @var string Container type
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 31,
        'endLine' => 31,
        'startColumn' => 5,
        'endColumn' => 34,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'width' => 
      array (
        'declaringClassName' => 'PhpOffice\\PhpWord\\Element\\Cell',
        'implementingClassName' => 'PhpOffice\\PhpWord\\Element\\Cell',
        'name' => 'width',
        'modifiers' => 4,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * Cell width.
 *
 * @var ?int
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 38,
        'endLine' => 38,
        'startColumn' => 5,
        'endColumn' => 19,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'style' => 
      array (
        'declaringClassName' => 'PhpOffice\\PhpWord\\Element\\Cell',
        'implementingClassName' => 'PhpOffice\\PhpWord\\Element\\Cell',
        'name' => 'style',
        'modifiers' => 4,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * Cell style.
 *
 * @var ?CellStyle
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 45,
        'endLine' => 45,
        'startColumn' => 5,
        'endColumn' => 19,
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
          'width' => 
          array (
            'name' => 'width',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 53,
                'endLine' => 53,
                'startTokenPos' => 67,
                'startFilePos' => 1201,
                'endTokenPos' => 67,
                'endFilePos' => 1204,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 53,
            'endLine' => 53,
            'startColumn' => 33,
            'endColumn' => 45,
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
                'startLine' => 53,
                'endLine' => 53,
                'startTokenPos' => 74,
                'startFilePos' => 1216,
                'endTokenPos' => 74,
                'endFilePos' => 1219,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 53,
            'endLine' => 53,
            'startColumn' => 48,
            'endColumn' => 60,
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
 * Create new instance.
 *
 * @param null|int $width
 * @param array|CellStyle $style
 */',
        'startLine' => 53,
        'endLine' => 57,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'PhpOffice\\PhpWord\\Element',
        'declaringClassName' => 'PhpOffice\\PhpWord\\Element\\Cell',
        'implementingClassName' => 'PhpOffice\\PhpWord\\Element\\Cell',
        'currentClassName' => 'PhpOffice\\PhpWord\\Element\\Cell',
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
 * Get cell style.
 *
 * @return ?CellStyle
 */',
        'startLine' => 64,
        'endLine' => 67,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'PhpOffice\\PhpWord\\Element',
        'declaringClassName' => 'PhpOffice\\PhpWord\\Element\\Cell',
        'implementingClassName' => 'PhpOffice\\PhpWord\\Element\\Cell',
        'currentClassName' => 'PhpOffice\\PhpWord\\Element\\Cell',
        'aliasName' => NULL,
      ),
      'getWidth' => 
      array (
        'name' => 'getWidth',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get cell width.
 *
 * @return ?int
 */',
        'startLine' => 74,
        'endLine' => 77,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'PhpOffice\\PhpWord\\Element',
        'declaringClassName' => 'PhpOffice\\PhpWord\\Element\\Cell',
        'implementingClassName' => 'PhpOffice\\PhpWord\\Element\\Cell',
        'currentClassName' => 'PhpOffice\\PhpWord\\Element\\Cell',
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