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

		$email_queue = json_decode( get_ds_option( 'wallets_email_queue', '[]' ) );

		$max_batch_size = absint(
			get_ds_option(
				'wallets_emails_max_batch_size',
				DEFAULT_CRON_EMAILS_MAX_BATCH_SIZE
			)
		);

		$i = 0;
		while ( $i++ < $max_batch_size && $email_queue && time() < $this->task_start_time + $this->timeout ) {
			$email_args = array_shift( $email_queue );

			$sending_to = null;

			if ( isset( $email_args[ 0 ] ) ) {
				if ( is_array( $email_args[ 0 ] ) ) {
					$sending_to = implode( ',', $email_args[ 0 ] );
				} elseif ( is_string( $email_args[ 0 ] ) ) {
					$sending_to = $email_args[ 0 ];
				}
			}

			if ( $sending_to ) {
				$this->log( 'Sending email to: ' . $sending_to  );

				$result = call_user_func_array( 'wp_mail', $email_args );
				if ( ! $result ) {
					$this->log( 'Could not send email to: ' . ( is_array( $email_args[ 0 ] ) ? implode( ',', $email_args[ 0 ] ) : $email_args[ 0 ] ) );
				}
			}

			// Here we save the queue after every send. This way, we don't lose state if the process dies.
			update_ds_option( 'wallets_email_queue', json_encode( $email_queue ) );
		}
	}
}
new Email_Queue_Task; // @phan-suppress-current-line PhanNoopNew