<?php namespace DSWallets;

/**
 * Email sent to the sender of a fiat withdrawal to a bank account.
 *
 * The withdrawal is as of yet not confirmed (pending state/pending post status).
 * Explains that the user will need to wait for an admin to process the withdrawal.
 *
 * @var DSWallets\Transaction $tx The transaction that this email message is about.
 * @phan-file-suppress PhanUndeclaredVariable
 *
 * @author dashed-slug <info@dashed-slug.net>
 * @since 6.0.0 Introduced.
 */
defined( 'ABSPATH' ) || die( -1 );

$payment_details = json_decode( $tx->address->label );
if ( $payment_details && isset( $payment_details->iban ) && isset( $payment_details->swiftBic ) ) {
	$addressing_method = 'iban';
} elseif ( $payment_details && isset( $payment_details->routingNumber ) && isset( $payment_details->accountNumber ) ) {
	$addressing_method = 'routing';
} elseif ( $payment_details && isset( $payment_details->ifsc ) && isset( $payment_details->indianAccNum ) ) {
	$addressing_method = 'ifsc';
}

?>
<p><?php esc_html_e( $tx->user->display_name ); ?>,</p>

<p>
<?php
	esc_html_e(
		sprintf(
			// translators: %1$s is replaced with the currency name. %2$s is replaced with the amount.
			__(
				'You have requested a %1$s withdrawal of %2$s to your bank account. Your withdrawal is now pending.',
				'wallets'
			),
			$tx->get_amount_as_string( 'amount', false, true ),
			$tx->currency->name
		)
	);
?>
</p>

<?php if ( $tx->nonce ): ?>
<p><?php esc_html_e( 'To confirm that you want the withdrawal to proceed, you must now click on the following confirmation link:', 'wallets' ); ?></p>

<p style="font-size:xx-large;"><a href="<?php esc_attr_e( $tx->get_confirmation_link() ); ?>" target="_blank"><?php esc_html_e( $tx->get_confirmation_link() ); ?></a></p>

<?php else: ?>

<p><?php esc_html_e( 'An admin will process your withdrawal soon.', 'wallets' ); ?></p>

<?php endif; ?>

<p>
<?php
	esc_html_e(
		'You will receive another notification when we send out the withdrawal. '.
		'After we send out the withdrawal, please allow a few days for the ' .
		'banking system to process the transaction.',
		'wallets'
	);
?>
</p>

<p><?php esc_html_e( 'Transaction details follow:', 'wallets' ); ?></p>

<table>
	<tbody>
		<tr>
			<th style="align: right;"><?php esc_html_e( 'Currency:', 'wallets' ); ?></th>
			<td><?php esc_html_e( $tx->currency->name ); ?> (<?php esc_html_e( $tx->currency->symbol ); ?>)</td>
		</tr>

		<tr>
			<th style="align: right;"><?php esc_html_e( 'Amount:', 'wallets' ); ?></th>
			<td><?php esc_html_e( $tx->get_amount_as_string( 'amount', true ) ); ?></td>
		</tr>

		<tr>
			<th style="align: right;"><?php esc_html_e( 'Fees to be paid:', 'wallets' ); ?></th>
			<td><?php esc_html_e( $tx->get_amount_as_string( 'fee', true ) ); ?></td>
		</tr>

		<tr>
			<th style="align: right;"><?php esc_html_e( 'Your full name and address:', 'wallets' ); ?></th>
			<td><pre><?php esc_html_e( $tx->address ? $tx->address->address : __( 'undisclosed address', 'wallets' ) ); ?></pre></td>
		</tr>

		<tr>
			<th style="align: right;"><?php esc_html_e( 'Your bank\'s name and address:', 'wallets' ); ?></th>
			<td><pre><?php esc_html_e( $tx->address->extra ); ?></pre></td>
		</tr>

		<?php if ( 'iban' == $addressing_method ): ?>

		<tr>
			<th style="align: right;"><?php esc_html_e( 'SWIFT/BIC:', 'wallets' ); ?></th>
			<td><code><?php esc_html_e( $payment_details->swiftBic ); ?></code></td>
		</tr>

		<tr>
			<th style="align: right;"><?php esc_html_e( 'IBAN:', 'wallets' ); ?></th>
			<td><code><?php esc_html_e( $payment_details->iban ); ?></code></td>
		</tr>

		<?php elseif ( 'routing' == $addressing_method ): ?>

		<tr>
			<th style="align: right;"><?php esc_html_e( 'Routing number:', 'wallets' ); ?></th>
			<td><code><?php esc_html_e( $payment_details->routingNumber ); ?></code></td>
		</tr>

		<tr>
			<th style="align: right;"><?php esc_html_e( 'Account number:', 'wallets' ); ?></th>
			<td><code><?php esc_html_e( $payment_details->accountNumber ); ?></code></td>
		</tr>

		<?php elseif ( 'ifsc' == $addressing_method ): ?>

		<tr>
			<th style="align: right;"><?php esc_html_e( 'IFSC:', 'wallets' ); ?></th>
			<td><code><?php esc_html_e( $payment_details->ifsc ); ?></code></td>
		</tr>

		<tr>
			<th style="align: right;"><?php esc_html_e( 'Account number:', 'wallets' ); ?></th>
			<td><code><?php esc_html_e( $payment_details->indianAccNum ); ?></code></td>
		</tr>

		<?php endif; ?>

		<?php if ( $tx->comment ): ?>

		<tr>
			<th style="align: right;"><?php esc_html_e( 'Comment/Notes:', 'wallets' ); ?></th>
			<td><?php esc_html_e( $tx->comment ); ?></td>
		</tr>

		<?php endif; ?>

	</tbody>
</table>
