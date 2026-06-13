<?php

namespace NovaToolsSEO\GMC;

use NovaToolsSEO\Core\Logger;

defined( 'ABSPATH' ) || exit;

class SyncEngine {

	const OPTION_SYNC_STATE   = 'wseo_gmc_sync_state';
	const ACTION_SINGLE_PUSH  = 'wseo_gmc_push_product';
	const ACTION_BATCH_CHUNK  = 'wseo_gmc_sync_batch';
	const ACTION_RECURRING    = 'wseo_gmc_recurring_sync';
	const BATCH_SIZE          = 50;

	private $client;

	public function __construct( ApiClient $client ) {
		$this->client = $client;
	}

	public function init() {
		add_action( self::ACTION_SINGLE_PUSH, array( $this, 'handle_single_push' ) );
		add_action( self::ACTION_BATCH_CHUNK, array( $this, 'handle_batch_chunk' ) );
		add_action( self::ACTION_RECURRING, array( $this, 'handle_recurring_sync' ) );

		add_action( 'woocommerce_update_product', array( $this, 'on_product_update' ), 10, 1 );
		add_action( 'woocommerce_product_set_stock_status', array( $this, 'on_stock_change' ), 10, 1 );
	}

	public function on_product_update( $product_id ) {
		if ( get_option( 'wseo_gmc_realtime_sync', '0' ) !== '1' ) {
			return;
		}

		$product = wc_get_product( $product_id );
		if ( ! $product || 'publish' !== $product->get_status() ) {
			return;
		}

		$this->schedule_single_push( $product_id );
	}

	public function on_stock_change( $product_id ) {
		$this->on_product_update( $product_id );
	}

	public function schedule_single_push( $product_id ) {
		if ( function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action( time(), self::ACTION_SINGLE_PUSH, array( 'product_id' => $product_id ) );
		} else {
			wp_schedule_single_event( time(), self::ACTION_SINGLE_PUSH, array( 'product_id' => $product_id ) );
		}
	}

	public function handle_single_push( $product_id ) {
		$merchant_id = get_option( 'wseo_gmc_merchant_id', '' );
		if ( empty( $merchant_id ) ) {
			Logger::log( 'gmc_error', 'No Merchant Center ID configured', array( 'product_id' => $product_id ) );
			return;
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return;
		}

		$items = ProductMapper::map( $product );

		foreach ( $items as $item ) {
			$result = $this->client->upsert_product( $merchant_id, $item );

			if ( is_wp_error( $result ) ) {
				Logger::log( 'gmc_error', $result->get_error_message(), array(
					'product_id' => $product_id,
					'offer_id'   => $item['offerId'] ?? '',
				) );
			} else {
				Logger::log( 'gmc_sync', 'Product pushed to GMC', array(
					'product_id' => $product_id,
					'offer_id'   => $item['offerId'] ?? '',
				) );
			}
		}
	}

	public function start_batch_sync( $merchant_id ) {
		$total = $this->count_products();

		$state = array(
			'status'       => 'active',
			'total'        => $total,
			'processed'    => 0,
			'errors'       => 0,
			'merchant_id'  => $merchant_id,
			'started_at'   => current_time( 'mysql' ),
			'current_page' => 0,
		);

		update_option( self::OPTION_SYNC_STATE, $state, 'no' );

		$this->schedule_batch_chunk( 1, $merchant_id );

		return $state;
	}

	private function schedule_batch_chunk( $page, $merchant_id ) {
		if ( function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action( time(), self::ACTION_BATCH_CHUNK, array(
				'page'        => $page,
				'merchant_id' => $merchant_id,
			) );
		} else {
			wp_schedule_single_event( time(), self::ACTION_BATCH_CHUNK, array(
				'page'        => $page,
				'merchant_id' => $merchant_id,
			) );
		}
	}

