<?php

namespace ChocChop\Admin;

defined( 'ABSPATH' ) || exit;

use ChocChop\Core\QueueManager;
use ChocChop\Core\Security;
use ChocChop\Core\Pipeline;
use ChocChop\Core\VoiceManager;
use ChocChop\Core\URLScraper;
use ChocChop\Core\DocumentProcessor;
use ChocChop\Admin\OAuthCallbackHandler;

class QueueAjaxHandler {
    public function __construct() {
        // Queue operations
        add_action('wp_ajax_choc_chop_get_queue', [$this, 'get_queue']);
        add_action('wp_ajax_choc_chop_delete_queue_item', [$this, 'delete_queue_item']);
        add_action('wp_ajax_choc_chop_bulk_delete', [$this, 'bulk_delete']);

        // Pipeline operations
        add_action('wp_ajax_choc_chop_check_emails', [$this, 'check_emails']);
        add_action('wp_ajax_choc_chop_run_pipeline', [$this, 'run_pipeline']);

        // Multi-source ingestion
        add_action('wp_ajax_choc_chop_add_url', [$this, 'add_url']);
        add_action('wp_ajax_choc_chop_upload_document', [$this, 'upload_document']);

        // Voice profile
        add_action('wp_ajax_choc_chop_regenerate_voice', [$this, 'regenerate_voice']);

        // Gmail disconnect
        add_action('wp_ajax_choc_chop_disconnect_gmail', [$this, 'disconnect_gmail']);

        // Recipe management
        add_action('wp_ajax_sp_chop_save_recipe', [$this, 'save_recipe']);
        add_action('wp_ajax_sp_chop_delete_recipe', [$this, 'delete_recipe']);
    }

    /**
     * Get queue items (for filtering/pagination)
     */
    public function get_queue() {
        check_ajax_referer('choc_chop_queue_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('No autorizado.', 'sp-chop')]);
            return;
        }

        $pipeline_stage = isset($_POST['pipeline_stage']) ? sanitize_text_field(wp_unslash($_POST['pipeline_stage'])) : '';
        $page = isset($_POST['page']) ? max(1, intval($_POST['page'])) : 1;
        $per_page = isset($_POST['per_page']) ? max(1, intval($_POST['per_page'])) : 20;

        $items = QueueManager::get_queue_items([
            'pipeline_stage' => $pipeline_stage,
            'limit' => $per_page,
            'offset' => ($page - 1) * $per_page,
        ]);

        $stats = QueueManager::get_stats();

        wp_send_json_success([
            'items' => $items,
            'stats' => $stats,
        ]);
    }

    /**
     * Delete queue item
     */
    public function delete_queue_item() {
        check_ajax_referer('choc_chop_queue_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('No autorizado.', 'sp-chop')]);
            return;
        }

        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

        if (empty($id)) {
            wp_send_json_error(['message' => __('ID de ítem inválido.', 'sp-chop')]);
            return;
        }

        $deleted = QueueManager::delete_queue_item($id);

        if (!$deleted) {
            wp_send_json_error(['message' => __('No se pudo eliminar el ítem.', 'sp-chop')]);
            return;
        }

        wp_send_json_success(['message' => __('Ítem eliminado.', 'sp-chop')]);
    }

    /**
     * Bulk delete
     */
    public function bulk_delete() {
        check_ajax_referer('choc_chop_queue_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('No autorizado.', 'sp-chop')]);
            return;
        }

        $ids = isset($_POST['ids']) ? array_map('intval', $_POST['ids']) : [];

        if (empty($ids)) {
            wp_send_json_error(['message' => __('No se seleccionaron ítems.', 'sp-chop')]);
            return;
        }

        $deleted = QueueManager::delete_multiple($ids);

        /* translators: %1$d: number of items deleted */
        $message = sprintf(_n('%1$d ítem eliminado.', '%1$d ítems eliminados.', $deleted, 'sp-chop'), $deleted);

        wp_send_json_success(['message' => $message, 'deleted' => $deleted]);
    }

