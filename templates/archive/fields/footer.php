<?php
/**
 * Legacy archive fields footer (compatibility shim)
 *
 * @deprecated 2.3.0 Use archive/sections/footer.php instead.
 * @package     Havenlytics
 * @subpackage  Templates/archive/fields
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

hvnly_include_deprecated_template( 'archive/fields/footer.php' );
