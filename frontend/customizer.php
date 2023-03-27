<?php

/**
 * Customizer integration.
 *
 * @since 4.1.0
 * @author Alexandros Georgiou <info@dashed-slug.net>
 */


namespace DSWallets;

// don't load directly
defined( 'ABSPATH' ) || die( -1 );

/* defaults */

/** Opacity of a UI while it is loading data, from 0 to 1.
 * @var string|number
 */
const WALLETS_GENERAL_OPACITY_LOADING         = '0.5';

/** Color for the border of the UIs.
 *
 * @var string Color value in hex, must start with #.
 */
const WALLETS_BORDER_COLOR                    = '#000000';

/** Style for the border of the UIs.
 *
 * @var string Can be a valid CSS value, such as solid, dashed, etc.
 */
const WALLETS_BORDER_STYLE                    = 'solid';

/** Width in pixels for the border of the UIs.
 * @var string|number
 */
const WALLETS_BORDER_WIDTH_PX                 = '1';

/** Corner radius in pixels for the border of the UIs. Allows for rounded corners.
 * @var string|number
 */
const WALLETS_BORDER_RADIUS_PX                = '0';

/** Padding in pixels around the border of the UIs.
 * @var string|number
 */
const WALLETS_BORDER_PADDING_PX               = '20';

/** Horizontal offset of shadow in pixels.
 * @var string|number
 */
const WALLETS_BORDER_SHADOW_OFFSET_X_PX       = '0';

/** Vertical offset of shadow in pixels.
 * @var string|number
 */
const WALLETS_BORDER_SHADOW_OFFSET_Y_PX       = '0';

/** Shadow color in hex.
 * @var string
 */
const WALLETS_BORDER_SHADOW_COLOR             = '#000000';

/** Shadow radius in pixels.
 * @var string|number
 */
const WALLETS_BORDER_SHADOW_BLUR_RADIUS_PX    = '0';

/**
 * Font color in hex.
 * @var string
 */
const WALLETS_FONT_COLOR                      = '#202020';

/**
 * Font size in points.
 * @var string|number
 */
const WALLETS_FONT_SIZE_PT                    = '12';

/**
 * Font color for field labels, in hex.
 * @var string
 */
const WALLETS_FONT_LABEL_COLOR                = '#000000';

/**
 * Font size for field labels, in points.
 * @var string|number
 */
const WALLETS_FONT_LABEL_SIZE_PT              = '14';

/**
 * Background color, in hex, for rows showing transactions with PENDING status.
 * @var string
 */
const WALLETS_TXCOLORS_PENDING                = '#808080';

/**
 * Background color, in hex, for rows showing transactions with DONE status.
 * @var string
 */
const WALLETS_TXCOLORS_DONE                   = '#58b858';

/**
 * Background color, in hex, for rows showing transactions with FAILED status.
 * @var string
 */
const WALLETS_TXCOLORS_FAILED                 = '#d85848';

/**
 * Background color, in hex, for rows showing transactions with CANCELLE status.
 * @var string
 */
const WALLETS_TXCOLORS_CANCELLED              = '#f8f828';

/**
 * Width for currency logos/icons, in pixels. Since logos are usually square, this is also the height.
 * @var string|number
 */
const WALLETS_ICON_WIDTH_PX                   = '64';

/**
 * Horizontal offset for shadow behind currency logos/icons, in pixels.
 * @var string|number
 */
const WALLETS_ICON_SHADOW_OFFSET_X_PX         = '0';

/**
 * Vertical offset for shadow behind currency logos/icons, in pixels.
 * @var string|number
 */
const WALLETS_ICON_SHADOW_OFFSET_Y_PX         = '0';

/**
 * Color for shadow behind currency logos/icons, in hex.
 * @var string
 */
const WALLETS_ICON_SHADOW_COLOR               = '#000000';

/**
 * Radius for shadow behind currency logos/icons, in pixels. For no shadow, leave at 0.
 * @var string|number
 */
const WALLETS_ICON_SHADOW_BLUR_RADIUS_PX      = '0';