	public function handle_batch_chunk( $page, $merchant_id ) {
		$state = get_option( self::OPTION_SYNC_STATE, array() );

		if ( empty( $state ) || 'cancelled' === ( $state['status'] ?? '' ) ) {
			return;
		}

		$products = $this->get_products_page( $page );

		foreach ( $products as $product ) {
			if ( 'publish' !== $product->get_status() ) {
				continue;
			}

			$items = ProductMapper::map( $product );

			foreach ( $items as $item ) {
				$result = $this->client->upsert_product( $merchant_id, $item );

				$state['processed']++;

				if ( is_wp_error( $result ) ) {
					$state['errors']++;
					Logger::log( 'gmc_error', $result->get_error_message(), array(
						'product_id' => $product->get_id(),
						'offer_id'   => $item['offerId'] ?? '',
					) );
				} else {
					Logger::log( 'gmc_sync', 'Batch: product pushed to GMC', array(
						'product_id' => $product->get_id(),
						'offer_id'   => $item['offerId'] ?? '',
					) );
				}
			}
		}

		$state['current_page'] = $page;

		if ( $state['processed'] >= $state['total'] ) {
			$state['status']     = 'complete';
			$state['completed_at'] = current_time( 'mysql' );
		} else {
			$this->schedule_batch_chunk( $page + 1, $merchant_id );
		}

		update_option( self::OPTION_SYNC_STATE, $state, 'no' );
	}

	public function cancel_sync() {
		$state = get_option( self::OPTION_SYNC_STATE, array() );

		if ( ! empty( $state ) && in_array( $state['status'] ?? '', array( 'active', 'paused' ), true ) ) {
			$state['status'] = 'cancelled';
			$state['cancelled_at'] = current_time( 'mysql' );
			update_option( self::OPTION_SYNC_STATE, $state, 'no' );
		}

		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::ACTION_BATCH_CHUNK );
		}
	}

	public function pause_sync() {
		$state = get_option( self::OPTION_SYNC_STATE, array() );

		if ( ! empty( $state ) && 'active' === ( $state['status'] ?? '' ) ) {
			$state['status'] = 'paused';
			$state['paused_at'] = current_time( 'mysql' );
			update_option( self::OPTION_SYNC_STATE, $state, 'no' );

			if ( function_exists( 'as_unschedule_all_actions' ) ) {
				as_unschedule_all_actions( self::ACTION_BATCH_CHUNK );
			}
		}
	}

	public function resume_sync() {
		$state = get_option( self::OPTION_SYNC_STATE, array() );

		if ( ! empty( $state ) && 'paused' === ( $state['status'] ?? '' ) ) {
			$state['status'] = 'active';
			unset( $state['paused_at'] );
			update_option( self::OPTION_SYNC_STATE, $state, 'no' );

			$this->schedule_batch_chunk( $state['current_page'] + 1, $state['merchant_id'] );
		}
	}

	public function get_sync_state() {
		$state = get_option( self::OPTION_SYNC_STATE, array() );

		if ( empty( $state ) ) {
			return array(
				'status'     => 'idle',
				'total'      => 0,
				'processed'  => 0,
				'errors'     => 0,
				'percentage' => 0,
			);
		}

		$state['percentage'] = $state['total'] > 0
			? round( ( $state['processed'] / $state['total'] ) * 100, 1 )
			: 0;

		return $state;
	}

	public function update_recurring_schedule( $schedule ) {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::ACTION_RECURRING );
		}

		if ( 'disabled' === $schedule ) {
			return;
		}

		$interval = 'daily' === $schedule ? DAY_IN_SECONDS : WEEK_IN_SECONDS;

		if ( function_exists( 'as_schedule_recurring_action' ) ) {
			as_schedule_recurring_action( time() + $interval, $interval, self::ACTION_RECURRING );
		}
	}

	public function handle_recurring_sync() {
		$merchant_id = get_option( 'wseo_gmc_merchant_id', '' );

		if ( empty( $merchant_id ) || ! ( new OAuth() )->is_connected() ) {
			return;
		}

		$this->start_batch_sync( $merchant_id );
	}

	private function count_products() {
		global $wpdb;

		$non_variable = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts}
			 WHERE post_type = 'product' AND post_status = 'publish'
			 AND ID NOT IN (
				 SELECT DISTINCT post_parent FROM {$wpdb->posts}
				 WHERE post_type = 'product_variation' AND post_status = 'publish'
			 )"
		);

		$variations = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts}
			 WHERE post_type = 'product_variation' AND post_status = 'publish'"
		);

		return $non_variable + $variations;
	}

	private function get_products_page( $page ) {
		$offset = ( $page - 1 ) * self::BATCH_SIZE;

		return wc_get_products( array(
			'status'  => 'publish',
			'limit'   => self::BATCH_SIZE,
			'offset'  => $offset,
			'orderby' => 'ID',
			'order'   => 'ASC',
		) );
	}
}
