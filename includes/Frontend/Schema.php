<?php

namespace NovaToolsSEO\Frontend;

use NovaToolsSEO\Core\BreadcrumbCollector;
use NovaToolsSEO\Traits\Base;

defined( 'ABSPATH' ) || exit;

class Schema {

	use Base;

	public function init() {
		add_action( 'wp_head', array( $this, 'output' ), 2 );
	}

	public function output() {
		$schemas = array();

		if ( is_front_page() || is_home() ) {
			$schemas[] = $this->get_website_schema();
			$schemas[] = $this->get_local_business_schema();
		}

		if ( is_singular( 'post' ) ) {
			$schemas[] = $this->get_article_schema();
		}

		if ( is_singular( 'page' ) ) {
			$schemas[] = $this->get_local_business_schema_for_page();
		}

		$schemas[] = $this->get_breadcrumb_schema();

		// Collect schemas registered by other classes (e.g. ProductSchema).
		$schemas = apply_filters( 'wseo_register_schemas', $schemas );

		// Filter out empty entries.
		$schemas = array_filter( $schemas );

		if ( empty( $schemas ) ) {
			return;
		}

		$schemas = array_values( $schemas );

		if ( 1 === count( $schemas ) ) {
			$output = $schemas[0];
		} else {
			// Strip @context from each schema — the @graph wrapper provides it.
			$graph_items = array_map( function ( $s ) {
				unset( $s['@context'] );
				return $s;
			}, $schemas );

			$output = array(
				'@context' => 'https://schema.org',
				'@graph'   => $graph_items,
			);
		}

		printf(
			'<script type="application/ld+json">%s</script>' . "\n",
			wp_json_encode( $output, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
		);
	}

	private function get_local_business_data() {
		$data = get_option( 'wseo_local_seo', array() );
		if ( empty( $data ) || ! is_array( $data ) ) {
			return array();
		}
		return $data;
	}

	public function get_local_business_schema() {
		$data = $this->get_local_business_data();
		if ( empty( $data['business_name'] ) ) {
			return array();
		}

		return $this->build_local_business_schema( $data );
	}

	private function get_local_business_schema_for_page() {
		$data = $this->get_local_business_data();
		if ( empty( $data['business_name'] ) ) {
			return array();
		}

		$post_id = get_queried_object_id();

		// Check contact page
		if ( ! empty( $data['contact_page_id'] ) && (int) $data['contact_page_id'] === $post_id ) {
			return $this->build_local_business_schema( $data );
		}

		// Check per-page meta checkbox
		$enabled = get_post_meta( $post_id, '_wseo_local_business', true );
		if ( $enabled ) {
			return $this->build_local_business_schema( $data );
		}

		return array();
	}

	private function build_local_business_schema( $data ) {
		$schema = array(
			'@context' => 'https://schema.org',
			'@type'    => ! empty( $data['business_type'] ) ? $data['business_type'] : 'LocalBusiness',
			'name'     => $data['business_name'],
		);

		// Address
		$address = array();
		if ( ! empty( $data['street_address'] ) ) {
			$address['streetAddress'] = $data['street_address'];
		}
		if ( ! empty( $data['city'] ) ) {
			$address['addressLocality'] = $data['city'];
		}
		if ( ! empty( $data['state_region'] ) ) {
			$address['addressRegion'] = $data['state_region'];
		}
		if ( ! empty( $data['postal_code'] ) ) {
			$address['postalCode'] = $data['postal_code'];
		}
		if ( ! empty( $data['country'] ) ) {
			$address['addressCountry'] = $data['country'];
		}
		if ( ! empty( $address ) ) {
			$address['@type'] = 'PostalAddress';
			$schema['address'] = $address;
		}

		// Contact
		if ( ! empty( $data['phone'] ) ) {
			$schema['telephone'] = $data['phone'];
		}
		if ( ! empty( $data['website_url'] ) ) {
			$schema['url'] = $data['website_url'];
		}
		if ( ! empty( $data['email'] ) ) {
			$schema['email'] = $data['email'];
		}

		// Geo
		if ( ! empty( $data['latitude'] ) && ! empty( $data['longitude'] ) ) {
			$schema['geo'] = array(
				'@type'     => 'GeoCoordinates',
				'latitude'  => $data['latitude'],
				'longitude' => $data['longitude'],
			);
		}

		// Opening hours
		if ( ! empty( $data['opening_hours'] ) && is_array( $data['opening_hours'] ) ) {
			$hours = array();
			foreach ( $data['opening_hours'] as $entry ) {
				if ( ! empty( $entry['closed'] ) ) {
					continue;
				}
				$hours[] = array(
					'@type'    => 'OpeningHoursSpecification',
					'dayOfWeek' => $entry['day_of_week'],
					'opens'    => $entry['opens'],
					'closes'   => $entry['closes'],
				);
			}

			// Holiday overrides
			if ( ! empty( $data['holiday_overrides'] ) && is_array( $data['holiday_overrides'] ) ) {
				foreach ( $data['holiday_overrides'] as $holiday ) {
					if ( empty( $holiday['date'] ) ) {
						continue;
					}
					if ( ! empty( $holiday['closed'] ) ) {
						$hours[] = array(
							'@type'        => 'OpeningHoursSpecification',
							'validFrom'    => $holiday['date'],
							'validThrough' => $holiday['date'],
						);
					} else {
						$hours[] = array(
							'@type'        => 'OpeningHoursSpecification',
							'validFrom'    => $holiday['date'],
							'validThrough' => $holiday['date'],
							'opens'        => ! empty( $holiday['opens'] ) ? $holiday['opens'] : '00:00',
							'closes'       => ! empty( $holiday['closes'] ) ? $holiday['closes'] : '23:59',
						);
					}
				}
			}

			if ( ! empty( $hours ) ) {
				$schema['openingHoursSpecification'] = $hours;
			}
		}

		// Area served
		if ( ! empty( $data['area_served'] ) && is_array( $data['area_served'] ) ) {
			$areas = array();
			foreach ( $data['area_served'] as $name ) {
				$type = preg_match( '/^\d+$/', $name ) ? 'PostalCode' : 'City';
				$areas[] = array(
					'@type' => $type,
					'name'  => $name,
				);
			}
			if ( ! empty( $areas ) ) {
				$schema['areaServed'] = $areas;
			}
		}

		// Image fallback
		$custom_logo_id = get_theme_mod( 'custom_logo' );
		if ( $custom_logo_id ) {
			$schema['image'] = wp_get_attachment_url( $custom_logo_id );
		}

		// sameAs entity linking
		if ( ! empty( $data['sameas'] ) && is_array( $data['sameas'] ) ) {
			$schema['sameAs'] = array_values( array_filter( $data['sameas'] ) );
		}

		// areaServed GeoShape polygon — merge with text-based areas if both exist
		if ( ! empty( $data['geoshape_coordinates'] ) && is_array( $data['geoshape_coordinates'] ) && count( $data['geoshape_coordinates'] ) >= 3 ) {
			$polygon_parts = array();
			foreach ( $data['geoshape_coordinates'] as $coord ) {
				if ( isset( $coord['lat'] ) && isset( $coord['lng'] ) ) {
					$polygon_parts[] = $coord['lat'] . ',' . $coord['lng'];
				}
			}
			if ( count( $polygon_parts ) >= 3 ) {
				$geoshape = array(
					'@type'   => 'GeoShape',
					'polygon' => implode( ' ', $polygon_parts ),
				);
				if ( isset( $schema['areaServed'] ) && is_array( $schema['areaServed'] ) ) {
					$schema['areaServed'][] = $geoshape;
				} else {
					$schema['areaServed'] = array( $geoshape );
				}
			}
		}

		// containedInPlace landmarks
		if ( ! empty( $data['landmarks'] ) && is_array( $data['landmarks'] ) ) {
			$places = array();
			foreach ( $data['landmarks'] as $landmark ) {
				if ( ! empty( $landmark['name'] ) ) {
					$place = array(
						'@type' => 'Place',
						'name'  => $landmark['name'],
					);
					if ( ! empty( $landmark['url'] ) ) {
						$place['url'] = $landmark['url'];
					}
					$places[] = $place;
				}
			}
			if ( ! empty( $places ) ) {
				$schema['containedInPlace'] = $places;
			}
		}

		return $schema;
	}

	private function get_website_schema() {
		return array(
			'@context' => 'https://schema.org',
			'@type'    => 'WebSite',
			'name'     => get_bloginfo( 'name' ),
			'url'      => home_url( '/' ),
			'potentialAction' => array(
				'@type'  => 'SearchAction',
				'target' => array(
					'@type'       => 'EntryPoint',
					'urlTemplate' => home_url( '/?s={search_term_string}' ),
				),
				'query-input' => 'required name=search_term_string',
			),
		);
	}

	private function get_article_schema() {
		$post_id = get_queried_object_id();
		$post = get_post( $post_id );

		$schema = array(
			'@context' => 'https://schema.org',
			'@type'    => is_singular( 'post' ) ? 'BlogPosting' : 'Article',
			'headline' => get_the_title( $post_id ),
			'datePublished' => get_the_date( 'c', $post_id ),
			'dateModified'  => get_the_modified_date( 'c', $post_id ),
			'author'   => array(
				'@type' => 'Person',
				'name'  => get_the_author_meta( 'display_name', $post->post_author ),
			),
			'publisher' => array(
				'@type' => 'Organization',
				'name'  => get_bloginfo( 'name' ),
			),
			'mainEntityOfPage' => array(
				'@type' => 'WebPage',
				'@id'   => get_permalink( $post_id ),
			),
		);

		$image = get_the_post_thumbnail_url( $post_id, 'full' );
		if ( $image ) {
			$schema['image'] = $image;
		}

		return $schema;
	}

	private function get_breadcrumb_schema() {
		$collector = new BreadcrumbCollector();
		$items = $collector->get_items();
		if ( empty( $items ) ) {
			return array();
		}

		$list_items = array();
		$position = 1;
		foreach ( $items as $item ) {
			$entry = array(
				'@type'    => 'ListItem',
				'position' => $position++,
				'name'     => $item['name'],
			);
			if ( ! empty( $item['url'] ) ) {
				$entry['item'] = $item['url'];
			}
			$list_items[] = $entry;
		}

		return array(
			'@context' => 'https://schema.org',
			'@type'    => 'BreadcrumbList',
			'itemListElement' => $list_items,
		);
	}
}
