<?php
/**
 * Pipeline Class
 *
 * Central orchestrator for the email-to-draft pipeline.
 * Coordinates the flow from email fetching through triage, extraction, and draft generation.
 *
 * @package ChocChop\Core
 * @since 1.0.0
 */

namespace ChocChop\Core;

use ChocChop\Core\EmailFetcher;
use ChocChop\Core\EmailTriage;
use ChocChop\Core\ContentExtractor;
use ChocChop\Core\DraftGenerator;
use ChocChop\Core\DocumentProcessor;
use ChocChop\Core\URLScraper;
use ChocChop\Core\Config;
use ChocChop\Core\CostTracker;
use ChocChop\Core\ErrorHandler;
use ChocChop\Core\SystemCardManager;
use ChocChop\Core\RecipeManager;

/**
 * Pipeline orchestrator class
 */
class Pipeline {

	/**
	 * Run the complete email-to-draft pipeline
	 *
	 * @param int $max_emails Maximum number of emails to process.
	 * @return array Processing results with success status, counts, and errors.
	 */
	public function run( int $max_emails = 3 ): array {
		// Budget check: abort if over monthly budget.
		if ( ! CostTracker::is_within_budget() ) {
			$spend  = CostTracker::get_monthly_spend();
			$budget = (float) Config::get( 'monthly_budget', 0 );
			ErrorHandler::log( 'Pipeline', sprintf( 'Presupuesto excedido ($%.2f / $%.2f), omitiendo ejecución', $spend, $budget ) );
			return [
				'success'   => false,
				'processed' => 0,
				'discarded' => 0,
				'errors'    => [ sprintf( 'Presupuesto mensual excedido ($%.2f / $%.2f)', $spend, $budget ) ],
			];
		}

		// Memory check: abort if over 200MB.
		$memory_limit = 200 * 1024 * 1024;
		if ( memory_get_usage() > $memory_limit ) {
			return [
				'success'   => false,
				'processed' => 0,
				'discarded' => 0,
				'errors'    => [ 'Uso de memoria supera 200MB, abortando ejecución del pipeline' ],
			];
		}

		$processed_count = 0;
		$discarded_count = 0;
		$errors          = [];

		// Fetch emails.
		$fetcher = new EmailFetcher();
		$emails  = $fetcher->fetch_emails( $max_emails );

		if ( empty( $emails ) ) {
			update_option( 'choc_chop_last_email_check', time() );
			return [
				'success'   => true,
				'processed' => 0,
				'discarded' => 0,
				'errors'    => [],
			];
		}

		$triage = new EmailTriage();

		foreach ( $emails as $email ) {
			try {
				// Re-check budget before each email (costs accumulate in batch).
				if ( ! CostTracker::is_within_budget() ) {
					$errors[] = 'Presupuesto mensual excedido durante el lote';
					break;
				}

				// Stage 1: Fetch and Triage.
				$queue_id = $this->stage_fetch_and_triage( $email );

				if ( null === $queue_id ) {
					$discarded_count++;
					continue;
				}

				// Stage 2: Extract content.
				$extract_success = $this->stage_extract( $queue_id );

				if ( ! $extract_success ) {
					$errors[] = sprintf( 'Extracción falló para ítem %d', $queue_id );
					continue;
				}

				// Check relevance score from extraction.
				$queue_item = QueueManager::get_queue_item( $queue_id );
				if ( $queue_item && isset( $queue_item['triage_score'] ) && $queue_item['triage_score'] < 30 ) {
					QueueManager::update_queue_item( $queue_id, [ 'pipeline_stage' => 'discarded' ] );
					$discarded_count++;
					continue;
				}

				// Stage 3: Generate draft.
				$generate_success = $this->stage_generate( $queue_id );

				if ( ! $generate_success ) {
					$errors[] = sprintf( 'Generación de borrador falló para ítem %d', $queue_id );
					continue;
				}

				$processed_count++;

			} catch ( \Exception $e ) {
				$errors[] = sprintf( 'Error en pipeline: %s', $e->getMessage() );
				ErrorHandler::log( 'Pipeline', $e->getMessage() );
			}
		}

		// Update last check timestamp.
		update_option( 'choc_chop_last_email_check', time() );

		return [
			'success'   => true,
			'processed' => $processed_count,
			'discarded' => $discarded_count,
			'errors'    => $errors,
		];
	}

