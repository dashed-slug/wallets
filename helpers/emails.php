<?php

namespace DSWallets;


/**
 * Helpers for enqueueing outgoing emails.
 *
 * These are processed by the Email Queue cron task.
 *
 * @see \DSWallets\Email_Queue_Task
 * @since 6.0.0 Introduced.
 * @author Alexandros Georgiou <info@dashed-slug.net>
 */

defined( 'ABSPATH' ) || die( -1 );

/**
 * Enqueue an email for later sending via wp_mail() by the email cron task.
 *
 * @param string|array $to          Array or comma-separated list of email addresses to send message.
 * @param string       $subject     Email subject.
 * @param string       $message     Message contents.
 * @param string|array $headers     (Optional) Additional headers.
 * @param string|array $attachments (Optional) Files to attach.
 */
function wp_mail_enqueue( $to, string $subject, string $message, $headers = '', $attachments = [] ): void {
	$email_queue = json_decode(
		get_ds_option(
			'wallets_email_queue',
			'[]'
		)
	);

	if ( ! is_array( $email_queue ) ) {
		error_log(
			sprintf(
				'%s: Warning: Email queue was not array. Deleting.',
				__FUNCTION__
			)
		);
		$email_queue = [];
	}

	array_push(
		$email_queue,
		func_get_args()
	);

	update_ds_option(
		'wallets_email_queue',
		json_encode( $email_queue )
	);
}

/**
 * Enqueue an email for later sending via wp_mail() by the email cron task.
 *
 * @see \DSWallets\Email_Queue_Task
 * @since 6.0.0 Introduced.
 *
 * @param string       $subject     Email subject
 * @param string       $message     Message contents
 * @param string|array $headers     (Optional) Additional headers.
 * @param string|array $attachments (Optional) Files to attach.
 */
function wp_mail_enqueue_to_admins( $subject, $message, $headers = '', $attachments = [] ): void {
	wp_mail_enqueue( get_admin_emails(), $subject, $message, $headers, $attachments );
}


function get_admin_emails(): array {

	static $admin_emails = [];

	if ( ! $admin_emails ) {

		$roles__in = [];
		foreach( wp_roles()->roles as $role_slug => $role ) {
			if( ! empty( $role['capabilities']['manage_options'] ) ) {
				$roles__in[] = $role_slug;
			}
		}

		if ( $roles__in ) {
			$users = get_users(
				[
					'roles__in' => $roles__in,
					'fields'    => array( 'id' , 'display_name', 'user_email' ),
				]
			);

			foreach ( $users as $user ) {
				if ( ds_user_can( $user->id, 'manage_options' ) ) {
					$admin_emails[] = $user->user_email;
				}
			}
		}
	}

	return $admin_emails;

}

add_action(
	'wallets_email_notify',
	function( Transaction $tx ): void {

		$specialized_part = "{$tx->category}_{$tx->status}";
		if ( 'move' == $tx->category ) {
			if ( $tx->amount > 0 ) {
				$specialized_part .= '_recipient';
			} else {
				$specialized_part .= '_sender';
			}
		}

		try {
			$template_file_name = get_template_part( 'email', $specialized_part );
		} catch ( \Exception $e ) {
			wp_mail_enqueue_to_admins(
				__( 'Email template not found', 'wallets' ),
				<<<EMAIL
Could not find email template file email-{$specialized_part}.php

Cannot notify user about transaction:
	$tx
EMAIL
			);
			return;
		}

		global $wpdb;
		$wpdb->query( 'SET autocommit=0' );

		ob_start();

		try {

			include $template_file_name;

		} catch ( \Exception $e ) {

			ob_end_clean();

			wp_mail_enqueue_to_admins(
				sprintf(
					__( '%1$s thrown by %2$s', 'wallets' ),
					get_class( $e ),
					$template_file_name
				),
				<<<EMAIL
The email template file email-{$specialized_part}.php
threw exception with message:
{$e->getMessage()}

while notifying user about transaction:
$tx
EMAIL
			);

			return;

		} finally {
			// We don't want email templates to modify DB state.
			// Admin should hook into wallets_email_notify
			// with an appropriate priority, to modify the transaction.
			$wpdb->query( 'ROLLBACK' );
			$wpdb->query( 'SET autocommit=1' );

		}

		$email_body = ob_get_clean();
		$email_to = $tx->user->user_email;
		$email_subject = apply_filters( 'wallets_email_notify_subject', '', $tx );
		$email_headers = [
			'MIME-Version: 1.0',
			'Content-Type: text/html; charset=UTF-8',
		];

		$bcc_admins = get_ds_option( 'wallets_email_forwarding_enabled' ) ||
		(
			'failed' == $tx->status &&
			get_ds_option( 'wallets_email_error_forwarding_enabled' )
		);

		if ( $bcc_admins ) {
			$email_headers[] = 'Bcc: ' . implode( ', ', get_admin_emails() );
		}

		if ( $email_to ) {

			wp_mail_enqueue(
				$email_to,
				$email_subject,
				$email_body,
				$email_headers
			);

		} else {
			error_log(
				sprintf(
					"wallets_email_notify: Don't know where to send email for %s %s transaction %d",
					$tx->status,
					$tx->category,
					$tx->post_id
				)
			);
		}

	},
	10, // priority
	2  // argument count
);

add_filter(
	'wallets_email_notify_subject',
	function( string $subject, Transaction $tx ): string {

		switch ( $tx->category ) {
			case 'deposit':
				$category = __( 'deposit', 'wallets' );
				break;

			case 'withdrawal':
				$category = __( 'withdrawal', 'wallets' );
				break;

			case 'move':
				if ( $tx->amount > 0 ) {
					$category = __( 'credit', 'wallets' );
				} else {
					$category = __( 'debit', 'wallets' );
				}
				break;

			default:
				$category = $tx->category;
				break;
		}

		switch ( $tx->status ) {
			case 'pending':
				$status = __( 'pending', 'wallets' );
				break;

			case 'done':
				$status = __( 'executed', 'wallets' );
				break;

			case 'cancelled':
				$status = __( 'cancelled', 'wallets' );
				break;

			case 'failed':
				$status = __( 'failed', 'wallets' );
				break;

		}

		return sprintf(
			// Translators: %1$s is the site's name, %2$2 is the tx category (one of "deposit", "withdrawal", "move"), %3$s is the tx status (one of "pending", "done", "cancelled", "failed")
			__( '%1$s: Your %2$s transaction is %3$s', 'wallets' ),
			get_bloginfo( 'name' ),
			$category,
			$status
		);
	},
	10, // priority
	2  // argument count
);