add_action( 'customize_register', function( object $wp_customize ) {

	$wp_customize->add_panel(
		'wallets_panel',
		array(
			'title'      => __( 'Bitcoin and Altcoin Wallets' ),
			'capability' => 'manage_wallets',
			'priority'   => 150,
		)
	);

	$wp_customize->add_section(
		'wallets_general_section',
		array(
			'title'        => __( 'General', 'wallets' ),
			'description'  => __( 'Styling that affects all the wallet UIs.', 'wallets' ),
			'panel'        => 'wallets_panel',
			'priority'     => 10,
		)
	);

	$wp_customize->add_setting(
		'wallets_general_opacity_loading',
		array(
			'default' => WALLETS_GENERAL_OPACITY_LOADING,
		)
	);

	$wp_customize->add_control(
		'wallets_general_opacity_loading_control',
		array(
			'label'       => __( 'Opacity while loading', 'wallets' ),
			'description' => __( 'Color the opacity of UIs while data is communicated with the server.', 'wallets' ),
			'section'     => 'wallets_general_section',
			'settings'    => 'wallets_general_opacity_loading',
			'priority'    => 1,
			'type'        => 'range',
			'input_attrs' => array(
				'min'  => 0,
				'max'  => 1,
				'step' => 0.05,
			),
		)
	);

	$wp_customize->add_section(
		'wallets_border_section',
		array(
			'title'        => __( 'Borders', 'wallets' ),
			'description'  => __( 'Styling that affects the border or the wallet UIs.', 'wallets' ),
			'panel'        => 'wallets_panel',
			'priority'     => 10,
		)
	);

	$wp_customize->add_setting(
		'wallets_border_color',
		array(
			'default' => WALLETS_BORDER_COLOR,
		)
	);

	$wp_customize->add_control(
		new \WP_Customize_Color_Control(
			$wp_customize,
			'wallets_border_color_control',
			array(
				'label'       => __( 'Color', 'wallets' ),
				'description' => __( 'Color of the surrounding border around wallet UIs.', 'wallets' ),
				'section'     => 'wallets_border_section',
				'settings'    => 'wallets_border_color',
				'priority'    => 1,
			)
		)
	);

	$wp_customize->add_setting(
		'wallets_border_style',
		array(
			'default'           => WALLETS_BORDER_STYLE,
		)
	);

	$wp_customize->add_control(
		'wallets_border_style_control',
		array(
			'label'       => __( 'Line style', 'wallets' ),
			'description' => __( 'Style of the surrounding border around wallet UIs.', 'wallets' ),
			'section'     => 'wallets_border_section',
			'settings'    => 'wallets_border_style',
			'type'        => 'radio',
			'priority'    => 2,
			'choices'     => array(
				'none'   => __( 'None',   'wallets' ),
				'hidden' => __( 'Hidden', 'wallets' ),
				'dotted' => __( 'Dotted', 'wallets' ),
				'dashed' => __( 'Dashed', 'wallets' ),
				'solid'  => __( 'Solid',  'wallets' ),
				'double' => __( 'Double', 'wallets' ),
				'groove' => __( 'Groove', 'wallets' ),
				'ridge'  => __( 'Ridge',  'wallets' ),
				'inset'  => __( 'Inset',  'wallets' ),
				'outset' => __( 'Outset', 'wallets' ),
			)
		)
	);

	$wp_customize->add_setting(
		'wallets_border_width_px',
		array(
			'default'           => WALLETS_BORDER_WIDTH_PX,
			'sanitize_callback' => 'absint',
		)
	);

	$wp_customize->add_control(
		'wallets_border_width_px_control',
		array(
			'label'       => __( 'Width (px)', 'wallets' ),
			'description' => __( 'Width of the surrounding border around wallet UIs, measured in pixels.', 'wallets' ),
			'section'     => 'wallets_border_section',
			'settings'    => 'wallets_border_width_px',
			'type'        => 'number',
			'priority'    => 3,
			'input_attrs' => array(
				'min'  => 0,
				'max'  => 50,
				'step' => 1,
			)
		)
	);

	$wp_customize->add_setting(
		'wallets_border_radius_px',
		array(
			'default'           => WALLETS_BORDER_RADIUS_PX,
			'sanitize_callback' => 'absint',
		)
	);

	$wp_customize->add_control(
		'wallets_border_radius_px_control',
		array(
			'label'       => __( 'Radius (px)', 'wallets' ),
			'description' => __( 'Radius on corners of the surrounding border around wallet UIs, measured in pixels.', 'wallets' ),
			'section'     => 'wallets_border_section',
			'settings'    => 'wallets_border_radius_px',
			'type'        => 'number',
			'priority'    => 3,
			'input_attrs' => array(
				'min'  => 0,
				'max'  => 500,
				'step' => 1,
			)
		)
	);

	$wp_customize->add_setting(
		'wallets_border_padding_px',
		array(
			'default'           => WALLETS_BORDER_PADDING_PX,
			'sanitize_callback' => 'absint',
		)
	);

	$wp_customize->add_control(
		'wallets_border_padding_px_control',
		array(
			'label'       => __( 'Padding width (px)', 'wallets' ),
			'description' => __( 'Padding width inside of the wallet UI borders, measured in pixels.', 'wallets' ),
			'section'     => 'wallets_border_section',
			'settings'    => 'wallets_border_padding_px',
			'type'        => 'number',
			'priority'    => 3,
			'input_attrs' => array(
				'min'  => 0,
				'max'  => 50,
				'step' => 1,
			)
		)
	);

	$wp_customize->add_setting(
		'wallets_border_shadow_offset_x_px',
		array(
			'default'           => WALLETS_BORDER_SHADOW_OFFSET_X_PX,
			'sanitize_callback' => function( $val ) {
				// force one argument (intval has two)
				return intval( $val );
			}
		)
	);

	$wp_customize->add_control(
		'wallets_border_shadow_offset_x_px_control',
		array(
			'label'       => __( 'Shadow offset X (px)', 'wallets' ),
			'description' => __( 'Horizontal offset of the UI box shadows, measured in pixels.', 'wallets' ),
			'section'     => 'wallets_border_section',
			'settings'    => 'wallets_border_shadow_offset_x_px',
			'type'        => 'number',
			'priority'    => 4,
			'input_attrs' => array(
				'min'  => 0,
				'max'  => 50,
				'step' => 1,
			)
		)
	);

	$wp_customize->add_setting(
		'wallets_border_shadow_offset_y_px',
		array(
			'default'           => WALLETS_BORDER_SHADOW_OFFSET_Y_PX,
			'sanitize_callback' => function( $val ) {
				// force one argument (intval has two)
				return intval( $val );
			}
		)
	);

	$wp_customize->add_control(
		'wallets_border_shadow_offset_y_px_control',
		array(
			'label'       => __( 'Shadow offset Y (px)', 'wallets' ),
			'description' => __( 'Vertical offset of the UI box shadows, measured in pixels.', 'wallets' ),
			'section'     => 'wallets_border_section',
			'settings'    => 'wallets_border_shadow_offset_y_px',
			'type'        => 'number',
			'priority'    => 5,
			'input_attrs' => array(
				'min'  => 0,
				'max'  => 50,
				'step' => 1,
			)
		)
	);

	$wp_customize->add_setting(
		'wallets_border_shadow_color',
		array(
			'default' => WALLETS_BORDER_SHADOW_COLOR,
		)
	);

	$wp_customize->add_control(
		new \WP_Customize_Color_Control(
			$wp_customize,
			'wallets_border_shadow_color_control',
			array(
				'label'       => __( 'Shadow color', 'wallets' ),
				'description' => __( 'Color of the box shadows behind wallet UIs.', 'wallets' ),
				'section'     => 'wallets_border_section',
				'settings'    => 'wallets_border_shadow_color',
				'priority'    => 6,
			)
		)
	);

	$wp_customize->add_setting(
		'wallets_border_shadow_blur_radius_px',
		array(
			'default'           => WALLETS_BORDER_SHADOW_BLUR_RADIUS_PX,
			'sanitize_callback' => 'absint',
		)
	);

	$wp_customize->add_control(
		'wallets_border_shadow_blur_radius_px_control',
		array(
			'label'       => __( 'Shadow blur radius (px)', 'wallets' ),
			'description' => __( 'Gaussian blur radius of the UI box shadows, measured in pixels.', 'wallets' ),
			'section'     => 'wallets_border_section',
			'settings'    => 'wallets_border_shadow_blur_radius_px',
			'type'        => 'number',
			'priority'    => 7,
			'input_attrs' => array(
				'min'  => 0,
				'max'  => 50,
				'step' => 1,
			)
		)
	);

	$wp_customize->add_section(
		'wallets_font_section',
		array(
			'title'        => __( 'Text', 'wallets' ),
			'description'  => __( 'Styling that affects the text within the wallet UIs.', 'wallets' ),
			'panel'        => 'wallets_panel',
			'priority'     => 20,
		)
	);

	$wp_customize->add_setting(
		'wallets_font_color',
		array(
			'default' => WALLETS_FONT_COLOR,
		)
	);

	$wp_customize->add_control(
		new \WP_Customize_Color_Control(
			$wp_customize,
			'wallets_font_color_control',
			array(
				'label'       => __( 'Color', 'wallets' ),
				'description' => __( 'Color of the text in the wallet UIs.', 'wallets' ),
				'section'     => 'wallets_font_section',
				'settings'    => 'wallets_font_color',
				'priority'    => 1,
			)
		)
	);

	$wp_customize->add_setting(
		'wallets_font_size_pt',
		array(
			'default'           => WALLETS_FONT_SIZE_PT,
			'sanitize_callback' => 'absint',
		)
	);

	$wp_customize->add_control(
		'wallets_font_size_pt_control',
		array(
			'label'       => __( 'Font size (pt)', 'wallets' ),
			'description' => __( 'Size of the text font in the wallet UIs, measured in points.', 'wallets' ),
			'section'     => 'wallets_font_section',
			'settings'    => 'wallets_font_size_pt',
			'type'        => 'number',
			'priority'    => 1,
			'input_attrs' => array(
				'min'  => 5,
				'max'  => 100,
				'step' => 1,
			)
		)
	);

	$wp_customize->add_setting(
		'wallets_font_label_color',
		array(
			'default' => WALLETS_FONT_LABEL_COLOR,
		)
	);

	$wp_customize->add_control(
		new \WP_Customize_Color_Control(
			$wp_customize,
			'wallets_font_label_color_control',
			array(
				'label'       => __( 'Label color', 'wallets' ),
				'description' => __( 'Color of the labels/headers in the wallet UIs.', 'wallets' ),
				'section'     => 'wallets_font_section',
				'settings'    => 'wallets_font_label_color',
				'priority'    => 2,
			)
		)
	);

	$wp_customize->add_setting(
		'wallets_font_label_size_pt',
		array(
			'default'           => WALLETS_FONT_LABEL_SIZE_PT,
			'sanitize_callback' => 'absint',
		)
	);

	$wp_customize->add_control(
		'wallets_font_label_size_pt_control',
		array(
			'label'       => __( 'Label font size (pt)', 'wallets' ),
			'description' => __( 'Size of the labels/headers font in the wallet UIs, measured in points.', 'wallets' ),
			'section'     => 'wallets_font_section',
			'settings'    => 'wallets_font_label_size_pt',
			'type'        => 'number',
			'priority'    => 2,
			'input_attrs' => array(
				'min'  => 5,
				'max'  => 100,
				'step' => 1,
			)
		)
	);

	$wp_customize->add_section(
		'wallets_txcolors_section',
		array(
			'title'        => __( 'Transaction colors', 'wallets' ),
			'description'  => __( 'In transactions lists, the transactions are color-coded by status. These colors can be set here.', 'wallets' ),
			'panel'        => 'wallets_panel',
			'priority'     => 30,
		)
	);

	$wp_customize->add_setting(
		'wallets_txcolors_pending',
		array(
			'default' => WALLETS_TXCOLORS_PENDING,
		)
	);

	$wp_customize->add_control(
		new \WP_Customize_Color_Control(
			$wp_customize,
			'wallets_txcolors_pending_control',
			array(
				'label'       => __( 'Status "pending"', 'wallets' ),
				'description' => __( 'Color for transactions in the "pending" state.', 'wallets' ),
				'section'     => 'wallets_txcolors_section',
				'settings'    => 'wallets_txcolors_pending',
				'priority'    => 2,
			)
		)
	);

	$wp_customize->add_setting(
		'wallets_txcolors_done',
		array(
			'default' => WALLETS_TXCOLORS_DONE,
		)
	);

	$wp_customize->add_control(
		new \WP_Customize_Color_Control(
			$wp_customize,
			'wallets_txcolors_done_control',
			array(
				'label'       => __( 'Status "done"', 'wallets' ),
				'description' => __( 'Color for transactions in the "done" state.', 'wallets' ),
				'section'     => 'wallets_txcolors_section',
				'settings'    => 'wallets_txcolors_done',
				'priority'    => 3,
			)
		)
	);

	$wp_customize->add_setting(
		'wallets_txcolors_failed',
		array(
			'default' => WALLETS_TXCOLORS_FAILED,
		)
	);

	$wp_customize->add_control(
		new \WP_Customize_Color_Control(
			$wp_customize,
			'wallets_txcolors_failed_control',
			array(
				'label'       => __( 'Status "failed"', 'wallets' ),
				'description' => __( 'Color for transactions in the "failed" state.', 'wallets' ),
				'section'     => 'wallets_txcolors_section',
				'settings'    => 'wallets_txcolors_failed',
				'priority'    => 4,
			)
		)
	);

	$wp_customize->add_setting(
		'wallets_txcolors_cancelled',
		array(
			'default' => WALLETS_TXCOLORS_CANCELLED,
		)
	);

	$wp_customize->add_control(
		new \WP_Customize_Color_Control(
			$wp_customize,
			'wallets_txcolors_cancelled_control',
			array(
				'label'       => __( 'Status "cancelled"', 'wallets' ),
				'description' => __( 'Color for transactions in the "cancelled" state.', 'wallets' ),
				'section'     => 'wallets_txcolors_section',
				'settings'    => 'wallets_txcolors_cancelled',
				'priority'    => 5,
			)
		)
	);

	$wp_customize->add_section(
		'wallets_icon_section',
		array(
			'title'        => __( 'Currency icons', 'wallets' ),
			'description'  => __(
				'Styling that affects any currency icons appearing within the wallet UIs. ' .
				'(Note: These settings do not affect icons appearing in dropdown menus or in special menu items.)',
				'wallets'
				),
			'panel'        => 'wallets_panel',
			'priority'     => 40,
		)
	);

	$wp_customize->add_setting(
		'wallets_icon_width_px',
		array(
			'default'           => WALLETS_ICON_WIDTH_PX,
			'sanitize_callback' => 'absint',
		)
	);

	$wp_customize->add_control(
		'wallets_icon_width_px',
		array(
			'label'       => __( 'Icon size (px)', 'wallets' ),
			'description' => __( 'The width of currency icons, measured in pixels.', 'wallets' ),
			'section'     => 'wallets_icon_section',
			'settings'    => 'wallets_icon_width_px',
			'type'        => 'number',
			'priority'    => 1,
			'input_attrs' => array(
				'min'  => 8,
				'max'  => 128,
				'step' => 1,
			)
		)
	);

	$wp_customize->add_setting(
		'wallets_icon_shadow_offset_x_px',
		array(
			'default'           => WALLETS_ICON_SHADOW_OFFSET_X_PX,
			'sanitize_callback' => function( $val ) {
				// force one argument (intval has two)
				return intval( $val );
			}
		)
	);

	$wp_customize->add_control(
		'wallets_icon_shadow_offset_x_px_control',
		array(
			'label'       => __( 'Shadow offset X (px)', 'wallets' ),
			'description' => __( 'Horizontal offset of the drop shadows behind currency icons, measured in pixels.', 'wallets' ),
			'section'     => 'wallets_icon_section',
			'settings'    => 'wallets_icon_shadow_offset_x_px',
			'type'        => 'number',
			'priority'    => 2,
			'input_attrs' => array(
				'min'  => 0,
				'max'  => 50,
				'step' => 1,
			)
		)
	);

	$wp_customize->add_setting(
		'wallets_icon_shadow_offset_y_px',
		array(
			'default'           => WALLETS_ICON_SHADOW_OFFSET_Y_PX,
			'sanitize_callback' => function( $val ) {
				// force one argument (intval has two)
				return intval( $val );
			}
		)
	);

	$wp_customize->add_control(
		'wallets_icon_shadow_offset_y_px_control',
		array(
			'label'       => __( 'Shadow offset Y (px)', 'wallets' ),
			'description' => __( 'Vertical offset of the drop shadows behind currency icons, measured in pixels.', 'wallets' ),
			'section'     => 'wallets_icon_section',
			'settings'    => 'wallets_icon_shadow_offset_y_px',
			'type'        => 'number',
			'priority'    => 3,
			'input_attrs' => array(
				'min'  => 0,
				'max'  => 50,
				'step' => 1,
			)
		)
	);

	$wp_customize->add_setting(
		'wallets_icon_shadow_color',
		array(
			'default' => WALLETS_ICON_SHADOW_COLOR,
		)
	);

	$wp_customize->add_control(
		new \WP_Customize_Color_Control(
			$wp_customize,
			'wallets_icon_shadow_color_control',
			array(
				'label'       => __( 'Shadow color', 'wallets' ),
				'description' => __( 'Color of the drop shadows behind currency icons.', 'wallets' ),
				'section'     => 'wallets_icon_section',
				'settings'    => 'wallets_icon_shadow_color',
				'priority'    => 4,
			)
		)
	);

	$wp_customize->add_setting(
		'wallets_icon_shadow_blur_radius_px',
		array(
			'default'           => WALLETS_ICON_SHADOW_BLUR_RADIUS_PX,
			'sanitize_callback' => 'absint',
		)
	);

	$wp_customize->add_control(
		'wallets_icon_shadow_blur_radius_px_control',
		array(
			'label'       => __( 'Shadow blur radius (px)', 'wallets' ),
			'description' => __( 'Gaussian blur radius of the shadow behind currency icons, measured in pixels.', 'wallets' ),
			'section'     => 'wallets_icon_section',
			'settings'    => 'wallets_icon_shadow_blur_radius_px',
			'type'        => 'number',
			'priority'    => 5,
			'input_attrs' => array(
				'min'  => 0,
				'max'  => 50,
				'step' => 1,
			)
		)
	);

}, 10, 2 );

