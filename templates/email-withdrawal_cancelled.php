<?php namespace DSWallets;

/**
 * Email sent to the sender of a withdrawal that has been cancelled (cancelled state/draft post status).
 *
 * @var DSWallets\Transaction $tx The transaction that this email message is about.
 * @phan-file-suppress PhanUndeclaredVariable
 *
 * @author dashed-slug <info@dashed-slug.net>
 * @since 6.0.0 Introduced.
 */
defined( 'ABSPATH' ) || die( -1 );

?>
<p><?php esc_html_e( $tx->user->display_name ); ?>,</p>

<p>
<?php
	esc_html_e(
		sprintf(
			// translators: %1$s is replaced with the currency name. %2$s is replaced with the amount. %3$s is replaced with the address.
			__(
				'Your %1$s withdrawal of %2$s to address %3$s has been cancelled.',
				'wallets'
			),
			$tx->currency->name,
			$tx->get_amount_as_string( 'amount', false, true ),
			$tx->address ? $tx->address->address : __( 'undisclosed address', 'wallets' )
		)
	);
?>
</p>

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

		<?php if ( $tx->fee ): ?>
		<tr>
			<th style="align: right;"><?php esc_html_e( 'Fees:', 'wallets' ); ?></th>
			<td><?php esc_html_e( $tx->get_amount_as_string( 'fee', true ) ); ?></td>
		</tr>
		<?php endif; ?>

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
