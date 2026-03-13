<?php declare(strict_types = 1);

// odsl-C:\xampp\htdocs\san-enrique-lgu-scholarship\app\includes\functions\application_periods.php-PHPStan\BetterReflection\Reflection\ReflectionFunction-applicant_has_application_in_period
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.2.12-51bb6a3393b1cb24c19502d2e2e11a4f1dd92b5ddd9868bf8b02471e3594285a',
   'data' => 
  array (
    'name' => 'applicant_has_application_in_period',
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
        'startLine' => 171,
        'endLine' => 171,
        'startColumn' => 46,
        'endColumn' => 57,
        'parameterIndex' => 0,
        'isOptional' => false,
      ),
      'userId' => 
      array (
        'name' => 'userId',
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
        'startLine' => 171,
        'endLine' => 171,
        'startColumn' => 60,
        'endColumn' => 70,
        'parameterIndex' => 1,
        'isOptional' => false,
      ),
      'period' => 
      array (
        'name' => 'period',
        'default' => 
        array (
          'code' => '\\null',
          'attributes' => 
          array (
            'startLine' => 171,
            'endLine' => 171,
            'startTokenPos' => 1374,
            'startFilePos' => 5558,
            'endTokenPos' => 1374,
            'endFilePos' => 5561,
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
        'isVariadic' => false,
        'byRef' => false,
        'isPromoted' => false,
        'attributes' => 
        array (
        ),
        'startLine' => 171,
        'endLine' => 171,
        'startColumn' => 73,
        'endColumn' => 93,
        'parameterIndex' => 2,
        'isOptional' => true,
      ),
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
    'startLine' => 171,
    'endLine' => 233,
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
        'name' => 'applicant_has_application_in_period',
        'filename' => 'C:/xampp/htdocs/san-enrique-lgu-scholarship/app/includes/functions/application_periods.php',
      ),
    ),
  ),
));