<?php
/**
 * VideoObject schema type. Also feeds the video sitemap.
 *
 * @package NovaToolsSEO\Frontend\Schema\Types
 */

namespace NovaToolsSEO\Frontend\Schema\Types;

use NovaToolsSEO\Frontend\Schema\SchemaType;

defined( 'ABSPATH' ) || exit;

class VideoType extends SchemaType {

	/**
	 * {@inheritDoc}
	 */
	public function id() {
		return 'video';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label() {
		return __( 'Video', 'novatools-seo' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function fields() {
		return array(
			array(
				'name'     => 'name',
				'label'    => __( 'Video title', 'novatools-seo' ),
				'type'     => 'text',
				'required' => true,
			),
			array(
				'name'  => 'description',
				'label' => __( 'Description', 'novatools-seo' ),
				'type'  => 'textarea',
			),
			array(
				'name'     => 'thumbnail_url',
				'label'    => __( 'Thumbnail URL', 'novatools-seo' ),
				'type'     => 'url',
				'required' => true,
				'help'     => __( 'Required for rich results.', 'novatools-seo' ),
			),
			array(
				'name'  => 'content_url',
				'label' => __( 'Content URL', 'novatools-seo' ),
				'type'  => 'url',
				'help'  => __( 'Direct media file URL (MP4).', 'novatools-seo' ),
			),
			array(
				'name'  => 'embed_url',
				'label' => __( 'Embed URL', 'novatools-seo' ),
				'type'  => 'url',
				'help'  => __( 'Player iframe URL (e.g. YouTube embed).', 'novatools-seo' ),
			),
			array(
				'name'  => 'upload_date',
				'label' => __( 'Upload date', 'novatools-seo' ),
				'type'  => 'datetime',
				'help'  => __( 'ISO 8601, e.g. 2024-06-01T12:00. Defaults to post date.', 'novatools-seo' ),
			),
			array(
				'name'  => 'duration',
				'label' => __( 'Duration', 'novatools-seo' ),
				'type'  => 'duration',
				'help'  => __( 'ISO 8601, e.g. PT1M30S', 'novatools-seo' ),
			),
			array(
				'name'  => 'expires',
				'label' => __( 'Expires', 'novatools-seo' ),
				'type'  => 'datetime',
				'help'  => __( 'When the video becomes unavailable (optional).', 'novatools-seo' ),
			),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function build( array $data, $post_id ) {
		$name      = isset( $data['name'] ) ? trim( (string) $data['name'] ) : '';
		$thumbnail = isset( $data['thumbnail_url'] ) ? trim( (string) $data['thumbnail_url'] ) : '';

		if ( '' === $name || '' === $thumbnail ) {
			return array();
		}

		$schema = array(
			'@context'     => 'https://schema.org',
			'@type'        => 'VideoObject',
			'name'         => $name,
			'thumbnailUrl' => $thumbnail,
			'uploadDate'   => ! empty( $data['upload_date'] ) ? $data['upload_date'] : get_the_date( 'c', $post_id ),
		);

		$mapped = $this->apply_field_map(
			$data,
			array(
				'description' => 'description',
				'content_url' => 'contentUrl',
				'embed_url'   => 'embedUrl',
				'duration'    => 'duration',
				'expires'     => 'expires',
			)
		);
		$schema = array_merge( $schema, $mapped );

		return $schema;
	}
}
