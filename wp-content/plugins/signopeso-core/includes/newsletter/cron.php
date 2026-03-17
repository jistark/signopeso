<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Schedule the newsletter cron event.
 */
function sp_schedule_newsletter_cron() {
    $settings = get_option( 'sp_newsletter_settings', array() );

    if ( empty( $settings['enabled'] ) ) {
        wp_clear_scheduled_hook( 'sp_newsletter_send' );
        return;
    }

    if ( ! wp_next_scheduled( 'sp_newsletter_send' ) ) {
        $frequency = ( $settings['frequency'] ?? 'daily' ) === 'weekly' ? 'weekly' : 'daily';
        $send_time = $settings['send_time'] ?? '08:00';

        // Calculate next send timestamp.
        $today     = current_time( 'Y-m-d' );
        $timestamp = strtotime( "{$today} {$send_time}" );

        if ( $timestamp < current_time( 'timestamp' ) ) {
            $timestamp += DAY_IN_SECONDS;
        }

        wp_schedule_event( $timestamp, $frequency, 'sp_newsletter_send' );
    }
}
add_action( 'admin_init', 'sp_schedule_newsletter_cron' );

/**
 * Send the newsletter digest.
 */
function sp_send_newsletter_digest() {
    require_once SP_PLUGIN_DIR . 'includes/newsletter/digest-builder.php';
    require_once SP_PLUGIN_DIR . 'includes/newsletter/adapters/resend.php';

    $html = sp_build_digest_html();
    if ( ! $html ) {
        return;
    }

    $settings = get_option( 'sp_newsletter_settings', array() );
    $subject  = 'SignoPeso — ' . date_i18n( 'j \d\e F, Y' );

    $adapter = new SP_Resend_Adapter();
    $result  = $adapter->send_digest( $html, $subject );

    // Log result.
    update_option( 'sp_newsletter_last_sent', current_time( 'timestamp' ) );
    update_option( 'sp_newsletter_last_status', $result ? 'success' : 'failed' );
    update_option( 'sp_newsletter_last_post_count', sp_get_digest_post_count() );
}
add_action( 'sp_newsletter_send', 'sp_send_newsletter_digest' );
