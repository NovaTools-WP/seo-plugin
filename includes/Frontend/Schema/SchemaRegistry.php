<?php
/**
 * Schema type registry.
 *
 * Holds the built-in schema types, hooks `wseo_register_schemas` to append
 * the JSON-LD for each type a post has enabled in `_wseo_schema`, and exposes
 * the field configuration (for the admin builder) plus a shared sanitization
 * entry point used by the REST + classic-editor save paths.
 *
 * @package NovaToolsSEO\Frontend\Schema
 */

namespace NovaToolsSEO\Frontend\Schema;

use NovaToolsSEO\Frontend\Schema\Types\FaqType;
use NovaToolsSEO\Frontend\Schema\Types\HowToType;
use NovaToolsSEO\Frontend\Schema\Types\RecipeType;
use NovaToolsSEO\Frontend\Schema\Types\VideoType;
use NovaToolsSEO\Traits\Base;

defined( 'ABSPATH' ) || exit;

class SchemaRegistry {

	use Base;

	/**
	 * Registered schema types keyed by id.
	 *
	 * @var SchemaType[]
	 */
	private $types = array();

	/**
	 * Register default types and the schema output filter.
	 *
	 * @return void
	 */
	public function init() {
		$this->register_defaults();
		add_filter( 'wseo_register_schemas', array( $this, 'register_schemas' ) );
	}

	/**
	 * Instantiate the built-in schema types.
	 *
	 * @return void
	 */
	protected function register_defaults() {
		if ( ! empty( $this->types ) ) {
			return;
		}

		$types = array(
			new FaqType(),
			new HowToType(),
			new RecipeType(),
			new VideoType(),
		);

		foreach ( $types as $type ) {
			$this->types[ $type->id() ] = $type;
		}
	}

	/**
	 * Append JSON-LD for each schema type the queried post has enabled.
	 *
	 * @param array $schemas Existing schemas.
	 * @return array
	 */
	public function register_schemas( $schemas ) {
		if ( ! is_singular() ) {
			return $schemas;
		}

		$post_id   = get_queried_object_id();
		$post_type = get_post_type();

		if ( ! $post_id ) {
			return $schemas;
		}

		$raw = get_post_meta( $post_id, '_wseo_schema', true );
		if ( ! is_array( $raw ) || empty( $raw ) ) {
			return $schemas;
		}

		foreach ( $this->types as $type ) {
			if ( ! isset( $raw[ $type->id() ] ) ) {
				continue;
			}
			if ( ! $type->enabled_for_post_type( $post_type ) ) {
				continue;
			}

			$built = $type->build( $raw[ $type->id() ], $post_id );
			if ( ! empty( $built ) ) {
				$schemas[] = $built;
			}
		}

		return $schemas;
	}

	/**
	 * Sanitize the full `_wseo_schema` payload for storage.
	 *
	 * Drops unknown types and types that contain no meaningful data.
	 *
	 * @param mixed $raw Raw payload.
	 * @return array
	 */
	public static function sanitize_for_storage( $raw ) {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$registry = self::get_instance();
		if ( empty( $registry->types ) ) {
			$registry->register_defaults();
		}

		$clean = array();
		foreach ( $raw as $id => $data ) {
			if ( ! isset( $registry->types[ $id ] ) ) {
				continue;
			}

			$sanitized = $registry->types[ $id ]->sanitize( $data );
			if ( $registry->type_has_data( $sanitized ) ) {
				$clean[ $id ] = $sanitized;
			}
		}

		return $clean;
	}

	/**
	 * Build the admin UI configuration for the React builder.
	 *
	 * Optionally filtered by post type so the UI only shows applicable types.
	 *
	 * @param string $post_type Optional post type to filter by.
	 * @return array
	 */
	public function get_config( $post_type = '' ) {
		if ( empty( $this->types ) ) {
			$this->register_defaults();
		}

		$config = array();
		foreach ( $this->types as $type ) {
			if ( '' !== $post_type && ! $type->enabled_for_post_type( $post_type ) ) {
				continue;
			}

			$config[] = array(
				'id'     => $type->id(),
				'label'  => $type->label(),
				'fields' => $type->fields(),
			);
		}

		return $config;
	}

	/**
	 * Get a registered schema type by id.
	 *
	 * @param string $id Type id.
	 * @return SchemaType|null
	 */
	public function get_type( $id ) {
		if ( empty( $this->types ) ) {
			$this->register_defaults();
		}
		return $this->types[ $id ] ?? null;
	}

	/**
	 * Whether a sanitized type payload contains any meaningful data.
	 *
	 * @param array $data Sanitized data.
	 * @return bool
	 */
	protected function type_has_data( $data ) {
		if ( ! is_array( $data ) ) {
			return false;
		}

		foreach ( $data as $v ) {
			if ( is_array( $v ) ) {
				if ( ! empty( $v ) ) {
					return true;
				}
			} elseif ( trim( (string) $v ) !== '' ) {
				return true;
			}
		}

		return false;
	}
}
