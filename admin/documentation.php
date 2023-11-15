<?php

/**
 * Renders the markdown docs in the plugin for easier access.
 *
 * @since 6.0.0 Introduced.
 * @author Alexandros Georgiou <info@dashed-slug.net>
 */

namespace DSWallets;

// don't load directly
defined( 'ABSPATH' ) || die( -1 );

// Hook this plugin's docs
add_filter(
	'wallets_documentation',
	function( array $docs ): array {
		$docs['wallets'] = [
			[
				'slug' => 'intro',
				'title' => 'Introduction to the plugin',
				'file' => DSWALLETS_PATH . '/docs/intro.md',
			],
			[
				'slug' => 'features',
				'title' => 'Plugin features',
				'file' => DSWALLETS_PATH . '/docs/features.md',
			],
			[
				'slug' => 'install',
				'title' => 'Installation instructions',
				'file' => DSWALLETS_PATH . '/docs/install.md',
			],
			[
				'slug' => 'post-types',
				'title' => 'The post types',
				'file' => DSWALLETS_PATH . '/docs/post-types.md',
			],
			[
				'slug' => 'migration',
				'title' => 'Migrating from 5.x',
				'file' => DSWALLETS_PATH . '/docs/migration.md',
			],
			[
				'slug' => 'frontend',
				'title' => 'Frontend and Shortcodes',
				'file' => DSWALLETS_PATH . '/docs/frontend.md',
			],
			[
				'slug' => 'tools',
				'title' => 'Tools',
				'file' => DSWALLETS_PATH . '/docs/tools.md',
			],
			[
				'slug' => 'dashboard',
				'title' => 'Dashboard',
				'file' => DSWALLETS_PATH . '/docs/dashboard.md',
			],
			[
				'slug' => 'multisite',
				'title' => 'Multisite',
				'file' => DSWALLETS_PATH . '/docs/multisite.md',
			],
			[
				'slug' => 'settings',
				'title' => 'Settings reference',
				'file' => DSWALLETS_PATH . '/docs/settings.md',
			],
			[
				'slug' => 'developer',
				'title' => 'Developer reference',
				'file' => DSWALLETS_PATH . '/docs/developer.md',
			],
			[
				'slug' => 'l10n',
				'title' => 'Localization',
				'file' => DSWALLETS_PATH . '/docs/l10n.md',
			],
			[
				'slug' => 'faq',
				'title' => 'FAQ',
				'file' => DSWALLETS_PATH . '/docs/faq.md',
			],
			[
				'slug' => 'glossary',
				'title' => 'Glossary',
				'file' => DSWALLETS_PATH . '/docs/glossary.md',
			],
			[
				'slug' => 'troubleshooting',
				'title' => 'Troubleshooting common issues',
				'file' => DSWALLETS_PATH . '/docs/troubleshooting.md',
			],
			[
				'slug' => 'contact',
				'title' => 'Contact Support',
				'file' => DSWALLETS_PATH . '/docs/contact.md',
			],
		];

		return $docs;
	}
);

add_action(
	'admin_enqueue_scripts',
	function() {
		if ( isset( $_GET['wallets-component'] ) && isset( $_GET['wallets-doc'] ) ) {
			wp_enqueue_script( 'wallets-admin-docs' );
		}
	}
);


