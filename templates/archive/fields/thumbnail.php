<?php
/**
 * Legacy archive fields thumbnail (compatibility shim)
 *
 * @deprecated 2.3.0 Use archive/sections/thumbnail.php instead.
 * @package     Havenlytics
 * @subpackage  Templates/archive/fields
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

hvnly_include_deprecated_template( 'archive/fields/thumbnail.php' );
