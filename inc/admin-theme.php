<?php
/**
 * Keep wp-admin aligned with the active block-theme design.
 *
 * @package FunkyCommerceHeadless
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Convert WordPress preset references into their CSS custom-property form.
 */
function funkycommerce_admin_theme_resolve_preset_reference( $value ) {
	return preg_replace_callback(
		'/var:preset\|([a-z0-9-]+)\|([a-z0-9-]+)/i',
		static function ( $matches ) {
			return sprintf(
				'var(--wp--preset--%s--%s)',
				sanitize_title( $matches[1] ),
				sanitize_title( $matches[2] )
			);
		},
		(string) $value
	);
}

/**
 * Sanitize one dynamic Global Styles value for use in generated CSS.
 */
function funkycommerce_admin_theme_css_value( $property, $value, $fallback ) {
	$value       = funkycommerce_admin_theme_resolve_preset_reference( trim( (string) $value ) );
	$declaration = safecss_filter_attr( $property . ':' . $value );

	if ( '' === $value || 0 !== strpos( $declaration, $property . ':' ) ) {
		return $fallback;
	}

	$sanitized = trim( substr( $declaration, strlen( $property ) + 1 ), " \t\n\r\0\x0B;" );
	return '' !== $sanitized ? $sanitized : $fallback;
}

/**
 * Read a nested Global Styles value.
 */
function funkycommerce_admin_theme_array_value( $source, $path, $fallback = '' ) {
	foreach ( $path as $key ) {
		if ( ! is_array( $source ) || ! array_key_exists( $key, $source ) ) {
			return $fallback;
		}
		$source = $source[ $key ];
	}

	return is_scalar( $source ) ? (string) $source : $fallback;
}

/**
 * Index a preset collection by slug.
 */
function funkycommerce_admin_theme_index_presets( $settings, $section, $preset_name, $value_key, $property ) {
	$indexed = array();
	foreach ( funkycommerce_get_theme_presets( $settings, $section, $preset_name ) as $preset ) {
		$slug  = sanitize_title( $preset['slug'] ?? '' );
		$value = funkycommerce_admin_theme_css_value( $property, $preset[ $value_key ] ?? '', '' );
		if ( $slug && $value ) {
			$indexed[ $slug ] = $value;
		}
	}
	return $indexed;
}

/**
 * Return the active Site Editor palette and typography choices.
 */
function funkycommerce_get_admin_theme_tokens() {
	$settings = function_exists( 'wp_get_global_settings' ) ? wp_get_global_settings() : array();
	$styles   = function_exists( 'wp_get_global_styles' ) ? wp_get_global_styles() : array();
	$colors   = funkycommerce_admin_theme_index_presets( $settings, 'color', 'palette', 'color', 'color' );
	$fonts    = funkycommerce_admin_theme_index_presets( $settings, 'typography', 'fontFamilies', 'fontFamily', 'font-family' );

	$background = funkycommerce_admin_theme_css_value(
		'color',
		funkycommerce_admin_theme_array_value( $styles, array( 'color', 'background' ), $colors['background'] ?? '' ),
		'#f7f7f8'
	);
	$text = funkycommerce_admin_theme_css_value(
		'color',
		funkycommerce_admin_theme_array_value( $styles, array( 'color', 'text' ), $colors['foreground'] ?? '' ),
		'#18181b'
	);
	$accent = funkycommerce_admin_theme_css_value(
		'color',
		funkycommerce_admin_theme_array_value(
			$styles,
			array( 'elements', 'link', 'color', 'text' ),
			funkycommerce_admin_theme_array_value( $styles, array( 'elements', 'button', 'color', 'background' ), $colors['brand'] ?? '' )
		),
		'#7c3aed'
	);
	$button_text = funkycommerce_admin_theme_css_value(
		'color',
		funkycommerce_admin_theme_array_value( $styles, array( 'elements', 'button', 'color', 'text' ), $background ),
		$background
	);
	$heading = funkycommerce_admin_theme_css_value(
		'color',
		funkycommerce_admin_theme_array_value( $styles, array( 'elements', 'heading', 'color', 'text' ), $text ),
		$text
	);
	$body_font = funkycommerce_admin_theme_css_value(
		'font-family',
		funkycommerce_admin_theme_array_value(
			$styles,
			array( 'typography', 'fontFamily' ),
			$fonts['system'] ?? '-apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif'
		),
		'-apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif'
	);
	$heading_font = funkycommerce_admin_theme_css_value(
		'font-family',
		funkycommerce_admin_theme_array_value( $styles, array( 'elements', 'heading', 'typography', 'fontFamily' ), $body_font ),
		$body_font
	);
	$button_font = funkycommerce_admin_theme_css_value(
		'font-family',
		funkycommerce_admin_theme_array_value( $styles, array( 'elements', 'button', 'typography', 'fontFamily' ), $body_font ),
		$body_font
	);

	return array(
		'background'   => $background,
		'text'         => $text,
		'accent'       => $accent,
		'button_text'  => $button_text,
		'heading'      => $heading,
		'body_font'    => $body_font,
		'heading_font' => $heading_font,
		'button_font'  => $button_font,
		'colors'       => $colors,
		'fonts'        => $fonts,
	);
}

