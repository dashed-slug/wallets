<?php

/**
 * Functions that queue outgoing emails.
 *
 * @author Alexandros Georgiou <info@dashed-slug.net>
 * @since 6.0.0
 */

namespace DSWallets;

defined( 'ABSPATH' ) || die( -1 );

class Email_Queue_Task extends Task {

	public function __construct() {
		$this->priority = 50;
		parent::__construct();
	}

	private $email_queue = [];

	public function run(): void {
		$this->task_start_time = time();

		$email_queue = json_decode( get_ds_option( 'wallets_email_queue', '[]' ), true );

		$max_batch_size = absint(
			get_ds_option(
				'wallets_emails_max_batch_size',
				DEFAULT_CRON_EMAILS_MAX_BATCH_SIZE
			)
		);

		// Maximum recipients allowed in a single SMTP DATA command
		$max_recipients_per_email = absint(
			get_ds_option(
				'wallets_emails_max_recipients_batch_size',
				DEFAULT_CRON_EMAILS_MAX_RECIPIENTS_BATCH_SIZE
			)
		);

		$i = 0;
		while ( $i++ < $max_batch_size && ! empty( $email_queue ) && time() < $this->task_start_time + $this->timeout ) {

			// Peek at the first item in the queue
			$email_args = array_shift( $email_queue );
			$recipients = $email_args[0] ?? '';

			// Standardize recipients to an array
			$recipients_array = is_array( $recipients ) ? $recipients : explode( ',', $recipients );
			$recipients_array = array_filter( array_map( 'trim', $recipients_array ) );

			if ( count( $recipients_array ) > $max_recipients_per_email ) {
				// Slice the first chunk to send now
				$current_batch = array_slice( $recipients_array, 0, $max_recipients_per_email );

				// Slice the remaining to put back in the queue
				$remaining_recipients = array_slice( $recipients_array, $max_recipients_per_email );

				// Prepare the "remaining" item and push it back to the START of the queue
				$remaining_args = $email_args;
				$remaining_args[0] = $remaining_recipients;
				array_unshift( $email_queue, $remaining_args );

				// Update local args for the current send
				$email_args[0] = $current_batch;
			}

			// Proceed with sending the current batch
			$sending_to = is_array( $email_args[0] ) ? implode( ',', $email_args[0] ) : $email_args[0];

			if ( $sending_to ) {
				$this->log( 'Sending email batch to: ' . $sending_to );
				$result = call_user_func_array( 'wp_mail', $email_args );

				if ( ! $result ) {
					$this->log( 'Could not send email to: ' . $sending_to );
				}
			}

			// Save state after every iteration
			update_ds_option( 'wallets_email_queue', json_encode( array_values( $email_queue ) ) );
		}
	}
}
new Email_Queue_Task; // @phan-suppress-current-line PhanNoopNew