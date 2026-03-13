<?php declare(strict_types = 1);

// odsl-C:\xampp\htdocs\san-enrique-lgu-scholarship\app\includes\functions\audit_notifications.php-PHPStan\BetterReflection\Reflection\ReflectionFunction-create_notification
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.2.12-0bb7b31dbf64c31a9b5dbc09b234cfcb23401738db7f7afbf8a5c00e98bef4f1',
   'data' => 
  array (
    'name' => 'create_notification',
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
        'startLine' => 160,
        'endLine' => 160,
        'startColumn' => 5,
        'endColumn' => 16,
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
        'startLine' => 161,
        'endLine' => 161,
        'startColumn' => 5,
        'endColumn' => 15,
        'parameterIndex' => 1,
        'isOptional' => false,
      ),
      'title' => 
      array (
        'name' => 'title',
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
        'startLine' => 162,
        'endLine' => 162,
        'startColumn' => 5,
        'endColumn' => 17,
        'parameterIndex' => 2,
        'isOptional' => false,
      ),
      'message' => 
      array (
        'name' => 'message',
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
        'startLine' => 163,
        'endLine' => 163,
        'startColumn' => 5,
        'endColumn' => 19,
        'parameterIndex' => 3,
        'isOptional' => false,
      ),
      'notificationType' => 
      array (
        'name' => 'notificationType',
        'default' => 
        array (
          'code' => '\'system\'',
          'attributes' => 
          array (
            'startLine' => 164,
            'endLine' => 164,
            'startTokenPos' => 1253,
            'startFilePos' => 4500,
            'endTokenPos' => 1253,
            'endFilePos' => 4507,
          ),
        ),
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
        'startLine' => 164,
        'endLine' => 164,
        'startColumn' => 5,
        'endColumn' => 39,
        'parameterIndex' => 4,
        'isOptional' => true,
      ),
      'relatedUrl' => 
      array (
        'name' => 'relatedUrl',
        'default' => 
        array (
          'code' => '\\null',
          'attributes' => 
          array (
            'startLine' => 165,
            'endLine' => 165,
            'startTokenPos' => 1263,
            'startFilePos' => 4537,
            'endTokenPos' => 1263,
            'endFilePos' => 4540,
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
        'startLine' => 165,
        'endLine' => 165,
        'startColumn' => 5,
        'endColumn' => 30,
        'parameterIndex' => 5,
        'isOptional' => true,
      ),
      'createdByUserId' => 
      array (
        'name' => 'createdByUserId',
        'default' => 
        array (
          'code' => '\\null',
          'attributes' => 
          array (
            'startLine' => 166,
            'endLine' => 166,
            'startTokenPos' => 1273,
            'startFilePos' => 4572,
            'endTokenPos' => 1273,
            'endFilePos' => 4575,
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
                  'name' => 'int',
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
        'startLine' => 166,
        'endLine' => 166,
        'startColumn' => 5,
        'endColumn' => 32,
        'parameterIndex' => 6,
        'isOptional' => true,
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
    'startLine' => 159,
    'endLine' => 222,
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
        'name' => 'create_notification',
        'filename' => 'C:/xampp/htdocs/san-enrique-lgu-scholarship/app/includes/functions/audit_notifications.php',
      ),
    ),
  ),
));