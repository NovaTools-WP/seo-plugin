<?php

namespace NovaToolsSEO\Admin;

use NovaToolsSEO\Traits\Base;

class BrandSameAs {

	use Base;

	public function init() {
		$taxonomies = $this->get_brand_taxonomies();

		foreach ( $taxonomies as $taxonomy ) {
			add_action( "{$taxonomy}_edit_form_fields", array( $this, 'render_fields' ), 10, 2 );
			add_action( "edited_{$taxonomy}", array( $this, 'save' ) );
			add_action( "created_{$taxonomy}", array( $this, 'save' ) );
		}
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
				<script>
				(function(){
					var container = document.getElementById('wseo-sameas-rows');
					var addBtn = document.getElementById('wseo-sameas-add');
					addBtn.addEventListener('click', function(){
						var row = document.createElement('div');
						row.className = 'wseo-sameas-row';
						row.style.cssText = 'display:flex;gap:4px;margin-bottom:4px;';
						row.innerHTML = '<input type="url" name="_wseo_sameas[]" class="regular-text" style="flex:1;" placeholder="https://..." /><button type="button" class="button wseo-sameas-remove" style="color:#a00;">&times;</button>';
						container.appendChild(row);
					});
					container.addEventListener('click', function(e){
						if(e.target.classList.contains('wseo-sameas-remove')){
							e.target.parentElement.remove();
						}
					});
				})();
				</script>
			</td>
		</tr>
		<?php
	}

	public function save( $term_id ) {
		if ( ! current_user_can( 'edit_term', $term_id ) ) {
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
