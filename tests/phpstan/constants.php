<?php
/**
 * Constant declarations for static analysis.
 *
 * The plugin defines these in its main file at runtime; PHPStan needs them
 * declared so their types are known when analysing src/.
 *
 * @package Faytuks\StructuredData
 */

define( 'FN_STRUCTURED_DATA_VERSION', '1.0.0' );
define( 'FN_STRUCTURED_DATA_FILE', __FILE__ );
define( 'FN_STRUCTURED_DATA_PATH', __DIR__ );
define( 'FN_STRUCTURED_DATA_URL', 'https://example.invalid/wp-content/plugins/fn-structured-data/' );
