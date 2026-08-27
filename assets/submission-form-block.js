( function ( blocks, blockEditor, components, element, i18n ) {
	const el = element.createElement;
	const TextControl = components.TextControl;
	const ToggleControl = components.ToggleControl;
	const InspectorControls = blockEditor.InspectorControls;
	const PanelBody = components.PanelBody;

	blocks.registerBlockType( 'funkycommerce/submission-form', {
		title: i18n.__( 'FunkyCommerce Form', 'funkycommerce-headless' ),
		icon: 'feedback',
		category: 'widgets',
		description: i18n.__( 'A backend-stored contact form with optional protected attachments.', 'funkycommerce-headless' ),
		edit: function ( props ) {
			const attributes = props.attributes;
			const set = props.setAttributes;
			return el(
				element.Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: i18n.__( 'Form settings', 'funkycommerce-headless' ) },
						el( TextControl, { label: i18n.__( 'Form ID', 'funkycommerce-headless' ), value: attributes.formId, onChange: function ( value ) { set( { formId: value } ); } } ),
						el( TextControl, { label: i18n.__( 'Form name', 'funkycommerce-headless' ), value: attributes.formName, onChange: function ( value ) { set( { formName: value } ); } } ),
						el( ToggleControl, { label: i18n.__( 'Allow attachments', 'funkycommerce-headless' ), checked: attributes.uploads, onChange: function ( value ) { set( { uploads: value } ); } } )
					)
				),
				el(
					'div',
					{ className: props.className },
					el( TextControl, { label: i18n.__( 'Heading', 'funkycommerce-headless' ), value: attributes.title, onChange: function ( value ) { set( { title: value } ); } } ),
					el( 'p', null, i18n.__( 'The storefront renders Name, Email, Message, and optional attachment fields.', 'funkycommerce-headless' ) )
				)
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n );
