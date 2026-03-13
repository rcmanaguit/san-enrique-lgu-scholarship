<?php declare(strict_types = 1);

// odsl-C:\xampp\htdocs\san-enrique-lgu-scholarship\app\includes\functions\core.php-PHPStan\BetterReflection\Reflection\ReflectionFunction-find_user_by_mobile
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.2.12-c141e40a0be2387843ea464bed1c794c64bab82d1250be382033a35c52ad818e',
   'data' => 
  array (
    'name' => 'find_user_by_mobile',
    'parameters' => 
    array (
      'conn' => 
      array (
        'name' => 'conn',
        'default' => NULL,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'mysqli',
            'isIdentifier' => false,
          ),
        ),
        'isVariadic' => false,
        'byRef' => false,
        'isPromoted' => false,
        'attributes' => 
        array (
        ),
        'startLine' => 215,
        'endLine' => 215,
        'startColumn' => 30,
        'endColumn' => 41,
        'parameterIndex' => 0,
        'isOptional' => false,
      ),
      'phone' => 
      array (
        'name' => 'phone',
        'default' => NULL,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'isVariadic' => false,
        'byRef' => false,
        'isPromoted' => false,
        'attributes' => 
        array (
        ),
        'startLine' => 215,
        'endLine' => 215,
        'startColumn' => 44,
        'endColumn' => 56,
        'parameterIndex' => 1,
        'isOptional' => false,
      ),
    ),
    'returnsReference' => false,
    'returnType' => 
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
              'name' => 'array',
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
    'attributes' => 
    array (
    ),
    'docComment' => NULL,
    'startLine' => 215,
    'endLine' => 242,
    'startColumn' => 1,
    'endColumn' => 1,
    'couldThrow' => false,
    'isClosure' => false,
    'isGenerator' => false,
    'isVariadic' => false,
    'isStatic' => false,
    'namespace' => NULL,
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'find_user_by_mobile',
        'filename' => 'C:/xampp/htdocs/san-enrique-lgu-scholarship/app/includes/functions/core.php',
      ),
    ),
  ),
));