	/**
	 * Process a single queue item through remaining pipeline stages
	 *
	 * @param int $queue_id Queue item ID.
	 * @return array Processing result.
	 */
	public function process_queue_item( int $queue_id ): array {
		// Budget check before processing.
		if ( ! CostTracker::is_within_budget() ) {
			return [
				'success' => false,
				'error'   => 'Presupuesto mensual excedido',
			];
		}

		$queue_item = QueueManager::get_queue_item( $queue_id );

		if ( ! $queue_item ) {
			return [
				'success' => false,
				'error'   => 'Ítem no encontrado en la cola',
			];
		}

		$stage = $queue_item['pipeline_stage'];

		// Failed items: determine restart stage and reset before reprocessing.
		if ( 'failed' === $stage ) {
			if ( ! empty( $queue_item['extracted_data'] ) && is_array( $queue_item['extracted_data'] ) ) {
				$stage = 'extracted';
			} else {
				$stage = 'queued';
			}
			QueueManager::update_queue_item( $queue_id, [
				'pipeline_stage' => $stage,
				'error_message'  => null,
			] );
		}

		try {
			if ( 'queued' === $stage ) {
				// Run extraction then generation.
				$extract_success = $this->stage_extract( $queue_id );
				if ( ! $extract_success ) {
					$item  = QueueManager::get_queue_item( $queue_id );
					$error = ! empty( $item['error_message'] ) ? $item['error_message'] : 'Extracción falló';
					return [
						'success' => false,
						'error'   => $error,
					];
				}

				$generate_success = $this->stage_generate( $queue_id );
				if ( ! $generate_success ) {
					$item  = QueueManager::get_queue_item( $queue_id );
					$error = ! empty( $item['error_message'] ) ? $item['error_message'] : 'Generación de borrador falló';
					return [
						'success' => false,
						'error'   => $error,
					];
				}

				return [
					'success' => true,
					'stage'   => 'complete',
				];

			} elseif ( 'extracted' === $stage ) {
				// Run generation only.
				$generate_success = $this->stage_generate( $queue_id );
				if ( ! $generate_success ) {
					$item  = QueueManager::get_queue_item( $queue_id );
					$error = ! empty( $item['error_message'] ) ? $item['error_message'] : 'Generación de borrador falló';
					return [
						'success' => false,
						'error'   => $error,
					];
				}

				return [
					'success' => true,
					'stage'   => 'complete',
				];
			}

			return [
				'success' => false,
				'error'   => sprintf( 'Etapa de pipeline inválida: %s', $stage ),
			];

		} catch ( \Exception $e ) {
			ErrorHandler::log( 'Pipeline', sprintf( 'Error procesando ítem %d: %s', $queue_id, $e->getMessage() ) );
			return [
				'success' => false,
				'error'   => $e->getMessage(),
			];
		}
	}

