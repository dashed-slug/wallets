<?php namespace DSWallets;

/**
 * Email sent to the user who has performed a fiat deposit via bank transfer.
 *
 * The deposit is now marked as confirmed (done state/publish post status).
 *
 * @var DSWallets\Transaction $tx The transaction that this email message is about.
 * @phan-file-suppress PhanUndeclaredVariable
 *
 * @author Alexandros Georgiou <info@dashed-slug.net>
 * @since 6.0.0 Introduced.
 */
defined( 'ABSPATH' ) || die( -1 );

/* !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
 * !!!                                         WARNING                                           !!!
 * !!!                                                                                           !!!
 * !!! DO NOT EDIT THESE TEMPLATE FILES IN THE wp-content/plugins/wallets/templates DIRECTORY    !!!
 * !!!                                                                                           !!!
 * !!! Any changes you make here will be overwritten the next time the plugin is updated.        !!!
 * !!!                                                                                           !!!
 * !!! If you want to modify a template, copy it under a theme or child theme.                   !!!
 * !!!                                                                                           !!!
 * !!! To learn how to do this, see the plugin's documentation at:                               !!!
 * !!! "Frontend & Shortcodes" -> "Modifying the UI appearance" -> "Editing the template files". !!!
 * !!!                                                                                           !!!
 * !!! Try not to break the JavaScript code or knockout.js bindings.                             !!!
 * !!! I don't provide support for modified templates.                                           !!!
 * !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
 */

$payment_details = json_decode( $tx->address->label );
if ( $payment_details ) {
	if ( isset( $payment_details->iban ) && isset( $payment_details->swiftBic ) ) {
		$addressing_method = 'iban';
	} elseif ( isset( $payment_details->swiftBic) && isset( $payment_details->accountNumber ) ) {
		$addressing_method = 'swacc';
	} elseif ( isset( $payment_details->routingNumber ) && isset( $payment_details->accountNumber ) ) {
		$addressing_method = 'routing';
	} elseif ( isset( $payment_details->ifsc ) && isset( $payment_details->indianAccNum ) ) {
		$addressing_method = 'ifsc';
	}
}

?>
<p><?php esc_html_e( $tx->user->display_name ); ?>,</p>

<p>
<?php
	esc_html_e(
		sprintf(
			// translators: %1$s is replaced with the currency name. %2$s is replaced with the amount.
			__(
				'You have performed a %1$s deposit of %2$s from your bank account. The deposit has now been credited to your account on this site.',
				'wallets'
			),
			$tx->currency->name,
			$tx->get_amount_as_string( 'amount', false, true )
		)
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
			<th style="align: right;"><?php esc_html_e( 'Fees paid:', 'wallets' ); ?></th>
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

		<?php elseif ( 'swacc' == $addressing_method ): ?>

		<tr>
			<th style="align: right;"><?php esc_html_e( 'SWIFT/BIC:', 'wallets' ); ?></th>
			<td><code><?php esc_html_e( $payment_details->swiftBic ); ?></code></td>
		</tr>

		<tr>
			<th style="align: right;"><?php esc_html_e( 'Account number:', 'wallets' ); ?></th>
			<td><code><?php esc_html_e( $payment_details->accountNumber ); ?></code></td>
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
