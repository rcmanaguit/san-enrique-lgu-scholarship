<?php declare(strict_types = 1);

// odsl-C:\xampp\htdocs\san-enrique-lgu-scholarship\app\includes\sms.php-PHPStan\BetterReflection\Reflection\ReflectionFunction-sms_send
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.65.0.9-8.2.12-dedd63a5565301aca7282b5ec85819a8908061c5a02ea084fb1a92f71e95538e',
   'data' => 
  array (
    'name' => 'sms_send',
    'parameters' => 
    array (
      'recipientPhone' => 
      array (
        'name' => 'recipientPhone',
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
        'startLine' => 142,
        'endLine' => 142,
        'startColumn' => 19,
        'endColumn' => 40,
        'parameterIndex' => 0,
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
        'startLine' => 142,
        'endLine' => 142,
        'startColumn' => 43,
        'endColumn' => 57,
        'parameterIndex' => 1,
        'isOptional' => false,
      ),
      'userId' => 
      array (
        'name' => 'userId',
        'default' => 
        array (
          'code' => '\\null',
          'attributes' => 
          array (
            'startLine' => 142,
            'endLine' => 142,
            'startTokenPos' => 1204,
            'startFilePos' => 4478,
            'endTokenPos' => 1204,
            'endFilePos' => 4481,
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
        'startLine' => 142,
        'endLine' => 142,
        'startColumn' => 60,
        'endColumn' => 78,
        'parameterIndex' => 2,
        'isOptional' => true,
      ),
      'smsType' => 
      array (
        'name' => 'smsType',
        'default' => 
        array (
          'code' => '\'single\'',
          'attributes' => 
          array (
            'startLine' => 142,
            'endLine' => 142,
            'startTokenPos' => 1213,
            'startFilePos' => 4502,
            'endTokenPos' => 1213,
            'endFilePos' => 4509,
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
        'startLine' => 142,
        'endLine' => 142,
        'startColumn' => 81,
        'endColumn' => 106,
        'parameterIndex' => 3,
        'isOptional' => true,
      ),
    ),
    'returnsReference' => false,
    'returnType' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
      'data' => 
      array (
        'name' => 'array',
        'isIdentifier' => true,
      ),
    ),
    'attributes' => 
    array (
    ),
    'docComment' => NULL,
    'startLine' => 142,
    'endLine' => 203,
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
        'name' => 'sms_send',
        'filename' => 'C:/xampp/htdocs/san-enrique-lgu-scholarship/app/includes/sms.php',
      ),
    ),
  ),
));