<?php

namespace DSWallets;

defined( 'ABSPATH' ) || die( -1 );

/**
 * Cron task abstract class.
 *
 * Concrete implementations of a task must extend this and implement run();
 *
 * Applications can use $this->log() for logging.
 *
 * @since 6.0.0 Introduced.
 * @author Alexandros Georgiou <info@dashed-slug.net>
 *
 */
abstract class Task {

	/** @var $name string The name of this task. */
	protected $name;

	/** @var $priority int The priority with which to attach to cron action. */
	protected $priority = 10;

	/** @var $verbose boolean Whether to write all log() output to debug log. */
	protected $verbose = false;

	/** @var $task_start_time int Timestamp at which this task is starting execution. */
	protected $task_start_time = 0;

	/** @var $timeout How many seconds this task is allowed to run. The task's run() method must respect this limit. */
	protected $timeout = DEFAULT_CRON_TASK_TIMEOUT;

	/** @var $start_time int Timestamp at which tasks batch starts execution. */
	private static $start_time = 0;

	/** @var $start_memory int Memory used at start of tasks batch execution, measured in bytes. */
	private static $start_memory = 0;

	/**
	 * Constructor hooks this task into the cron job action.
	 *
	 * Call this after your sub-class constructor.
	 * This way, any changes you make to $this->priority will have an effect.
	 * WARNING: If you forget to call `parent::__construct();`, your task will not run.
	 */
	public function __construct() {
		$this->verbose = 'on' == get_ds_option( 'wallets_cron_verbose', false );
		if ( ! $this->name ) {
			$this->name = get_called_class();
		}

		$timeout = absint( get_ds_option( 'wallets_cron_task_timeout', DEFAULT_CRON_TASK_TIMEOUT ) );
		if ( $timeout ) {
			$this->timeout = $timeout;
		}

		add_action(
			'wallets_cron_tasks',
			function() {
				$this->log( 'Task started.' );

				try {
					$this->run();

				} catch ( \Exception $e ) {
					wp_mail_enqueue_to_admins(
						sprintf(
							'%s: The %s cron task has encountered an error',
							get_bloginfo( 'wpurl' ),
							$this->name
						),
						sprintf(
							"The %s cron task has thrown an exception. This should not happen.\n" .
							"Please contact support at https://wordpress.org/support/plugin/wallets and show the following information:\n" .
							"\n" .
							"%s",
							$this->name,
							$e->__toString()
						)
					);
				}

				$this->log(
					sprintf(
						'Task finished. Elapsed: %d sec, Mem delta: %d bytes, Mem peak: %d bytes, PHP / WP mem limits: %d MB / %d MB',
						time() - self::$start_time,
						number_format( memory_get_usage() - self::$start_memory ),
						number_format( memory_get_peak_usage() ),
						ini_get( 'memory_limit' ),
						WP_MEMORY_LIMIT
					)
				);
			},
			$this->priority
		);
	}

	/**
	 * Do the task.
	 *
	 * Concrete implementations must do the work here in chuncks.
	 * The max execution time, usually about 15 or 30 secs must be eenough for all the tasks.
	 * So be careful!
	 *
	 * @throws \Exception If something goes wrong.
	 */
	protected abstract function run(): void;