/**
 * Build CSS custom properties shared by wp-admin and the editor canvas.
 */
function funkycommerce_get_admin_theme_variables_css( $tokens ) {
	$declarations = array(
		'--funky-admin-background:' . $tokens['background'],
		'--funky-admin-text:' . $tokens['text'],
		'--funky-admin-accent:' . $tokens['accent'],
		'--funky-admin-accent-text:' . $tokens['button_text'],
		'--funky-admin-heading:' . $tokens['heading'],
		'--funky-admin-body-font:' . $tokens['body_font'],
		'--funky-admin-heading-font:' . $tokens['heading_font'],
		'--funky-admin-button-font:' . $tokens['button_font'],
	);

	foreach ( $tokens['colors'] as $slug => $color ) {
		$declarations[] = '--wp--preset--color--' . $slug . ':' . $color;
	}
	foreach ( $tokens['fonts'] as $slug => $font ) {
		$declarations[] = '--wp--preset--font-family--' . $slug . ':' . $font;
	}

	return ':root{' . implode( ';', $declarations ) . '}';
}

/**
 * Generate the scoped wp-admin theme.
 */
function funkycommerce_get_admin_theme_css() {
	$tokens     = funkycommerce_get_admin_theme_tokens();
	$variables  = funkycommerce_get_admin_theme_variables_css( $tokens );
	$font_faces = function_exists( 'funkycommerce_get_font_face_styles' ) ? funkycommerce_get_font_face_styles( false ) : '';

	return $font_faces . $variables . '
body.wp-admin{
	--funky-admin-surface:var(--funky-admin-background);
	--funky-admin-surface-raised:var(--funky-admin-background);
	--funky-admin-border:var(--funky-admin-text);
	--funky-admin-muted:var(--funky-admin-text);
	--funky-admin-accent-soft:var(--funky-admin-background);
	background:var(--funky-admin-surface)!important;
	color:var(--funky-admin-text)!important;
	font-family:var(--funky-admin-body-font)!important;
}
@supports (color:color-mix(in srgb,#000 50%,#fff)){
	body.wp-admin{
		--funky-admin-surface:color-mix(in srgb,var(--funky-admin-background) 96%,var(--funky-admin-text) 4%);
		--funky-admin-surface-raised:color-mix(in srgb,var(--funky-admin-background) 99%,var(--funky-admin-text) 1%);
		--funky-admin-border:color-mix(in srgb,var(--funky-admin-text) 18%,transparent);
		--funky-admin-muted:color-mix(in srgb,var(--funky-admin-text) 68%,var(--funky-admin-background));
		--funky-admin-accent-soft:color-mix(in srgb,var(--funky-admin-accent) 14%,var(--funky-admin-background));
	}
}
body.wp-admin :where(button,input,select,textarea){font-family:var(--funky-admin-body-font)!important}
body.wp-admin :where(.wrap h1,.wrap h2,.wrap h3,.wrap h4,.wrap h5,.wrap h6,.editor-post-title__input,.components-modal__header-heading){
	color:var(--funky-admin-heading)!important;
	font-family:var(--funky-admin-heading-font)!important;
}
body.wp-admin :where(a,.button-link,.components-button.is-link){color:var(--funky-admin-accent)!important}
body.wp-admin :where(a,.button,.components-button,input,select,textarea):focus{
	border-color:var(--funky-admin-accent)!important;
	box-shadow:0 0 0 1px var(--funky-admin-accent)!important;
	outline-color:var(--funky-admin-accent)!important;
}
body.wp-admin #wpadminbar,
body.wp-admin #adminmenuback,
body.wp-admin #adminmenuwrap,
body.wp-admin #adminmenu{
	background:var(--funky-admin-text)!important;
	color:var(--funky-admin-background)!important;
	font-family:var(--funky-admin-body-font)!important;
}
body.wp-admin #wpadminbar :where(.ab-item,.ab-label,.display-name,.username,.ab-empty-item),
body.wp-admin #wpadminbar :where(input,select,textarea){
	font-family:var(--funky-admin-body-font)!important;
}
body.wp-admin #wpadminbar :where(.ab-item,.ab-label),
body.wp-admin #adminmenu :where(a,.wp-menu-image:before){
	color:var(--funky-admin-background)!important;
}
body.wp-admin #adminmenu :where(a:hover,.wp-has-current-submenu>a.menu-top,.current a.menu-top,.wp-menu-open>a.menu-top),
body.wp-admin #adminmenu .wp-submenu a:focus,
body.wp-admin #adminmenu .wp-submenu a:hover,
body.wp-admin #wpadminbar .ab-top-menu>li.hover>.ab-item,
body.wp-admin #wpadminbar.nojq .quicklinks .ab-top-menu>li>.ab-item:focus,
body.wp-admin #wpadminbar:not(.mobile) .ab-top-menu>li:hover>.ab-item{
	background:var(--funky-admin-accent)!important;
	color:var(--funky-admin-accent-text)!important;
}
body.wp-admin #adminmenu .wp-submenu,
body.wp-admin #adminmenu .wp-has-current-submenu .wp-submenu,
body.wp-admin #wpadminbar .menupop .ab-sub-wrapper{
	background:var(--funky-admin-text)!important;
}
body.wp-admin #adminmenu .wp-menu-arrow,
body.wp-admin #adminmenu .wp-menu-arrow div{background:var(--funky-admin-accent)!important}
body.wp-admin :where(.postbox,.stuffbox,.card,.notice,.welcome-panel,.plugin-card,.theme,.components-panel,.components-popover__content,.components-modal__frame){
	background-color:var(--funky-admin-surface-raised)!important;
	border-color:var(--funky-admin-border)!important;
	color:var(--funky-admin-text)!important;
}
body.wp-admin :where(.alternate,.striped>tbody>:nth-child(odd),ul.striped>:nth-child(odd)){
	background-color:var(--funky-admin-accent-soft)!important;
}
body.wp-admin :where(.widefat,.wp-list-table){
	background-color:var(--funky-admin-surface-raised)!important;
	border-color:var(--funky-admin-border)!important;
	color:var(--funky-admin-text)!important;
}
body.wp-admin :where(.widefat thead td,.widefat thead th,.widefat tfoot td,.widefat tfoot th){
	background-color:var(--funky-admin-accent-soft)!important;
	border-color:var(--funky-admin-border)!important;
	color:var(--funky-admin-heading)!important;
}
body.wp-admin :where(input[type=color],input[type=date],input[type=datetime-local],input[type=datetime],input[type=email],input[type=month],input[type=number],input[type=password],input[type=search],input[type=tel],input[type=text],input[type=time],input[type=url],input[type=week],select,textarea){
	background-color:var(--funky-admin-background)!important;
	border-color:var(--funky-admin-border)!important;
	color:var(--funky-admin-text)!important;
}
body.wp-admin .wp-core-ui .button-primary,
body.wp-admin .components-button.is-primary{
	background:var(--funky-admin-accent)!important;
	border-color:var(--funky-admin-accent)!important;
	color:var(--funky-admin-accent-text)!important;
	font-family:var(--funky-admin-button-font)!important;
}
body.wp-admin .wp-core-ui .button-primary:hover,
body.wp-admin .wp-core-ui .button-primary:focus,
body.wp-admin .components-button.is-primary:hover,
body.wp-admin .components-button.is-primary:focus{
	background:var(--funky-admin-accent)!important;
	border-color:var(--funky-admin-accent)!important;
	color:var(--funky-admin-accent-text)!important;
}
@supports (color:color-mix(in srgb,#000 50%,#fff)){
	body.wp-admin #adminmenu .wp-submenu,
	body.wp-admin #adminmenu .wp-has-current-submenu .wp-submenu,
	body.wp-admin #wpadminbar .menupop .ab-sub-wrapper{
		background:color-mix(in srgb,var(--funky-admin-text) 92%,var(--funky-admin-background))!important;
	}
	body.wp-admin .wp-core-ui .button-primary:hover,
	body.wp-admin .wp-core-ui .button-primary:focus,
	body.wp-admin .components-button.is-primary:hover,
	body.wp-admin .components-button.is-primary:focus{
		background:color-mix(in srgb,var(--funky-admin-accent) 84%,var(--funky-admin-text))!important;
		border-color:color-mix(in srgb,var(--funky-admin-accent) 84%,var(--funky-admin-text))!important;
	}
}
body.wp-admin :where(input[type=checkbox]:checked,input[type=radio]:checked)::before{color:var(--funky-admin-accent)!important}
body.wp-admin :where(.interface-interface-skeleton__header,.interface-interface-skeleton__sidebar,.edit-post-layout__metaboxes,.editor-header){
	background:var(--funky-admin-surface-raised)!important;
	color:var(--funky-admin-text)!important;
}
body.wp-admin #screen-meta{
	background-color:var(--funky-admin-surface-raised)!important;
	border-color:var(--funky-admin-border)!important;
	color:var(--funky-admin-text)!important;
}
body.wp-admin #screen-meta-links{display:block!important}
body.wp-admin #screen-meta-links .screen-meta-toggle{
	display:block!important;
	font-family:var(--funky-admin-body-font)!important;
}
body.wp-admin #screen-meta-links .show-settings{
	background:var(--funky-admin-surface-raised)!important;
	border-color:var(--funky-admin-border)!important;
	color:var(--funky-admin-text)!important;
	font-family:var(--funky-admin-body-font)!important;
}
body.wp-admin #screen-meta-links .show-settings:focus{
	border-color:var(--funky-admin-accent)!important;
	box-shadow:0 0 0 1px var(--funky-admin-accent)!important;
}
/* WordPress clones these hidden templates into the active list table. */
body.wp-admin.edit-php #inlineedit{display:none!important}
body.wp-admin :where(.row-actions span.delete a,.row-actions span.trash a,.row-actions span.spam a,.submitbox .submitdelete){
	color:#b32d2e!important;
}
body.wp-admin :where(.description,.howto,.subsubsub,.row-actions,.components-base-control__help){color:var(--funky-admin-muted)!important}
';
}

