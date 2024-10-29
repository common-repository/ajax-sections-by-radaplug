/**
 * Retrieves the translation of text.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-i18n/
 */
import { __ } from '@wordpress/i18n';
/**
 * React hook that is used to mark the block wrapper element.
 * It provides all the necessary props like the class name.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-block-editor/#useblockprops
 */
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl, ToggleControl, SelectControl } from '@wordpress/components';

import { useState } from 'react';
import { Component } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { ServerSideRender } from '@wordpress/server-side-render';

/**
 * Lets webpack process CSS, SASS or SCSS files referenced in JavaScript files.
 * Those files can contain any CSS code that gets applied to the editor.
 *
 * @see https://www.npmjs.com/package/@wordpress/scripts#using-css
 */
import './editor.scss';

/**
 * The edit function describes the structure of your block in the context of the
 * editor. This represents what the editor will render when the block is used.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/#edit
 *
 * @return {Element} Element to render.
 */
export default function Edit( { attributes, setAttributes, clientId } ) {
    const { post_type, post, radaplug_load_via_ajax, delay, button, spinner, radaplug_entrance_animation, radaplug_animation_duration } = attributes;

    if (typeof attributes.post_type == 'undefined') {
        attributes.post_type = 'post';
    }

    if (typeof attributes.post == 'undefined') {
        attributes.post = '';
    }

    if (typeof attributes.radaplug_load_via_ajax == 'undefined') {
        attributes.radaplug_load_via_ajax = true;
    }

if (typeof attributes.button == 'undefined') {
        attributes.button = '';
    }

    if (typeof attributes.delay == 'undefined') {
        attributes.delay = '';
    }

    if (typeof attributes.spinner == 'undefined') {
        attributes.spinner = true;
    }

    if (typeof attributes.blockId == 'undefined') {
        const blockGuteId = clientId;
        const blockAttrId = wp.data.select( 'core/block-editor' ).getSelectedBlockClientId();
    }

    const div = React.useRef( null );
    React.useEffect(
        () => {
            if( div.current ) {
                const radaplug_ajax_div = div.current;
                radaplug_ajax_div.setAttribute('type', attributes.post_type);
                radaplug_ajax_div.setAttribute('_load_via_ajax', attributes.radaplug_load_via_ajax);
                radaplug_ajax_div.setAttribute('delay', attributes.delay);
                radaplug_ajax_div.setAttribute('button', attributes.button);
                radaplug_ajax_div.setAttribute('spinner', attributes.spinner ? 'yes' : 'no');
                if (isNaN(attributes.post)) {
                    if (typeof radaplug_ajax_section == 'function') {
                        radaplug_ajax_section(attributes.post, 0, attributes.post_type, 0, function (response) {
                            radaplug_ajax_div.setAttribute('post', response.post_id);
                            if (typeof radaplug_scan_section == 'function') {
                                radaplug_scan_section(response.post_id);
                            }
                        });
                    }
                } else {
                    radaplug_ajax_div.setAttribute('post', attributes.post);
                    if (typeof radaplug_scan_section == 'function') {
                        radaplug_scan_section(attributes.post);
                    }
                }
            }
        }, 
    );
      
	return (
        <>
            <InspectorControls>
				<PanelBody title={ __( 'Settings', 'ajax-sections' ) }>
					<TextControl
                        label={ __(
                            'Post ID or Slug',
                            'ajax-sections'
                        ) }
                        help= 'Input the Post ID number to insert its content'
                        value={ attributes.post || '' }
                        onChange={ ( value ) =>
                            setAttributes( { post: value } )
                        }
                    />
                    <SelectControl
                        label={ __(
                            'Post Type',
                            'ajax-sections'
                        ) }
                        help= 'Select the Post Type that matches your Post ID (More types in Pro Version)'
                        value={ attributes.post_type || '' }
                        options={ [
                            { label: 'Wordpress Blog Post', value: 'post' },
                            { label: 'Gutenberg Pattern', value: 'wp_block' },
                        ] }
                        onChange={ ( value ) =>
                            setAttributes( { post_type: value } )
                        }
                        __nextHasNoMarginBottom
                    />
                    <ToggleControl
                        checked={ attributes.radaplug_load_via_ajax }
                        label={ __(
                            'Load Asynchronously via Ajax',
                            'ajax-sections'
                        ) }
                        onChange={ () =>
                            setAttributes( {
                                radaplug_load_via_ajax: ! attributes.radaplug_load_via_ajax,
                            } )
                        }
                    />
                { attributes.radaplug_load_via_ajax == 1 && (
					<TextControl
                        label={ __(
                            'Ajax Delay (ms)',
                            'ajax-sections'
                        ) }
                        help= 'Ajax request will start after this delay'
                        value={ attributes.delay || '' }
                        onChange={ ( value ) =>
                            setAttributes( { delay: value } )
                        }
                    />
                ) }
                { attributes.radaplug_load_via_ajax == 1 && (
                    <TextControl
                        label={ __(
                            'Ajax Button Text',
                            'ajax-sections'
                        ) }
                        help= 'Keep this empty if no button needed'
                        value={ attributes.button || '' }
                        onChange={ ( value ) =>
                            setAttributes( { button: value } )
                        }
                    />
                ) }
                { attributes.radaplug_load_via_ajax == 1 && (
                    <ToggleControl
                        checked={ attributes.spinner }
                        label={ __(
                            'Show Spinner While Loading',
                            'ajax-sections'
                        ) }
                        onChange={ () =>
                            setAttributes( {
                                spinner: ! attributes.spinner,
                            } )
                        }
                    />
                ) }
                </PanelBody>
				{ attributes.radaplug_load_via_ajax == 1 && (
                    <PanelBody title={ __( 'Motion Effects (Pro Version)', 'ajax-sections' ) }>
                        <SelectControl
                            label="Entrance Animation"
                            help= 'These effects will applly to the content after loading via ajax'
                            value={ attributes.radaplug_entrance_animation || '' }
                            options={ [
                                { label: 'Show', value: 'show' },
                                { label: 'Fade In', value: 'fadeIn' },
                                { label: 'Slide Down', value: 'slideDown' },
                            ] }
                            onChange={ ( value ) =>
                                setAttributes( { radaplug_entrance_animation: value } )
                            }
                            __nextHasNoMarginBottom
                        />
                        <TextControl
                            label={ __(
                                'Animation Duration (ms)',
                                'ajax-sections'
                            ) }
                            value={ attributes.radaplug_animation_duration || '' }
                            onChange={ ( value ) =>
                                setAttributes( { radaplug_animation_duration: value } )
                            }
                        />
                </PanelBody>
                ) }
            </InspectorControls>
			<div { ...useBlockProps() }>
				{ __(
					'Ajax Sections by Radaplug',
					'ajax-sections'
				) }
                <div id='radaplug-message-div'></div>
                <div ref={ div } class='radaplug-ajax-section' _page_builder='gutenberg'></div>
			</div>
        </>
	);
}
