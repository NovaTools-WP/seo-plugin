<?php
/**
 * HowTo schema type.
 *
 * @package NovaToolsSEO\Frontend\Schema\Types
 */

namespace NovaToolsSEO\Frontend\Schema\Types;

use NovaToolsSEO\Frontend\Schema\SchemaType;

defined( 'ABSPATH' ) || exit;

class HowToType extends SchemaType {

	/**
	 * {@inheritDoc}
	 */
	public function id() {
		return 'howto';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label() {
		return __( 'How-To', 'novatools-seo' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function fields() {
		return array(
			array(
				'name'     => 'name',
				'label'    => __( 'Title', 'novatools-seo' ),
				'type'     => 'text',
				'required' => true,
				'help'     => __( 'Name of the how-to.', 'novatools-seo' ),
			),
			array(
				'name'  => 'description',
				'label' => __( 'Description', 'novatools-seo' ),
				'type'  => 'textarea',
			),
			array(
				'name'  => 'total_time',
				'label' => __( 'Total time', 'novatools-seo' ),
				'type'  => 'duration',
				'help'  => __( 'ISO 8601 duration, e.g. PT30M', 'novatools-seo' ),
			),
			array(
				'name'  => 'estimated_cost',
				'label' => __( 'Estimated cost', 'novatools-seo' ),
				'type'  => 'text',
				'help'  => __( 'Free-form, e.g. "USD 10".', 'novatools-seo' ),
			),
			array(
				'name'           => 'steps',
				'label'          => __( 'Steps', 'novatools-seo' ),
				'singular_label' => __( 'Step', 'novatools-seo' ),
				'type'           => 'group',
				'multiple'       => true,
				'fields'         => array(
					array(
						'name'  => 'name',
						'label' => __( 'Step title', 'novatools-seo' ),
						'type'  => 'text',
					),
					array(
						'name'     => 'text',
						'label'    => __( 'Instructions', 'novatools-seo' ),
						'type'     => 'textarea',
						'required' => true,
					),
				),
			),
			array(
				'name'           => 'tools',
				'label'          => __( 'Tools', 'novatools-seo' ),
				'singular_label' => __( 'Tool', 'novatools-seo' ),
				'type'           => 'group',
				'multiple'       => true,
				'fields'         => array(
					array(
						'name'  => 'name',
						'label' => __( 'Tool', 'novatools-seo' ),
						'type'  => 'text',
					),
				),
			),
			array(
				'name'           => 'supply',
				'label'          => __( 'Supplies', 'novatools-seo' ),
				'singular_label' => __( 'Supply', 'novatools-seo' ),
				'type'           => 'group',
				'multiple'       => true,
				'fields'         => array(
					array(
						'name'  => 'name',
						'label' => __( 'Supply', 'novatools-seo' ),
						'type'  => 'text',
					),
				),
			),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function build( array $data, $post_id ) {
		$name = isset( $data['name'] ) ? trim( (string) $data['name'] ) : '';
		if ( '' === $name ) {
			return array();
		}

		$steps_raw = isset( $data['steps'] ) && is_array( $data['steps'] ) ? $data['steps'] : array();
		$steps     = array();
		foreach ( $steps_raw as $s ) {
			$text = isset( $s['text'] ) ? trim( (string) $s['text'] ) : '';
			if ( '' === $text ) {
				continue;
			}
			$step = array(
				'@type' => 'HowToStep',
				'text'  => $text,
			);
			$sname = isset( $s['name'] ) ? trim( (string) $s['name'] ) : '';
			if ( '' !== $sname ) {
				$step['name'] = $sname;
			}
			$steps[] = $step;
		}

		// HowTo requires at least one step.
		if ( empty( $steps ) ) {
			return array();
		}

		$schema = array(
			'@context' => 'https://schema.org',
			'@type'    => 'HowTo',
			'name'     => $name,
			'step'     => $steps,
		);

		if ( ! empty( $data['description'] ) ) {
			$schema['description'] = $data['description'];
		}
		if ( ! empty( $data['total_time'] ) ) {
			$schema['totalTime'] = $data['total_time'];
		}
		if ( ! empty( $data['estimated_cost'] ) ) {
			$schema['estimatedCost'] = array(
				'@type' => 'MonetaryAmount',
				'text'  => $data['estimated_cost'],
			);
		}

		$tools = $this->collect_names( $data['tools'] ?? array() );
		if ( ! empty( $tools ) ) {
			$schema['tool'] = array_map( function ( $n ) {
				return array( '@type' => 'HowToTool', 'name' => $n );
			}, $tools );
		}

		$supplies = $this->collect_names( $data['supply'] ?? array() );
		if ( ! empty( $supplies ) ) {
			$schema['supply'] = array_map( function ( $n ) {
				return array( '@type' => 'HowToSupply', 'name' => $n );
			}, $supplies );
		}

		if ( has_post_thumbnail( $post_id ) ) {
			$schema['image'] = get_the_post_thumbnail_url( $post_id, 'full' );
		}

		return $schema;
	}

	/**
	 * Extract non-empty `name` values from a group of name-only entries.
	 *
	 * @param array $items Group items.
	 * @return string[]
	 */
	protected function collect_names( $items ) {
		if ( ! is_array( $items ) ) {
			return array();
		}
		$out = array();
		foreach ( $items as $item ) {
			$name = isset( $item['name'] ) ? trim( (string) $item['name'] ) : '';
			if ( '' !== $name ) {
				$out[] = $name;
			}
		}
		return $out;
	}
}
