<?php

namespace NovaToolsSEO\Admin;

use NovaToolsSEO\Traits\Base;

defined( 'ABSPATH' ) || exit;

class BrandSameAs {

	use Base;

	public function init() {
		$taxonomies = $this->get_brand_taxonomies();

		foreach ( $taxonomies as $taxonomy ) {
			add_action( "{$taxonomy}_edit_form_fields", array( $this, 'render_fields' ), 10, 2 );
			add_action( "edited_{$taxonomy}", array( $this, 'save' ) );
			add_action( "created_{$taxonomy}", array( $this, 'save' ) );
		}

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	public function enqueue_scripts( $hook ) {
		if ( 'term.php' !== $hook ) {
			return;
		}

		wp_enqueue_script(
			'wseo-brand-sameas',
			plugin_dir_url( dirname( __DIR__, 2 ) ) . 'assets/admin/js/brand-sameas.js',
			array(),
			NOVATOOLS_SEO_VERSION,
			true
		);
	}

	private function get_brand_taxonomies() {
		$taxonomies = array();

		if ( taxonomy_exists( 'product_brand' ) ) {
			$taxonomies[] = 'product_brand';
		}

		if ( taxonomy_exists( 'brand' ) ) {
			$taxonomies[] = 'brand';
		}

		return apply_filters( 'wseo_brand_taxonomies', $taxonomies );
	}

	public function render_fields( $tag ) {
		$urls = get_term_meta( $tag->term_id, '_wseo_sameas', true );
		$urls = is_array( $urls ) ? $urls : array();
		wp_nonce_field( 'novatools_seo_save_brand_sameas', 'novatools_seo_brand_sameas_nonce' );
		?>
		<tr class="form-field">
			<th scope="row"><label for="wseo-sameas"><?php esc_html_e( 'Entity URLs (sameAs)', 'novatools-seo' ); ?></label></th>
			<td>
				<div id="wseo-sameas-rows">
					<?php foreach ( $urls as $i => $url ) : ?>
						<div class="wseo-sameas-row" style="display:flex;gap:4px;margin-bottom:4px;">
							<input type="url" name="_wseo_sameas[]" value="<?php echo esc_url( $url ); ?>" class="regular-text" style="flex:1;" placeholder="https://en.wikipedia.org/wiki/..." />
							<button type="button" class="button wseo-sameas-remove" style="color:#a00;">&times;</button>
						</div>
					<?php endforeach; ?>
				</div>
				<button type="button" class="button" id="wseo-sameas-add"><?php esc_html_e( '+ Add URL', 'novatools-seo' ); ?></button>
				<p class="description"><?php esc_html_e( 'Links to authoritative entity pages (Wikipedia, Wikidata, Google Business, Facebook, LinkedIn, etc.).', 'novatools-seo' ); ?></p>
			</td>
		</tr>
		<?php
	}

	public function save( $term_id ) {
		if ( ! current_user_can( 'edit_term', $term_id ) ) {
			return;
		}

		if ( ! isset( $_POST['novatools_seo_brand_sameas_nonce'] ) || ! wp_verify_nonce( $_POST['novatools_seo_brand_sameas_nonce'], 'novatools_seo_save_brand_sameas' ) ) {
			return;
		}

		$urls = isset( $_POST['_wseo_sameas'] ) ? $_POST['_wseo_sameas'] : array();

		$sanitized = array();
		foreach ( $urls as $url ) {
			$url = esc_url_raw( $url );
			if ( $url ) {
				$sanitized[] = $url;
			}
		}

		update_term_meta( $term_id, '_wseo_sameas', $sanitized );
	}
}
