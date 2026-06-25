<?php
/**
 * FAQ (FAQPage) schema type.
 *
 * @package NovaToolsSEO\Frontend\Schema\Types
 */

namespace NovaToolsSEO\Frontend\Schema\Types;

use NovaToolsSEO\Frontend\Schema\SchemaType;

defined( 'ABSPATH' ) || exit;

class FaqType extends SchemaType {

	/**
	 * {@inheritDoc}
	 */
	public function id() {
		return 'faq';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label() {
		return __( 'FAQ', 'novatools-seo' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function enabled_for_post_type( $post_type ) {
		// Products use the dedicated product FAQ builder + ProductSchema FAQPage.
		return 'product' !== $post_type;
	}

	/**
	 * {@inheritDoc}
	 */
	public function fields() {
		return array(
			array(
				'name'     => 'questions',
				'label'    => __( 'Questions & Answers', 'novatools-seo' ),
				'type'     => 'group',
				'multiple' => true,
				'fields'   => array(
					array(
						'name'     => 'question',
						'label'    => __( 'Question', 'novatools-seo' ),
						'type'     => 'text',
						'required' => true,
					),
					array(
						'name'     => 'answer',
						'label'    => __( 'Answer', 'novatools-seo' ),
						'type'     => 'textarea',
						'required' => true,
					),
				),
			),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function build( array $data, $post_id ) {
		$questions = isset( $data['questions'] ) && is_array( $data['questions'] ) ? $data['questions'] : array();

		$entities = array();
		foreach ( $questions as $q ) {
			$question = isset( $q['question'] ) ? trim( (string) $q['question'] ) : '';
			$answer   = isset( $q['answer'] ) ? trim( (string) $q['answer'] ) : '';

			if ( '' === $question || '' === $answer ) {
				continue;
			}

			$entities[] = array(
				'@type'          => 'Question',
				'name'           => $question,
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => $answer,
				),
			);
		}

		if ( empty( $entities ) ) {
			return array();
		}

		return array(
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'mainEntity' => $entities,
		);
	}
}
