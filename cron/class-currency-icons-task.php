<?php

/**
 * Retrieve currency icons from CoinGecko ID.
 *
 * Currencies with a CoinGecko ID but without a featured image,
 * are loaded in batches.
 *
 * If the CoinGecko ID is not known,
 * the algorithm has to lookup the currency by its ticker symbol.
 * If only one currency exists for the ticker, then OK, we use that.
 * Otherwise the algorithm cannot guess which currency is meant by the ticker symbol.
 *
 * The algorithm will download icons into `wp-content/uploads/wallets`,
 * and add them into the media library. It will then set the featured image
 * for a currency to be that media library entry.
 *
 *
 * @author Alexandros Georgiou <info@dashed-slug.net>
 * @since 6.0.0 Introduced.
 */

namespace DSWallets;


defined( 'ABSPATH' ) || die( -1 );

class Currency_Icons_Task extends Task {

	private $max_batch_size = 2;

	public function __construct() {
		$this->priority = 2000;

		/**
		 * Maximum batch size for Currency Icons task.
		 *
		 * Controls how fast currency icons are being retrieved and saved for newly created currencies.
		 *
		 * By default, the Currency Icons task will process two currencies in one cron run.
		 *
		 * Retrieving currency from Coingecko and downloading a logo image, creating an attachment
		 * out of it, and setting it as a featured image at the currency post, all while
		 * checking if the file already exists from a previous run,
		 * if the attachment exists, etc., takes time!
		 *
		 * Given that usually PHP execution limit is 30 sec and we also have other tasks to run,
		 * two should be plenty. Don't increase it unless you know your server can handle it!
		 *
		 * @since 6.0.0 Introduced.
		 *
		 * @param int $max_batch_size Maximum amount of currencies to retrieve icons for in one run of this task.
		 *
		 */
		$this->max_batch_size = apply_filters(
			'wallets_currency_icons_batch_size',
			$this->max_batch_size
		);

		parent::__construct();
	}

	public function run(): void {

		$this->task_start_time = time();

		$currency_ids = get_ids_for_coingecko_currencies_without_icon();

		shuffle( $currency_ids ); // ensure no starvation even in case of hardcore errors

		$this->log(
			sprintf(
				'There are %d currencies remaining with an assigned CoinGecko ID but no featured image!',
				count( $currency_ids )
			)
		);

		foreach ( $currency_ids as $currency_id ) {

			try {

				$currency = Currency::load( $currency_id );

			} catch ( \Exception $e ) {

				$this->log(
					sprintf(
						'Currency %d cannot be loaded!',
						$currency_id
					)
				);

				continue;
			}

			if ( $currency->coingecko_id ) {

				$url = "https://api.coingecko.com/api/v3/coins/{$currency->coingecko_id}?localization=false&tickers=false&market_data=false&community_data=false&developer_data=false&sparkline=false";

				$this->log(
					sprintf(
						'Currency %d: Retrieving currency data from: %s',
						$currency_id,
						$url
					)
				);

				$response = ds_http_get( $url );

				if ( ! $response ) {

					$this->log(
						sprintf(
							'Currency %d: No valid reponse from: %s',
							$currency_id,
							$url
						)
					);

				} else {

					$coingecko_currency_data = json_decode( $response );

					if ( ! $coingecko_currency_data ) {

						$this->log(
							sprintf(
								'Currency %d: Invalid JSON reponse: %s',
								$currency_id,
								$response
							)
						);

					} else {

						if ( isset( $coingecko_currency_data->id ) && $coingecko_currency_data->id == $currency->coingecko_id ) {

							foreach ( [ 'large', 'small', 'thumb' ] as $image_size ) {
								if ( isset( $coingecko_currency_data->image->{$image_size} ) ) {
									$icon_remote_url = $coingecko_currency_data->image->{$image_size};
									break;
								}
							}

							$extension = pathinfo( parse_url( $icon_remote_url)['path'], PATHINFO_EXTENSION );
							$filename = "{$currency->coingecko_id}.$extension";

							$upload_dir = wp_upload_dir();

							if ( $upload_dir['error'] ) {

								$this->log(
									sprintf(
										'Currency %d: Error while retrieving upload dir',
										$currency_id
									)
								);

							} else {

								$wallets_dir   = $upload_dir['basedir'] . '/wallets';
								$full_filename = $upload_dir['basedir'] . "/wallets/$filename";
								$full_url      = $upload_dir['baseurl'] . "/wallets/$filename";

								if ( ! file_exists( $wallets_dir ) ) {
									$this->log(
										sprintf(
											'Creating directory %s',
											$wallets_dir
										)
									);
									wp_mkdir_p( $wallets_dir );
								}

								if ( ! file_exists( $full_filename ) ) {
									$icon_data = ds_http_get( $icon_remote_url );

									if ( ! $icon_data ) {

										$this->log(
											sprintf(
												'Currency %d: Failed to retrieve currency image data from: %s',
												$currency_id,
												$icon_remote_url
											)
										);
									} else {

										if ( ! ( file_exists( $wallets_dir ) && is_dir( $wallets_dir ) && is_writable( $wallets_dir ) ) ) {

											$this->log(
												sprintf(
													'Currency %d: Cannot write to directory: %s',
													$currency_id,
													$wallets_dir
												)
											);

										} else {

											$this->log(
												sprintf(
													'Currency %d: Saving currency icon data to %s',
													$currency_id,
													$full_filename
												)
											);

											$result = file_put_contents(
												$full_filename,
												$icon_data
											);

											if ( ! $result ) {
												$this->log(
													sprintf(
														'Currency %d: Could not write icon data to %s',
														$currency_id,
														$full_filename
													)
												);

											}
										}
									}
								} // end if not image file already exists

								$wp_filetype = wp_check_filetype( $filename );

								$attachment = [
									'post_mime_type' => $wp_filetype['type'],
									'post_title'     => sprintf( __( 'Logo for %s currency', 'wallets' ), $currency->name ),
									'post_content'   => '',
									'post_status'    => 'inherit',
								];

								$this->log(
									sprintf(
										'Currency %d: Creating new attachment for image file: %s',
										$currency_id,
										$full_filename
									)
								);

								$attachment_id = wp_insert_attachment( $attachment, $full_filename );

								if ( is_wp_error( $attachment_id ) ) {

									$this->log(
										sprintf(
											'Currency %d: Attachment could not be created for image file: %s',
											$currency_id,
											$full_filename
										)
									);

								} else {

									require_once( ABSPATH . 'wp-admin/includes/image.php' );

									$attachment_metadata = wp_generate_attachment_metadata(
										$attachment_id,
										$full_filename
									);

									$this->log(
										sprintf(
											'Generating attachment metadata for attachment id %d and file %s',
											$attachment_id,
											$full_filename
										)
									);

									wp_update_attachment_metadata(
										$attachment_id,
										$attachment_metadata
									);

									$this->log(
										sprintf(
											'Currency %d: Setting new attachment %d as featured image',
											$currency_id,
											$attachment_id
										)
									);

									set_post_thumbnail( $currency_id, $attachment_id );
								}
							}
						}
					}
				}
			}

			if ( time() >= $this->task_start_time + $this->timeout ) {
				break;
			}
		}

	}
}

new Currency_Icons_Task; // @phan-suppress-current-line PhanNoopNew
