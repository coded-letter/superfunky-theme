( function ( blocks, blockEditor, components, element, i18n, serverSideRender ) {
	var el = element.createElement;
	var InspectorControls = blockEditor.InspectorControls;
	var TextControl = components.TextControl;
	var TextareaControl = components.TextareaControl;
	var RangeControl = components.RangeControl;
	var ToggleControl = components.ToggleControl;
	var SelectControl = components.SelectControl;

	blocks.registerBlockType( 'funkycommerce/video-hero', {
		apiVersion: 3,
		title: i18n.__( 'Video hero/banner', 'funkycommerce-headless' ),
		icon: 'format-video',
		category: 'design',
		attributes: {
			src: { type: 'string', default: '' },
			variant: { type: 'string', default: 'fullbleed' },
			poster: { type: 'string', default: '' },
			kicker: { type: 'string', default: '' },
			title: { type: 'string', default: 'Video hero' },
			description: { type: 'string', default: '' },
			primaryCtaLabel: { type: 'string', default: '' },
			primaryCtaHref: { type: 'string', default: '' },
			secondaryCtaLabel: { type: 'string', default: '' },
			secondaryCtaHref: { type: 'string', default: '' },
			align: { type: 'string', default: 'left' },
			height: { type: 'string', default: '70vh' },
			overlayOpacity: { type: 'number', default: 55 },
			autoplay: { type: 'boolean', default: true },
			loop: { type: 'boolean', default: true },
			muted: { type: 'boolean', default: true }
		},
		edit: function ( props ) {
			var a = props.attributes;
			function field( name ) {
				return function ( value ) {
					var update = {};
					update[ name ] = value;
					props.setAttributes( update );
				};
			}
			return el( element.Fragment, {},
				el( InspectorControls, {},
					el( components.PanelBody, { title: i18n.__( 'Media', 'funkycommerce-headless' ), initialOpen: true },
						el( TextControl, { label: i18n.__( 'Video URL (MP4, WebM, YouTube, or Vimeo)', 'funkycommerce-headless' ), value: a.src, onChange: field( 'src' ) } ),
						el( TextControl, { label: i18n.__( 'Poster/fallback image URL', 'funkycommerce-headless' ), value: a.poster, onChange: field( 'poster' ) } ),
						el( ToggleControl, { label: i18n.__( 'Autoplay', 'funkycommerce-headless' ), checked: a.autoplay, onChange: field( 'autoplay' ) } ),
						el( ToggleControl, { label: i18n.__( 'Loop', 'funkycommerce-headless' ), checked: a.loop, onChange: field( 'loop' ) } ),
						el( ToggleControl, { label: i18n.__( 'Muted', 'funkycommerce-headless' ), checked: a.muted, onChange: field( 'muted' ) } )
					),
					el( components.PanelBody, { title: i18n.__( 'Content and appearance', 'funkycommerce-headless' ) },
						el( SelectControl, { label: i18n.__( 'View', 'funkycommerce-headless' ), value: a.variant, options: [ { label: 'Full bleed', value: 'fullbleed' }, { label: 'Atmospheric glow', value: 'glow' }, { label: 'Split media/content', value: 'split' }, { label: 'Minimal editorial', value: 'minimal' }, { label: 'Compact strip', value: 'strip' } ], onChange: field( 'variant' ) } ),
						el( TextControl, { label: i18n.__( 'Kicker', 'funkycommerce-headless' ), value: a.kicker, onChange: field( 'kicker' ) } ),
						el( TextControl, { label: i18n.__( 'Heading', 'funkycommerce-headless' ), value: a.title, onChange: field( 'title' ) } ),
						el( TextareaControl, { label: i18n.__( 'Description', 'funkycommerce-headless' ), value: a.description, onChange: field( 'description' ) } ),
						el( TextControl, { label: i18n.__( 'Primary button label', 'funkycommerce-headless' ), value: a.primaryCtaLabel, onChange: field( 'primaryCtaLabel' ) } ),
						el( TextControl, { label: i18n.__( 'Primary button URL', 'funkycommerce-headless' ), value: a.primaryCtaHref, onChange: field( 'primaryCtaHref' ) } ),
						el( TextControl, { label: i18n.__( 'Secondary button label', 'funkycommerce-headless' ), value: a.secondaryCtaLabel, onChange: field( 'secondaryCtaLabel' ) } ),
						el( TextControl, { label: i18n.__( 'Secondary button URL', 'funkycommerce-headless' ), value: a.secondaryCtaHref, onChange: field( 'secondaryCtaHref' ) } ),
						el( SelectControl, { label: i18n.__( 'Text alignment', 'funkycommerce-headless' ), value: a.align, options: [ { label: 'Left', value: 'left' }, { label: 'Center', value: 'center' }, { label: 'Right', value: 'right' } ], onChange: field( 'align' ) } ),
						el( TextControl, { label: i18n.__( 'Minimum height', 'funkycommerce-headless' ), value: a.height, onChange: field( 'height' ) } ),
						el( RangeControl, { label: i18n.__( 'Overlay opacity', 'funkycommerce-headless' ), value: a.overlayOpacity, min: 0, max: 90, onChange: field( 'overlayOpacity' ) } )
					)
				),
				el( serverSideRender, { block: 'funkycommerce/video-hero', attributes: a } )
			);
		},
		save: function () { return null; }
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n, window.wp.serverSideRender );
