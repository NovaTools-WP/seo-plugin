<?php

namespace NovaToolsSEO\Frontend;

use NovaToolsSEO\Core\BreadcrumbCollector;
use NovaToolsSEO\Traits\Base;

defined( 'ABSPATH' ) || exit;

class Breadcrumbs {

	use Base;

	public function init() {
		add_shortcode( 'wseo_breadcrumbs', array( $this, 'render_shortcode' ) );
	}

	public function render( $echo = true ) {
		$collector = new BreadcrumbCollector();
		$items = $collector->get_items();

		if ( empty( $items ) ) {
			return '';
		}

		// The last item is the current page — it should not be a link.
		$last_index = count( $items ) - 1;
		$items[ $last_index ]['url'] = '';

		$html = '<nav class="wseo-breadcrumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'novatools-seo' ) . '">';
		$html .= '<ol class="wseo-breadcrumbs-list" itemscope itemtype="https://schema.org/BreadcrumbList">';

		foreach ( $items as $index => $item ) {
			$position = $index + 1;
			$html .= '<li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">';

			if ( ! empty( $item['url'] ) ) {
				$html .= '<a itemprop="item" href="' . esc_url( $item['url'] ) . '">';
				$html .= '<span itemprop="name">' . esc_html( $item['name'] ) . '</span>';
				$html .= '</a>';
			} else {
				$html .= '<span itemprop="name">' . esc_html( $item['name'] ) . '</span>';
			}

			$html .= '<meta itemprop="position" content="' . esc_attr( $position ) . '" />';
			$html .= '</li>';
		}

		$html .= '</ol>';
		$html .= '</nav>';

		if ( $echo ) {
			echo $html;
		}

		return $html;
	}

	public function render_shortcode( $atts ) {
		return $this->render( false );
	}
}

function wseo_breadcrumbs( $echo = true ) {
	return Breadcrumbs::get_instance()->render( $echo );
}
