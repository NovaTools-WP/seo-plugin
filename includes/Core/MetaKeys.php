<?php

namespace NovaToolsSEO\Core;

defined( 'ABSPATH' ) || exit;

class MetaKeys {

	const POST_SEO = [
		'_wseo_title',
		'_wseo_description',
		'_wseo_canonical',
		'_wseo_robots',
		'_wseo_og_title',
		'_wseo_og_description',
		'_wseo_og_image',
		'_wseo_twitter_card',
		'_wseo_twitter_title',
		'_wseo_twitter_description',
		'_wseo_twitter_image',
	];

	const POST_ALL = [
		'_wseo_title',
		'_wseo_description',
		'_wseo_canonical',
		'_wseo_robots',
		'_wseo_og_title',
		'_wseo_og_description',
		'_wseo_og_image',
		'_wseo_twitter_card',
		'_wseo_twitter_title',
		'_wseo_twitter_description',
		'_wseo_twitter_image',
		'_wseo_local_business',
	];

	const TERM_SEO = [
		'_wseo_title',
		'_wseo_description',
		'_wseo_robots',
	];

	const PRODUCT = [
		'_wseo_gtin',
		'_wseo_mpn',
		'_wseo_isbn',
		'_wseo_brand',
		'_wseo_item_condition',
		'_wseo_faq',
		'_wseo_local_inventory',
	];

	const PRODUCT_TEXT = [
		'_wseo_gtin',
		'_wseo_mpn',
		'_wseo_isbn',
		'_wseo_brand',
		'_wseo_item_condition',
	];
}
