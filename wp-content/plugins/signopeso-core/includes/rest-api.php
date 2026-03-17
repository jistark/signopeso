<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function sp_register_rest_routes() {
    register_rest_route( 'signopeso/v1', '/subscribe', array(
        'methods'             => 'POST',
        'callback'            => 'sp_handle_subscribe',
        'permission_callback' => '__return_true',
        'args'                => array(
            'email' => array(
                'required'          => true,
                'sanitize_callback' => 'sanitize_email',
                'validate_callback' => function( $email ) {
                    return is_email( $email );
                },
            ),
        ),
    ) );
}
add_action( 'rest_api_init', 'sp_register_rest_routes' );

function sp_handle_subscribe( WP_REST_Request $request ) {
    $email = $request->get_param( 'email' );

    // Basic rate limiting via transient.
    $ip_key = 'sp_sub_' . md5( $_SERVER['REMOTE_ADDR'] ?? '' );
    if ( get_transient( $ip_key ) ) {
        return new WP_REST_Response( array( 'message' => 'Espera un momento antes de intentar de nuevo.' ), 429 );
    }
    set_transient( $ip_key, true, 60 );

    require_once SP_PLUGIN_DIR . 'includes/newsletter/adapters/resend.php';
    $adapter = new SP_Resend_Adapter();
    $result  = $adapter->add_subscriber( $email );

    if ( $result ) {
        return new WP_REST_Response( array( 'message' => '¡Suscrito!' ), 200 );
    }

    return new WP_REST_Response( array( 'message' => 'Error al suscribir. Intenta más tarde.' ), 500 );
}