/**
 * Load the generated theme throughout wp-admin.
 */
function funkycommerce_enqueue_admin_theme() {
	$settings = function_exists( 'funkycommerce_control_center_settings' )
		? funkycommerce_control_center_settings()
		: (array) get_option( 'funkycommerce_control_center', array() );
	if ( 'yes' !== ( $settings['admin_block_theme_styles'] ?? 'no' ) ) {
		return;
	}

	wp_enqueue_style( 'colors' );
	wp_add_inline_style( 'colors', funkycommerce_get_admin_theme_css() );
}
add_action( 'admin_enqueue_scripts', 'funkycommerce_enqueue_admin_theme', PHP_INT_MAX );

/**
 * Add the same selected colors and fonts to iframe-based block editor canvases.
 */
function funkycommerce_add_block_editor_theme_styles( $editor_settings ) {
	$tokens     = funkycommerce_get_admin_theme_tokens();
	$variables  = funkycommerce_get_admin_theme_variables_css( $tokens );
	$editor_css = $variables . '
:where(body){
	background-color:var(--funky-admin-background);
	color:var(--funky-admin-text);
	font-family:var(--funky-admin-body-font);
}
:where(h1,h2,h3,h4,h5,h6){
	color:var(--funky-admin-heading);
	font-family:var(--funky-admin-heading-font);
}
:where(a){color:var(--funky-admin-accent)}
:where(button,input[type=button],input[type=submit],.wp-element-button,.wp-block-button__link){
	font-family:var(--funky-admin-button-font);
}';

	if ( ! isset( $editor_settings['styles'] ) || ! is_array( $editor_settings['styles'] ) ) {
		$editor_settings['styles'] = array();
	}
	$editor_settings['styles'][] = array(
		'css'            => $editor_css,
		'__unstableType' => 'theme',
	);

	return $editor_settings;
}
add_filter( 'block_editor_settings_all', 'funkycommerce_add_block_editor_theme_styles' );

