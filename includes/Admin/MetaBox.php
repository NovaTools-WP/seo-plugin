<?php

namespace NovaToolsSEO\Admin;

use NovaToolsSEO\Traits\Base;

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

		wp_nonce_field( 'novatools_seo_save_meta', 'novatools_seo_nonce' );

		echo '<div id="novatools-seo-meta-box" data-post-id="' . esc_attr( $post->ID ) . '" data-permalink="' . esc_url( $permalink ) . '" data-meta="' . esc_attr( wp_json_encode( $meta ) ) . '"></div>';
	}

	public function render_term_meta_box( $tag, $taxonomy ) {
		$meta = $this->get_term_seo_data( $tag->term_id );
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

		$fields = array( '_wseo_title', '_wseo_description', '_wseo_canonical', '_wseo_robots', '_wseo_og_title', '_wseo_og_description', '_wseo_og_image', '_wseo_twitter_card', '_wseo_twitter_title', '_wseo_twitter_description', '_wseo_twitter_image' );

		foreach ( $fields as $field ) {
			if ( isset( $_POST[ $field ] ) ) {
				update_post_meta( $post_id, $field, sanitize_text_field( $_POST[ $field ] ) );
			}
		}
	}

	public function save_term_meta( $term_id ) {
		$fields = array( '_wseo_title', '_wseo_description', '_wseo_robots' );

		foreach ( $fields as $field ) {
			if ( isset( $_POST[ $field ] ) ) {
				update_term_meta( $term_id, $field, sanitize_text_field( $_POST[ $field ] ) );
			}
		}
	}

	public function enqueue_meta_box_scripts( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		// The admin bundle handles meta box rendering when the container exists
	}

	private function get_post_seo_data( $post_id ) {
		$keys = array( '_wseo_title', '_wseo_description', '_wseo_canonical', '_wseo_robots', '_wseo_og_title', '_wseo_og_description', '_wseo_og_image', '_wseo_twitter_card', '_wseo_twitter_title', '_wseo_twitter_description', '_wseo_twitter_image' );

		$data = array();
		foreach ( $keys as $key ) {
			$data[ $key ] = get_post_meta( $post_id, $key, true );
		}

		return $data;
	}

	private function get_term_seo_data( $term_id ) {
		$keys = array( '_wseo_title', '_wseo_description', '_wseo_robots' );

		$data = array();
		foreach ( $keys as $key ) {
			$data[ $key ] = get_term_meta( $term_id, $key, true );
		}

		return $data;
	}
}