    /**
     * Check emails and run pipeline
     */
    public function check_emails() {
        // Accept nonce from either Queue page or Settings page
        $nonce = isset($_POST['nonce']) ? sanitize_key(wp_unslash($_POST['nonce'])) : '';
        if ( ! wp_verify_nonce( $nonce, 'choc_chop_queue_nonce' )
            && ! wp_verify_nonce( $nonce, 'choc_chop_settings_nonce' ) ) {
            wp_send_json_error( [ 'message' => __( 'Verificación de seguridad falló. Recarga la página.', 'sp-chop' ) ] );
            return;
        }

        if (!current_user_can('manage_options')) {
            Security::log_security_event('unauthorized_ajax_attempt', ['action' => 'check_emails']);
            wp_send_json_error(['message' => __('No autorizado.', 'sp-chop')]);
            return;
        }

        if (!Security::check_rate_limit('check_emails', 5, 60)) {
            Security::log_security_event('rate_limit_exceeded', ['action' => 'check_emails']);
            wp_send_json_error(['message' => __('Demasiadas solicitudes. Espera un momento antes de intentar de nuevo.', 'sp-chop')]);
            return;
        }

        // Check if this is a test-only request
        $test_only = isset($_POST['test_only']) && $_POST['test_only'] === 'true';

        if ($test_only) {
            // Test connection only
            $fetcher = new \ChocChop\Core\EmailFetcher();

            if (!$fetcher->is_available()) {
                wp_send_json_error(['message' => __('Gmail API no configurada. Autoriza la conexión en Settings.', 'sp-chop')]);
                return;
            }

            $test_result = $fetcher->test_connection();

            if ($test_result['success']) {
                wp_send_json_success(['message' => __('Conexión de email exitosa.', 'sp-chop')]);
            } else {
                wp_send_json_error(['message' => $test_result['error']]);
            }
            return;
        }

        // Run full pipeline
        $pipeline = new Pipeline();
        $result = $pipeline->run();

        if (!$result['success']) {
            $errors = $result['errors'] ?? [];
            $error_msg = !empty($errors) ? implode('; ', $errors) : __('Falló la ejecución del pipeline.', 'sp-chop');
            wp_send_json_error(['message' => $error_msg]);
            return;
        }

        $message = sprintf(
            /* translators: %1$d: number of emails processed, %2$d: number of emails discarded */
            __('%1$d emails procesados, %2$d descartados.', 'sp-chop'),
            $result['processed'] ?? 0,
            $result['discarded'] ?? 0
        );

        if (!empty($result['errors'])) {
            /* translators: %d: number of errors that occurred */
            $message .= ' ' . sprintf(__('%d errores.', 'sp-chop'), count($result['errors']));
        }

        wp_send_json_success(['message' => $message, 'result' => $result]);
    }

    /**
     * Run pipeline for a single queue item
     */
    public function run_pipeline() {
        check_ajax_referer('choc_chop_queue_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('No autorizado.', 'sp-chop')]);
            return;
        }

        if (!Security::check_rate_limit('run_pipeline', 10, 60)) {
            wp_send_json_error(['message' => __('Demasiadas solicitudes. Espera un momento antes de intentar de nuevo.', 'sp-chop')]);
            return;
        }

        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

        if (empty($id)) {
            wp_send_json_error(['message' => __('ID de ítem inválido.', 'sp-chop')]);
            return;
        }

        // Update system card slug if provided.
        $system_card = isset( $_POST['system_card'] ) ? sanitize_key( wp_unslash( $_POST['system_card'] ) ) : '';
        if ( ! empty( $system_card ) ) {
            QueueManager::update_queue_item( $id, array( 'system_card_slug' => $system_card ) );
        }

        $pipeline = new Pipeline();
        $result = $pipeline->process_queue_item($id);

        if (!$result['success']) {
            wp_send_json_error(['message' => $result['error']]);
            return;
        }

        // Fetch the completed queue item for cost/post details.
        $item    = QueueManager::get_queue_item( $id );
        $post_id = $item ? ( $item['post_id'] ?? 0 ) : 0;
        $cost    = $item ? (float) ( $item['pass1_cost'] ?? 0 ) + (float) ( $item['pass2_cost'] ?? 0 ) : 0;

        if ( $post_id ) {
            $message = sprintf(
                'Borrador creado: <a href="%s" target="_blank">%s</a> · Costo: $%s',
                esc_url( get_edit_post_link( $post_id ) ),
                esc_html( get_the_title( $post_id ) ),
                number_format( $cost, 4 )
            );
        } else {
            $message = sprintf( __( 'Pipeline completado. Costo: $%s', 'sp-chop' ), number_format( $cost, 4 ) );
        }

        wp_send_json_success(['message' => $message, 'result' => $result]);
    }