/**
 * Add a language selector to the native Code block and serialize a standard
 * language class for frontend syntax highlighting.
 */
function funkycommerce_enqueue_code_block_language_control() {
	$handle = 'funkycommerce-code-block-language';
	wp_register_script(
		$handle,
		false,
		array( 'wp-block-editor', 'wp-blocks', 'wp-components', 'wp-compose', 'wp-element', 'wp-hooks', 'wp-i18n' ),
		FUNKYCOMMERCE_HEADLESS_VERSION,
		true
	);
	wp_enqueue_script( $handle );
	wp_add_inline_script(
		$handle,
		 <<<'JS'
(function (wp) {
	if (!wp || !wp.blocks || !wp.hooks || !wp.blockEditor) return;
	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var languages = [
		{ label: wp.i18n.__('Plain text', 'funkycommerce'), value: '' },
		{ label: 'Bash / Shell', value: 'bash' },
		{ label: 'CSS', value: 'css' },
		{ label: 'HTML / XML', value: 'markup' },
		{ label: 'JavaScript', value: 'javascript' },
		{ label: 'JSON', value: 'json' },
		{ label: 'JSX', value: 'jsx' },
		{ label: 'PHP', value: 'php' },
		{ label: 'Python', value: 'python' },
		{ label: 'SQL', value: 'sql' },
		{ label: 'TypeScript', value: 'typescript' },
		{ label: 'TSX', value: 'tsx' }
	];
	var themes = [
		{ label: wp.i18n.__('Auto (site theme)', 'funkycommerce'), value: 'auto' },
		{ label: 'One Light', value: 'one-light' },
		{ label: 'One Dark', value: 'one-dark' },
		{ label: 'Dracula', value: 'dracula' },
		{ label: 'Duotone Light', value: 'duotone-light' },
		{ label: 'Duotone Dark', value: 'duotone-dark' },
		{ label: 'Prism Default', value: 'prism' },
		{ label: 'Prism Coy', value: 'coy' },
		{ label: 'Prism Dark', value: 'dark' },
		{ label: 'Prism Funky', value: 'funky' },
		{ label: 'Prism Okaidia', value: 'okaidia' },
		{ label: 'Prism Solarized Light', value: 'solarized-light' },
		{ label: 'Prism Tomorrow Night', value: 'tomorrow' },
		{ label: 'Prism Twilight', value: 'twilight' }
	];

	wp.hooks.addFilter('blocks.registerBlockType', 'funkycommerce/code-language-attribute', function (settings, name) {
		if (name !== 'core/code') return settings;
		return Object.assign({}, settings, {
			attributes: Object.assign({}, settings.attributes, {
				language: {
					type: 'string',
					source: 'attribute',
					selector: 'pre',
					attribute: 'data-code-language',
					default: ''
				},
				theme: {
					type: 'string',
					source: 'attribute',
					selector: 'pre',
					attribute: 'data-code-theme',
					default: 'auto'
				}
			})
		});
	});

	var withLanguageControl = wp.compose.createHigherOrderComponent(function (BlockEdit) {
		return function (props) {
			if (props.name !== 'core/code') return el(BlockEdit, props);
			return el(Fragment, null,
				el(BlockEdit, props),
				el(wp.blockEditor.InspectorControls, null,
					el(wp.components.PanelBody, {
						title: wp.i18n.__('Code highlighting', 'funkycommerce'),
						initialOpen: true
					}, el(wp.components.SelectControl, {
						label: wp.i18n.__('Language', 'funkycommerce'),
						value: props.attributes.language || '',
						options: languages,
						onChange: function (language) {
							props.setAttributes({ language: language });
						}
					}), el(wp.components.SelectControl, {
						label: wp.i18n.__('Color theme', 'funkycommerce'),
						value: props.attributes.theme || 'auto',
						options: themes,
						onChange: function (theme) {
							props.setAttributes({ theme: theme });
						}
					}))
				)
			);
		};
	}, 'withFunkycommerceCodeLanguageControl');
	wp.hooks.addFilter('editor.BlockEdit', 'funkycommerce/code-language-control', withLanguageControl);

	wp.hooks.addFilter('blocks.getSaveContent.extraProps', 'funkycommerce/code-language-markup', function (extraProps, blockType, attributes) {
		if (blockType.name !== 'core/code') return extraProps;
		var classNames = (extraProps.className || '').split(/\s+/).filter(function (name) {
			return name && name.indexOf('language-') !== 0;
		});
		if (attributes.language) classNames.push('language-' + attributes.language);
		var nextProps = Object.assign({}, extraProps, { className: classNames.join(' ') });
		if (attributes.language) nextProps['data-code-language'] = attributes.language;
		if (attributes.theme && attributes.theme !== 'auto') nextProps['data-code-theme'] = attributes.theme;
		return nextProps;
	});
}(window.wp));
JS
	);
}
add_action( 'enqueue_block_editor_assets', 'funkycommerce_enqueue_code_block_language_control' );
