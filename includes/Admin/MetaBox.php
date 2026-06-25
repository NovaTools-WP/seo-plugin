<?php

namespace NovaToolsSEO\Admin;

use NovaToolsSEO\Traits\Base;
use NovaToolsSEO\Core\MetaKeys;
use NovaToolsSEO\Core\Tokens;
use NovaToolsSEO\Core\DependencyCheck;
use NovaToolsSEO\Frontend\Schema\SchemaRegistry;
use NovaToolsSEO\Admin\Settings;
use NovaToolsSEO\Libs\Assets;
use NovaToolsSEO\Assets\Admin as AssetsAdmin;

defined( 'ABSPATH' ) || exit;

class MetaBox {

	use Base;

	public function init() {
		add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ) );
		add_action( 'save_post', array( $this, 'save_meta' ), 10, 2 );
		foreach ( get_taxonomies( array( 'public' => true ) ) as $taxonomy ) {
			add_action( "{$taxonomy}_edit_form_fields", array( $this, 'render_term_meta_box' ), 10, 2 );
			add_action( "edited_{$taxonomy}", array( $this, 'save_term_meta' ) );
		}
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_meta_box_scripts' ) );
	}

	public function register_meta_boxes() {
		$post_types = get_post_types( array( 'public' => true ) );
		foreach ( $post_types as $post_type ) {
			add_meta_box(
				'novatools-seo-meta-box',
				__( 'SEO', 'novatools-seo' ),
				array( $this, 'render_meta_box' ),
				$post_type,
				'normal',
				'high'
			);
		}
	}

	public function render_meta_box( $post ) {
		$meta = $this->get_post_seo_data( $post->ID );
		$permalink = get_permalink( $post->ID );
		$defaults = $this->get_title_defaults( $post );

		wp_nonce_field( 'novatools_seo_save_meta', 'novatools_seo_nonce' );

		printf(
			'<div id="wseo-react-meta-box" data-post-id="%s" data-post-type="%s" data-permalink="%s" data-default-template="%s" data-default-title="%s" data-meta="%s"></div>',
			esc_attr( $post->ID ),
			esc_attr( $post->post_type ),
			esc_url( $permalink ),
			esc_attr( $defaults['template'] ),
			esc_attr( $defaults['resolved'] ),
			esc_attr( wp_json_encode( $meta ) )
		);
	}

	public function render_term_meta_box( $tag, $taxonomy ) {
		$meta = $this->get_term_seo_data( $tag->term_id );
		wp_nonce_field( 'novatools_seo_save_term_meta', 'novatools_seo_term_nonce' );
		?>
		<tr class="form-field">
			<th scope="row"><?php esc_html_e( 'SEO Title', 'novatools-seo' ); ?></th>
			<td><input type="text" name="_wseo_title" value="<?php echo esc_attr( $meta['_wseo_title'] ?? '' ); ?>" class="large-text" /></td>
		</tr>
		<tr class="form-field">
			<th scope="row"><?php esc_html_e( 'SEO Description', 'novatools-seo' ); ?></th>
			<td><textarea name="_wseo_description" class="large-text" rows="3"><?php echo esc_textarea( $meta['_wseo_description'] ?? '' ); ?></textarea></td>
		</tr>
		<tr class="form-field">
			<th scope="row"><?php esc_html_e( 'Robots', 'novatools-seo' ); ?></th>
			<td>
				<select name="_wseo_robots">
					<option value="" <?php selected( empty( $meta['_wseo_robots'] ) ); ?>><?php esc_html_e( 'Default', 'novatools-seo' ); ?></option>
					<option value="index,follow" <?php selected( ( $meta['_wseo_robots'] ?? '' ), 'index,follow' ); ?>><?php esc_html_e( 'index, follow', 'novatools-seo' ); ?></option>
					<option value="noindex,follow" <?php selected( ( $meta['_wseo_robots'] ?? '' ), 'noindex,follow' ); ?>><?php esc_html_e( 'noindex, follow', 'novatools-seo' ); ?></option>
					<option value="index,nofollow" <?php selected( ( $meta['_wseo_robots'] ?? '' ), 'index,nofollow' ); ?>><?php esc_html_e( 'index, nofollow', 'novatools-seo' ); ?></option>
					<option value="noindex,nofollow" <?php selected( ( $meta['_wseo_robots'] ?? '' ), 'noindex,nofollow' ); ?>><?php esc_html_e( 'noindex, nofollow', 'novatools-seo' ); ?></option>
				</select>
			</td>
		</tr>
		<?php
	}

	public function save_meta( $post_id, $post ) {
		if ( ! isset( $_POST['novatools_seo_nonce'] ) || ! wp_verify_nonce( $_POST['novatools_seo_nonce'], 'novatools_seo_save_meta' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		foreach ( MetaKeys::POST_ALL as $field ) {
			if ( isset( $_POST[ $field ] ) ) {
				update_post_meta( $post_id, $field, sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) );
			}
		}

		// Structured schema data (array) sent as a JSON string from the
		// classic-editor hidden-input fallback.
		if ( isset( $_POST['_wseo_schema'] ) ) {
			$decoded = json_decode( wp_unslash( $_POST['_wseo_schema'] ), true );
			$clean   = is_array( $decoded ) ? SchemaRegistry::sanitize_for_storage( $decoded ) : array();
			if ( empty( $clean ) ) {
				delete_post_meta( $post_id, '_wseo_schema' );
			} else {
				update_post_meta( $post_id, '_wseo_schema', $clean );
			}
		}
	}

	public function save_term_meta( $term_id ) {
		if ( ! current_user_can( 'edit_term', $term_id ) ) {
			return;
		}

		if ( ! isset( $_POST['novatools_seo_term_nonce'] ) || ! wp_verify_nonce( $_POST['novatools_seo_term_nonce'], 'novatools_seo_save_term_meta' ) ) {
			return;
		}

		foreach ( MetaKeys::TERM_SEO as $field ) {
			if ( isset( $_POST[ $field ] ) ) {
				update_term_meta( $term_id, $field, sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) );
			}
		}
	}

	public function enqueue_meta_box_scripts( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		wp_enqueue_media();

		$manifest_dir = NOVATOOLS_SEO_DIR . '/assets/admin/dist';

		if ( DependencyCheck::is_novatools_active() ) {
			// In addon mode, the main bundle is not loaded on post pages.
			// Load it separately to mount the SeoMetaBox component.
			Assets\enqueue_asset(
				$manifest_dir,
				'src/admin/main.jsx',
				array(
					'dependencies' => array( 'react', 'react-dom' ),
					'handle'       => AssetsAdmin::HANDLE . '-metabox',
					'in-footer'    => true,
				)
			);

			wp_localize_script( AssetsAdmin::HANDLE . '-metabox', AssetsAdmin::OBJ_NAME, AssetsAdmin::get_instance()->get_data() );
		} else {
			// Standalone mode: enqueue the main script if not already loaded.
			if ( ! wp_script_is( AssetsAdmin::HANDLE, 'enqueued' ) ) {
				Assets\enqueue_asset(
					$manifest_dir,
					'src/admin/main.jsx',
					AssetsAdmin::get_instance()->get_config()
				);

				wp_localize_script( AssetsAdmin::HANDLE, AssetsAdmin::OBJ_NAME, AssetsAdmin::get_instance()->get_data() );
			}
		}
	}

	/**
	 * Resolve the default SEO title template for the post being edited.
	 *
	 * Returns both the raw template (with %%variables%%) and the fully
	 * resolved value, so the editor can preview the default that applies
	 * when the per-post SEO title field is left empty.
	 *
	 * @param \WP_Post $post The post being edited.
	 * @return array{template:string,resolved:string}
	 */
	private function get_title_defaults( $post ) {
		$settings  = Settings::get_instance();
		$post_type = $post->post_type;

		$template = $settings->get(
			"general_{$post_type}_title_template",
			$settings->get( 'general_title_template', '%%title%% %%sep%% %%sitename%%' )
		);

		if ( '' === $template ) {
			$template = '%%title%% %%sep%% %%sitename%%';
		}

		return array(
			'template' => $template,
			'resolved' => Tokens::replace( $template, $post ),
		);
	}

	private function get_post_seo_data( $post_id ) {
		$keys = array_merge( MetaKeys::POST_ALL, MetaKeys::POST_SCHEMA, [ '_thumbnail_id' ] );

		$data = array();
		foreach ( $keys as $key ) {
			$data[ $key ] = get_post_meta( $post_id, $key, true );
		}

		// Ensure the structured schema payload is always an array for the UI.
		if ( ! is_array( $data['_wseo_schema'] ) ) {
			$data['_wseo_schema'] = array();
		}

		if ( has_post_thumbnail( $post_id ) ) {
			$data['_thumbnail_url'] = get_the_post_thumbnail_url( $post_id, 'large' );
		} else {
			$data['_thumbnail_url'] = '';
		}

		return $data;
	}

	private function get_term_seo_data( $term_id ) {
		$keys = MetaKeys::TERM_SEO;

		$data = array();
		foreach ( $keys as $key ) {
			$data[ $key ] = get_term_meta( $term_id, $key, true );
		}

		return $data;
	}
}
