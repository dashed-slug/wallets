<?php namespace DSWallets;

/**
 * Email sent to the sender of an internal transaction (move) that has been cancelled (cancelled state/draft post status).
 *
 * @var DSWallets\Transaction $tx The transaction that this email message is about.
 * @phan-file-suppress PhanUndeclaredVariable
 *
 * @author Alexandros Georgiou <info@dashed-slug.net>
 * @since 6.0.0 Introduced.
 */
defined( 'ABSPATH' ) || die( -1 );


$other_tx = $tx->get_other_tx();
?>
<p><?php esc_html_e( $tx->user->display_name ); ?>,</p>

<?php if ( $other_tx ): ?>
	 <p>
	<?php
		esc_html_e(
			sprintf(
				// translators: %1$s is replaced with the user name. %2$s is replaced with the currency name. %3$s is replaced with the amount.
				__(
					'User %1$s has attempted to send you the %2$s amount of %3$s, but the transaction has been cancelled.',
					'wallets'
				),
				esc_html_e( $other_tx->user->display_name ),
				$tx->currency->name,
				$tx->get_amount_as_string( 'amount', false, true )
			)
		);
	?>
	</p>

<?php else: ?>

	 <p>
	<?php
		esc_html_e(
			sprintf(
				// translators: %1$s is replaced with the currency name. %2$s is replaced with the amount.
				__(
					'You were about to receive the %1$s amount of %2$s, but the transaction has been cancelled.',
					'wallets'
				),
				$tx->get_amount_as_string( 'amount', false, true ),
				$tx->currency->name
			)
		);
	?>
	</p>

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

		<?php if ( $other_tx ): ?>
		<tr>
			<th style="align: right;"><?php esc_html_e( 'Recipient:', 'wallets' ); ?></th>
			<td><?php esc_html_e( $other_tx->user->display_name ); ?></td>
		</tr>
		<?php endif; ?>

	</tbody>
</table>