	public final static function register(): void {

		if ( is_net_active() && ! is_main_site() ) {
			// On net active multisite we only run cron on the first blog.
			return;
		}

		add_filter(
			'cron_schedules',
			function( $schedules ) {
				$schedules['wallets_one_minute'] = array(
					'interval' => MINUTE_IN_SECONDS,
					'display'  => esc_html__( 'One minute', 'wallets' ),
				);

				$schedules['wallets_three_minutes'] = array(
					'interval' => 3 * MINUTE_IN_SECONDS,
					'display'  => esc_html__( 'Three minutes', 'wallets' ),
				);

				$schedules['wallets_five_minutes'] = array(
					'interval' => 5 * MINUTE_IN_SECONDS,
					'display'  => esc_html__( 'Five minutes', 'wallets' ),
				);

				$schedules['wallets_ten_minutes'] = array(
					'interval' => 10 * MINUTE_IN_SECONDS,
					'display'  => esc_html__( 'Ten minutes', 'wallets' ),
				);

				$schedules['wallets_twenty_minutes'] = array(
					'interval' => 20 * MINUTE_IN_SECONDS,
					'display'  => esc_html__( 'Twenty minutes', 'wallets' ),
				);

				$schedules['wallets_thirty_minutes'] = array(
					'interval' => 30 * MINUTE_IN_SECONDS,
					'display'  => esc_html__( 'Half an hour', 'wallets' ),
				);

				return $schedules;
			}
		);

		add_action(
			'init',
			function(): void {
				if ( false === wp_next_scheduled( 'wallets_cron_tasks' ) ) {
					$cron_interval = get_ds_option( 'wallets_cron_interval', 'wallets_never' );
					if ( 'wallets_never' != $cron_interval ) {
						wp_schedule_event( time(), $cron_interval, 'wallets_cron_tasks' );
					}
				}
			}
		);

		register_deactivation_hook(
			DSWALLETS_FILE,
			function(): void {
				if ( false !== wp_next_scheduled( 'wallets_cron_tasks' ) ) {
					wp_clear_scheduled_hook( 'wallets_cron_tasks' );
				}
			}
		);

		add_action(
			'admin_notices',
			function() {
				$cron_last_run = absint( get_ds_option( 'wallets_cron_last_run', 0 ) );

				if ( time() - $cron_last_run > HOUR_IN_SECONDS ):
				?>
				<div class="notice wallets-notice notice-warning">
				<?php
					printf(
						__(
							'Cron tasks have not run for at least one hour. ' .
							'If you see this message once only, you can ignore it. ' .
							'If the message persists, you must trigger cron manually. ' .
							'Consult the documentation under "<a href="%s">Troubleshooting</a>" to see how.',
							'wallets'
						),
						add_query_arg(
							[
								'page' => 'wallets_docs',
								'wallets-component' => 'wallets',
								'wallets-doc' => 'troubleshooting',
							],
							admin_url( 'admin.php')
						)
					);
				?>
				</div>
				<?php
				endif;
			}
		);

		add_action(
			'wallets_cron_tasks',
			function() {

				$verbose = 'on' == get_ds_option( 'wallets_cron_verbose', false );
				$cron_interval = self::get_cron_interval();
				if ( ! $cron_interval ) {
					$title = (string) __( 'Cron not set to run', 'wallets' );
					$msg   = (string) __(
						'Cron tasks are disabled by an admin. We shouldn\'t get here',
						'wallets'
					);
					if ( $verbose ) {
						error_log( sprintf( '%s: %s', $title, $msg ) );
					}

					wp_die( $msg, $title, 503 );
					exit;
				}

				$cron_last_run = absint( get_ds_option( 'wallets_cron_last_run', 0 ) );

				if ( ( $cron_last_run + $cron_interval * MINUTE_IN_SECONDS ) > time() ) {
					$title = (string) __( 'Cron locked', 'wallets' );
					$msg   = sprintf(
						(string) __(
							'Cron tasks are currently locked and will run again in at least %d seconds.',
							'wallets'
						),
						$cron_last_run + $cron_interval * MINUTE_IN_SECONDS - time()
					);

					if ( $verbose ) {
						error_log( sprintf( '%s: %s', $title, $msg ) );
					}

					wp_die( $msg, $title, 503 );
					exit;
				}

				self::$start_time = time();
				update_ds_option( 'wallets_cron_last_run', self::$start_time );
				self::$start_memory = memory_get_usage();

				if ( $verbose ) {
					error_log(
						sprintf(
							'[%d] %s: Starting cron tasks!',
							time(),
							'Bitcoin and Altcoin Wallets'
						)
					);
				}
			},
			0 // runs before any other task
		);

		add_action(
			'wallets_cron_tasks',
			function() {
				$elapsed_time = time() - self::$start_time;
				$used_memory  = memory_get_usage() - self::$start_memory;

				update_ds_option( 'wallets_cron_last_elapsed_time', $elapsed_time );
				update_ds_option( 'wallets_cron_last_peak_mem',     memory_get_peak_usage() );
				update_ds_option( 'wallets_cron_last_mem_delta',    $used_memory );

				if ( get_ds_option( 'wallets_cron_verbose', false ) ) {
					error_log(
						sprintf(
							"[%d] %s: All cron tasks finished after %01.2f seconds. %s bytes of memory were used.\n",
							time(),
							'Bitcoin and Altcoin Wallets',
							$elapsed_time,
							number_format( $used_memory )
						)
					);
				}
			},
			PHP_INT_MAX // runs after all other tasks
		);
	}

	private static function get_cron_interval(): int {
		$interval = get_ds_option( 'wallets_cron_interval', DEFAULT_CRON_INTERVAL );

		switch ( $interval ) {
			case 'wallets_one_minute':
				return 1;

			case 'wallets_three_minutes':
				return 3;

			case 'wallets_five_minutes':
				return 5;

			case 'wallets_ten_minutes':
				return 10;

			case 'wallets_twenty_minutes':
				return 20;

			case 'wallets_thirty_minutes':
				return 30;

			default:
				return 0;
		}

	}

	/**
	 * Cron task logging.
	 *
	 * Migration task is always logged.
	 *
	 * @param string $message The string to log.
	 * @param bool $force Whether to force log the string, even if no verbose logging is checked.
	 */

	public final function log( string $message, bool $force = false ): void {

		if ( $this->verbose || $force ) {
			if ( is_multisite() && ! is_net_active() ) {
				error_log(
					sprintf(
						'(blog %d) [%d] %s: %s: %s',
						get_current_blog_id(),
						time(),
						'Bitcoin and Altcoin Wallets',
						$this->name,
						$message
					)
				);
			} else {
				error_log(
					sprintf(
						'[%d] %s: %s: %s',
						time(),
						'Bitcoin and Altcoin Wallets',
						$this->name,
						$message
					)
				);
			}
		}
	}
}

Task::register();