    /**
     * Regenerate voice profile
     */
    public function regenerate_voice() {
        check_ajax_referer('choc_chop_settings_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('No autorizado.', 'sp-chop')]);
            return;
        }

        if (!Security::check_rate_limit('regenerate_voice', 3, 300)) {
            wp_send_json_error(['message' => __('Demasiadas solicitudes. Espera 5 minutos.', 'sp-chop')]);
            return;
        }

        try {
            $voice_manager = new VoiceManager();
            $voice_manager->refresh_voice_profile();

            $profile = get_option('choc_chop_style_guide', '');

            wp_send_json_success([
                'message' => __('Perfil de voz regenerado.', 'sp-chop'),
                'profile' => $profile
            ]);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * Disconnect Gmail OAuth
     */
    public function disconnect_gmail() {
        check_ajax_referer('choc_chop_settings_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('No autorizado.', 'sp-chop')]);
            return;
        }

        OAuthCallbackHandler::disconnect();

        wp_send_json_success(['message' => __('Gmail desconectado correctamente.', 'sp-chop')]);
    }

    /**
     * Add URL to queue
     */
    public function add_url() {
        check_ajax_referer( 'choc_chop_queue_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Unauthorized', 'sp-chop' ) ) );
            return;
        }

        if ( ! Security::check_rate_limit( 'add_url', 10, 60 ) ) {
            wp_send_json_error( array( 'message' => __( 'Rate limit exceeded. Please wait before trying again.', 'sp-chop' ) ) );
            return;
        }

        $url = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';

        if ( empty( $url ) || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
            wp_send_json_error( array( 'message' => __( 'Ingresa una URL válida.', 'sp-chop' ) ) );
            return;
        }

        $scraper = new URLScraper();
        $result  = $scraper->queue_url( $url );

        if ( ! $result['success'] ) {
            wp_send_json_error( array( 'message' => $result['error'] ) );
            return;
        }

