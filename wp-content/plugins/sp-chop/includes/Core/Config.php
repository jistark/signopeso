<?php
/**
 * Centralized Configuration Registry
 *
 * Wraps get_option/update_option with the choc_chop_ prefix and provides
 * a complete inventory of all plugin option keys.
 *
 * @package ChocChop\Core
 * @since 1.0.0
 */

namespace ChocChop\Core;

class Config {

	/**
	 * Complete list of all plugin option keys (without prefix)
	 *
	 * Used by uninstall.php and for documentation purposes.
	 */
	const ALL_KEYS = array(
		'ai_pricing',
		'ai_provider',
		'anthropic_key',
		'anthropic_model',
		'cloudflare_account_id',
		'cloudflare_api_token',
		'db_version',
		'email_enabled',
		'email_username',
		'gemini_key',
		'gemini_model',
		'gmail_access_token',
		'gmail_client_id',
		'gmail_client_secret',
		'gmail_refresh_token',
		'gmail_token_expiry',
		'known_senders',
		'last_email_check',
		'monthly_budget',
		'pipeline_auto',
		'site_recipes',
		'style_guide',
		'system_cards',
		'voice_edit_patterns',
		'voice_examples',
		'voice_profile',
		'voice_refreshed_at',
		'webhook_key',
	);

	/**
	 * Option name prefix
	 */
	const PREFIX = 'choc_chop_';

	/**
	 * Get a plugin option
	 *
	 * @param string $key     Option key (without prefix).
	 * @param mixed  $default Default value if option doesn't exist.
	 * @return mixed Option value.
	 */
	public static function get( string $key, $default = false ) {
		return get_option( self::PREFIX . $key, $default );
	}

	/**
	 * Set a plugin option
	 *
	 * @param string $key   Option key (without prefix).
	 * @param mixed  $value Value to store.
	 * @return bool True if updated, false otherwise.
	 */
	public static function set( string $key, $value ): bool {
		return update_option( self::PREFIX . $key, $value );
	}

	/**
	 * Delete a plugin option
	 *
	 * @param string $key Option key (without prefix).
	 * @return bool True if deleted, false otherwise.
	 */
	public static function delete( string $key ): bool {
		return delete_option( self::PREFIX . $key );
	}

	/**
	 * Get all prefixed option keys (with prefix)
	 *
	 * @return array Full option names for use with delete_option, etc.
	 */
	public static function get_all_option_names(): array {
		return array_map(
			function ( $key ) {
				return self::PREFIX . $key;
			},
			self::ALL_KEYS
		);
	}
}
