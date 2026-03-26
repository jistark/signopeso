<?php
// phpcs:ignore WordPress.Files.FileName
namespace ChocChop;

defined( 'ABSPATH' ) || exit;

use ChocChop\Core\QueueManager;
use ChocChop\Core\CostTracker;
use ChocChop\Core\VoiceManager;
use ChocChop\Core\SystemCardManager;

class Plugin {
    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init();
    }

    private function init() {
        // Load translations
        add_action('init', [$this, 'load_textdomain']);

        // Add security headers
        add_action('send_headers', [$this, 'add_security_headers']);

        // Check for database migration
        $db_version = get_option('choc_chop_db_version', '1.0');
        if (version_compare($db_version, '2.0', '<')) {
            $this->migrate_to_v2();
        }
        if (version_compare($db_version, '3.0', '<')) {
            $this->migrate_to_v3();
        }
        if (version_compare($db_version, '4.0', '<')) {
            $this->migrate_to_v4();
        }
        if (version_compare($db_version, '5.0', '<')) {
            $this->migrate_to_v5();
        }
        if (version_compare($db_version, '6.0', '<')) {
            $this->migrate_to_v6();
        }
        if (version_compare($db_version, '7.0', '<')) {
            $this->migrate_to_v7();
        }

        // Register OAuth callback handler (outside is_admin check — admin_post_ needs it)
        new Admin\OAuthCallbackHandler();

        // Initialize admin interface
        if (is_admin()) {
            new Admin\QueuePage();
            new Admin\SettingsPage();
            new Admin\QueueAjaxHandler();
            new Admin\DashboardWidget();
            new Admin\PostMetaBox();
        }

        // Initialize cron jobs
        new Core\Scheduler();

        // Initialize webhook handler
        new Core\WebhookHandler();

        // Register VoiceManager hook for post publishing
        add_action('wp_after_insert_post', [VoiceManager::class, 'on_post_published'], 10, 4);
    }

    /**
     * Load plugin translations
     */
    public function load_textdomain() {
        // phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- Required for WordPress.com Business compatibility; WP.org auto-loading not available.
        load_plugin_textdomain(
            'sp-chop',
            false,
            dirname(plugin_basename(CHOC_CHOP_PLUGIN_DIR)) . '/languages/'
        );
    }

    /**
     * Add security headers
     */
    public function add_security_headers() {
        // Prevent clickjacking
        if (!headers_sent()) {
            header('X-Frame-Options: SAMEORIGIN');
            header('X-Content-Type-Options: nosniff');
            header('X-XSS-Protection: 1; mode=block');
            header('Referrer-Policy: strict-origin-when-cross-origin');
        }
    }

    /**
     * Migration from v1.x to v2.0
     * Updates database schema and options for the new pipeline architecture
     */
    private function migrate_to_v2() {
        global $wpdb;

        // Drop and recreate queue table with new schema
        $queue_table = $wpdb->prefix . 'choc_chop_queue';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Migration: table name from $wpdb->prefix.
        $wpdb->query("DROP TABLE IF EXISTS {$queue_table}");
        QueueManager::create_table();

        // Alter usage table to add new columns
        $usage_table = $wpdb->prefix . 'choc_chop_usage';

        // Check if table exists before altering
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Migration: table name from $wpdb->prefix.
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$usage_table}'") === $usage_table;

        if ($table_exists) {
            // Add pass_number column if it doesn't exist
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Migration: table name from $wpdb->prefix.
            $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$usage_table} LIKE 'pass_number'");
            if (empty($column_exists)) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Migration: table name from $wpdb->prefix.
                $wpdb->query("ALTER TABLE {$usage_table} ADD COLUMN pass_number TINYINT(1) DEFAULT 0 AFTER operation");
            }

            // Add queue_id column if it doesn't exist
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Migration: table name from $wpdb->prefix.
            $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$usage_table} LIKE 'queue_id'");
            if (empty($column_exists)) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Migration: table name from $wpdb->prefix.
                $wpdb->query("ALTER TABLE {$usage_table} ADD COLUMN queue_id BIGINT(20) DEFAULT NULL AFTER post_id");
            }

            // Rename estimated_cost to actual_cost if needed
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Migration: table name from $wpdb->prefix.
            $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$usage_table} LIKE 'estimated_cost'");
            if (!empty($column_exists)) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Migration: table name from $wpdb->prefix.
                $wpdb->query("ALTER TABLE {$usage_table} CHANGE COLUMN estimated_cost actual_cost DECIMAL(10,6) NOT NULL");
            }
        }

        // Add new options for v2.0
        add_option('choc_chop_style_guide', '');
        add_option('choc_chop_voice_examples', '');
        add_option('choc_chop_voice_edit_patterns', '');
        add_option('choc_chop_voice_refreshed_at', 0);
        add_option('choc_chop_known_senders', '');
        add_option('choc_chop_last_email_check', 0);
        add_option('choc_chop_pipeline_auto', 'yes');
        add_option('choc_chop_monthly_budget', 0);

        // Set default email server if empty
        $email_server = get_option('choc_chop_email_server', '');
        if (empty($email_server)) {
            update_option('choc_chop_email_server', '{imap.gmail.com:993/imap/ssl}INBOX');
        }

        // Delete old options that are no longer used
        delete_option('choc_chop_source_feed');
        delete_option('choc_chop_rewrite_prompt');
        delete_option('choc_chop_auto_fetch');
        delete_option('choc_chop_openai_key');
        delete_option('choc_chop_openai_model');
        delete_option('choc_chop_prompt_templates');

        // Update database version
        update_option('choc_chop_db_version', '2.0');
    }

    /**
     * Migration from v2.x to v3.0
     * Replaces IMAP settings with Gmail API OAuth2 settings
     */
    private function migrate_to_v3() {
        // Add new Gmail API OAuth options
        add_option('choc_chop_gmail_client_id', '');
        add_option('choc_chop_gmail_client_secret', '');
        add_option('choc_chop_gmail_refresh_token', '');
        add_option('choc_chop_gmail_access_token', '');
        add_option('choc_chop_gmail_token_expiry', 0);

        // Remove old IMAP-specific options
        delete_option('choc_chop_email_server');
        delete_option('choc_chop_email_folder');
        delete_option('choc_chop_email_password');

        // Keep: choc_chop_email_username, choc_chop_known_senders

        update_option('choc_chop_db_version', '3.0');
    }

    /**
     * Migration from v3.x to v4.0
     * Adds retry_count column for failed item retry logic
     */
    private function migrate_to_v4() {
        global $wpdb;

        $queue_table = $wpdb->prefix . 'choc_chop_queue';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Migration: table name from $wpdb->prefix.
        $column_exists = $wpdb->get_results( "SHOW COLUMNS FROM {$queue_table} LIKE 'retry_count'" );
        if ( empty( $column_exists ) ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Migration: table name from $wpdb->prefix.
            $wpdb->query( "ALTER TABLE {$queue_table} ADD COLUMN retry_count TINYINT(1) NOT NULL DEFAULT 0 AFTER error_message" );
        }

        update_option( 'choc_chop_db_version', '4.0' );
    }

    /**
     * Migration from v4.x to v5.0
     * Adds system_card_slug and source_type columns to queue table.
     * Populates default system cards.
     */
    private function migrate_to_v5() {
        global $wpdb;

        $queue_table = $wpdb->prefix . 'choc_chop_queue';

        // Add system_card_slug column if it doesn't exist.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Migration: table name from $wpdb->prefix.
        $column_exists = $wpdb->get_results( "SHOW COLUMNS FROM {$queue_table} LIKE 'system_card_slug'" );
        if ( empty( $column_exists ) ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Migration: table name from $wpdb->prefix.
            $wpdb->query( "ALTER TABLE {$queue_table} ADD COLUMN system_card_slug VARCHAR(50) DEFAULT NULL AFTER retry_count" );
        }

        // Add source_type column if it doesn't exist.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Migration: table name from $wpdb->prefix.
        $column_exists = $wpdb->get_results( "SHOW COLUMNS FROM {$queue_table} LIKE 'source_type'" );
        if ( empty( $column_exists ) ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Migration: table name from $wpdb->prefix.
            $wpdb->query( "ALTER TABLE {$queue_table} ADD COLUMN source_type VARCHAR(20) NOT NULL DEFAULT 'email' AFTER system_card_slug" );
        }

        // Backfill existing queue items with source_type = 'email'.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Migration: table name from $wpdb->prefix.
        $wpdb->query( "UPDATE {$queue_table} SET source_type = 'email' WHERE source_type = '' OR source_type IS NULL" );

        // Populate default system cards.
        if ( ! get_option( SystemCardManager::OPTION_KEY ) ) {
            update_option( SystemCardManager::OPTION_KEY, SystemCardManager::get_default_cards() );
        }

        update_option( 'choc_chop_db_version', '5.0' );
    }

    /**
     * Migration from v5.x to v6.0
     * Adds source_url column for URL ingestion pipeline
     */
    private function migrate_to_v6() {
        global $wpdb;

        $queue_table = $wpdb->prefix . 'choc_chop_queue';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Migration: table name from $wpdb->prefix.
        $column_exists = $wpdb->get_results( "SHOW COLUMNS FROM {$queue_table} LIKE 'source_url'" );
        if ( empty( $column_exists ) ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Migration: table name from $wpdb->prefix.
            $wpdb->query( "ALTER TABLE {$queue_table} ADD COLUMN source_url TEXT DEFAULT NULL AFTER source_type" );
        }

        update_option( 'choc_chop_db_version', '6.0' );
    }

    /**
     * Migration from v6.x to v7.0
     * Adds site_recipes option for per-domain content extraction recipes
     */
    private function migrate_to_v7() {
        add_option( 'choc_chop_site_recipes', '{}' );
        update_option( 'choc_chop_db_version', '7.0' );
    }

    public static function activate() {
        // Set default options
        $defaults = [
            'choc_chop_ai_provider' => 'gemini',
            'choc_chop_anthropic_key' => '',
            'choc_chop_gemini_key' => '',
            'choc_chop_anthropic_model' => 'claude-3-5-haiku-20241022',
            'choc_chop_gemini_model' => 'gemini-2.0-flash-exp',
            'choc_chop_email_enabled' => 'no',
            'choc_chop_email_username' => '',
            'choc_chop_gmail_client_id' => '',
            'choc_chop_gmail_client_secret' => '',
            'choc_chop_gmail_refresh_token' => '',
            'choc_chop_gmail_access_token' => '',
            'choc_chop_gmail_token_expiry' => 0,
            'choc_chop_webhook_key' => wp_generate_password(32, false),
            'choc_chop_pipeline_auto' => 'yes',
            'choc_chop_monthly_budget' => 0,
            'choc_chop_style_guide' => '',
            'choc_chop_voice_examples' => '',
            'choc_chop_voice_edit_patterns' => '',
            'choc_chop_voice_refreshed_at' => 0,
            'choc_chop_known_senders' => '',
            'choc_chop_last_email_check' => 0,
            'choc_chop_site_recipes' => '{}',
            'choc_chop_db_version' => '7.0',
        ];

        foreach ($defaults as $key => $value) {
            add_option($key, $value);
        }

        // Create database tables
        CostTracker::create_table();
        QueueManager::create_table();

        // Schedule pipeline cron (every 15 minutes)
        if (!wp_next_scheduled('choc_chop_run_pipeline')) {
            wp_schedule_event(time(), 'choc_chop_15min', 'choc_chop_run_pipeline');
        }

        // Schedule voice refresh cron (weekly)
        if (!wp_next_scheduled('choc_chop_refresh_voice')) {
            wp_schedule_event(time(), 'weekly', 'choc_chop_refresh_voice');
        }

        // Add custom cron interval for 15 minutes if not already defined
        add_filter('cron_schedules', function($schedules) {
            if (!isset($schedules['choc_chop_15min'])) {
                $schedules['choc_chop_15min'] = [
                    'interval' => 900,
                    'display' => __('Every 15 Minutes', 'sp-chop'),
                ];
            }
            return $schedules;
        });
    }

    public static function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook('choc_chop_run_pipeline');
        wp_clear_scheduled_hook('choc_chop_refresh_voice');
        wp_clear_scheduled_hook('choc_chop_check_emails');
    }
}
