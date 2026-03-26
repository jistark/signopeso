<?php

namespace ChocChop\Admin;

defined( 'ABSPATH' ) || exit;

use ChocChop\AI\ProviderFactory;
use ChocChop\Core\EmailFetcher;
use ChocChop\Core\CostTracker;
use ChocChop\Core\SystemCardManager;
use ChocChop\Admin\OAuthCallbackHandler;

class SettingsPage {
    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    /** @var string Hook suffix for script enqueuing. */
    private $hook_suffix = '';

    public function add_menu() {
        $this->hook_suffix = add_submenu_page(
            'sp-chop',
            __('Configuración', 'sp-chop'),
            __('Configuración', 'sp-chop'),
            'manage_options',
            'sp-chop-settings',
            [$this, 'render']
        );
    }

    public function enqueue_scripts($hook) {
        if ($hook !== $this->hook_suffix) {
            return;
        }

        wp_enqueue_style(
            'choc-chop-admin',
            CHOC_CHOP_PLUGIN_URL . 'assets/admin.css',
            [],
            CHOC_CHOP_VERSION
        );

        wp_enqueue_script(
            'choc-chop-admin',
            CHOC_CHOP_PLUGIN_URL . 'assets/admin.js',
            ['jquery'],
            CHOC_CHOP_VERSION,
            true
        );

        wp_localize_script('choc-chop-admin', 'chocChopAdmin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('choc_chop_settings_nonce'),
        ]);
    }

    public function register_settings() {
        // Gemini Settings
        register_setting('choc_chop_settings', 'choc_chop_gemini_key', [
            'sanitize_callback' => [$this, 'sanitize_api_key'],
            'default' => ''
        ]);
        register_setting('choc_chop_settings', 'choc_chop_gemini_model', [
            'sanitize_callback' => [$this, 'sanitize_gemini_model'],
            'default' => 'gemini-2.5-flash'
        ]);

        // Anthropic Settings (Backup)
        register_setting('choc_chop_settings', 'choc_chop_anthropic_key', [
            'sanitize_callback' => [$this, 'sanitize_api_key'],
            'default' => ''
        ]);
        register_setting('choc_chop_settings', 'choc_chop_anthropic_model', [
            'sanitize_callback' => [$this, 'sanitize_anthropic_model'],
            'default' => 'claude-3-5-haiku-20241022'
        ]);

        // Monthly Budget Alert
        register_setting('choc_chop_settings', 'choc_chop_monthly_budget', [
            'sanitize_callback' => [$this, 'sanitize_decimal'],
            'default' => '50.00'
        ]);

        // Gmail API OAuth Settings
        register_setting('choc_chop_settings', 'choc_chop_gmail_client_id', [
            'sanitize_callback' => [$this, 'sanitize_api_key'],
            'default' => ''
        ]);
        register_setting('choc_chop_settings', 'choc_chop_gmail_client_secret', [
            'sanitize_callback' => [$this, 'sanitize_api_key'],
            'default' => ''
        ]);
        register_setting('choc_chop_settings', 'choc_chop_known_senders', [
            'sanitize_callback' => 'sanitize_textarea_field',
            'default' => ''
        ]);

        // Cloudflare Browser Rendering (URL scrape fallback)
        register_setting('choc_chop_settings', 'choc_chop_cloudflare_account_id', [
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ''
        ]);
        register_setting('choc_chop_settings', 'choc_chop_cloudflare_api_token', [
            'sanitize_callback' => [$this, 'sanitize_api_key'],
            'default' => ''
        ]);

        // Pipeline Auto-Learning
        register_setting('choc_chop_settings', 'choc_chop_pipeline_auto', [
            'sanitize_callback' => [$this, 'sanitize_checkbox'],
            'default' => 'yes'
        ]);

        // System Cards — the sanitize callback reads the real card fields from $_POST
        register_setting('choc_chop_settings', 'choc_chop_system_cards', [
            'sanitize_callback' => [$this, 'sanitize_system_cards'],
        ]);
    }

    private static $sanitized_keys = [];

    public function sanitize_api_key($value) {
        $option_name = str_replace('sanitize_option_', '', current_filter());
        $value = sanitize_text_field($value);

        if (empty($value)) {
            // Preserve existing encrypted value when field is left blank
            $existing = get_option($option_name, '');
            return $existing;
        }

        // Prevent double encryption — WordPress can fire sanitize_option twice per request
        if (isset(self::$sanitized_keys[$option_name])) {
            return self::$sanitized_keys[$option_name];
        }

        $encrypted = \ChocChop\Core\Security::encrypt($value);
        self::$sanitized_keys[$option_name] = $encrypted;
        return $encrypted;
    }

    public function sanitize_gemini_model($value) {
        return \ChocChop\Core\Security::is_valid_model($value, 'gemini') ? $value : 'gemini-2.5-flash';
    }

    public function sanitize_anthropic_model($value) {
        return \ChocChop\Core\Security::is_valid_model($value, 'anthropic') ? $value : 'claude-3-5-haiku-20241022';
    }

    public function sanitize_checkbox($value) {
        return $value === 'yes' ? 'yes' : 'no';
    }

    public function sanitize_decimal($value) {
        $value = floatval($value);
        return $value >= 0 ? number_format($value, 2, '.', '') : '50.00';
    }

    /**
     * Sanitize system cards from POST data
     *
     * WordPress calls this when processing the choc_chop_system_cards setting.
     * We ignore $value (the hidden input placeholder) and read the real card
     * fields from $_POST['choc_chop_cards'].
     *
     * @param mixed $value Value from the hidden input (ignored).
     * @return array Sanitized cards array.
     */
    public function sanitize_system_cards( $value ) {
        // Read real card data from POST — nonce already verified by options.php
        // which calls check_admin_referer() before invoking sanitize callbacks.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified by options.php before this sanitize callback runs.
        $raw_cards = isset( $_POST['choc_chop_cards'] ) ? wp_unslash( $_POST['choc_chop_cards'] ) : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing -- Sanitized below; nonce via options.php.

        // If no card data posted (e.g. saving from a different tab), preserve existing.
        if ( ! is_array( $raw_cards ) ) {
            return get_option( 'choc_chop_system_cards', SystemCardManager::get_default_cards() );
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified by options.php before this sanitize callback runs.
        $default_index = isset( $_POST['choc_chop_default_card'] ) ? absint( $_POST['choc_chop_default_card'] ) : -1;

        $cards = array();
        foreach ( $raw_cards as $index => $card_data ) {
            if ( empty( $card_data['slug'] ) || empty( $card_data['name'] ) ) {
                continue;
            }
            $card_data['is_default'] = ( (int) $index === $default_index );
            $card_data['is_active']  = ! empty( $card_data['is_active'] );
            $cards[] = array(
                'slug'          => sanitize_key( $card_data['slug'] ?? '' ),
                'name'          => sanitize_text_field( $card_data['name'] ?? '' ),
                'system_prompt' => sanitize_textarea_field( $card_data['system_prompt'] ?? '' ),
                'sp_formato'    => sanitize_key( $card_data['sp_formato'] ?? '' ),
                'word_min'      => max( 0, absint( $card_data['word_min'] ?? 80 ) ),
                'word_max'      => max( 0, absint( $card_data['word_max'] ?? 800 ) ),
                'is_default'    => $card_data['is_default'],
                'is_active'     => $card_data['is_active'],
            );
        }

        // Ensure at least one default if none selected.
        $has_default = false;
        foreach ( $cards as $card ) {
            if ( ! empty( $card['is_default'] ) ) {
                $has_default = true;
                break;
            }
        }
        if ( ! $has_default && ! empty( $cards ) ) {
            $cards[0]['is_default'] = true;
        }

        return $cards;
    }

    public function render() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'sp-chop'));
        }

        $gmail_connected   = EmailFetcher::is_available();
        $gmail_email       = get_option( 'choc_chop_email_username', '' );
        $auth_url          = OAuthCallbackHandler::get_auth_url();
        $has_client_config = ! empty( get_option( 'choc_chop_gmail_client_id', '' ) );

        // Get voice profile
        $voice_profile = get_option('choc_chop_voice_profile', '');
        $example_posts = $this->get_example_posts();
        ?>
        <div class="wrap choc-chop-settings-wrap">
            <h1><?php esc_html_e('SignoPeso Chop Settings', 'sp-chop'); ?></h1>

            <form method="post" action="options.php">
                <?php settings_fields('choc_chop_settings'); ?>

                <!-- Tabs -->
                <div class="choc-chop-settings-tabs">
                    <button type="button" class="settings-tab active" data-tab="gmail"><?php esc_html_e('Gmail', 'sp-chop'); ?></button>
                    <button type="button" class="settings-tab" data-tab="ia"><?php esc_html_e('IA', 'sp-chop'); ?></button>
                    <button type="button" class="settings-tab" data-tab="voz"><?php esc_html_e('Voz del Sitio', 'sp-chop'); ?></button>
                    <button type="button" class="settings-tab" data-tab="tarjetas"><?php esc_html_e('Tarjetas', 'sp-chop'); ?></button>
                    <button type="button" class="settings-tab" data-tab="recetas"><?php esc_html_e('Recetas', 'sp-chop'); ?></button>
                </div>

                <!-- Tab 1: Gmail -->
                <div class="settings-tab-content active" id="tab-gmail">
                    <div class="settings-section">
                        <h2><?php esc_html_e('Gmail API Configuration', 'sp-chop'); ?></h2>

                        <!-- Auth Status -->
                        <div class="gmail-auth-status <?php echo $gmail_connected ? 'connected' : 'disconnected'; ?>">
                            <?php if ($gmail_connected): ?>
                                <span class="dashicons dashicons-yes-alt"></span>
                                <?php
                                /* translators: %s: Gmail email address that is connected */
                                printf(esc_html__('Conectado como: %s', 'sp-chop'), '<strong>' . esc_html($gmail_email) . '</strong>'); ?>
                            <?php else: ?>
                                <span class="dashicons dashicons-warning"></span>
                                <?php esc_html_e('No autorizado', 'sp-chop'); ?>
                            <?php endif; ?>
                        </div>

                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e('Client ID', 'sp-chop'); ?></th>
                                <td>
                                    <input type="password" name="choc_chop_gmail_client_id" value="" class="regular-text" autocomplete="off" placeholder="<?php echo get_option('choc_chop_gmail_client_id') ? esc_attr__('••••••••', 'sp-chop') : ''; ?>">
                                    <p class="description"><?php esc_html_e('OAuth 2.0 Client ID from Google Cloud Console', 'sp-chop'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Client Secret', 'sp-chop'); ?></th>
                                <td>
                                    <input type="password" name="choc_chop_gmail_client_secret" value="" class="regular-text" autocomplete="off" placeholder="<?php echo get_option('choc_chop_gmail_client_secret') ? esc_attr__('••••••••', 'sp-chop') : ''; ?>">
                                    <p class="description"><?php esc_html_e('OAuth 2.0 Client Secret from Google Cloud Console', 'sp-chop'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Authorization', 'sp-chop'); ?></th>
                                <td>
                                    <div class="gmail-auth-actions">
                                        <?php if ($gmail_connected): ?>
                                            <button type="button" id="test-email-connection" class="button"><?php esc_html_e('Test Connection', 'sp-chop'); ?></button>
                                            <button type="button" id="disconnect-gmail" class="button button-link-delete"><?php esc_html_e('Desconectar', 'sp-chop'); ?></button>
                                        <?php elseif ($auth_url): ?>
                                            <a href="<?php echo esc_url($auth_url); ?>" class="button button-primary"><?php esc_html_e('Autorizar Gmail', 'sp-chop'); ?></a>
                                        <?php else: ?>
                                            <p class="description"><?php esc_html_e('Guarda el Client ID y Client Secret primero, luego podrás autorizar.', 'sp-chop'); ?></p>
                                        <?php endif; ?>
                                        <span class="spinner"></span>
                                    </div>
                                    <div id="email-test-result"></div>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Known Senders', 'sp-chop'); ?></th>
                                <td>
                                    <textarea name="choc_chop_known_senders" rows="6" class="large-text"><?php echo esc_textarea(get_option('choc_chop_known_senders', '')); ?></textarea>
                                    <p class="description"><?php esc_html_e('Enter one email address per line. Only emails from these senders will be processed.', 'sp-chop'); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Tab 2: IA -->
                <div class="settings-tab-content" id="tab-ia">
                    <div class="settings-section">
                        <h2><?php esc_html_e('AI Provider Settings', 'sp-chop'); ?></h2>

                        <h3><?php esc_html_e('Google Gemini (Primary)', 'sp-chop'); ?></h3>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e('Gemini API Key', 'sp-chop'); ?></th>
                                <td>
                                    <input type="password" name="choc_chop_gemini_key" value="" class="regular-text" autocomplete="off" placeholder="<?php echo get_option('choc_chop_gemini_key') ? esc_attr__('••••••••', 'sp-chop') : ''; ?>">
                                    <p class="description"><?php
                                    /* translators: %s: URL to Google AI Studio API key page */
                                    printf(wp_kses(__('Get your API key from <a href="%s" target="_blank" rel="noopener noreferrer">Google AI Studio</a>', 'sp-chop'), ['a' => ['href' => [], 'target' => [], 'rel' => []]]), 'https://makersuite.google.com/app/apikey'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Gemini Model', 'sp-chop'); ?></th>
                                <td>
                                    <select name="choc_chop_gemini_model">
                                        <option value="gemini-2.5-flash" <?php selected(get_option('choc_chop_gemini_model'), 'gemini-2.5-flash'); ?>><?php esc_html_e('Gemini 2.5 Flash (Fastest, Recommended)', 'sp-chop'); ?></option>
                                        <option value="gemini-2.5-pro" <?php selected(get_option('choc_chop_gemini_model'), 'gemini-2.5-pro'); ?>><?php esc_html_e('Gemini 2.5 Pro (Best Quality)', 'sp-chop'); ?></option>
                                        <option value="gemini-2.0-flash" <?php selected(get_option('choc_chop_gemini_model'), 'gemini-2.0-flash'); ?>><?php esc_html_e('Gemini 2.0 Flash (Balanced)', 'sp-chop'); ?></option>
                                        <option value="gemini-2.0-pro" <?php selected(get_option('choc_chop_gemini_model'), 'gemini-2.0-pro'); ?>><?php esc_html_e('Gemini 2.0 Pro (Advanced)', 'sp-chop'); ?></option>
                                    </select>
                                </td>
                            </tr>
                        </table>

                        <h3><?php esc_html_e('Anthropic Claude (Backup)', 'sp-chop'); ?></h3>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e('Anthropic API Key', 'sp-chop'); ?></th>
                                <td>
                                    <input type="password" name="choc_chop_anthropic_key" value="" class="regular-text" autocomplete="off" placeholder="<?php echo get_option('choc_chop_anthropic_key') ? esc_attr__('••••••••', 'sp-chop') : ''; ?>">
                                    <p class="description"><?php esc_html_e('Used as fallback if Gemini fails', 'sp-chop'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Claude Model', 'sp-chop'); ?></th>
                                <td>
                                    <select name="choc_chop_anthropic_model">
                                        <option value="claude-3-5-haiku-20241022" <?php selected(get_option('choc_chop_anthropic_model'), 'claude-3-5-haiku-20241022'); ?>><?php esc_html_e('Claude 3.5 Haiku (Fastest, Cheapest)', 'sp-chop'); ?></option>
                                        <option value="claude-3-5-sonnet-20241022" <?php selected(get_option('choc_chop_anthropic_model'), 'claude-3-5-sonnet-20241022'); ?>><?php esc_html_e('Claude 3.5 Sonnet (Best Quality)', 'sp-chop'); ?></option>
                                    </select>
                                </td>
                            </tr>
                        </table>

                        <h3><?php esc_html_e('Budget Management', 'sp-chop'); ?></h3>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e('Monthly Budget Alert', 'sp-chop'); ?></th>
                                <td>
                                    <input type="number" name="choc_chop_monthly_budget" value="<?php echo esc_attr(get_option('choc_chop_monthly_budget', '50.00')); ?>" step="0.01" min="0" class="small-text"> USD
                                    <?php
                                    $monthly_spend = CostTracker::get_monthly_spend();
                                    $budget_val = (float) get_option('choc_chop_monthly_budget', 0);
                                    $over_budget = $budget_val > 0 && $monthly_spend >= $budget_val;
                                    ?>
                                    <span style="margin-left: 8px; <?php echo $over_budget ? 'color: #d63638; font-weight: 600;' : 'color: #50575e;'; ?>">
                                        <?php
                                        /* translators: %s: current month's spend in USD */
                                        printf(esc_html__('Current month: $%s', 'sp-chop'), esc_html(number_format($monthly_spend, 4)));
                                        ?>
                                    </span>
                                    <p class="description"><?php esc_html_e('Pipeline will pause when monthly costs reach this amount. Set to 0 for unlimited.', 'sp-chop'); ?></p>
                                </td>
                            </tr>
                        </table>
                        <h3><?php esc_html_e('Cloudflare (Fallback)', 'sp-chop'); ?></h3>
                        <p class="description"><?php esc_html_e('Used as fallback when direct URL fetch fails. Create a token with "Browser Rendering - Edit" permission.', 'sp-chop'); ?></p>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e('Account ID', 'sp-chop'); ?></th>
                                <td>
                                    <input type="text" name="choc_chop_cloudflare_account_id" value="<?php echo esc_attr(get_option('choc_chop_cloudflare_account_id', '')); ?>" class="regular-text" autocomplete="off">
                                    <p class="description"><?php esc_html_e('Your Cloudflare Account ID (found in the dashboard URL or sidebar).', 'sp-chop'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('API Token', 'sp-chop'); ?></th>
                                <td>
                                    <input type="password" name="choc_chop_cloudflare_api_token" value="" class="regular-text" autocomplete="off" placeholder="<?php echo get_option('choc_chop_cloudflare_api_token') ? esc_attr__('••••••••', 'sp-chop') : ''; ?>">
                                    <p class="description"><?php esc_html_e('API token with Browser Rendering permission. Stored encrypted.', 'sp-chop'); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Tab 3: Voz del Sitio -->
                <div class="settings-tab-content" id="tab-voz">
                    <div class="settings-section">
                        <h2><?php esc_html_e('Voice Profile - Editorial Style', 'sp-chop'); ?></h2>
                        <p class="description"><?php esc_html_e('This profile is auto-generated from your published posts to maintain consistent editorial voice.', 'sp-chop'); ?></p>

                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e('Current Style Profile', 'sp-chop'); ?></th>
                                <td>
                                    <textarea id="voice-profile-display" rows="12" class="large-text choc-chop-voice-profile" readonly><?php echo esc_textarea($voice_profile ? $voice_profile : __('No voice profile generated yet. Click "Regenerate" to create one from your published posts.', 'sp-chop')); ?></textarea>
                                    <p>
                                        <button type="button" id="regenerate-voice" class="button"><?php esc_html_e('Regenerate Voice Profile', 'sp-chop'); ?></button>
                                        <span class="spinner"></span>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Example Posts Used', 'sp-chop'); ?></th>
                                <td>
                                    <ul class="choc-chop-example-posts">
                                        <?php if (!empty($example_posts)): ?>
                                            <?php foreach ($example_posts as $post): ?>
                                                <li><?php echo esc_html($post->post_title); ?> <span style="color: #666;">(<?php echo esc_html(get_the_date('', $post->ID)); ?>)</span></li>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <li><?php esc_html_e('No posts available for analysis', 'sp-chop'); ?></li>
                                        <?php endif; ?>
                                    </ul>
                                    <p class="description"><?php esc_html_e('Voice profile is generated from the 10 most recent published posts', 'sp-chop'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e('Auto-Learning', 'sp-chop'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="choc_chop_pipeline_auto" value="yes" <?php checked(get_option('choc_chop_pipeline_auto', 'yes'), 'yes'); ?>>
                                        <?php esc_html_e('Automatically update voice profile when new posts are published', 'sp-chop'); ?>
                                    </label>
                                    <p class="description"><?php esc_html_e('When enabled, the voice profile refreshes weekly to adapt to your evolving writing style', 'sp-chop'); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Tab 4: Tarjetas -->
                <div class="settings-tab-content" id="tab-tarjetas">
                    <div class="settings-section">
                        <h2><?php esc_html_e('System Cards — Prompt Templates', 'sp-chop'); ?></h2>
                        <p class="description"><?php esc_html_e('Each card defines a prompt template for a specific article format. Cards map to sp_formato taxonomy terms.', 'sp-chop'); ?></p>

                        <?php $this->render_cards_editor(); ?>
                    </div>
                </div>

                <?php submit_button(); ?>
            </form>

            <!-- Tab 5: Recetas (outside form — uses AJAX) -->
            <div class="settings-tab-content" id="tab-recetas">
                <div class="settings-section">
                    <h2><?php esc_html_e('Recetas de sitio', 'sp-chop'); ?></h2>
                    <p class="description"><?php esc_html_e('Reglas de extracción por dominio. Se auto-aprenden con cada scrape exitoso. Marca "Bloquear" para evitar que el auto-aprendizaje modifique una receta.', 'sp-chop'); ?></p>

                    <?php
                    $recipes = \ChocChop\Core\RecipeManager::get_all();
                    if ( empty( $recipes ) ) :
                    ?>
                        <p><?php esc_html_e('No hay recetas aún. Se crearán automáticamente al procesar URLs.', 'sp-chop'); ?></p>
                    <?php else : ?>
                        <table class="wp-list-table widefat striped" id="sp-chop-recipes-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Dominio', 'sp-chop'); ?></th>
                                    <th><?php esc_html_e('Selector', 'sp-chop'); ?></th>
                                    <th><?php esc_html_e('Éxitos', 'sp-chop'); ?></th>
                                    <th><?php esc_html_e('Aprendida', 'sp-chop'); ?></th>
                                    <th><?php esc_html_e('Estado', 'sp-chop'); ?></th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $recipes as $domain => $recipe ) : ?>
                                <tr data-domain="<?php echo esc_attr( $domain ); ?>">
                                    <td><strong><?php echo esc_html( $domain ); ?></strong></td>
                                    <td><code><?php echo esc_html( $recipe['content_selector'] ?? '—' ); ?></code></td>
                                    <td><?php echo absint( $recipe['success_count'] ?? 0 ); ?></td>
                                    <td><?php echo esc_html( $recipe['learned_at'] ?? '—' ); ?></td>
                                    <td><?php echo ! empty( $recipe['manual_override'] ) ? esc_html__('Bloqueada', 'sp-chop') : esc_html__('Auto', 'sp-chop'); ?></td>
                                    <td>
                                        <button type="button" class="button button-small sp-chop-edit-recipe"><?php esc_html_e('Editar', 'sp-chop'); ?></button>
                                        <button type="button" class="button button-small sp-chop-delete-recipe"><?php esc_html_e('Eliminar', 'sp-chop'); ?></button>
                                    </td>
                                </tr>
                                <tr class="sp-chop-recipe-editor" style="display:none;">
                                    <td colspan="6" style="padding: 16px;">
                                        <table class="form-table">
                                            <tr>
                                                <th scope="row"><?php esc_html_e('Selector de contenido', 'sp-chop'); ?></th>
                                                <td><input type="text" class="regular-text recipe-content-selector" value="<?php echo esc_attr( $recipe['content_selector'] ?? '' ); ?>" placeholder="div.article-body"></td>
                                            </tr>
                                            <tr>
                                                <th scope="row"><?php esc_html_e('Selectores a eliminar', 'sp-chop'); ?></th>
                                                <td><textarea class="large-text recipe-strip-selectors" rows="3" placeholder=".share-bar&#10;.related-news"><?php echo esc_textarea( implode( "\n", $recipe['strip_selectors'] ?? [] ) ); ?></textarea>
                                                <p class="description"><?php esc_html_e('Un selector CSS por línea. Se eliminan dentro del área de contenido.', 'sp-chop'); ?></p></td>
                                            </tr>
                                            <tr>
                                                <th scope="row"><?php esc_html_e('Patrones de texto', 'sp-chop'); ?></th>
                                                <td><textarea class="large-text recipe-strip-text" rows="3" placeholder="FacebookXWhatsApp.*link"><?php echo esc_textarea( implode( "\n", $recipe['strip_text'] ?? [] ) ); ?></textarea>
                                                <p class="description"><?php esc_html_e('Expresiones regulares, una por línea. Se aplican al texto extraído.', 'sp-chop'); ?></p></td>
                                            </tr>
                                            <tr>
                                                <th scope="row"><?php esc_html_e('Opciones', 'sp-chop'); ?></th>
                                                <td><label><input type="checkbox" class="recipe-manual-override" <?php checked( ! empty( $recipe['manual_override'] ) ); ?>> <?php esc_html_e('Bloquear auto-aprendizaje', 'sp-chop'); ?></label></td>
                                            </tr>
                                        </table>
                                        <p>
                                            <button type="button" class="button button-primary sp-chop-save-recipe"><?php esc_html_e('Guardar', 'sp-chop'); ?></button>
                                            <button type="button" class="button sp-chop-cancel-recipe"><?php esc_html_e('Cancelar', 'sp-chop'); ?></button>
                                        </p>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>

                    <h3><?php esc_html_e('Agregar receta manual', 'sp-chop'); ?></h3>
                    <div style="display:flex; gap:8px; align-items:center;">
                        <input type="text" id="new-recipe-domain" class="regular-text" placeholder="ejemplo.com">
                        <input type="text" id="new-recipe-selector" class="regular-text" placeholder="div.article-body">
                        <button type="button" id="sp-chop-add-recipe" class="button"><?php esc_html_e('Agregar', 'sp-chop'); ?></button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render the system cards editor
     */
    private function render_cards_editor() {
        $cards = SystemCardManager::get_cards();
        $default_slugs = array( 'corto', 'enlace', 'largo', 'cobertura' );

        // Get available sp_formato terms.
        $formato_terms = array();
        if ( taxonomy_exists( 'sp_formato' ) ) {
            $terms = get_terms( array(
                'taxonomy'   => 'sp_formato',
                'hide_empty' => false,
            ) );
            if ( ! is_wp_error( $terms ) ) {
                foreach ( $terms as $term ) {
                    $formato_terms[] = array( 'slug' => $term->slug, 'name' => $term->name );
                }
            }
        }

        ?>
        <div id="choc-chop-cards-editor">
            <?php foreach ( $cards as $index => $card ) :
                $is_default_card = in_array( $card['slug'], $default_slugs, true );
            ?>
            <div class="choc-chop-card-item" data-index="<?php echo esc_attr( $index ); ?>">
                <div class="card-header" role="button" tabindex="0">
                    <span class="card-toggle dashicons dashicons-arrow-right"></span>
                    <strong class="card-title-display"><?php echo esc_html( $card['name'] ); ?></strong>
                    <?php if ( ! empty( $card['is_default'] ) ) : ?>
                        <span class="status-badge badge-blue"><?php esc_html_e( 'Default', 'sp-chop' ); ?></span>
                    <?php endif; ?>
                    <?php if ( empty( $card['is_active'] ) ) : ?>
                        <span class="status-badge badge-gray"><?php esc_html_e( 'Inactive', 'sp-chop' ); ?></span>
                    <?php endif; ?>
                    <span class="card-word-range"><?php echo esc_html( $card['word_min'] . '–' . $card['word_max'] . ' palabras' ); ?></span>
                </div>
                <div class="card-body" style="display: none;">
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Slug', 'sp-chop' ); ?></th>
                            <td>
                                <input type="text" name="choc_chop_cards[<?php echo esc_attr( $index ); ?>][slug]" value="<?php echo esc_attr( $card['slug'] ); ?>" class="regular-text" <?php echo $is_default_card ? 'readonly' : ''; ?>>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Name', 'sp-chop' ); ?></th>
                            <td>
                                <input type="text" name="choc_chop_cards[<?php echo esc_attr( $index ); ?>][name]" value="<?php echo esc_attr( $card['name'] ); ?>" class="regular-text card-name-input">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'System Prompt', 'sp-chop' ); ?></th>
                            <td>
                                <textarea name="choc_chop_cards[<?php echo esc_attr( $index ); ?>][system_prompt]" rows="8" class="large-text"><?php echo esc_textarea( $card['system_prompt'] ); ?></textarea>
                                <p class="description"><?php esc_html_e( 'Variables: {site_name}, {voice_context}, {word_min}, {word_max}', 'sp-chop' ); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Word Range', 'sp-chop' ); ?></th>
                            <td>
                                <input type="number" name="choc_chop_cards[<?php echo esc_attr( $index ); ?>][word_min]" value="<?php echo esc_attr( $card['word_min'] ); ?>" min="0" class="small-text"> —
                                <input type="number" name="choc_chop_cards[<?php echo esc_attr( $index ); ?>][word_max]" value="<?php echo esc_attr( $card['word_max'] ); ?>" min="0" class="small-text">
                                <?php esc_html_e( 'words', 'sp-chop' ); ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'sp_formato', 'sp-chop' ); ?></th>
                            <td>
                                <?php if ( ! empty( $formato_terms ) ) : ?>
                                <select name="choc_chop_cards[<?php echo esc_attr( $index ); ?>][sp_formato]">
                                    <option value=""><?php esc_html_e( '— None —', 'sp-chop' ); ?></option>
                                    <?php foreach ( $formato_terms as $term ) : ?>
                                    <option value="<?php echo esc_attr( $term['slug'] ); ?>" <?php selected( $card['sp_formato'], $term['slug'] ); ?>><?php echo esc_html( $term['name'] ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php else : ?>
                                <input type="text" name="choc_chop_cards[<?php echo esc_attr( $index ); ?>][sp_formato]" value="<?php echo esc_attr( $card['sp_formato'] ); ?>" class="regular-text">
                                <p class="description"><?php esc_html_e( 'sp_formato taxonomy not registered. Enter slug manually.', 'sp-chop' ); ?></p>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e( 'Options', 'sp-chop' ); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="choc_chop_cards[<?php echo esc_attr( $index ); ?>][is_active]" value="1" <?php checked( $card['is_active'] ); ?>>
                                    <?php esc_html_e( 'Active', 'sp-chop' ); ?>
                                </label>
                                &nbsp;&nbsp;
                                <label>
                                    <input type="radio" name="choc_chop_default_card" value="<?php echo esc_attr( $index ); ?>" <?php checked( ! empty( $card['is_default'] ) ); ?>>
                                    <?php esc_html_e( 'Default card', 'sp-chop' ); ?>
                                </label>
                                <?php if ( ! $is_default_card ) : ?>
                                &nbsp;&nbsp;
                                <button type="button" class="button button-link-delete card-delete-btn"><?php esc_html_e( 'Delete Card', 'sp-chop' ); ?></button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <p>
            <button type="button" id="add-system-card" class="button">
                <span class="dashicons dashicons-plus-alt2" style="vertical-align: middle;"></span>
                <?php esc_html_e( 'Add Card', 'sp-chop' ); ?>
            </button>
        </p>

        <input type="hidden" name="choc_chop_system_cards" value="1">
        <?php
    }

    private function get_example_posts() {
        return get_posts([
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => 10,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);
    }
}
