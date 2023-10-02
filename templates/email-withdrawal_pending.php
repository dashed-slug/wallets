<?php namespace DSWallets;

/**
 * Email sent to the sender of a withdrawal that is as of yet not confirmed (pending state/pending post status).
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
?>
<p><?php esc_html_e( $tx->user->display_name ); ?>,</p>

<p>
<?php
	esc_html_e(
		sprintf(
			// translators: %1$s is replaced with the currency name. %2$s is replaced with the amount. %3$s is replaced with the address.
			__(
				'You have requested a %1$s withdrawal of %2$s to address %3$s. Your withdrawal is pending.',
				'wallets'
			),
			$tx->currency->name,
			$tx->get_amount_as_string( 'amount', false, true ),
			$tx->address ? $tx->address->address : __( 'undisclosed address', 'wallets' )
		)
	);
?>
</p>

<?php if ( $tx->nonce ): ?>
<p><?php esc_html_e( 'To confirm that you want the withdrawal to proceed, you must now click on the following confirmation link:', 'wallets' ); ?></p>

<p style="font-size:xx-large;"><a href="<?php esc_attr_e( $tx->get_confirmation_link() ); ?>" target="_blank"><?php esc_html_e( $tx->get_confirmation_link() ); ?></a></p>

<?php else: ?>

<p><?php esc_html_e( 'You will receive another notification when the withdrawal is executed.', 'wallets' ); ?></p>

<?php endif; ?>

<p><?php esc_html_e( 'Transaction details follow:', 'wallets' ); ?></p>

<table>
	<tbody>
		<?php if ( $tx->comment ): ?>
		<tr>
			<th style="align: right;"><?php esc_html_e( 'Comment:', 'wallets' ); ?></th>
			<td><?php esc_html_e( $tx->comment ); ?></td>
		</tr>
		<?php endif; ?>

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
			<th style="align: right;"><?php esc_html_e( 'Address:', 'wallets' ); ?></th>
			<td><?php esc_html_e( $tx->address ? $tx->address->address : __( 'undisclosed address', 'wallets' ) ); ?></td>
		</tr>

		<?php if ( $tx->address && $tx->address->extra ): ?>
		<tr>
			<th style="align: right;"><?php esc_html_e( $tx->currency->extra_field_name ?? __( 'Extra field', 'wallets' ) ); ?>:</th>
			<td><?php esc_html_e( $tx->address->extra ); ?></td>
		</tr>
		<?php endif; ?>

	</tbody>
</table>
