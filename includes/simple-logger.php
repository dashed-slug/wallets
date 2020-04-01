<?php

/**
 * Logs events using the Simple Logger plugin.
 */

// don't load directly
defined( 'ABSPATH' ) || die( '-1' );

if ( ! class_exists( 'Dashed_Slug_Wallets_Simple_Logger' ) && class_exists( 'SimpleLogger' ) ) {

	class Dashed_Slug_Wallets_Simple_Logger extends SimpleLogger {

		public $slug = 'Plugin_Wallets_Audit_Log';

		public function getInfo() {

			$arr_info = array(
				'name' => 'Bitcoin and Altcoin Wallets',
				'description' => 'Logs events related to cryptocurrency wallets and their transactions',
				'capability' => 'read', // a user's transaction can be executed via cron triggered by another user
				'messages' => array(
					'withdraw'         => __( "Withdrew {amount} {symbol}.",           'wallets' ),
					'withdraw_failed'  => __( "Failed to withdraw {amount} {symbol}.", 'wallets' ),
					'move_send'        => __( "Sent {amount} {symbol}.",               'wallets' ),
					'move_send_failed' => __( "Failed to send {amount} {symbol}.",     'wallets' ),
					'move_receive'     => __( "Received {amount} {symbol}.",           'wallets' ),
					'deposit'          => __( "Deposited {amount} {symbol}.",          'wallets' ),
				),
			);
			return $arr_info;
		}

		public function loaded() {
			if ( Dashed_Slug_Wallets::get_option( 'wallets_email_withdraw_enabled' ) ) {
				add_action( 'wallets_withdraw', array( &$this, 'withdraw' ) );
			}

			if ( Dashed_Slug_Wallets::get_option( 'wallets_email_withdraw_failed_enabled' ) ) {
				add_action( 'wallets_withdraw_failed', array( &$this, 'withdraw_failed' ) );
			}

			if ( Dashed_Slug_Wallets::get_option( 'wallets_email_move_send_enabled' ) ) {
				add_action( 'wallets_move_send', array( &$this, 'move_send' ) );
			}

			if ( Dashed_Slug_Wallets::get_option( 'wallets_email_move_send_failed_enabled' ) ) {
				add_action( 'wallets_move_send_failed', array( &$this, 'move_send_failed' ) );
			}

			if ( Dashed_Slug_Wallets::get_option( 'wallets_email_move_receive_enabled' ) ) {
				add_action( 'wallets_move_receive', array( &$this, 'move_receive' ) );
			}

			if ( Dashed_Slug_Wallets::get_option( 'wallets_email_deposit_enabled' ) ) {
				add_action( 'wallets_deposit', array( &$this, 'deposit' ) );
			}

		}

		public function withdraw( $tx_data ) {
			$context = array(
				'user'       => $tx_data->user->user_login,
				'amount'     => abs( $tx_data->amount ),
				'symbol'     => $tx_data->symbol,
				'address'    => $tx_data->address,
			);

			$this->infoMessage( __FUNCTION__, $context );
		}

		public function withdraw_failed( $tx_data ) {
			$context = array(
				'user'       => $tx_data->user->user_login,
				'amount'     => abs( $tx_data->amount ),
				'symbol'     => $tx_data->symbol,
				'address'    => $tx_data->address,
			);

			$this->errorMessage( __FUNCTION__, $context );
		}

		public function move_send( $tx_data ) {
			$context = array(
				'from'       => $tx_data->user->user_login,
				'amount'     => abs( $tx_data->amount ),
				'symbol'     => $tx_data->symbol,
				'to'         => $tx_data->other_user->user_login,
			);

			$this->infoMessage( __FUNCTION__, $context );
		}

		public function move_send_failed( $tx_data ) {
			$context = array(
				'from'       => $tx_data->user->user_login,
				'amount'     => abs( $tx_data->amount ),
				'symbol'     => $tx_data->symbol,
				'to'         => $tx_data->other_user->user_login,
			);

			$this->errorMessage( __FUNCTION__, $context );
		}

		public function move_receive( $tx_data ) {
			$context = array(
				'from'       => $tx_data->user->user_login,
				'amount'     => $tx_data->amount,
				'symbol'     => $tx_data->symbol,
				'to'         => $tx_data->other_user->user_login,
			);

			$this->infoMessage( __FUNCTION__, $context );
		}

		public function deposit( $tx_data) {
			$context = array(
				'user'       => $tx_data->user->user_login,
				'amount'     => $tx_data->amount,
				'symbol'     => $tx_data->symbol,
				'txid'       => $tx_data->txid,
			);

			$this->infoMessage( __FUNCTION__, $context );
		}

		public function getLogRowSenderImageOutput( $row ) {
			$img_src = plugins_url( 'assets/sprites/wallets-32x32.png', DSWALLETS_PATH . '/wallets.php' );
			$sender_image_html = sprintf( '<img src="%s" />', $img_src );
			$sender_image_html = apply_filters( 'simple_history/row_sender_image_output', $sender_image_html, $row );

			return $sender_image_html;
		}

		public function getLogRowHeaderOutput( $row ) {
			$html = "<strong class=\"{$this->slug}Logitem__inlineDivided\">Bitcoin and Altcoin Wallets</strong>";


			// HTML for date
			// Date (should...) always exist
			// http://developers.whatwg.org/text-level-semantics.html#the-time-element
			$date_html = '';
			$str_when  = '';

			// $row->date is in GMT
			$date_datetime = new DateTime( $row->date, new DateTimeZone( 'GMT' ) );

			// Current datetime in GMT
			$time_current = strtotime( current_time( 'mysql', 1 ) );

			/**
			 * Filter how many seconds as most that can pass since an
			 * event occured to show "nn minutes ago" (human diff time-format) instead of exact date
			 *
			 * @since 2.0
			 *
			 * @param int $time_ago_max_time Seconds
			 */
			$time_ago_max_time = DAY_IN_SECONDS * 2;
			$time_ago_max_time = apply_filters( 'simple_history/header_time_ago_max_time', $time_ago_max_time );

			/**
			 * Filter how many seconds as most that can pass since an
			 * event occured to show "just now" instead of exact date
			 *
			 * @since 2.0
			 *
			 * @param int $time_ago_max_time Seconds
			 */
			$time_ago_just_now_max_time = 30;
			$time_ago_just_now_max_time = apply_filters( 'simple_history/header_just_now_max_time', $time_ago_just_now_max_time );

			if ( $time_current - $date_datetime->getTimestamp() <= $time_ago_just_now_max_time ) {

				// show "just now" if event is very recent
				$str_when = __( 'Just now', 'simple-history' );

			} elseif ( $time_current - $date_datetime->getTimestamp() > $time_ago_max_time ) {

				/* translators: Date format for log row header, see http://php.net/date */
				$datef = __( 'M j, Y \a\t G:i', 'simple-history' );
				$str_when = date_i18n( $datef, strtotime( get_date_from_gmt( $row->date ) ) );

			} else {

				// Show "nn minutes ago" when event is xx seconds ago or earlier
				$date_human_time_diff = human_time_diff( $date_datetime->getTimestamp(), $time_current );
				/* translators: 1: last modified date and time in human time diff-format */
				$str_when = sprintf( __( '%1$s ago', 'simple-history' ), $date_human_time_diff );

			}

			$item_permalink = admin_url( 'index.php?page=simple_history_page' );
			if ( ! empty( $row->id ) ) {
				$item_permalink .= "#item/{$row->id}";
			}

			$date_format = get_option( 'date_format' ) . ' - ' . get_option( 'time_format' );
			$str_datetime_title = sprintf(
				__( '%1$s local time %3$s (%2$s GMT time)', 'simple-history' ),
				get_date_from_gmt( $date_datetime->format( 'Y-m-d H:i:s' ), $date_format ), // 1 local time
				$date_datetime->format( $date_format ), // GMT time
				PHP_EOL // 3, new line
			);

			$date_html = "<span class='{$this->slug}Logitem__permalink {$this->slug}Logitem__when {$this->slug}Logitem__inlineDivided'>";
			$date_html .= "<a class='' href='{$item_permalink}'>";
			$date_html .= sprintf(
				'<time datetime="%3$s" title="%1$s" class="">%2$s</time>',
				esc_attr( $str_datetime_title ), // 1 datetime attribute
				esc_html( $str_when ), // 2 date text, visible in log
				$date_datetime->format( DateTime::RFC3339 ) // 3
			);
			$date_html .= '</a>';
			$date_html .= '</span>';

			return "$html $date_html";
		}

		public function getLogRowDetailsOutput( $row ) {
			$context = isset( $row->context ) ? $row->context : array();

			ob_start();
			?>
				<table class="SimpleHistoryLogitem__keyValueTable">
					<tbody>

						<?php if ( isset( $context['user'] ) ): ?>
						<tr>
							<td><?php esc_html_e( 'User:', 'wallets' ); ?></td>
							<td><?php echo Dashed_Slug_Wallets::user_link( $context['user'] ); ?></td>
						</tr>
						<?php endif; ?>

						<?php if ( isset( $context['address'] ) && $context['address'] ):
							$pattern = apply_filters( "wallets_explorer_uri_add_$context[symbol]", '%s' );
						?>
						<tr>
							<td><?php esc_html_e( 'Address:', 'wallets' ); ?></td>
							<td><a href="<?php echo esc_attr( sprintf( $pattern, $context['address'] ) ); ?>"><?php esc_html_e( $context['address'] ); ?></a></td>
						</tr>
						<?php endif; ?>

						<?php if ( isset( $context['txid'] ) && $context['txid'] ):
							$pattern = apply_filters( "wallets_explorer_uri_tx_$context[symbol]", '%s' );
						?>
						<tr>
							<td><?php esc_html_e( 'Transaction ID:', 'wallets' ); ?></td>
							<td><a href="<?php echo esc_attr( sprintf( $pattern, $context['txid'] ) ); ?>"><?php esc_html_e( $context['txid'] ); ?></a></td>
						</tr>
						<?php endif; ?>

						<?php if ( isset( $context['from'] ) ): ?>
						<tr>
							<td><?php esc_html_e( 'Sender:', 'wallets' ); ?></td>
							<td><?php echo Dashed_Slug_Wallets::user_link( $context['from'] ); ?></td>
						</tr>
						<?php endif; ?>

						<?php if ( isset( $context['to'] ) ): ?>
						<tr>
							<td><?php esc_html_e( 'Recipient:', 'wallets' ); ?></td>
							<td><?php echo Dashed_Slug_Wallets::user_link( $context['to'] ); ?></td>
						</tr>
						<?php endif; ?>

					</tbody>
				</table>
			<?php
			$html = ob_get_clean();

			return $html;
		}

	} // end class
} // end if not class exists