add_action(
	'admin_menu',
	function() {
		add_menu_page(
			__( 'Documentation for the administrator of Bitcoin and Altcoin Wallets and its extensions', 'wallets' ),
			__( 'Wallets Admin Docs', 'wallets' ),
			'manage_wallets',
			'wallets_docs',
			function() {
				wp_enqueue_style( 'wallets-admin-styles' );

				if ( ! class_exists( 'Parsedown' ) ) {
					require_once DSWALLETS_PATH . '/third-party/Parsedown.php';
				}

				$parsedown_extra = false;
				if ( ! class_exists( 'ParsedownExtra' ) ) {
					try {
						require_once DSWALLETS_PATH . '/third-party/ParsedownExtra.php';
						$parsedown_extra = true;
					} catch ( \Exception $e ) {
						$parsedown_extra = false;
					}
				}

				/**
				 * Wallets documentation.
				 *
				 * Hook documentation for wallets or its extensions.
				 *
				 * The format is as follows:
				 *
				 * $doc = [
				 *		'component1' => [
				 *			[
				 *				'slug' => 'foo',
				 *				'title' => 'Chapter on foo topic for component1',
				 *				'file' => __DIR__ . '/foo.md',
				 *			],
				 *			[
				 *				'slug' => 'bar',
				 *				'title' => 'Chapter on bar topic for component1',
				 *				'file' => __DIR__ . '/bar.md',
				 *			],
				 *
				 *			(...)
				 *
				 *
				 *		],
				 *		'component2' => [
				 *			[
				 *				'slug' => 'foo',
				 *				'title' => 'Chapter on foo topic for component2',
				 *				'file' => __DIR__ . '/foo2.md',
				 *			],
				 *
				 *		(...)
				 *
				 *		],
				 * ];
				 *
				 * The above hooks two chapter files for foo and bar, in the documentation
				 * for 'component1'. It also hooks a foo chapter for `component2`.
				 *
				 * These chapters will be processed into HTML and displayed in the online documentation system.
				 *
				 * @since 6.0.0 Introduced.
				 *
				 * @param array $docs The documentations to hook. See above.
				 */
				$docs = apply_filters(
					'wallets_documentation',
					[]
				);


				if ( $docs ):

					ksort( $docs );

					$this_doc = false;
					if ( isset( $_GET['wallets-component'] ) && isset( $_GET['wallets-doc'] ) ) {
						foreach ( $docs[ $_GET['wallets-component'] ] as $doc ) {
							if ( $doc['slug'] == $_GET['wallets-doc'] ) {

								$this_doc = $doc;
								break;
							}
						}
					}

					if ( !$this_doc ): ?>
						<h1><?php esc_html_e( 'Documentation for Bitcoin and Altcoin Wallets and its extensions', 'wallets' ); ?></h1>
						<p><?php esc_html_e( '(Click on a chapter to display documentation)', 'wallets' );; ?></p>
						<main>
					<?php else: ?>
						<aside style="margin:1em;float:right;">
					<?php endif;


						foreach ( $docs as $component_slug => $component_docs ): ?>
						<nav class="card wallets-book">

							<h2 class="wallets <?php esc_attr_e( $component_slug ); ?>">
								<?php if ( isset( $_GET['wallets-component'] ) && $component_slug == $_GET['wallets-component'] ): ?>&#x1F4D6;<?php else: ?>&#x1F4D5;<?php endif; ?>
								<code><?php esc_html_e( $component_slug ); ?></code>
							</h2>

							<ol>
								<?php
								foreach ( $component_docs as $doc ):

									$doc_url = add_query_arg(
										[
											'wallets-component' => $component_slug,
											'wallets-doc'       => $doc['slug'],
										],
										admin_url( 'admin.php?page=wallets_docs' )
									);
									?>
									<li>
										<?php if ( $this_doc == $doc ): ?><strong><?php endif; ?>
										<a
											href="<?php esc_attr_e( $doc_url ); ?>">
											<?php esc_html_e( $doc['title'] ); ?>
										</a>
										<?php if ( $this_doc == $doc ): ?></strong><?php endif; ?>
									<?php
								endforeach;
								?>
							</ol>
						</nav>
						<?php
						endforeach;

					if ( ! $this_doc ): ?>
						</main>

					<?php else: ?>
						</aside>

						<?php
						if (
							isset( $this_doc['file'] ) &&
							file_exists( $this_doc['file'] ) &&
							is_file( $this_doc['file'] ) &&
							is_readable( $this_doc['file'] )
						):

							// load file and concatenate with glossary footer
							$markdown =
								file_get_contents( $this_doc['file'] ) . "\n" .
								file_get_contents( DSWALLETS_PATH . '/docs/glossary-footers.md' );

							// append google tracking vars to ds links
							$markdown = preg_replace(
								'/^\[([^\]]+)\]: https:\/\/(www\.)?dashed-slug\.net([^\s]+)/m',
								"[\\1]: https://\\2dashed-slug.net\\3?utm_source=wallets&utm_medium=docs&utm_campaign=$this_doc[slug]",
								$markdown
							);

							// render markdown
							if ( $parsedown_extra ) {
								try {
									$pd = new \ParsedownExtra;
								} catch ( \Exception $e ) {
									$pd = new \Parsedown;
								}
							} else {
								$pd = new \Parsedown;

								?>
								<div class="notice notice-warning">
									<p><?php echo __( 'The plugin uses <code>Parsedown</code> and <code>ParsedownExtra</code> to display the documentation. Another plugin that you are using is loading an older version of Parsedown, but not ParsedownExtra. The version of <code>ParsedownExtra</code> that the wallets plugin uses is not compatible with that old version of <code>Parsedown</code> loaded by that other plugin. The documentation may not display correctly.', 'wallets' ); ?></p>
									<p><?php printf( __( 'You can view the documentation using any markdown viewer. The markdown for this documentation page can be found here: <code>%s</code>', 'wallets' ), $this_doc['file'] ); ?></p>
									<p><?php echo __( 'You can view the documentation for the parent plugin via <a href="https://github.com/dashed-slug/wallets/tree/master/docs">github</a>.', 'wallets' ); ?></p>
								</div>
								<?php
							}

							$html = $pd->text( $markdown );

							// all external links open in new tab
							$html = str_replace( '<a href="http', '<a rel="external" target="_blank" href="http', $html );

							// remove trailing slash from all internal links
							$html = preg_replace( '/(href="[^"]+)\/"/', '\1"', $html );
						?>

						<main><?php echo $html; ?></main>
						<?php endif;
					endif;
				endif;
			},
			'dashicons-book'
		);
	}
);
