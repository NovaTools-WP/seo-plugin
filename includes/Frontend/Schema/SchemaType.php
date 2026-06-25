<?php
/**
 * Abstract base for declarative schema types.
 *
 * A schema type declares its admin field definitions (which double as the
 * sanitization schema) and implements build() to turn stored data into a
 * schema.org JSON-LD array. The SchemaRegistry reads each enabled type from
 * the `_wseo_schema` post meta and appends the built arrays via the
 * `wseo_register_schemas` filter.
 *
 * @package NovaToolsSEO\Frontend\Schema
 */

namespace NovaToolsSEO\Frontend\Schema;

defined( 'ABSPATH' ) || exit;

abstract class SchemaType {

	/**
	 * Unique id used as the `_wseo_schema` array key.
	 *
	 * @return string
	 */
	abstract public function id();

	/**
	 * Human-readable label shown in the admin builder.
	 *
	 * @return string
	 */
	abstract public function label();

	/**
	 * Field definitions driving both the admin UI and server-side sanitization.
	 *
	 * Field types: text, textarea, url, number, select, duration, datetime,
	 * date, boolean, group (with optional `multiple` for repeatable cards).
	 *
	 * @return array
	 */
	abstract public function fields();

	/**
	 * Build the schema.org JSON-LD array (with @context/@type) — or an empty
	 * array when required fields are missing (the registry array_filter()s it).
	 *
	 * @param array   $data    Sanitized type data from `_wseo_schema`.
	 * @param int     $post_id Post id.
	 * @return array
	 */
	abstract public function build( array $data, $post_id );

	/**
	 * Whether this type should be available for the given post type.
	 *
	 * @param string $post_type Post type.
	 * @return bool
	 */
	public function enabled_for_post_type( $post_type ) {
		return true;
	}

	/**
	 * Sanitize raw input data against the field definitions.
	 *
	 * @param mixed $raw Raw data (expected array).
	 * @return array
	 */
	public function sanitize( $raw ) {
		if ( ! is_array( $raw ) ) {
			return array();
		}
		return $this->sanitize_fields( $raw, $this->fields() );
	}

	/**
	 * Walk a set of field definitions and sanitize the matching data keys.
	 *
	 * @param array $data   Data to sanitize.
	 * @param array $fields Field definitions.
	 * @return array
	 */
	protected function sanitize_fields( $data, $fields ) {
		$clean = array();

		foreach ( $fields as $field ) {
			$name = $field['name'] ?? '';
			if ( '' === $name || ! array_key_exists( $name, $data ) ) {
				continue;
			}

			if ( 'group' === ( $field['type'] ?? 'text' ) ) {
				$clean[ $name ] = $this->sanitize_group( $data[ $name ], $field );
			} else {
				$clean[ $name ] = $this->sanitize_value( $data[ $name ], $field );
			}
		}

		return $clean;
	}

	/**
	 * Sanitize a group field (repeatable or single object).
	 *
	 * @param mixed $value Raw value.
	 * @param array $field Field definition.
	 * @return array
	 */
	protected function sanitize_group( $value, $field ) {
		$sub_fields = $field['fields'] ?? array();

		if ( empty( $field['multiple'] ) ) {
			return is_array( $value ) ? $this->sanitize_fields( $value, $sub_fields ) : array();
		}

		if ( ! is_array( $value ) ) {
			return array();
		}

		$items = array();
		foreach ( array_values( $value ) as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$clean_entry = $this->sanitize_fields( $entry, $sub_fields );
			if ( $this->entry_has_data( $clean_entry ) ) {
				$items[] = $clean_entry;
			}
		}

		return $items;
	}

	/**
	 * Whether a sanitized group entry contains any meaningful data.
	 *
	 * @param array $entry Sanitized entry.
	 * @return bool
	 */
	protected function entry_has_data( $entry ) {
		foreach ( $entry as $v ) {
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

	/**
	 * Sanitize a single scalar field value.
	 *
	 * @param mixed $value Raw value.
	 * @param array $field Field definition.
	 * @return mixed
	 */
	protected function sanitize_value( $value, $field ) {
		$type = $field['type'] ?? 'text';

		if ( is_array( $value ) ) {
			return '';
		}

		$value = (string) $value;

		switch ( $type ) {
			case 'textarea':
				return sanitize_textarea_field( $value );

			case 'url':
				return '' === trim( $value ) ? '' : esc_url_raw( $value );

			case 'number':
				if ( '' === trim( $value ) ) {
					return '';
				}
				$num = ! empty( $field['integer'] ) ? (int) $value : (float) $value;
				if ( isset( $field['min'] ) ) {
					$num = max( (float) $field['min'], $num );
				}
				if ( isset( $field['max'] ) ) {
					$num = min( (float) $field['max'], $num );
				}
				return ! empty( $field['integer'] ) ? (int) $num : $num;

			case 'duration':
				$value = strtoupper( $value );
				$valid = preg_match( '/^P(\d+Y)?(\d+M)?(\d+D)?(T(\d+H)?(\d+M)?(\d+S)?)?$/', $value )
					&& preg_match( '/\d/', $value );
				return $valid ? $value : '';

			case 'datetime':
			case 'date':
				$ts = strtotime( $value );
				return $ts ? gmdate( 'c', $ts ) : '';

			case 'boolean':
				return $value ? '1' : '';

			case 'select':
				return in_array( $value, $this->select_options( $field ), true ) ? $value : '';

			case 'text':
			default:
				return sanitize_text_field( $value );
		}
	}

	/**
	 * Extract the allowed values from a select field's `options`.
	 *
	 * Supports both flat string lists and `{value,label}` object lists.
	 *
	 * @param array $field Field definition.
	 * @return string[]
	 */
	protected function select_options( $field ) {
		$allowed = array();
		foreach ( ( $field['options'] ?? array() ) as $opt ) {
			if ( is_array( $opt ) && isset( $opt['value'] ) ) {
				$allowed[] = (string) $opt['value'];
			} elseif ( is_scalar( $opt ) ) {
				$allowed[] = (string) $opt;
			}
		}
		return $allowed;
	}

	/**
	 * Helper: rename data keys to schema.org properties and drop empties.
	 *
	 * @param array $data Sanitized data.
	 * @param array $map  Map of `data_key => schema_property`.
	 * @return array
	 */
	protected function apply_field_map( $data, $map ) {
		$out = array();
		foreach ( $map as $key => $prop ) {
			if ( isset( $data[ $key ] ) && $this->not_empty( $data[ $key ] ) ) {
				$out[ $prop ] = $data[ $key ];
			}
		}
		return $out;
	}

	/**
	 * Whether a value is non-empty (scalar or array).
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	protected function not_empty( $value ) {
		if ( is_array( $value ) ) {
			return ! empty( $value );
		}
		return trim( (string) $value ) !== '';
	}
}
