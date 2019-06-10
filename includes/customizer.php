<?php

/**
 * Customizer integration.
 */

// don't load directly
defined( 'ABSPATH' ) || die( -1 );

if ( ! class_exists( 'Dashed_Slug_Wallets_Customizer' ) ) {
	class Dashed_Slug_Wallets_Customizer {

		/* defaults */

		const WALLETS_BORDER_COLOR                    = '#000000';
		const WALLETS_BORDER_STYLE                    = 'solid';
		const WALLETS_BORDER_WIDTH_PX                 = 1;
		const WALLETS_BORDER_PADDING_PX               = 20;
		const WALLETS_BORDER_SHADOW_OFFSET_X_PX       = 0;
		const WALLETS_BORDER_SHADOW_OFFSET_Y_PX       = 0;
		const WALLETS_BORDER_SHADOW_COLOR             = '#000000';
		const WALLETS_BORDER_SHADOW_BLUR_RADIUS_PX    = 0;

		const WALLETS_FONT_COLOR                      = '#202020';
		const WALLETS_FONT_SIZE_PT                    = '12';
		const WALLETS_FONT_LABEL_COLOR                = '#000000';
		const WALLETS_FONT_LABEL_SIZE_PT              = '14';

		const WALLETS_ICON_WIDTH_PX                   = 64;
		const WALLETS_ICON_SHADOW_OFFSET_X_PX         = 0;
		const WALLETS_ICON_SHADOW_OFFSET_Y_PX         = 0;
		const WALLETS_ICON_SHADOW_COLOR               = '#000000';
		const WALLETS_ICON_SHADOW_BLUR_RADIUS_PX      = 0;

		public function __construct() {
			add_action( 'customize_register', array( &$this, 'action_customize_register' ) );
			add_action( 'wp_head',            array( &$this, 'action_wp_head' ) );
		}

		public function action_customize_register( $wp_customize ) {

			$wp_customize->add_panel(
				'wallets_panel',
				array(
					'title'      => __( 'Bitcoin and Altcoin Wallets' ),
					'capability' => 'manage_wallets',
					'priority'   => 150,
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
					'default' => self::WALLETS_BORDER_COLOR,
				)
			);

			$wp_customize->add_control(
				new WP_Customize_Color_Control(
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
					'default'           => self::WALLETS_BORDER_STYLE,
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
					'default'           => self::WALLETS_BORDER_WIDTH_PX,
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
				'wallets_border_padding_px',
				array(
					'default'           => self::WALLETS_BORDER_PADDING_PX,
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
					'default'           => self::WALLETS_BORDER_SHADOW_OFFSET_X_PX,
					'sanitize_callback' => __CLASS__ . '::sanitize_int',
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
					'default'           => self::WALLETS_BORDER_SHADOW_OFFSET_Y_PX,
					'sanitize_callback' => __CLASS__ . '::sanitize_int',
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
					'default' => self::WALLETS_BORDER_SHADOW_COLOR,
				)
			);

			$wp_customize->add_control(
				new WP_Customize_Color_Control(
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
					'default'           => self::WALLETS_BORDER_SHADOW_BLUR_RADIUS_PX,
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
					'default' => self::WALLETS_FONT_COLOR,
				)
			);

			$wp_customize->add_control(
				new WP_Customize_Color_Control(
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
					'default'           => self::WALLETS_FONT_SIZE_PT,
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
					'default' => self::WALLETS_FONT_LABEL_COLOR,
				)
			);

			$wp_customize->add_control(
				new WP_Customize_Color_Control(
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
					'default'           => self::WALLETS_FONT_LABEL_SIZE_PT,
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
				'wallets_icon_section',
				array(
					'title'        => __( 'Coin icons', 'wallets' ),
					'description'  => __(
						'Styling that affects any coin icons appearing within the wallet UIs. ' .
						'(Note: These settings do not affect icons appearing in dropdown menus or in special menu items.)',
						'wallets'
					),
					'panel'        => 'wallets_panel',
					'priority'     => 30,
				)
			);

			$wp_customize->add_setting(
				'wallets_icon_width_px',
				array(
					'default'           => self::WALLETS_ICON_WIDTH_PX,
					'sanitize_callback' => 'absint',
				)
			);

			$wp_customize->add_control(
				'wallets_icon_width_px',
				array(
					'label'       => __( 'Icon size (px)', 'wallets' ),
					'description' => __( 'The width of coin icons, measured in pixels.', 'wallets' ),
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
					'default'           => self::WALLETS_ICON_SHADOW_OFFSET_X_PX,
					'sanitize_callback' => __CLASS__ . '::sanitize_int',
				)
			);

			$wp_customize->add_control(
				'wallets_icon_shadow_offset_x_px_control',
				array(
					'label'       => __( 'Shadow offset X (px)', 'wallets' ),
					'description' => __( 'Horizontal offset of the drop shadows behind coin icons, measured in pixels.', 'wallets' ),
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
					'default'           => self::WALLETS_ICON_SHADOW_OFFSET_Y_PX,
					'sanitize_callback' => __CLASS__ . '::sanitize_int',
				)
			);

			$wp_customize->add_control(
				'wallets_icon_shadow_offset_y_px_control',
				array(
					'label'       => __( 'Shadow offset Y (px)', 'wallets' ),
					'description' => __( 'Vertical offset of the drop shadows behind coin icons, measured in pixels.', 'wallets' ),
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
					'default' => self::WALLETS_ICON_SHADOW_COLOR,
				)
			);

			$wp_customize->add_control(
				new WP_Customize_Color_Control(
					$wp_customize,
					'wallets_icon_shadow_color_control',
					array(
						'label'       => __( 'Shadow color', 'wallets' ),
						'description' => __( 'Color of the drop shadows behind coin icons.', 'wallets' ),
						'section'     => 'wallets_icon_section',
						'settings'    => 'wallets_icon_shadow_color',
						'priority'    => 4,
					)
				)
			);

			$wp_customize->add_setting(
				'wallets_icon_shadow_blur_radius_px',
				array(
					'default'           => self::WALLETS_ICON_SHADOW_BLUR_RADIUS_PX,
					'sanitize_callback' => 'absint',
				)
			);

			$wp_customize->add_control(
				'wallets_icon_shadow_blur_radius_px_control',
				array(
					'label'       => __( 'Shadow blur radius (px)', 'wallets' ),
					'description' => __( 'Gaussian blur radius of the shadow behind coin icons, measured in pixels.', 'wallets' ),
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

		}

		public function action_wp_head() {

			?>
<style type="text/css">
	.dashed-slug-wallets {
		border:
			<?php echo get_theme_mod( 'wallets_border_width_px',              self::WALLETS_BORDER_WIDTH_PX              ); ?>px
			<?php echo get_theme_mod( 'wallets_border_style',                 self::WALLETS_BORDER_STYLE                 ); ?>
			<?php echo get_theme_mod( 'wallets_border_color',                 self::WALLETS_BORDER_COLOR                 ); ?>;

		padding:
			<?php echo get_theme_mod( 'wallets_border_padding_px',            self::WALLETS_BORDER_PADDING_PX            ); ?>px;

		color:
			<?php echo get_theme_mod( 'wallets_font_color',                   self::WALLETS_FONT_COLOR                   ); ?>;

		font-size:
			<?php echo get_theme_mod( 'wallets_font_size_pt',                 self::WALLETS_FONT_SIZE_PT                 ); ?>pt;

		box-shadow:
			<?php echo get_theme_mod( 'wallets_border_shadow_offset_x_px',    self::WALLETS_BORDER_SHADOW_OFFSET_X_PX    ); ?>px
			<?php echo get_theme_mod( 'wallets_border_shadow_offset_y_px',    self::WALLETS_BORDER_SHADOW_OFFSET_Y_PX    ); ?>px
			<?php echo get_theme_mod( 'wallets_border_shadow_blur_radius_px', self::WALLETS_BORDER_SHADOW_BLUR_RADIUS_PX ); ?>px
			<?php echo get_theme_mod( 'wallets_border_shadow_color',          self::WALLETS_BORDER_SHADOW_COLOR          ); ?>;
	}

	.dashed-slug-wallets label,
	.dashed-slug-wallets table th {

		color:
			<?php echo get_theme_mod( 'wallets_font_label_color',           self::WALLETS_FONT_LABEL_COLOR             ); ?>;

		font-size:
			<?php echo get_theme_mod( 'wallets_font_label_size_pt',         self::WALLETS_FONT_LABEL_SIZE_PT           ); ?>pt;
	}

	.dashed-slug-wallets tbody td.icon,
	.dashed-slug-wallets tbody td.icon img {
		width:
			<?php echo get_theme_mod( 'wallets_icon_width_px',              self::WALLETS_ICON_WIDTH_PX                ); ?>px;
		height:
			<?php echo get_theme_mod( 'wallets_icon_width_px',              self::WALLETS_ICON_WIDTH_PX                ); ?>px;
	}

	.dashed-slug-wallets tbody td.icon img {
		filter: drop-shadow(
			<?php echo get_theme_mod( 'wallets_icon_shadow_offset_x_px',    self::WALLETS_ICON_SHADOW_OFFSET_X_PX        ); ?>px
			<?php echo get_theme_mod( 'wallets_icon_shadow_offset_y_px',    self::WALLETS_ICON_SHADOW_OFFSET_Y_PX        ); ?>px
			<?php echo get_theme_mod( 'wallets_icon_shadow_blur_radius_px', self::WALLETS_ICON_SHADOW_BLUR_RADIUS_PX     ); ?>px
			<?php echo get_theme_mod( 'wallets_icon_shadow_color',          self::WALLETS_ICON_SHADOW_COLOR              ); ?>);
	}
</style>
			<?php

		}

		public static function sanitize_int( $value ) {
			// we need a sanitizer function that accepts only one argument,
			// while intval can accept two arguments
			return intval( $value );
		}

	} // end class Dashed_Slug_Wallets_Customizer
	new Dashed_Slug_Wallets_Customizer();
}
