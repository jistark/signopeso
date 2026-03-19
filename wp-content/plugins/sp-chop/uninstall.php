<?php
/**
 * Uninstall handler for SignoPeso Chop
 *
 * Removes all plugin data: custom tables, options, post meta, transients, and log files.
 * This file is called by WordPress when the plugin is deleted via the admin UI.
 *
 * @package ChocChop
 * @since 1.0.0
 */

// Exit if not called by WordPress uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Drop custom tables.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Uninstall cleanup.
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}choc_chop_queue" );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Uninstall cleanup.
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}choc_chop_usage" );

// Delete all choc_chop_* options.
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Uninstall file, not loaded at runtime.
$choc_chop_option_keys = array(
	'choc_chop_ai_pricing',
	'choc_chop_ai_provider',
	'choc_chop_anthropic_key',
	'choc_chop_anthropic_model',
	'choc_chop_cloudflare_account_id',
	'choc_chop_cloudflare_api_token',
	'choc_chop_db_version',
	'choc_chop_email_enabled',
	'choc_chop_email_username',
	'choc_chop_gemini_key',
	'choc_chop_gemini_model',
	'choc_chop_gmail_access_token',
	'choc_chop_gmail_client_id',
	'choc_chop_gmail_client_secret',
	'choc_chop_gmail_refresh_token',
	'choc_chop_gmail_token_expiry',
	'choc_chop_known_senders',
	'choc_chop_last_email_check',
	'choc_chop_monthly_budget',
	'choc_chop_pipeline_auto',
	'choc_chop_site_recipes',
	'choc_chop_style_guide',
	'choc_chop_system_cards',
	'choc_chop_voice_edit_patterns',
	'choc_chop_voice_examples',
	'choc_chop_voice_profile',
	'choc_chop_voice_refreshed_at',
	'choc_chop_webhook_key',
);

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Uninstall file loop variable.
foreach ( $choc_chop_option_keys as $choc_chop_key ) {
	delete_option( $choc_chop_key );
}

// Delete all _choc_chop_* post meta across all posts.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Uninstall cleanup of plugin post meta.
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_choc_chop_%'" );

// Clean up transients.
delete_transient( 'choc_chop_monthly_spend' );
delete_transient( 'choc_chop_popular_posts' );

// Clean up scrape context transients.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_sp_chop_scrape_ctx_%' OR option_name LIKE '_transient_timeout_sp_chop_scrape_ctx_%'" );

// Remove error log file.
$choc_chop_upload_dir = wp_upload_dir();
$choc_chop_log_file   = $choc_chop_upload_dir['basedir'] . '/choc-chop-errors.log';
if ( file_exists( $choc_chop_log_file ) ) {
	wp_delete_file( $choc_chop_log_file );
}

// Also remove rotated log backup.
$choc_chop_log_backup = $choc_chop_log_file . '.old';
if ( file_exists( $choc_chop_log_backup ) ) {
	wp_delete_file( $choc_chop_log_backup );
}

// Clear any remaining scheduled events.
wp_clear_scheduled_hook( 'choc_chop_run_pipeline' );
wp_clear_scheduled_hook( 'choc_chop_refresh_voice' );
wp_clear_scheduled_hook( 'choc_chop_check_emails' );
