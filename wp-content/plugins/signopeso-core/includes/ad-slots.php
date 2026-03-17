<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register ad slots settings page.
 */
function sp_register_ad_settings() {
    register_setting( 'sp_ads', 'sp_ad_slots', array(
        'type'              => 'array',
        'sanitize_callback' => 'sp_sanitize_ad_slots',
        'default'           => array(),
    ) );

    add_options_page(
        'SignoPeso Ads',
        'SP Ads',
        'manage_options',
        'sp-ads',
        'sp_render_ad_settings_page'
    );
}
add_action( 'admin_menu', 'sp_register_ad_settings' );
add_action( 'admin_init', function() {
    register_setting( 'sp_ads', 'sp_ad_slots' );
});

function sp_sanitize_ad_slots( $input ) {
    $sanitized = array();
    $positions = array( 'header-leaderboard', 'single-below-content', 'sidebar' );

    foreach ( $positions as $pos ) {
        $sanitized[ $pos ] = array(
            'enabled' => ! empty( $input[ $pos ]['enabled'] ),
            'code'    => $input[ $pos ]['code'] ?? '',
        );
    }

    return $sanitized;
}

function sp_render_ad_settings_page() {
    $slots = get_option( 'sp_ad_slots', array() );
    $positions = array(
        'header-leaderboard'   => 'Header Leaderboard (728x90)',
        'single-below-content' => 'Single Post — Below Content (468x60)',
        'sidebar'              => 'Sidebar (300x250)',
    );
    ?>
    <div class="wrap">
        <h1>SignoPeso — Ad Slots</h1>
        <form method="post" action="options.php">
            <?php settings_fields( 'sp_ads' ); ?>
            <?php foreach ( $positions as $slug => $label ) :
                $slot = $slots[ $slug ] ?? array( 'enabled' => false, 'code' => '' );
            ?>
                <h2><?php echo esc_html( $label ); ?></h2>
                <label>
                    <input type="checkbox" name="sp_ad_slots[<?php echo esc_attr( $slug ); ?>][enabled]" value="1" <?php checked( $slot['enabled'] ); ?> />
                    Activado
                </label>
                <br><br>
                <textarea name="sp_ad_slots[<?php echo esc_attr( $slug ); ?>][code]" rows="5" cols="80"><?php echo esc_textarea( $slot['code'] ); ?></textarea>
                <hr>
            <?php endforeach; ?>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

/**
 * Get ad code for a position.
 */
function sp_get_ad_code( $position ) {
    $slots = get_option( 'sp_ad_slots', array() );
    $slot  = $slots[ $position ] ?? null;

    if ( ! $slot || empty( $slot['enabled'] ) || empty( $slot['code'] ) ) {
        return '';
    }

    return $slot['code'];
}