add_action(
	'wp_head',
	function() {
		?>
<style type="text/css">
	.dashed-slug-wallets {
		opacity:
			<?php echo get_theme_mod( 'wallets_general_opacity_loading',      WALLETS_GENERAL_OPACITY_LOADING      ); ?>;

		border:
			<?php echo get_theme_mod( 'wallets_border_width_px',              WALLETS_BORDER_WIDTH_PX              ); ?>px
			<?php echo get_theme_mod( 'wallets_border_style',                 WALLETS_BORDER_STYLE                 ); ?>
			<?php echo get_theme_mod( 'wallets_border_color',                 WALLETS_BORDER_COLOR                 ); ?>;

		border-radius:
			<?php echo get_theme_mod( 'wallets_border_radius_px',             WALLETS_BORDER_RADIUS_PX             ); ?>px;

		padding:
			<?php echo get_theme_mod( 'wallets_border_padding_px',            WALLETS_BORDER_PADDING_PX            ); ?>px;

		box-shadow:
			<?php echo get_theme_mod( 'wallets_border_shadow_offset_x_px',    WALLETS_BORDER_SHADOW_OFFSET_X_PX    ); ?>px
			<?php echo get_theme_mod( 'wallets_border_shadow_offset_y_px',    WALLETS_BORDER_SHADOW_OFFSET_Y_PX    ); ?>px
			<?php echo get_theme_mod( 'wallets_border_shadow_blur_radius_px', WALLETS_BORDER_SHADOW_BLUR_RADIUS_PX ); ?>px
			<?php echo get_theme_mod( 'wallets_border_shadow_color',          WALLETS_BORDER_SHADOW_COLOR          ); ?>;
	}

	.dashed-slug-wallets,
	.dashed-slug-wallets input,
	.dashed-slug-wallets select,
	.dashed-slug-wallets textarea {
		color:
			<?php echo get_theme_mod( 'wallets_font_color',                   WALLETS_FONT_COLOR                   ); ?>;

		font-size:
			<?php echo get_theme_mod( 'wallets_font_size_pt',                 WALLETS_FONT_SIZE_PT                 ); ?>pt;
	}

	.dashed-slug-wallets label,
	.dashed-slug-wallets table th {

		color:
			<?php echo get_theme_mod( 'wallets_font_label_color',           WALLETS_FONT_LABEL_COLOR             ); ?>;

		font-size:
			<?php echo get_theme_mod( 'wallets_font_label_size_pt',         WALLETS_FONT_LABEL_SIZE_PT           ); ?>pt;
	}

	.dashed-slug-wallets.transactions table tr.pending,
	.dashed-slug-wallets.transactions table tr.pending *,
	.dashed-slug-wallets.transactions-rows li.pending,
	.dashed-slug-wallets.transactions-rows li.pending * {
		background-color:
			<?php echo get_theme_mod( 'wallets_txcolors_pending',           WALLETS_TXCOLORS_PENDING             ); ?>;
	}

	.dashed-slug-wallets.transactions table tr.done,
	.dashed-slug-wallets.transactions table tr.done *,
	.dashed-slug-wallets.transactions-rows li.done,
	.dashed-slug-wallets.transactions-rows li.done * {
		background-color:
			<?php echo get_theme_mod( 'wallets_txcolors_done',              WALLETS_TXCOLORS_DONE                ); ?>;
	}

	.dashed-slug-wallets.transactions table tr.failed,
	.dashed-slug-wallets.transactions table tr.failed *,
	.dashed-slug-wallets.transactions-rows li.failed,
	.dashed-slug-wallets.transactions-rows li.failed * {
		background-color:
			<?php echo get_theme_mod( 'wallets_txcolors_failed',            WALLETS_TXCOLORS_FAILED              ); ?>;
	}

	.dashed-slug-wallets.transactions table tr.cancelled,
	.dashed-slug-wallets.transactions table tr.cancelled *,
	.dashed-slug-wallets.transactions-rows li.cancelled,
	.dashed-slug-wallets.transactions-rows li.cancelled * {
		background-color:
			<?php echo get_theme_mod( 'wallets_txcolors_cancelled',         WALLETS_TXCOLORS_CANCELLED           ); ?>;
	}

	.dashed-slug-wallets tbody td.icon,
	.dashed-slug-wallets tbody td.icon img {
		width:
			<?php echo get_theme_mod( 'wallets_icon_width_px',              WALLETS_ICON_WIDTH_PX                ); ?>px;
/*
		height:
			<?php echo get_theme_mod( 'wallets_icon_width_px',              WALLETS_ICON_WIDTH_PX                ); ?>px;
*/
	}

	.dashed-slug-wallets tbody td.icon img {
		filter: drop-shadow(
			<?php echo get_theme_mod( 'wallets_icon_shadow_offset_x_px',    WALLETS_ICON_SHADOW_OFFSET_X_PX        ); ?>px
			<?php echo get_theme_mod( 'wallets_icon_shadow_offset_y_px',    WALLETS_ICON_SHADOW_OFFSET_Y_PX        ); ?>px
			<?php echo get_theme_mod( 'wallets_icon_shadow_blur_radius_px', WALLETS_ICON_SHADOW_BLUR_RADIUS_PX     ); ?>px
			<?php echo get_theme_mod( 'wallets_icon_shadow_color',          WALLETS_ICON_SHADOW_COLOR              ); ?>);
	}
</style>

	<?php
	}
);