	/**
	 * Stage 1: Fetch and Triage email
	 *
	 * @param array $email Email data array.
	 * @return int|null Queue ID on success, null if not relevant.
	 */
	private function stage_fetch_and_triage( array $email ): ?int {
		$triage = new EmailTriage();

		// Evaluate email relevance.
		$evaluation = $triage->evaluate( $email );

		if ( ! $evaluation['relevant'] || $evaluation['score'] < 30 ) {
			return null;
		}

		// Clean body text.
		$cleaned_body = $triage->clean_body( $email['body'] );

		// Determine content source.
		$content_source = 'body';
		$content        = $cleaned_body;

		if ( ! empty( $email['attachments'] ) ) {
			foreach ( $email['attachments'] as $attachment ) {
				$mime_type = $attachment['mime_type'] ?? '';
				if ( in_array( $mime_type, [ 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' ], true ) ) {
					$content_source = 'attachment';
					break;
				}
			}
		}

		// Add to queue.
		$queue_data = [
			'email_uid'      => $email['uid'] ?? '',
			'email_from'     => $email['from'] ?? '',
			'email_subject'  => $email['subject'] ?? '',
			'email_date'     => $email['date'] ?? current_time( 'mysql' ),
			'content'        => $content,
			'content_source' => $content_source,
			'triage_score'   => $evaluation['score'],
			'pipeline_stage' => 'queued',
		];

		$queue_id = QueueManager::add_to_queue( $queue_data );

		return $queue_id;
	}

	/**
	 * Stage 2: Extract content and key points
	 *
	 * @param int $queue_id Queue item ID.
	 * @return bool Success status.
	 */
	private function stage_extract( int $queue_id ): bool {
		// Update stage to extracting.
		QueueManager::update_queue_item( $queue_id, [ 'pipeline_stage' => 'extracting' ] );

		// Get queue item.
		$queue_item = QueueManager::get_queue_item( $queue_id );
		if ( ! $queue_item ) {
			return false;
		}

		// Resolve content.
		$content = $this->resolve_content( $queue_item );

		// If content is empty and this is a URL source, re-scrape (fixes items queued before scraper improvements).
		if ( empty( $content ) && 'url' === ( $queue_item['source_type'] ?? '' ) && ! empty( $queue_item['source_url'] ) ) {
			ErrorHandler::log( 'Pipeline', sprintf( 'Re-scrapeando URL para ítem %d (contenido vacío)', $queue_id ) );
			$scraper      = new URLScraper();
			$scrape_result = $scraper->scrape_url( $queue_item['source_url'] );
			if ( $scrape_result['success'] ) {
				$content = $scrape_result['article']['content'] ?? '';
				if ( ! empty( $content ) ) {
					QueueManager::update_queue_item( $queue_id, [ 'content' => $content ] );
				}
			}
		}

		if ( empty( $content ) ) {
			$error_msg = 'Sin contenido disponible para extracción';
			if ( 'url' === ( $queue_item['source_type'] ?? '' ) ) {
				$error_msg = sprintf( 'Sin contenido extraído de %s — el sitio podría bloquear scrapers o requerir JavaScript.', $queue_item['source_url'] ?? 'URL desconocida' );
			}
			ErrorHandler::log( 'Pipeline', sprintf( 'Extracción fallida para ítem %d: %s', $queue_id, $error_msg ) );
			QueueManager::update_queue_item(
				$queue_id,
				[
					'pipeline_stage' => 'failed',
					'error_message'  => $error_msg,
				]
			);
			delete_transient( 'sp_chop_scrape_ctx_' . $queue_id );
			return false;
		}

		// Extract content.
		$extractor = new ContentExtractor();
		$result    = $extractor->extract( $content );

		if ( ! $result['success'] ) {
			QueueManager::update_queue_item(
				$queue_id,
				[
					'pipeline_stage' => 'failed',
					'error_message'  => $result['error'] ?? 'Extracción falló',
				]
			);
			delete_transient( 'sp_chop_scrape_ctx_' . $queue_id );
			return false;
		}

		// Update queue with extraction results.
		$update_data = [
			'extracted_data'   => wp_json_encode( $result['data'] ),
			'key_points'       => isset( $result['data']['key_points'] ) ? wp_json_encode( $result['data']['key_points'] ) : '',
			'pipeline_stage'   => 'extracted',
			'pass1_cost'       => $result['cost'] ?? 0,
			'pass1_tokens_in'  => $result['tokens_in'] ?? 0,
			'pass1_tokens_out' => $result['tokens_out'] ?? 0,
		];

		// Auto-populate system_card_slug from AI suggestion if not already set.
		$current_item = QueueManager::get_queue_item( $queue_id );
		if ( $current_item && empty( $current_item['system_card_slug'] ) && ! empty( $result['data']['suggested_format'] ) ) {
			$suggested = sanitize_key( $result['data']['suggested_format'] );
			$card      = SystemCardManager::get_card( $suggested );
			if ( $card && ! empty( $card['is_active'] ) ) {
				$update_data['system_card_slug'] = $suggested;
			}
		}

		QueueManager::update_queue_item( $queue_id, $update_data );

		return true;
	}

	/**
	 * Stage 3: Generate WordPress draft
	 *
	 * @param int $queue_id Queue item ID.
	 * @return bool Success status.
	 */
	private function stage_generate( int $queue_id ): bool {
		// Update stage to generating.
		QueueManager::update_queue_item( $queue_id, [ 'pipeline_stage' => 'generating' ] );

		// Get queue item.
		$queue_item = QueueManager::get_queue_item( $queue_id );
		if ( ! $queue_item ) {
			return false;
		}

		// Get extracted data (already decoded by QueueManager).
		$extracted_data = $queue_item['extracted_data'];
		if ( empty( $extracted_data ) || ! is_array( $extracted_data ) ) {
			QueueManager::update_queue_item(
				$queue_id,
				[
					'pipeline_stage' => 'failed',
					'error_message'  => 'Sin datos extraídos para generar borrador',
				]
			);
			delete_transient( 'sp_chop_scrape_ctx_' . $queue_id );
			return false;
		}

		// Generate draft.
		$generator       = new DraftGenerator();
		$system_card_slug = $queue_item['system_card_slug'] ?? '';
		$email_meta       = [
			'from'        => $queue_item['email_from'] ?? '',
			'subject'     => $queue_item['email_subject'] ?? '',
			'date'        => $queue_item['email_date'] ?? '',
			'source_url'  => $queue_item['source_url'] ?? '',
			'source_type' => $queue_item['source_type'] ?? 'email',
		];
		$result           = $generator->generate( $extracted_data, $email_meta, $system_card_slug );

		if ( ! $result['success'] ) {
			QueueManager::update_queue_item(
				$queue_id,
				[
					'pipeline_stage' => 'failed',
					'error_message'  => $result['error'] ?? 'Generación de borrador falló',
				]
			);
			delete_transient( 'sp_chop_scrape_ctx_' . $queue_id );
			return false;
		}

		// Update queue with generation results.
		$update_data = [
			'draft_content'    => $result['draft_content'] ?? '',
			'post_id'          => $result['post_id'] ?? 0,
			'pipeline_stage'   => 'complete',
			'pass2_cost'       => $result['cost'] ?? 0,
			'pass2_tokens_in'  => $result['tokens_in'] ?? 0,
			'pass2_tokens_out' => $result['tokens_out'] ?? 0,
			'processed_at'     => current_time( 'mysql' ),
		];

		QueueManager::update_queue_item( $queue_id, $update_data );

		// Auto-learn recipe for URL sources.
		if ( 'url' === ( $queue_item['source_type'] ?? '' ) ) {
			$ctx = get_transient( 'sp_chop_scrape_ctx_' . $queue_id );
			if ( is_array( $ctx ) && ! empty( $ctx['domain'] ) ) {
				RecipeManager::learn( $ctx['domain'], $ctx );
			}
			delete_transient( 'sp_chop_scrape_ctx_' . $queue_id );
		}

		return true;
	}

	/**
	 * Retry failed queue items with exponential backoff
	 *
	 * Called by Scheduler after the main pipeline run. Processes up to 2
	 * retriable items per cycle.
	 *
	 * @return array Results with retried count and errors.
	 */
	public function retry_failed_items(): array {
		$retried = 0;
		$errors  = [];

		$items = QueueManager::get_retriable_items( 2 );

		foreach ( $items as $item ) {
			if ( ! CostTracker::is_within_budget() ) {
				$errors[] = 'Presupuesto excedido, deteniendo reintentos';
				break;
			}

			$item_id = (int) $item['id'];
			QueueManager::increment_retry( $item_id );

			$result = $this->process_queue_item( $item_id );

			if ( $result['success'] ) {
				$retried++;
			} else {
				$errors[] = sprintf( 'Reintento falló para ítem %d: %s', $item_id, $result['error'] ?? 'Desconocido' );
			}
		}

		return [
			'retried' => $retried,
			'errors'  => $errors,
		];
	}

	/**
	 * Resolve content from queue item
	 *
	 * @param array $queue_item Queue item data.
	 * @return string Content string.
	 */
	private function resolve_content( array $queue_item ): string {
		$content_source = $queue_item['content_source'] ?? 'body';

		if ( 'body' === $content_source ) {
			$content = $queue_item['content'] ?? '';
		} elseif ( 'attachment' === $content_source ) {
			// Process attachment if available.
			// Note: In production, attachment data would need to be stored/referenced.
			// For now, fall back to stored content.
			$content = $queue_item['content'] ?? '';

			// TODO: Implement attachment processing when attachment storage is implemented.
			// $processor = new DocumentProcessor();
			// $content = $processor->extract_text( $attachment_path );
		} else {
			$content = $queue_item['content'] ?? '';
		}

		// Truncate to 50000 characters.
		if ( strlen( $content ) > 50000 ) {
			$content = substr( $content, 0, 50000 );
		}

		return $content;
	}

	/**
	 * Check if sufficient memory is available
	 *
	 * @param int $required Required memory in bytes (default 50MB).
	 * @return bool True if sufficient memory available.
	 */
	private function check_memory( int $required = 50000000 ): bool {
		$memory_limit = ini_get( 'memory_limit' );

		// Parse memory limit.
		if ( preg_match( '/^(\d+)(.)$/', $memory_limit, $matches ) ) {
			$limit = (int) $matches[1];
			$unit  = strtoupper( $matches[2] );

			if ( 'G' === $unit ) {
				$limit *= 1024 * 1024 * 1024;
			} elseif ( 'M' === $unit ) {
				$limit *= 1024 * 1024;
			} elseif ( 'K' === $unit ) {
				$limit *= 1024;
			}
		} else {
			$limit = (int) $memory_limit;
		}

		$current_usage = memory_get_usage();
		$available     = $limit - $current_usage;

		return $available > $required;
	}
}
