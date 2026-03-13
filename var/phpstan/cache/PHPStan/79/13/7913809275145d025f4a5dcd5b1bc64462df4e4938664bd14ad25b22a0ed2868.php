<?php declare(strict_types = 1);

// odsl-C:\xampp\htdocs\san-enrique-lgu-scholarship\app\includes\functions\audit_notifications.php-PHPStan\BetterReflection\Reflection\ReflectionFunction-create_notifications_for_roles
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.2.12-0bb7b31dbf64c31a9b5dbc09b234cfcb23401738db7f7afbf8a5c00e98bef4f1',
   'data' => 
  array (
    'name' => 'create_notifications_for_roles',
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
        'startLine' => 225,
        'endLine' => 225,
        'startColumn' => 5,
        'endColumn' => 16,
        'parameterIndex' => 0,
        'isOptional' => false,
      ),
      'roles' => 
      array (
        'name' => 'roles',
        'default' => NULL,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'isVariadic' => false,
        'byRef' => false,
        'isPromoted' => false,
        'attributes' => 
        array (
        ),
        'startLine' => 226,
        'endLine' => 226,
        'startColumn' => 5,
        'endColumn' => 16,
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
        'startLine' => 227,
        'endLine' => 227,
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
        'startLine' => 228,
        'endLine' => 228,
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
            'startLine' => 229,
            'endLine' => 229,
            'startTokenPos' => 1765,
            'startFilePos' => 6622,
            'endTokenPos' => 1765,
            'endFilePos' => 6629,
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
        'startLine' => 229,
        'endLine' => 229,
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
            'startLine' => 230,
            'endLine' => 230,
            'startTokenPos' => 1775,
            'startFilePos' => 6659,
            'endTokenPos' => 1775,
            'endFilePos' => 6662,
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
        'startLine' => 230,
        'endLine' => 230,
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
            'startLine' => 231,
            'endLine' => 231,
            'startTokenPos' => 1785,
            'startFilePos' => 6694,
            'endTokenPos' => 1785,
            'endFilePos' => 6697,
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
        'startLine' => 231,
        'endLine' => 231,
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
        'name' => 'int',
        'isIdentifier' => true,
      ),
    ),
    'attributes' => 
    array (
    ),
    'docComment' => NULL,
    'startLine' => 224,
    'endLine' => 278,
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
        'name' => 'create_notifications_for_roles',
        'filename' => 'C:/xampp/htdocs/san-enrique-lgu-scholarship/app/includes/functions/audit_notifications.php',
      ),
    ),
  ),
));