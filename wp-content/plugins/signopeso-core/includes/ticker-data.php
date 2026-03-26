<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/* ── Settings page ─────────────────────────────────────────────── */

function sp_ticker_settings_menu() {
    add_options_page(
        'Ticker Data',
        'Ticker ($P)',
        'manage_options',
        'sp-ticker',
        'sp_ticker_settings_page'
    );
}
add_action( 'admin_menu', 'sp_ticker_settings_menu' );

function sp_ticker_register_settings() {
    register_setting( 'sp_ticker', 'sp_ticker_settings', 'sp_ticker_sanitize' );
}
add_action( 'admin_init', 'sp_ticker_register_settings' );

function sp_ticker_sanitize( $input ) {
    return array(
        'banxico_token' => sanitize_text_field( $input['banxico_token'] ?? '' ),
        'owm_api_key'   => sanitize_text_field( $input['owm_api_key'] ?? '' ),
        'owm_city'      => sanitize_text_field( $input['owm_city'] ?? 'Mexico City' ),
    );
}

function sp_get_ticker_setting( $key ) {
    $opts = get_option( 'sp_ticker_settings', array() );
    return $opts[ $key ] ?? '';
}

function sp_ticker_settings_page() {
    $opts = get_option( 'sp_ticker_settings', array() );
    ?>
    <div class="wrap">
        <h1>Ticker Data — SignoPeso</h1>
        <form method="post" action="options.php">
            <?php settings_fields( 'sp_ticker' ); ?>
            <table class="form-table">
                <tr>
                    <th>Token API Banxico (SIE)</th>
                    <td><input type="text" name="sp_ticker_settings[banxico_token]" value="<?php echo esc_attr( $opts['banxico_token'] ?? '' ); ?>" class="regular-text" placeholder="Tu token de Banxico SIE"></td>
                </tr>
                <tr>
                    <th>API Key OpenWeatherMap</th>
                    <td><input type="text" name="sp_ticker_settings[owm_api_key]" value="<?php echo esc_attr( $opts['owm_api_key'] ?? '' ); ?>" class="regular-text" placeholder="Tu API key de OWM"></td>
                </tr>
                <tr>
                    <th>Ciudad (clima)</th>
                    <td><input type="text" name="sp_ticker_settings[owm_city]" value="<?php echo esc_attr( $opts['owm_city'] ?? 'Mexico City' ); ?>" class="regular-text"></td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

/* ── FX Rates (Banxico SIE API) ────────────────────────────────── */

function sp_get_fx_rates() {
    $cached = get_transient( 'sp_fx_rates' );
    if ( false !== $cached ) {
        return $cached;
    }

    $token = sp_get_ticker_setting( 'banxico_token' );
    if ( ! $token ) {
        return array( 'usd' => '—', 'eur' => '—' );
    }

    // SF43718 = USD/MXN tipo de cambio FIX
    // SF46410 = EUR/MXN tipo de cambio
    $url = 'https://www.banxico.org.mx/SieAPIRest/service/v1/series/SF43718,SF46410/datos/oportuno';

    $response = wp_remote_get( $url, array(
        'timeout' => 10,
        'headers' => array( 'Bmx-Token' => $token ),
    ) );

    if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
        // Cache the failure briefly to avoid hammering the API
        $fallback = array( 'usd' => '—', 'eur' => '—' );
        set_transient( 'sp_fx_rates', $fallback, 5 * MINUTE_IN_SECONDS );
        return $fallback;
    }

    $body   = json_decode( wp_remote_retrieve_body( $response ), true );
    $series = $body['bmx']['series'] ?? array();

    $rates = array( 'usd' => '—', 'eur' => '—' );

    foreach ( $series as $serie ) {
        $id    = $serie['idSerie'] ?? '';
        $datos = $serie['datos'] ?? array();
        $last  = end( $datos );
        $valor = $last['dato'] ?? '—';

        if ( 'SF43718' === $id ) {
            $rates['usd'] = $valor;
        } elseif ( 'SF46410' === $id ) {
            $rates['eur'] = $valor;
        }
    }

    set_transient( 'sp_fx_rates', $rates, HOUR_IN_SECONDS );

    return $rates;
}

/* ── Weather (OpenWeatherMap API) ──────────────────────────────── */

function sp_get_weather() {
    $cached = get_transient( 'sp_weather' );
    if ( false !== $cached ) {
        return $cached;
    }

    $api_key = sp_get_ticker_setting( 'owm_api_key' );
    $city    = sp_get_ticker_setting( 'owm_city' ) ?: 'Mexico City';

    if ( ! $api_key ) {
        return array( 'temp' => '—', 'condition' => '', 'emoji' => '🌡' );
    }

    $url = add_query_arg( array(
        'q'     => $city,
        'appid' => $api_key,
        'units' => 'metric',
        'lang'  => 'es',
    ), 'https://api.openweathermap.org/data/2.5/weather' );

    $response = wp_remote_get( $url, array( 'timeout' => 10 ) );

    if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
        $fallback = array( 'temp' => '—', 'condition' => '', 'emoji' => '🌡' );
        set_transient( 'sp_weather', $fallback, 5 * MINUTE_IN_SECONDS );
        return $fallback;
    }

    $data = json_decode( wp_remote_retrieve_body( $response ), true );

    $temp      = isset( $data['main']['temp'] ) ? round( $data['main']['temp'] ) : '—';
    $condition = $data['weather'][0]['description'] ?? '';
    $icon_code = $data['weather'][0]['icon'] ?? '01d';

    // Map OWM icon codes to emojis
    $emoji_map = array(
        '01' => '☀️', // clear
        '02' => '⛅', // few clouds
        '03' => '☁️', // scattered
        '04' => '☁️', // broken
        '09' => '🌧', // shower rain
        '10' => '🌦', // rain
        '11' => '⛈', // thunderstorm
        '13' => '❄️', // snow
        '50' => '🌫', // mist
    );

    $icon_prefix = substr( $icon_code, 0, 2 );
    $emoji       = $emoji_map[ $icon_prefix ] ?? '🌡';

    $weather = array(
        'temp'      => $temp,
        'condition' => mb_strtolower( $condition ),
        'emoji'     => $emoji,
    );

    set_transient( 'sp_weather', $weather, 30 * MINUTE_IN_SECONDS );

    return $weather;
}

/* ── Last Update (most recent post modification) ───────────────── */

function sp_get_last_update() {
    $posts = get_posts( array(
        'numberposts' => 1,
        'orderby'     => 'modified',
        'order'       => 'DESC',
        'post_type'   => 'post',
        'post_status' => 'publish',
    ) );

    if ( empty( $posts ) ) {
        return time();
    }

    return get_post_modified_time( 'U', false, $posts[0] );
}

/* ── Aggregate all ticker data ─────────────────────────────────── */

function sp_get_ticker_data() {
    return array(
        'fx'         => sp_get_fx_rates(),
        'weather'    => sp_get_weather(),
        'lastUpdate' => (int) sp_get_last_update(),
    );
}
