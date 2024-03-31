/**
 * React hook that is used to mark the block wrapper element.
 * It provides all the necessary props like the class name.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-block-editor/#useblockprops
 */
import { useBlockProps } from '@wordpress/block-editor';

/**
 * The save function defines the way in which the different attributes should
 * be combined into the final markup, which is then serialized by the block
 * editor into `post_content`.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/#save
 *
 * @param {Object} props            Properties passed to the function.
 * @param {Object} props.attributes Available block attributes.
 *
 * @return {Element} Element to render.
 */
export default function save( { attributes } ) {
	const {
		isPath,
		start,
		end,
		geoJson,
		locationName,
		locationCoords,
		weather,
		weatherSlug,
		temperature,
		mapZoom
	} = attributes;

// return null to render in php
	return null;

	return (
		<div {...useBlockProps.save()}>
			<div id="" style={{width: "100%", height: "400px"}}></div>
			<div style={{textAlign: "center", display: "flex", alignItems: "center", justifyContent: "center", gap: "20px"}}>
				<div>
					<p style={{fontSize: "1.1em", fontWeight: "bold", marginRight: "20px"}}>
						{ locationName }
					</p>
				</div>
				<div>
                    <span style={{fontSize: "2em", margin: 0}}>
                        <img src={ weatherSlug } alt={ weather } style={{height: "1.6em", float: "left", marginRight: "10px"}}/>
						{ temperature }°C
                    </span>
				</div>
				<div>
					<p style={{margin: 0}}>
						{ weather }
					</p>
				</div>
			</div>
		</div>
	);
}
