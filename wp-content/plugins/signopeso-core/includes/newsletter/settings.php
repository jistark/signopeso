<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function sp_register_newsletter_settings() {
    register_setting( 'sp_newsletter', 'sp_newsletter_settings' );

    add_options_page(
        'SignoPeso Newsletter',
        'SP Newsletter',
        'manage_options',
        'sp-newsletter',
        'sp_render_newsletter_settings'
    );
}
add_action( 'admin_menu', 'sp_register_newsletter_settings' );
add_action( 'admin_init', function() {
    register_setting( 'sp_newsletter', 'sp_newsletter_settings' );
});

function sp_render_newsletter_settings() {
    $settings = get_option( 'sp_newsletter_settings', array() );
    ?>
    <div class="wrap">
        <h1>SignoPeso — Newsletter</h1>
        <form method="post" action="options.php">
            <?php settings_fields( 'sp_newsletter' ); ?>
            <table class="form-table">
                <tr>
                    <th>Activar Newsletter</th>
                    <td><label><input type="checkbox" name="sp_newsletter_settings[enabled]" value="1" <?php checked( $settings['enabled'] ?? false ); ?> /> Activado</label></td>
                </tr>
                <tr>
                    <th>Frecuencia</th>
                    <td>
                        <select name="sp_newsletter_settings[frequency]">
                            <option value="daily" <?php selected( $settings['frequency'] ?? '', 'daily' ); ?>>Diario</option>
                            <option value="weekly" <?php selected( $settings['frequency'] ?? '', 'weekly' ); ?>>Semanal (Lunes)</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th>Hora de envío</th>
                    <td><input type="time" name="sp_newsletter_settings[send_time]" value="<?php echo esc_attr( $settings['send_time'] ?? '08:00' ); ?>" /></td>
                </tr>
                <tr>
                    <th>Resend API Key</th>
                    <td><input type="password" name="sp_newsletter_settings[resend_api_key]" value="<?php echo esc_attr( $settings['resend_api_key'] ?? '' ); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th>Resend Audience ID</th>
                    <td><input type="text" name="sp_newsletter_settings[resend_audience_id]" value="<?php echo esc_attr( $settings['resend_audience_id'] ?? '' ); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th>From Email</th>
                    <td><input type="email" name="sp_newsletter_settings[from_email]" value="<?php echo esc_attr( $settings['from_email'] ?? '' ); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th>From Name</th>
                    <td><input type="text" name="sp_newsletter_settings[from_name]" value="<?php echo esc_attr( $settings['from_name'] ?? 'SignoPeso' ); ?>" class="regular-text" /></td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
        <hr>
        <h2>Test</h2>
        <form method="post">
            <?php wp_nonce_field( 'sp_newsletter_test', 'sp_test_nonce' ); ?>
            <p><button type="submit" name="sp_test_send" class="button">Enviar digest de prueba</button></p>
        </form>
        <?php
        if ( isset( $_POST['sp_test_send'] ) && wp_verify_nonce( $_POST['sp_test_nonce'] ?? '', 'sp_newsletter_test' ) ) {
            require_once SP_PLUGIN_DIR . 'includes/newsletter/digest-builder.php';
            require_once SP_PLUGIN_DIR . 'includes/newsletter/adapters/resend.php';
            $test_html = sp_build_digest_html();
            if ( ! $test_html ) {
                echo '<div class="notice notice-warning"><p>No hay posts nuevos para el digest.</p></div>';
            } else {
                $test_adapter = new SP_Resend_Adapter();
                $test_result  = $test_adapter->send_digest( $test_html, '[TEST] SignoPeso — ' . date_i18n( 'j \d\e F, Y' ) );
                echo $test_result
                    ? '<div class="notice notice-success"><p>Digest de prueba enviado.</p></div>'
                    : '<div class="notice notice-error"><p>Error al enviar.</p></div>';
            }
        }
        ?>
    </div>
    <?php
}
