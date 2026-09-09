<?php
/**
 * Uninstall routine.
 *
 * @package Faytuks\StructuredData
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'fn_structured_data_settings' );

delete_metadata( 'post', 0, '_fn_structured_data_article_type', '', true );
delete_metadata( 'user', 0, 'fn_structured_data_dismissed_rank_math_notice', '', true );
