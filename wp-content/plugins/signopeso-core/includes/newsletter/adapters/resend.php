<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/subscriber-interface.php';
require_once __DIR__ . '/sender-interface.php';

class SP_Resend_Adapter implements SP_Newsletter_Subscriber, SP_Newsletter_Sender {

    private string $api_key;
    private string $audience_id;
    private string $from_email;
    private string $from_name;

    public function __construct() {
        $settings          = get_option( 'sp_newsletter_settings', array() );
        $this->api_key     = $settings['resend_api_key'] ?? '';
        $this->audience_id = $settings['resend_audience_id'] ?? '';
        $this->from_email  = $settings['from_email'] ?? '';
        $this->from_name   = $settings['from_name'] ?? 'SignoPeso';
    }

    public function add_subscriber( string $email ): bool {
        if ( ! $this->api_key || ! $this->audience_id ) {
            return false;
        }

        $response = wp_remote_post( "https://api.resend.com/audiences/{$this->audience_id}/contacts", array(
            'headers' => array(
                'Authorization' => "Bearer {$this->api_key}",
                'Content-Type'  => 'application/json',
            ),
            'body' => wp_json_encode( array( 'email' => $email ) ),
        ) );

        return ! is_wp_error( $response ) && in_array( wp_remote_retrieve_response_code( $response ), array( 200, 201 ), true );
    }

    public function send_digest( string $html, string $subject ): bool {
        if ( ! $this->api_key || ! $this->from_email ) {
            return false;
        }

        // Get all contacts from the audience.
        $contacts_response = wp_remote_get( "https://api.resend.com/audiences/{$this->audience_id}/contacts", array(
            'headers' => array( 'Authorization' => "Bearer {$this->api_key}" ),
        ) );

        if ( is_wp_error( $contacts_response ) ) {
            return false;
        }

        $contacts_body = json_decode( wp_remote_retrieve_body( $contacts_response ), true );
        $emails        = array();

        if ( ! empty( $contacts_body['data'] ) ) {
            foreach ( $contacts_body['data'] as $contact ) {
                if ( ! empty( $contact['email'] ) && empty( $contact['unsubscribed'] ) ) {
                    $emails[] = $contact['email'];
                }
            }
        }

        if ( empty( $emails ) ) {
            return false;
        }

        // Send via Resend batch or individual.
        $response = wp_remote_post( 'https://api.resend.com/emails', array(
            'headers' => array(
                'Authorization' => "Bearer {$this->api_key}",
                'Content-Type'  => 'application/json',
            ),
            'body' => wp_json_encode( array(
                'from'    => "{$this->from_name} <{$this->from_email}>",
                'to'      => $emails,
                'subject' => $subject,
                'html'    => $html,
            ) ),
        ) );

        return ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response );
    }
}
