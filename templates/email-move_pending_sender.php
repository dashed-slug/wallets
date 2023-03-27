<?php namespace DSWallets;

/**
 * Email sent to the sender of an internal transaction (move) that is pending (pending state/pending post status).
 * This email contains a confirmation link.
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
				// translators: %1$s is replaced with the currency name. %2$s is replaced with the amount. %3$s is replaced with the user name.
				__(
					'You are about to send the %1$s amount of %2$s to user %3$s.',
					'wallets'
				),
				$tx->currency->name,
				$tx->get_amount_as_string( 'amount', false, true ),
				$other_tx->user->display_name
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
					'The %1$s amount of %2$s is about to be deducted from your account.',
					'wallets'
				),
				$tx->currency->name,
				$tx->get_amount_as_string( 'amount', false, true )
			)
		);
	?>
	</p>

<?php endif; ?>

<?php if ( $tx->nonce ): ?>
<p><?php esc_html_e( 'You must first click on the following confirmation link:', 'wallets' ); ?></p>

<p style="font-size:xx-large;"><a href="<?php esc_attr_e( $tx->get_confirmation_link() ); ?>" target="_blank"><?php esc_html_e( $tx->get_confirmation_link() ); ?></a></p>

<p><?php esc_html_e( 'Once the transaction is confirmed, your wallet will be credited and the recipient\'s wallet will be debited.', 'wallets' ); ?></p>
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
			<th style="align: right;"><?php esc_html_e( 'Fee:', 'wallets' ); ?></th>
			<td><?php esc_html_e( $tx->get_amount_as_string( 'fee', true ) ); ?></td>
		</tr>

		<?php if ( $other_tx ): ?>
		<tr>
			<th style="align: right;"><?php esc_html_e( 'Recipient:', 'wallets' ); ?></th>
			<td><?php esc_html_e( $other_tx->user->display_name ); ?></td>
		</tr>
		<?php endif; ?>
	</tbody>
</table>