        wp_send_json_success( array(
            'message'  => sprintf(
                /* translators: %s: title of the scraped article */
                __( 'URL en cola: %s', 'sp-chop' ),
                $result['title']
            ),
            'queue_id' => $result['queue_id'],
            'title'    => $result['title'],
        ) );
    }

    /**
     * Upload document to queue
     */
    public function upload_document() {
        check_ajax_referer( 'choc_chop_queue_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Unauthorized', 'sp-chop' ) ) );
            return;
        }

        if ( ! Security::check_rate_limit( 'upload_document', 10, 60 ) ) {
            wp_send_json_error( array( 'message' => __( 'Rate limit exceeded. Please wait before trying again.', 'sp-chop' ) ) );
            return;
        }

        if ( empty( $_FILES['document'] ) ) {
            wp_send_json_error( array( 'message' => __( 'No se subió ningún archivo.', 'sp-chop' ) ) );
            return;
        }

        $allowed_mimes = array(
            'pdf'  => 'application/pdf',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'doc'  => 'application/msword',
            'txt'  => 'text/plain',
        );

        $overrides = array(
            'test_form' => false,
            'mimes'     => $allowed_mimes,
        );

        $upload = wp_handle_upload( $_FILES['document'], $overrides );

        if ( isset( $upload['error'] ) ) {
            wp_send_json_error( array( 'message' => $upload['error'] ) );
            return;
        }

        $file_path = $upload['file'];
        $filename  = isset( $_FILES['document']['name'] ) ? sanitize_file_name( wp_unslash( $_FILES['document']['name'] ) ) : 'document';

        // Process document.
        $processor = new DocumentProcessor();
        $result    = $processor->process_document( $file_path );

        // Clean up uploaded file.
        wp_delete_file( $file_path );

        if ( ! $result['success'] ) {
            wp_send_json_error( array( 'message' => $result['error'] ?? __( 'No se pudo procesar el documento.', 'sp-chop' ) ) );
            return;
        }

        $content = $result['content'] ?? '';
        if ( empty( $content ) ) {
            wp_send_json_error( array( 'message' => __( 'No se extrajo texto del documento.', 'sp-chop' ) ) );
            return;
        }

        $title = DocumentProcessor::generate_title_from_filename( $filename );

        // Queue the extracted content.
        $queue_id = QueueManager::add_to_queue( array(
            'email_uid'      => 'upload_' . md5( $filename . time() ),
            'email_subject'  => $title,
            'content'        => $content,
            'email_from'     => '',
            'email_date'     => current_time( 'mysql' ),
            'content_source' => 'body',
            'source_type'    => 'upload',
            'triage_score'   => 80,
        ) );

        if ( ! $queue_id ) {
            wp_send_json_error( array( 'message' => __( 'No se pudo agregar a la cola.', 'sp-chop' ) ) );
            return;
        }

        wp_send_json_success( array(
            'message'  => sprintf(
                /* translators: %s: generated title from filename */
                __( 'Documento en cola: %s', 'sp-chop' ),
                $title
            ),
            'queue_id' => $queue_id,
            'title'    => $title,
        ) );
    }

    /**
     * Save site recipe
     */
    public function save_recipe() {
        check_ajax_referer( 'choc_chop_settings_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'No autorizado.', 'sp-chop' ) ) );
            return;
        }

        $domain = isset( $_POST['domain'] ) ? sanitize_text_field( wp_unslash( $_POST['domain'] ) ) : '';
        if ( empty( $domain ) ) {
            wp_send_json_error( array( 'message' => __( 'Dominio requerido.', 'sp-chop' ) ) );
            return;
        }

        // Parse strip_selectors and strip_text from newline-separated strings.
        $strip_selectors_raw = isset( $_POST['strip_selectors'] ) ? sanitize_textarea_field( wp_unslash( $_POST['strip_selectors'] ) ) : '';
        $strip_text_raw      = isset( $_POST['strip_text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['strip_text'] ) ) : '';

        $strip_selectors = array_values( array_filter( array_map( 'trim', explode( "\n", $strip_selectors_raw ) ) ) );
        $strip_text      = array_values( array_filter( array_map( 'trim', explode( "\n", $strip_text_raw ) ) ) );

        $recipe = array(
            'content_selector' => isset( $_POST['content_selector'] ) ? sanitize_text_field( wp_unslash( $_POST['content_selector'] ) ) : '',
            'strip_selectors'  => $strip_selectors,
            'strip_text'       => $strip_text,
            'manual_override'  => ! empty( $_POST['manual_override'] ),
            'learned_at'       => current_time( 'mysql' ),
            'success_count'    => 0,
        );

        // Preserve existing success_count if editing.
        $existing = \ChocChop\Core\RecipeManager::get_recipe( $domain );
        if ( $existing ) {
            $recipe['success_count'] = $existing['success_count'] ?? 0;
        }

        \ChocChop\Core\RecipeManager::save_recipe( $domain, $recipe );

        wp_send_json_success( array(
            'message' => sprintf( __( 'Receta guardada para %s.', 'sp-chop' ), $domain ),
        ) );
    }

    /**
     * Delete site recipe
     */
    public function delete_recipe() {
        check_ajax_referer( 'choc_chop_settings_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'No autorizado.', 'sp-chop' ) ) );
            return;
        }

        $domain = isset( $_POST['domain'] ) ? sanitize_text_field( wp_unslash( $_POST['domain'] ) ) : '';
        if ( empty( $domain ) ) {
            wp_send_json_error( array( 'message' => __( 'Dominio requerido.', 'sp-chop' ) ) );
            return;
        }

        \ChocChop\Core\RecipeManager::delete_recipe( $domain );

        wp_send_json_success( array(
            'message' => sprintf( __( 'Receta eliminada para %s.', 'sp-chop' ), $domain ),
        ) );
    }

    /**
     * Check if email already exists in queue
     *
     * @param string $email_uid
     * @return bool
     */
    private function is_duplicate($email_uid) {
        global $wpdb;

        $queue_table = $wpdb->prefix . 'choc_chop_queue';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name from $wpdb->prefix, not user input.
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $queue_table WHERE email_uid = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $email_uid
        ));

        return $count > 0;
    }
}
