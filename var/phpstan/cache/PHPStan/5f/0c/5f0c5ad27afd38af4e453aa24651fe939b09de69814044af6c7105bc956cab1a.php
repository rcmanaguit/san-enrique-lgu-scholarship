<?php declare(strict_types = 1);

// osfsl-C:/xampp/htdocs/san-enrique-lgu-scholarship/vendor/composer/../dompdf/dompdf/src/Dompdf.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Dompdf\Dompdf
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-fcfa4aa872e0baef71337f765e800a8c728d738f741cba8e9cce4eddd9ea130a-8.2.12-6.65.0.9',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Dompdf\\Dompdf',
        'filename' => 'C:/xampp/htdocs/san-enrique-lgu-scholarship/vendor/composer/../dompdf/dompdf/src/Dompdf.php',
      ),
    ),
    'namespace' => 'Dompdf',
    'name' => 'Dompdf\\Dompdf',
    'shortName' => 'Dompdf',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Dompdf - PHP5 HTML to PDF renderer
 *
 * Dompdf loads HTML and does its best to render it as a PDF.  It gets its
 * name from the new DomDocument PHP5 extension.  Source HTML is first
 * parsed by a DomDocument object.  Dompdf takes the resulting DOM tree and
 * attaches a {@link Frame} object to each node.  {@link Frame} objects store
 * positioning and layout information and each has a reference to a {@link
 * Style} object.
 *
 * Style information is loaded and parsed (see {@link Stylesheet}) and is
 * applied to the frames in the tree by using XPath.  CSS selectors are
 * converted into XPath queries, and the computed {@link Style} objects are
 * applied to the {@link Frame}s.
 *
 * {@link Frame}s are then decorated (in the design pattern sense of the
 * word) based on their CSS display property ({@link
 * http://www.w3.org/TR/CSS21/visuren.html#propdef-display}).
 * Frame_Decorators augment the basic {@link Frame} class by adding
 * additional properties and methods specific to the particular type of
 * {@link Frame}.  For example, in the CSS layout model, block frames
 * (display: block;) contain line boxes that are usually filled with text or
 * other inline frames.  The Block therefore adds a $lines
 * property as well as methods to add {@link Frame}s to lines and to add
 * additional lines.  {@link Frame}s also are attached to specific
 * AbstractPositioner and {@link AbstractFrameReflower} objects that contain the
 * positioining and layout algorithm for a specific type of frame,
 * respectively.  This is an application of the Strategy pattern.
 *
 * Layout, or reflow, proceeds recursively (post-order) starting at the root
 * of the document.  Space constraints (containing block width & height) are
 * pushed down, and resolved positions and sizes bubble up.  Thus, every
 * {@link Frame} in the document tree is traversed once (except for tables
 * which use a two-pass layout algorithm).  If you are interested in the
 * details, see the reflow() method of the Reflower classes.
 *
 * Rendering is relatively straightforward once layout is complete. {@link
 * Frame}s are rendered using an adapted {@link Cpdf} class, originally
 * written by Wayne Munro, http://www.ros.co.nz/pdf/.  (Some performance
 * related changes have been made to the original {@link Cpdf} class, and
 * the {@link Dompdf\\Adapter\\CPDF} class provides a simple, stateless interface to
 * PDF generation.)  PDFLib support has now also been added, via the {@link
 * Dompdf\\Adapter\\PDFLib}.
 *
 *
 * @package dompdf
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 67,
    'endLine' => 1545,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => NULL,
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
      'version' => 
      array (
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'name' => 'version',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'dompdf\'',
          'attributes' => 
          array (
            'startLine' => 74,
            'endLine' => 74,
            'startTokenPos' => 74,
            'startFilePos' => 3053,
            'endTokenPos' => 74,
            'endFilePos' => 3060,
          ),
        ),
        'docComment' => '/**
 * Version string for dompdf
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 74,
        'endLine' => 74,
        'startColumn' => 5,
        'endColumn' => 32,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'dom' => 
      array (
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'name' => 'dom',
        'modifiers' => 4,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * DomDocument representing the HTML document
 *
 * @var DOMDocument
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 81,
        'endLine' => 81,
        'startColumn' => 5,
        'endColumn' => 17,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'tree' => 
      array (
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'name' => 'tree',
        'modifiers' => 4,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * FrameTree derived from the DOM tree
 *
 * @var FrameTree
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 88,
        'endLine' => 88,
        'startColumn' => 5,
        'endColumn' => 18,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'css' => 
      array (
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'name' => 'css',
        'modifiers' => 4,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * Stylesheet for the document
 *
 * @var Stylesheet
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 95,
        'endLine' => 95,
        'startColumn' => 5,
        'endColumn' => 17,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'canvas' => 
      array (
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'name' => 'canvas',
        'modifiers' => 4,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * Actual PDF renderer
 *
 * @var Canvas
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 102,
        'endLine' => 102,
        'startColumn' => 5,
        'endColumn' => 20,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'paperSize' => 
      array (
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'name' => 'paperSize',
        'modifiers' => 4,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * Desired paper size (\'letter\', \'legal\', \'A4\', etc.)
 *
 * @var string|float[]
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 109,
        'endLine' => 109,
        'startColumn' => 5,
        'endColumn' => 23,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'paperOrientation' => 
      array (
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'name' => 'paperOrientation',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => '"portrait"',
          'attributes' => 
          array (
            'startLine' => 116,
            'endLine' => 116,
            'startTokenPos' => 120,
            'startFilePos' => 3739,
            'endTokenPos' => 120,
            'endFilePos' => 3748,
          ),
        ),
        'docComment' => '/**
 * Paper orientation (\'portrait\' or \'landscape\')
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 116,
        'endLine' => 116,
        'startColumn' => 5,
        'endColumn' => 43,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'callbacks' => 
      array (
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'name' => 'callbacks',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 123,
            'endLine' => 123,
            'startTokenPos' => 131,
            'startFilePos' => 3863,
            'endTokenPos' => 132,
            'endFilePos' => 3864,
          ),
        ),
        'docComment' => '/**
 * Callbacks on new page and new element
 *
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 123,
        'endLine' => 123,
        'startColumn' => 5,
        'endColumn' => 28,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'cacheId' => 
      array (
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'name' => 'cacheId',
        'modifiers' => 4,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * Experimental caching capability
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 130,
        'endLine' => 130,
        'startColumn' => 5,
        'endColumn' => 21,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'baseHost' => 
      array (
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'name' => 'baseHost',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => '""',
          'attributes' => 
          array (
            'startLine' => 138,
            'endLine' => 138,
            'startTokenPos' => 150,
            'startFilePos' => 4095,
            'endTokenPos' => 150,
            'endFilePos' => 4096,
          ),
        ),
        'docComment' => '/**
 * Base hostname
 *
 * Used for relative paths/urls
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 138,
        'endLine' => 138,
        'startColumn' => 5,
        'endColumn' => 27,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'basePath' => 
      array (
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'name' => 'basePath',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => '""',
          'attributes' => 
          array (
            'startLine' => 146,
            'endLine' => 146,
            'startTokenPos' => 161,
            'startFilePos' => 4228,
            'endTokenPos' => 161,
            'endFilePos' => 4229,
          ),
        ),
        'docComment' => '/**
 * Absolute base path
 *
 * Used for relative paths/urls
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 146,
        'endLine' => 146,
        'startColumn' => 5,
        'endColumn' => 27,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'protocol' => 
      array (
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'name' => 'protocol',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => '""',
          'attributes' => 
          array (
            'startLine' => 153,
            'endLine' => 153,
            'startTokenPos' => 172,
            'startFilePos' => 4360,
            'endTokenPos' => 172,
            'endFilePos' => 4361,
          ),
        ),
        'docComment' => '/**
 * Protocol used to request file (file://, http://, etc)
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 153,
        'endLine' => 153,
        'startColumn' => 5,
        'endColumn' => 27,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'systemLocale' => 
      array (
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'name' => 'systemLocale',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'null',
          'attributes' => 
          array (
            'startLine' => 160,
            'endLine' => 160,
            'startTokenPos' => 183,
            'startFilePos' => 4462,
            'endTokenPos' => 183,
            'endFilePos' => 4465,
          ),
        ),
        'docComment' => '/**
 * The system\'s locale
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 160,
        'endLine' => 160,
        'startColumn' => 5,
        'endColumn' => 33,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'mbstringEncoding' => 
      array (
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'name' => 'mbstringEncoding',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'null',
          'attributes' => 
          array (
            'startLine' => 167,
            'endLine' => 167,
            'startTokenPos' => 194,
            'startFilePos' => 4590,
            'endTokenPos' => 194,
            'endFilePos' => 4593,
          ),
        ),
        'docComment' => '/**
 * The system\'s mbstring internal encoding
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 167,
        'endLine' => 167,
        'startColumn' => 5,
        'endColumn' => 37,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'pcreJit' => 
      array (
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'name' => 'pcreJit',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'null',
          'attributes' => 
          array (
            'startLine' => 174,
            'endLine' => 174,
            'startTokenPos' => 205,
            'startFilePos' => 4705,
            'endTokenPos' => 205,
            'endFilePos' => 4708,
          ),
        ),
        'docComment' => '/**
 * The system\'s PCRE JIT configuration
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 174,
        'endLine' => 174,
        'startColumn' => 5,
        'endColumn' => 28,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'defaultView' => 
      array (
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'name' => 'defaultView',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => '"Fit"',
          'attributes' => 
          array (
            'startLine' => 181,
            'endLine' => 181,
            'startTokenPos' => 216,
            'startFilePos' => 4830,
            'endTokenPos' => 216,
            'endFilePos' => 4834,
          ),
        ),
        'docComment' => '/**
 * The default view of the PDF in the viewer
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 181,
        'endLine' => 181,
        'startColumn' => 5,
        'endColumn' => 33,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'defaultViewOptions' => 
      array (
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'name' => 'defaultViewOptions',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 188,
            'endLine' => 188,
            'startTokenPos' => 227,
            'startFilePos' => 4970,
            'endTokenPos' => 228,
            'endFilePos' => 4971,
          ),
        ),
        'docComment' => '/**
 * The default view options of the PDF in the viewer
 *
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 188,
        'endLine' => 188,
        'startColumn' => 5,
        'endColumn' => 37,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'quirksmode' => 
      array (
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'name' => 'quirksmode',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 195,
            'endLine' => 195,
            'startTokenPos' => 239,
            'startFilePos' => 5111,
            'endTokenPos' => 239,
            'endFilePos' => 5115,
          ),
        ),
        'docComment' => '/**
 * Tells whether the DOM document is in quirksmode (experimental)
 *
 * @var bool
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 195,
        'endLine' => 195,
        'startColumn' => 5,
        'endColumn' => 32,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'allowedLocalFileExtensions' => 
      array (
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'name' => 'allowedLocalFileExtensions',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => '["htm", "html"]',
          'attributes' => 
          array (
            'startLine' => 204,
            'endLine' => 204,
            'startTokenPos' => 250,
            'startFilePos' => 5301,
            'endTokenPos' => 255,
            'endFilePos' => 5315,
          ),
        ),
        'docComment' => '/**
 * Local file extension whitelist
 *
 * File extensions supported by dompdf for local files.
 *
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 204,
        'endLine' => 204,
        'startColumn' => 5,
        'endColumn' => 58,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'messages' => 
      array (
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'name' => 'messages',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 209,
            'endLine' => 209,
            'startTokenPos' => 266,
            'startFilePos' => 5377,
            'endTokenPos' => 267,
            'endFilePos' => 5378,
          ),
        ),
        'docComment' => '/**
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 209,
        'endLine' => 209,
        'startColumn' => 5,
        'endColumn' => 27,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'options' => 
      array (
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'name' => 'options',
        'modifiers' => 4,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * @var Options
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 214,
        'endLine' => 214,
        'startColumn' => 5,
        'endColumn' => 21,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'fontMetrics' => 
      array (
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'name' => 'fontMetrics',
        'modifiers' => 4,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * @var FontMetrics
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 219,
        'endLine' => 219,
        'startColumn' => 5,
        'endColumn' => 25,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'native_fonts' => 
      array (
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'name' => 'native_fonts',
        'modifiers' => 17,
        'type' => NULL,
        'default' => 
        array (
          'code' => '["courier", "courier-bold", "courier-oblique", "courier-boldoblique", "helvetica", "helvetica-bold", "helvetica-oblique", "helvetica-boldoblique", "times-roman", "times-bold", "times-italic", "times-bolditalic", "symbol", "zapfdinbats"]',
          'attributes' => 
          array (
            'startLine' => 227,
            'endLine' => 232,
            'startTokenPos' => 294,
            'startFilePos' => 5636,
            'endTokenPos' => 337,
            'endFilePos' => 5909,
          ),
        ),
        'docComment' => '/**
 * The list of built-in fonts
 *
 * @var array
 * @deprecated
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 227,
        'endLine' => 232,
        'startColumn' => 5,
        'endColumn' => 6,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'nativeFonts' => 
      array (
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'name' => 'nativeFonts',
        'modifiers' => 17,
        'type' => NULL,
        'default' => 
        array (
          'code' => '["courier", "courier-bold", "courier-oblique", "courier-boldoblique", "helvetica", "helvetica-bold", "helvetica-oblique", "helvetica-boldoblique", "times-roman", "times-bold", "times-italic", "times-bolditalic", "symbol", "zapfdinbats"]',
          'attributes' => 
          array (
            'startLine' => 239,
            'endLine' => 244,
            'startTokenPos' => 350,
            'startFilePos' => 6021,
            'endTokenPos' => 393,
            'endFilePos' => 6294,
          ),
        ),
        'docComment' => '/**
 * The list of built-in fonts
 *
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 239,
        'endLine' => 244,
        'startColumn' => 5,
        'endColumn' => 6,
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
          'options' => 
          array (
            'name' => 'options',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 251,
                'endLine' => 251,
                'startTokenPos' => 408,
                'startFilePos' => 6431,
                'endTokenPos' => 408,
                'endFilePos' => 6434,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 251,
            'endLine' => 251,
            'startColumn' => 33,
            'endColumn' => 47,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Class constructor
 *
 * @param Options|array|null $options
 */',
        'startLine' => 251,
        'endLine' => 279,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'currentClassName' => 'Dompdf\\Dompdf',
        'aliasName' => NULL,
      ),
      'setPhpConfig' => 
      array (
        'name' => 'setPhpConfig',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Save the system\'s existing locale, PCRE JIT, and MBString encoding
 * configuration and configure the system for Dompdf processing
 */',
        'startLine' => 285,
        'endLine' => 299,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'currentClassName' => 'Dompdf\\Dompdf',
        'aliasName' => NULL,
      ),
      'restorePhpConfig' => 
      array (
        'name' => 'restorePhpConfig',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Restore the system\'s locale configuration
 */',
        'startLine' => 304,
        'endLine' => 322,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'currentClassName' => 'Dompdf\\Dompdf',
        'aliasName' => NULL,
      ),
      'load_html_file' => 
      array (
        'name' => 'load_html_file',
        'parameters' => 
        array (
          'file' => 
          array (
            'name' => 'file',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 328,
            'endLine' => 328,
            'startColumn' => 36,
            'endColumn' => 40,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param $file
 * @deprecated
 */',
        'startLine' => 328,
        'endLine' => 331,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'currentClassName' => 'Dompdf\\Dompdf',
        'aliasName' => NULL,
      ),
      'loadHtmlFile' => 
      array (
        'name' => 'loadHtmlFile',
        'parameters' => 
        array (
          'file' => 
          array (
            'name' => 'file',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 345,
            'endLine' => 345,
            'startColumn' => 34,
            'endColumn' => 38,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'encoding' => 
          array (
            'name' => 'encoding',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 345,
                'endLine' => 345,
                'startTokenPos' => 963,
                'startFilePos' => 9405,
                'endTokenPos' => 963,
                'endFilePos' => 9408,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 345,
            'endLine' => 345,
            'startColumn' => 41,
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
 * Loads an HTML file.
 *
 * If no encoding is given or set via `Content-Type` header, the document
 * encoding specified via `<meta>` tag is used. An existing Unicode BOM
 * always takes precedence.
 *
 * Parse errors are stored in the global array `$_dompdf_warnings`.
 *
 * @param string      $file     A filename or URL to load.
 * @param string|null $encoding Encoding of the file.
 */',
        'startLine' => 345,
        'endLine' => 392,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'currentClassName' => 'Dompdf\\Dompdf',
        'aliasName' => NULL,
      ),
      'load_html' => 
      array (
        'name' => 'load_html',
        'parameters' => 
        array (
          'str' => 
          array (
            'name' => 'str',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 399,
            'endLine' => 399,
            'startColumn' => 31,
            'endColumn' => 34,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'encoding' => 
          array (
            'name' => 'encoding',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 399,
                'endLine' => 399,
                'startTokenPos' => 1397,
                'startFilePos' => 11487,
                'endTokenPos' => 1397,
                'endFilePos' => 11490,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 399,
            'endLine' => 399,
            'startColumn' => 37,
            'endColumn' => 52,
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
 * @param string $str
 * @param string $encoding
 * @deprecated
 */',
        'startLine' => 399,
        'endLine' => 402,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'currentClassName' => 'Dompdf\\Dompdf',
        'aliasName' => NULL,
      ),
      'loadDOM' => 
      array (
        'name' => 'loadDOM',
        'parameters' => 
        array (
          'doc' => 
          array (
            'name' => 'doc',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 408,
            'endLine' => 408,
            'startColumn' => 29,
            'endColumn' => 32,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'quirksmode' => 
          array (
            'name' => 'quirksmode',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 408,
                'endLine' => 408,
                'startTokenPos' => 1430,
                'startFilePos' => 11681,
                'endTokenPos' => 1430,
                'endFilePos' => 11685,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 408,
            'endLine' => 408,
            'startColumn' => 35,
            'endColumn' => 53,
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
 * @param DOMDocument $doc
 * @param bool        $quirksmode
 */',
        'startLine' => 408,
        'endLine' => 423,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'currentClassName' => 'Dompdf\\Dompdf',
        'aliasName' => NULL,
      ),
      'loadHtml' => 
      array (
        'name' => 'loadHtml',
        'parameters' => 
        array (
          'str' => 
          array (
            'name' => 'str',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 436,
            'endLine' => 436,
            'startColumn' => 30,
            'endColumn' => 33,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'encoding' => 
          array (
            'name' => 'encoding',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 436,
                'endLine' => 436,
                'startTokenPos' => 1563,
                'startFilePos' => 12644,
                'endTokenPos' => 1563,
                'endFilePos' => 12647,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 436,
            'endLine' => 436,
            'startColumn' => 36,
            'endColumn' => 51,
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
 * Loads an HTML document from a string.
 *
 * If no encoding is given, the document encoding specified via `<meta>`
 * tag is used. An existing Unicode BOM always takes precedence.
 *
 * Parse errors are stored in the global array `$_dompdf_warnings`.
 *
 * @param string      $str      The HTML to load.
 * @param string|null $encoding Encoding of the string.
 */',
        'startLine' => 436,
        'endLine' => 531,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'currentClassName' => 'Dompdf\\Dompdf',
        'aliasName' => NULL,
      ),
      'remove_text_nodes' => 
      array (
        'name' => 'remove_text_nodes',
        'parameters' => 
        array (
          'node' => 
          array (
            'name' => 'node',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'DOMNode',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 537,
            'endLine' => 537,
            'startColumn' => 46,
            'endColumn' => 58,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param DOMNode $node
 * @deprecated
 */',
        'startLine' => 537,
        'endLine' => 540,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'currentClassName' => 'Dompdf\\Dompdf',
        'aliasName' => NULL,
      ),
      'removeTextNodes' => 
      array (
        'name' => 'removeTextNodes',
        'parameters' => 
        array (
          'node' => 
          array (
            'name' => 'node',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'DOMNode',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 545,
            'endLine' => 545,
            'startColumn' => 44,
            'endColumn' => 56,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param DOMNode $node
 */',
        'startLine' => 545,
        'endLine' => 558,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'currentClassName' => 'Dompdf\\Dompdf',
        'aliasName' => NULL,
      ),
      'processHtml' => 
      array (
        'name' => 'processHtml',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Builds the {@link FrameTree}, loads any CSS and applies the styles to
 * the {@link FrameTree}
 */',
        'startLine' => 564,
        'endLine' => 666,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'currentClassName' => 'Dompdf\\Dompdf',
        'aliasName' => NULL,
      ),
      'enable_caching' => 
      array (
        'name' => 'enable_caching',
        'parameters' => 
        array (
          'cacheId' => 
          array (
            'name' => 'cacheId',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 672,
            'endLine' => 672,
            'startColumn' => 36,
            'endColumn' => 43,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param string $cacheId
 * @deprecated
 */',
        'startLine' => 672,
        'endLine' => 675,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'currentClassName' => 'Dompdf\\Dompdf',
        'aliasName' => NULL,
      ),
      'enableCaching' => 
      array (
        'name' => 'enableCaching',
        'parameters' => 
        array (
          'cacheId' => 
          array (
            'name' => 'cacheId',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 682,
            'endLine' => 682,
            'startColumn' => 35,
            'endColumn' => 42,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Enable experimental caching capability
 *
 * @param string $cacheId
 */',
        'startLine' => 682,
        'endLine' => 685,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'currentClassName' => 'Dompdf\\Dompdf',
        'aliasName' => NULL,
      ),
      'parse_default_view' => 
      array (
        'name' => 'parse_default_view',
        'parameters' => 
        array (
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 692,
            'endLine' => 692,
            'startColumn' => 40,
            'endColumn' => 45,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param string $value
 * @return bool
 * @deprecated
 */',
        'startLine' => 692,
        'endLine' => 695,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'currentClassName' => 'Dompdf\\Dompdf',
        'aliasName' => NULL,
      ),
      'parseDefaultView' => 
      array (
        'name' => 'parseDefaultView',
        'parameters' => 
        array (
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 701,
            'endLine' => 701,
            'startColumn' => 38,
            'endColumn' => 43,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param string $value
 * @return bool
 */',
        'startLine' => 701,
        'endLine' => 714,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'currentClassName' => 'Dompdf\\Dompdf',
        'aliasName' => NULL,
      ),
      'render' => 
      array (
        'name' => 'render',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Renders the HTML to PDF
 */',
        'startLine' => 719,
        'endLine' => 850,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'currentClassName' => 'Dompdf\\Dompdf',
        'aliasName' => NULL,
      ),
      'writeLog' => 
      array (
        'name' => 'writeLog',
        'parameters' => 
        array (
          'logOutputFile' => 
          array (
            'name' => 'logOutputFile',
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
            'startLine' => 858,
            'endLine' => 858,
            'startColumn' => 31,
            'endColumn' => 51,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'startTime' => 
          array (
            'name' => 'startTime',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'float',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 858,
            'endLine' => 858,
            'startColumn' => 54,
            'endColumn' => 69,
            'parameterIndex' => 1,
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
 * Writes the output buffer in the log file
 *
 * @param string $logOutputFile
 * @param float $startTime
 */',
        'startLine' => 858,
        'endLine' => 876,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'currentClassName' => 'Dompdf\\Dompdf',
        'aliasName' => NULL,
      ),
      'add_info' => 
      array (
        'name' => 'add_info',
        'parameters' => 
        array (
          'label' => 
          array (
            'name' => 'label',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 883,
            'endLine' => 883,
            'startColumn' => 30,
            'endColumn' => 35,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 883,
            'endLine' => 883,
            'startColumn' => 38,
            'endColumn' => 43,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add meta information to the PDF after rendering.
 *
 * @deprecated
 */',
        'startLine' => 883,
        'endLine' => 886,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'currentClassName' => 'Dompdf\\Dompdf',
        'aliasName' => NULL,
      ),
      'addInfo' => 
      array (
        'name' => 'addInfo',
        'parameters' => 
        array (
          'label' => 
          array (
            'name' => 'label',
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
            'startLine' => 894,
            'endLine' => 894,
            'startColumn' => 29,
            'endColumn' => 41,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'value' => 
          array (
            'name' => 'value',
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
            'startLine' => 894,
            'endLine' => 894,
            'startColumn' => 44,
            'endColumn' => 56,
            'parameterIndex' => 1,
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
 * Add meta information to the PDF after rendering.
 *
 * @param string $label Label of the value (Creator, Producer, etc.)
 * @param string $value The text to set
 */',
        'startLine' => 894,
        'endLine' => 897,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'currentClassName' => 'Dompdf\\Dompdf',
        'aliasName' => NULL,
      ),
      'stream' => 
      array (
        'name' => 'stream',
        'parameters' => 
        array (
          'filename' => 
          array (
            'name' => 'filename',
            'default' => 
            array (
              'code' => '"document.pdf"',
              'attributes' => 
              array (
                'startLine' => 915,
                'endLine' => 915,
                'startTokenPos' => 4539,
                'startFilePos' => 29982,
                'endTokenPos' => 4539,
                'endFilePos' => 29995,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 915,
            'endLine' => 915,
            'startColumn' => 28,
            'endColumn' => 53,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'options' => 
          array (
            'name' => 'options',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 915,
                'endLine' => 915,
                'startTokenPos' => 4546,
                'startFilePos' => 30009,
                'endTokenPos' => 4547,
                'endFilePos' => 30010,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 915,
            'endLine' => 915,
            'startColumn' => 56,
            'endColumn' => 68,
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
 * Streams the PDF to the client.
 *
 * The file will open a download dialog by default. The options
 * parameter controls the output. Accepted options (array keys) are:
 *
 * \'compress\' = > 1 (=default) or 0:
 *   Apply content stream compression
 *
 * \'Attachment\' => 1 (=default) or 0:
 *   Set the \'Content-Disposition:\' HTTP header to \'attachment\'
 *   (thereby causing the browser to open a download dialog)
 *
 * @param string $filename the name of the streamed file
 * @param array $options header options (see above)
 */',
        'startLine' => 915,
        'endLine' => 922,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'currentClassName' => 'Dompdf\\Dompdf',
        'aliasName' => NULL,
      ),
      'output' => 
      array (
        'name' => 'output',
        'parameters' => 
        array (
          'options' => 
          array (
            'name' => 'options',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 936,
                'endLine' => 936,
                'startTokenPos' => 4593,
                'startFilePos' => 30508,
                'endTokenPos' => 4594,
                'endFilePos' => 30509,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 936,
            'endLine' => 936,
            'startColumn' => 28,
            'endColumn' => 40,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Returns the PDF as a string.
 *
 * The options parameter controls the output. Accepted options are:
 *
 * \'compress\' = > 1 or 0 - apply content stream compression, this is
 *    on (1) by default
 *
 * @param array $options options (see above)
 *
 * @return string
 */',
        'startLine' => 936,
        'endLine' => 945,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'currentClassName' => 'Dompdf\\Dompdf',
        'aliasName' => NULL,
      ),
      'output_html' => 
      array (
        'name' => 'output_html',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @return string
 * @deprecated
 */',
        'startLine' => 951,
        'endLine' => 954,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'currentClassName' => 'Dompdf\\Dompdf',
        'aliasName' => NULL,
      ),
      'outputHtml' => 
      array (
        'name' => 'outputHtml',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Returns the underlying HTML document as a string
 *
 * @return string
 */',
        'startLine' => 961,
        'endLine' => 964,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'currentClassName' => 'Dompdf\\Dompdf',
        'aliasName' => NULL,
      ),
      'get_option' => 
      array (
        'name' => 'get_option',
        'parameters' => 
        array (
          'key' => 
          array (
            'name' => 'key',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 973,
            'endLine' => 973,
            'startColumn' => 32,
            'endColumn' => 35,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the dompdf option value
 *
 * @param string $key
 * @return mixed
 * @deprecated
 */',
        'startLine' => 973,
        'endLine' => 976,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'currentClassName' => 'Dompdf\\Dompdf',
        'aliasName' => NULL,
      ),
      'set_option' => 
      array (
        'name' => 'set_option',
        'parameters' => 
        array (
          'key' => 
          array (
            'name' => 'key',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 984,
            'endLine' => 984,
            'startColumn' => 32,
            'endColumn' => 35,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 984,
            'endLine' => 984,
            'startColumn' => 38,
            'endColumn' => 43,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param string $key
 * @param mixed $value
 * @return $this
 * @deprecated
 */',
        'startLine' => 984,
        'endLine' => 990,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'currentClassName' => 'Dompdf\\Dompdf',
        'aliasName' => NULL,
      ),
      'set_options' => 
      array (
        'name' => 'set_options',
        'parameters' => 
        array (
          'options' => 
          array (
            'name' => 'options',
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
            'startLine' => 997,
            'endLine' => 997,
            'startColumn' => 33,
            'endColumn' => 46,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param array $options
 * @return $this
 * @deprecated
 */',
        'startLine' => 997,
        'endLine' => 1003,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'currentClassName' => 'Dompdf\\Dompdf',
        'aliasName' => NULL,
      ),
      'set_paper' => 
      array (
        'name' => 'set_paper',
        'parameters' => 
        array (
          'size' => 
          array (
            'name' => 'size',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1010,
            'endLine' => 1010,
            'startColumn' => 31,
            'endColumn' => 35,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'orientation' => 
          array (
            'name' => 'orientation',
            'default' => 
            array (
              'code' => '"portrait"',
              'attributes' => 
              array (
                'startLine' => 1010,
                'endLine' => 1010,
                'startTokenPos' => 4826,
                'startFilePos' => 31966,
                'endTokenPos' => 4826,
                'endFilePos' => 31975,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1010,
            'endLine' => 1010,
            'startColumn' => 38,
            'endColumn' => 62,
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
 * @param string $size
 * @param string $orientation
 * @deprecated
 */',
        'startLine' => 1010,
        'endLine' => 1013,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'currentClassName' => 'Dompdf\\Dompdf',
        'aliasName' => NULL,
      ),
      'setPaper' => 
      array (
        'name' => 'setPaper',
        'parameters' => 
        array (
          'size' => 
          array (
            'name' => 'size',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1022,
            'endLine' => 1022,
            'startColumn' => 30,
            'endColumn' => 34,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'orientation' => 
          array (
            'name' => 'orientation',
            'default' => 
            array (
              'code' => '"portrait"',
              'attributes' => 
              array (
                'startLine' => 1022,
                'endLine' => 1022,
                'startTokenPos' => 4861,
                'startFilePos' => 32347,
                'endTokenPos' => 4861,
                'endFilePos' => 32356,
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
            'startLine' => 1022,
            'endLine' => 1022,
            'startColumn' => 37,
            'endColumn' => 68,
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
        'docComment' => '/**
 * Sets the paper size & orientation
 *
 * @param string|float[] $size \'letter\', \'legal\', \'A4\', etc. {@link Dompdf\\Adapter\\CPDF::$PAPER_SIZES}
 * @param string $orientation \'portrait\' or \'landscape\'
 * @return $this
 */',
        'startLine' => 1022,
        'endLine' => 1035,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'currentClassName' => 'Dompdf\\Dompdf',
        'aliasName' => NULL,
      ),
      'getPaperSize' => 
      array (
        'name' => 'getPaperSize',
        'parameters' => 
        array (
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
        'docComment' => '/**
 * Gets the paper size
 *
 * @return float[] A four-element float array
 */',
        'startLine' => 1042,
        'endLine' => 1059,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'currentClassName' => 'Dompdf\\Dompdf',
        'aliasName' => NULL,
      ),
      'getPaperOrientation' => 
      array (
        'name' => 'getPaperOrientation',
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
        'docComment' => '/**
 * Gets the paper orientation
 *
 * @return string Either "portrait" or "landscape"
 */',
        'startLine' => 1066,
        'endLine' => 1069,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'currentClassName' => 'Dompdf\\Dompdf',
        'aliasName' => NULL,
      ),
      'setTree' => 
      array (
        'name' => 'setTree',
        'parameters' => 
        array (
          'tree' => 
          array (
            'name' => 'tree',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Dompdf\\Frame\\FrameTree',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1075,
            'endLine' => 1075,
            'startColumn' => 29,
            'endColumn' => 43,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param FrameTree $tree
 * @return $this
 */',
        'startLine' => 1075,
        'endLine' => 1079,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'currentClassName' => 'Dompdf\\Dompdf',
        'aliasName' => NULL,
      ),
      'get_tree' => 
      array (
        'name' => 'get_tree',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @return FrameTree
 * @deprecated
 */',
        'startLine' => 1085,
        'endLine' => 1088,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'currentClassName' => 'Dompdf\\Dompdf',
        'aliasName' => NULL,
      ),
      'getTree' => 
      array (
        'name' => 'getTree',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Returns the underlying {@link FrameTree} object
 *
 * @return FrameTree
 */',
        'startLine' => 1095,
        'endLine' => 1098,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'currentClassName' => 'Dompdf\\Dompdf',
        'aliasName' => NULL,
      ),
      'set_protocol' => 
      array (
        'name' => 'set_protocol',
        'parameters' => 
        array (
          'protocol' => 
          array (
            'name' => 'protocol',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1105,
            'endLine' => 1105,
            'startColumn' => 34,
            'endColumn' => 42,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param string $protocol
 * @return $this
 * @deprecated
 */',
        'startLine' => 1105,
        'endLine' => 1108,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'currentClassName' => 'Dompdf\\Dompdf',
        'aliasName' => NULL,
      ),
      'setProtocol' => 
      array (
        'name' => 'setProtocol',
        'parameters' => 
        array (
          'protocol' => 
          array (
            'name' => 'protocol',
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
            'startLine' => 1117,
            'endLine' => 1117,
            'startColumn' => 33,
            'endColumn' => 48,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Sets the protocol to use
 * FIXME validate these
 *
 * @param string $protocol
 * @return $this
 */',
        'startLine' => 1117,
        'endLine' => 1121,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'currentClassName' => 'Dompdf\\Dompdf',
        'aliasName' => NULL,
      ),
      'get_protocol' => 
      array (
        'name' => 'get_protocol',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @return string
 * @deprecated
 */',
        'startLine' => 1127,
        'endLine' => 1130,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'currentClassName' => 'Dompdf\\Dompdf',
        'aliasName' => NULL,
      ),
      'getProtocol' => 
      array (
        'name' => 'getProtocol',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Returns the protocol in use
 *
 * @return string
 */',
        'startLine' => 1137,
        'endLine' => 1140,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'currentClassName' => 'Dompdf\\Dompdf',
        'aliasName' => NULL,
      ),
      'set_host' => 
      array (
        'name' => 'set_host',
        'parameters' => 
        array (
          'host' => 
          array (
            'name' => 'host',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1146,
            'endLine' => 1146,
            'startColumn' => 30,
            'endColumn' => 34,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param string $host
 * @deprecated
 */',
        'startLine' => 1146,
        'endLine' => 1149,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'currentClassName' => 'Dompdf\\Dompdf',
        'aliasName' => NULL,
      ),
      'setBaseHost' => 
      array (
        'name' => 'setBaseHost',
        'parameters' => 
        array (
          'baseHost' => 
          array (
            'name' => 'baseHost',
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
            'startLine' => 1157,
            'endLine' => 1157,
            'startColumn' => 33,
            'endColumn' => 48,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Sets the base hostname
 *
 * @param string $baseHost
 * @return $this
 */',
        'startLine' => 1157,
        'endLine' => 1161,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'currentClassName' => 'Dompdf\\Dompdf',
        'aliasName' => NULL,
      ),
      'get_host' => 
      array (
        'name' => 'get_host',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @return string
 * @deprecated
 */',
        'startLine' => 1167,
        'endLine' => 1170,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'currentClassName' => 'Dompdf\\Dompdf',
        'aliasName' => NULL,
      ),
      'getBaseHost' => 
      array (
        'name' => 'getBaseHost',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Returns the base hostname
 *
 * @return string
 */',
        'startLine' => 1177,
        'endLine' => 1180,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'currentClassName' => 'Dompdf\\Dompdf',
        'aliasName' => NULL,
      ),
      'set_base_path' => 
      array (
        'name' => 'set_base_path',
        'parameters' => 
        array (
          'path' => 
          array (
            'name' => 'path',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1188,
            'endLine' => 1188,
            'startColumn' => 35,
            'endColumn' => 39,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Sets the base path
 *
 * @param string $path
 * @deprecated
 */',
        'startLine' => 1188,
        'endLine' => 1191,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'currentClassName' => 'Dompdf\\Dompdf',
        'aliasName' => NULL,
      ),
      'setBasePath' => 
      array (
        'name' => 'setBasePath',
        'parameters' => 
        array (
          'basePath' => 
          array (
            'name' => 'basePath',
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
            'startLine' => 1199,
            'endLine' => 1199,
            'startColumn' => 33,
            'endColumn' => 48,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Sets the base path
 *
 * @param string $basePath
 * @return $this
 */',
        'startLine' => 1199,
        'endLine' => 1203,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'currentClassName' => 'Dompdf\\Dompdf',
        'aliasName' => NULL,
      ),
      'get_base_path' => 
      array (
        'name' => 'get_base_path',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @return string
 * @deprecated
 */',
        'startLine' => 1209,
        'endLine' => 1212,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'currentClassName' => 'Dompdf\\Dompdf',
        'aliasName' => NULL,
      ),
      'getBasePath' => 
      array (
        'name' => 'getBasePath',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Returns the base path
 *
 * @return string
 */',
        'startLine' => 1219,
        'endLine' => 1222,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'currentClassName' => 'Dompdf\\Dompdf',
        'aliasName' => NULL,
      ),
      'set_default_view' => 
      array (
        'name' => 'set_default_view',
        'parameters' => 
        array (
          'default_view' => 
          array (
            'name' => 'default_view',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1230,
            'endLine' => 1230,
            'startColumn' => 38,
            'endColumn' => 50,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'options' => 
          array (
            'name' => 'options',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1230,
            'endLine' => 1230,
            'startColumn' => 53,
            'endColumn' => 60,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param string $default_view The default document view
 * @param array $options The view\'s options
 * @return $this
 * @deprecated
 */',
        'startLine' => 1230,
        'endLine' => 1233,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'currentClassName' => 'Dompdf\\Dompdf',
        'aliasName' => NULL,
      ),
      'setDefaultView' => 
      array (
        'name' => 'setDefaultView',
        'parameters' => 
        array (
          'defaultView' => 
          array (
            'name' => 'defaultView',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1242,
            'endLine' => 1242,
            'startColumn' => 36,
            'endColumn' => 47,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'options' => 
          array (
            'name' => 'options',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1242,
            'endLine' => 1242,
            'startColumn' => 50,
            'endColumn' => 57,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Sets the default view
 *
 * @param string $defaultView The default document view
 * @param array $options The view\'s options
 * @return $this
 */',
        'startLine' => 1242,
        'endLine' => 1247,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'currentClassName' => 'Dompdf\\Dompdf',
        'aliasName' => NULL,
      ),
      'set_http_context' => 
      array (
        'name' => 'set_http_context',
        'parameters' => 
        array (
          'http_context' => 
          array (
            'name' => 'http_context',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1254,
            'endLine' => 1254,
            'startColumn' => 38,
            'endColumn' => 50,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param resource $http_context
 * @return $this
 * @deprecated
 */',
        'startLine' => 1254,
        'endLine' => 1257,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'currentClassName' => 'Dompdf\\Dompdf',
        'aliasName' => NULL,
      ),
      'setHttpContext' => 
      array (
        'name' => 'setHttpContext',
        'parameters' => 
        array (
          'httpContext' => 
          array (
            'name' => 'httpContext',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1265,
            'endLine' => 1265,
            'startColumn' => 36,
            'endColumn' => 47,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Sets the HTTP context
 *
 * @param resource|array $httpContext
 * @return $this
 */',
        'startLine' => 1265,
        'endLine' => 1269,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'currentClassName' => 'Dompdf\\Dompdf',
        'aliasName' => NULL,
      ),
      'get_http_context' => 
      array (
        'name' => 'get_http_context',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @return resource
 * @deprecated
 */',
        'startLine' => 1275,
        'endLine' => 1278,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'currentClassName' => 'Dompdf\\Dompdf',
        'aliasName' => NULL,
      ),
      'getHttpContext' => 
      array (
        'name' => 'getHttpContext',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Returns the HTTP context
 *
 * @return resource
 */',
        'startLine' => 1285,
        'endLine' => 1288,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'currentClassName' => 'Dompdf\\Dompdf',
        'aliasName' => NULL,
      ),
      'setCanvas' => 
      array (
        'name' => 'setCanvas',
        'parameters' => 
        array (
          'canvas' => 
          array (
            'name' => 'canvas',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Dompdf\\Canvas',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1299,
            'endLine' => 1299,
            'startColumn' => 31,
            'endColumn' => 44,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Set a custom `Canvas` instance to render the document to.
 *
 * Be aware that the instance will be replaced on render if the document
 * defines a paper size different from the canvas.
 *
 * @param Canvas $canvas
 * @return $this
 */',
        'startLine' => 1299,
        'endLine' => 1307,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'currentClassName' => 'Dompdf\\Dompdf',
        'aliasName' => NULL,
      ),
      'get_canvas' => 
      array (
        'name' => 'get_canvas',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @return Canvas
 * @deprecated
 */',
        'startLine' => 1313,
        'endLine' => 1316,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'currentClassName' => 'Dompdf\\Dompdf',
        'aliasName' => NULL,
      ),
      'getCanvas' => 
      array (
        'name' => 'getCanvas',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Return the underlying Canvas instance (e.g. Dompdf\\Adapter\\CPDF, Dompdf\\Adapter\\GD)
 *
 * @return Canvas
 */',
        'startLine' => 1323,
        'endLine' => 1326,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'currentClassName' => 'Dompdf\\Dompdf',
        'aliasName' => NULL,
      ),
      'setCss' => 
      array (
        'name' => 'setCss',
        'parameters' => 
        array (
          'css' => 
          array (
            'name' => 'css',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Dompdf\\Css\\Stylesheet',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1332,
            'endLine' => 1332,
            'startColumn' => 28,
            'endColumn' => 42,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param Stylesheet $css
 * @return $this
 */',
        'startLine' => 1332,
        'endLine' => 1336,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'currentClassName' => 'Dompdf\\Dompdf',
        'aliasName' => NULL,
      ),
      'get_css' => 
      array (
        'name' => 'get_css',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @return Stylesheet
 * @deprecated
 */',
        'startLine' => 1342,
        'endLine' => 1345,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'currentClassName' => 'Dompdf\\Dompdf',
        'aliasName' => NULL,
      ),
      'getCss' => 
      array (
        'name' => 'getCss',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Returns the stylesheet
 *
 * @return Stylesheet
 */',
        'startLine' => 1352,
        'endLine' => 1355,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'currentClassName' => 'Dompdf\\Dompdf',
        'aliasName' => NULL,
      ),
      'setDom' => 
      array (
        'name' => 'setDom',
        'parameters' => 
        array (
          'dom' => 
          array (
            'name' => 'dom',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'DOMDocument',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1361,
            'endLine' => 1361,
            'startColumn' => 28,
            'endColumn' => 43,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param DOMDocument $dom
 * @return $this
 */',
        'startLine' => 1361,
        'endLine' => 1365,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'currentClassName' => 'Dompdf\\Dompdf',
        'aliasName' => NULL,
      ),
      'get_dom' => 
      array (
        'name' => 'get_dom',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @return DOMDocument
 * @deprecated
 */',
        'startLine' => 1371,
        'endLine' => 1374,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'currentClassName' => 'Dompdf\\Dompdf',
        'aliasName' => NULL,
      ),
      'getDom' => 
      array (
        'name' => 'getDom',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @return DOMDocument
 */',
        'startLine' => 1379,
        'endLine' => 1382,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'currentClassName' => 'Dompdf\\Dompdf',
        'aliasName' => NULL,
      ),
      'setOptions' => 
      array (
        'name' => 'setOptions',
        'parameters' => 
        array (
          'options' => 
          array (
            'name' => 'options',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Dompdf\\Options',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1388,
            'endLine' => 1388,
            'startColumn' => 32,
            'endColumn' => 47,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param Options $options
 * @return $this
 */',
        'startLine' => 1388,
        'endLine' => 1410,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'currentClassName' => 'Dompdf\\Dompdf',
        'aliasName' => NULL,
      ),
      'getOptions' => 
      array (
        'name' => 'getOptions',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @return Options
 */',
        'startLine' => 1415,
        'endLine' => 1418,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'currentClassName' => 'Dompdf\\Dompdf',
        'aliasName' => NULL,
      ),
      'get_callbacks' => 
      array (
        'name' => 'get_callbacks',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @return array
 * @deprecated
 */',
        'startLine' => 1424,
        'endLine' => 1427,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'currentClassName' => 'Dompdf\\Dompdf',
        'aliasName' => NULL,
      ),
      'getCallbacks' => 
      array (
        'name' => 'getCallbacks',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Returns the callbacks array
 *
 * @return array
 */',
        'startLine' => 1434,
        'endLine' => 1437,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'currentClassName' => 'Dompdf\\Dompdf',
        'aliasName' => NULL,
      ),
      'set_callbacks' => 
      array (
        'name' => 'set_callbacks',
        'parameters' => 
        array (
          'callbacks' => 
          array (
            'name' => 'callbacks',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1444,
            'endLine' => 1444,
            'startColumn' => 35,
            'endColumn' => 44,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param array $callbacks the set of callbacks to set
 * @return $this
 * @deprecated
 */',
        'startLine' => 1444,
        'endLine' => 1447,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'currentClassName' => 'Dompdf\\Dompdf',
        'aliasName' => NULL,
      ),
      'setCallbacks' => 
      array (
        'name' => 'setCallbacks',
        'parameters' => 
        array (
          'callbacks' => 
          array (
            'name' => 'callbacks',
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
            'startLine' => 1471,
            'endLine' => 1471,
            'startColumn' => 34,
            'endColumn' => 49,
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
        'docComment' => '/**
 * Define callbacks that allow modifying the document during render.
 *
 * The callbacks array should contain arrays with `event` set to a callback
 * event name and `f` set to a function or any other callable.
 *
 * The available callback events are:
 * * `begin_page_reflow`: called before page reflow
 * * `begin_frame`: called before a frame is rendered
 * * `end_frame`: called after frame rendering is complete
 * * `begin_page_render`: called before a page is rendered
 * * `end_page_render`: called after page rendering is complete
 * * `end_document`: called for every page after rendering is complete
 *
 * The function `f` receives three arguments `Frame $frame`, `Canvas $canvas`,
 * and `FontMetrics $fontMetrics` for all events but `end_document`. For
 * `end_document`, the function receives four arguments `int $pageNumber`,
 * `int $pageCount`, `Canvas $canvas`, and `FontMetrics $fontMetrics` instead.
 *
 * @param array $callbacks The set of callbacks to set.
 * @return $this
 */',
        'startLine' => 1471,
        'endLine' => 1486,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'currentClassName' => 'Dompdf\\Dompdf',
        'aliasName' => NULL,
      ),
      'get_quirksmode' => 
      array (
        'name' => 'get_quirksmode',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @return boolean
 * @deprecated
 */',
        'startLine' => 1492,
        'endLine' => 1495,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'currentClassName' => 'Dompdf\\Dompdf',
        'aliasName' => NULL,
      ),
      'getQuirksmode' => 
      array (
        'name' => 'getQuirksmode',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the quirks mode
 *
 * @return boolean true if quirks mode is active
 */',
        'startLine' => 1502,
        'endLine' => 1505,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'currentClassName' => 'Dompdf\\Dompdf',
        'aliasName' => NULL,
      ),
      'setFontMetrics' => 
      array (
        'name' => 'setFontMetrics',
        'parameters' => 
        array (
          'fontMetrics' => 
          array (
            'name' => 'fontMetrics',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Dompdf\\FontMetrics',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1511,
            'endLine' => 1511,
            'startColumn' => 36,
            'endColumn' => 59,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param FontMetrics $fontMetrics
 * @return $this
 */',
        'startLine' => 1511,
        'endLine' => 1515,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'currentClassName' => 'Dompdf\\Dompdf',
        'aliasName' => NULL,
      ),
      'getFontMetrics' => 
      array (
        'name' => 'getFontMetrics',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @return FontMetrics
 */',
        'startLine' => 1520,
        'endLine' => 1523,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'currentClassName' => 'Dompdf\\Dompdf',
        'aliasName' => NULL,
      ),
      '__get' => 
      array (
        'name' => '__get',
        'parameters' => 
        array (
          'prop' => 
          array (
            'name' => 'prop',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1536,
            'endLine' => 1536,
            'startColumn' => 20,
            'endColumn' => 24,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * PHP5 overloaded getter
 * Along with {@link Dompdf::__set()} __get() provides access to all
 * properties directly.  Typically __get() is not called directly outside
 * of this class.
 *
 * @param string $prop
 *
 * @throws Exception
 * @return mixed
 */',
        'startLine' => 1536,
        'endLine' => 1544,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Dompdf',
        'declaringClassName' => 'Dompdf\\Dompdf',
        'implementingClassName' => 'Dompdf\\Dompdf',
        'currentClassName' => 'Dompdf\\Dompdf',
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