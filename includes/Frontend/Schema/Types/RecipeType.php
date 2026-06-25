<?php
/**
 * Recipe schema type.
 *
 * @package NovaToolsSEO\Frontend\Schema\Types
 */

namespace NovaToolsSEO\Frontend\Schema\Types;

use NovaToolsSEO\Frontend\Schema\SchemaType;

defined( 'ABSPATH' ) || exit;

class RecipeType extends SchemaType {

	/**
	 * {@inheritDoc}
	 */
	public function id() {
		return 'recipe';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label() {
		return __( 'Recipe', 'novatools-seo' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function fields() {
		return array(
			array(
				'name'     => 'name',
				'label'    => __( 'Recipe name', 'novatools-seo' ),
				'type'     => 'text',
				'required' => true,
			),
			array(
				'name'  => 'description',
				'label' => __( 'Description', 'novatools-seo' ),
				'type'  => 'textarea',
			),
			array(
				'name'  => 'image',
				'label' => __( 'Image URL', 'novatools-seo' ),
				'type'  => 'url',
				'help'  => __( 'Falls back to the featured image.', 'novatools-seo' ),
			),
			array(
				'name'  => 'prep_time',
				'label' => __( 'Prep time', 'novatools-seo' ),
				'type'  => 'duration',
				'help'  => __( 'ISO 8601 duration, e.g. PT15M', 'novatools-seo' ),
			),
			array(
				'name'  => 'cook_time',
				'label' => __( 'Cook time', 'novatools-seo' ),
				'type'  => 'duration',
				'help'  => __( 'ISO 8601 duration, e.g. PT45M', 'novatools-seo' ),
			),
			array(
				'name'  => 'total_time',
				'label' => __( 'Total time', 'novatools-seo' ),
				'type'  => 'duration',
				'help'  => __( 'ISO 8601 duration, e.g. PT1H', 'novatools-seo' ),
			),
			array(
				'name'  => 'recipe_yield',
				'label' => __( 'Yield', 'novatools-seo' ),
				'type'  => 'text',
				'help'  => __( 'e.g. "4 servings"', 'novatools-seo' ),
			),
			array(
				'name'  => 'recipe_category',
				'label' => __( 'Category', 'novatools-seo' ),
				'type'  => 'text',
				'help'  => __( 'e.g. "Dessert"', 'novatools-seo' ),
			),
			array(
				'name'  => 'recipe_cuisine',
				'label' => __( 'Cuisine', 'novatools-seo' ),
				'type'  => 'text',
			),
			array(
				'name'  => 'keywords',
				'label' => __( 'Keywords', 'novatools-seo' ),
				'type'  => 'text',
				'help'  => __( 'Comma-separated.', 'novatools-seo' ),
			),
			array(
				'name'  => 'nutrition_calories',
				'label' => __( 'Calories', 'novatools-seo' ),
				'type'  => 'number',
				'help'  => __( 'Per serving (kcal).', 'novatools-seo' ),
			),
			array(
				'name'           => 'ingredients',
				'label'          => __( 'Ingredients', 'novatools-seo' ),
				'singular_label' => __( 'Ingredient', 'novatools-seo' ),
				'type'           => 'group',
				'multiple'       => true,
				'fields'         => array(
					array(
						'name'     => 'name',
						'label'    => __( 'Ingredient', 'novatools-seo' ),
						'type'     => 'text',
						'required' => true,
					),
				),
			),
			array(
				'name'           => 'instructions',
				'label'          => __( 'Instructions', 'novatools-seo' ),
				'singular_label' => __( 'Step', 'novatools-seo' ),
				'type'           => 'group',
				'multiple'       => true,
				'fields'         => array(
					array(
						'name'     => 'text',
						'label'    => __( 'Step', 'novatools-seo' ),
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
		$name = isset( $data['name'] ) ? trim( (string) $data['name'] ) : '';
		if ( '' === $name ) {
			return array();
		}

		$ingredients = $this->collect_group_values( $data['ingredients'] ?? array(), 'name' );
		$instructions = $this->collect_group_values( $data['instructions'] ?? array(), 'text' );

		// Recipe rich results require ingredients + instructions.
		if ( empty( $ingredients ) || empty( $instructions ) ) {
			return array();
		}

		$schema = array(
			'@context'          => 'https://schema.org',
			'@type'             => 'Recipe',
			'name'              => $name,
			'recipeIngredient'  => $ingredients,
			'recipeInstructions' => array_map( function ( $text ) {
				return array( '@type' => 'HowToStep', 'text' => $text );
			}, $instructions ),
		);

		// Image — explicit URL or featured-image fallback.
		$image = isset( $data['image'] ) ? trim( (string) $data['image'] ) : '';
		if ( '' === $image && has_post_thumbnail( $post_id ) ) {
			$image = get_the_post_thumbnail_url( $post_id, 'full' );
		}
		if ( '' !== $image ) {
			$schema['image'] = $image;
		}

		$mapped = $this->apply_field_map(
			$data,
			array(
				'description'     => 'description',
				'prep_time'       => 'prepTime',
				'cook_time'       => 'cookTime',
				'total_time'      => 'totalTime',
				'recipe_yield'    => 'recipeYield',
				'recipe_category' => 'recipeCategory',
				'recipe_cuisine'  => 'recipeCuisine',
				'keywords'        => 'keywords',
			)
		);
		$schema = array_merge( $schema, $mapped );

		if ( isset( $data['nutrition_calories'] ) && '' !== trim( (string) $data['nutrition_calories'] ) ) {
			$schema['nutrition'] = array(
				'@type'    => 'NutritionInformation',
				'calories' => $data['nutrition_calories'] . ' kcal',
			);
		}

		$post = get_post( $post_id );
		if ( $post ) {
			$schema['author']       = array(
				'@type' => 'Person',
				'name'  => get_the_author_meta( 'display_name', $post->post_author ),
			);
			$schema['datePublished'] = get_the_date( 'c', $post_id );
		}

		return $schema;
	}

	/**
	 * Collect non-empty scalar values from a group by a given key.
	 *
	 * @param array  $items Group items.
	 * @param string $key   Key to extract.
	 * @return string[]
	 */
	protected function collect_group_values( $items, $key ) {
		if ( ! is_array( $items ) ) {
			return array();
		}
		$out = array();
		foreach ( $items as $item ) {
			$val = isset( $item[ $key ] ) ? trim( (string) $item[ $key ] ) : '';
			if ( '' !== $val ) {
				$out[] = $val;
			}
		}
		return $out;
	}
}
