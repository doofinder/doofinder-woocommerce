<?php

namespace Doofinder\WC\Api;

defined( 'ABSPATH' ) or die();

interface Api_Wrapper {

	/**
	 * Update the data of a single item in the API.
	 *
	 * @param string $item_type
	 * @param int    $id
	 * @param array  $data
	 *
	 * @return string Status of update operation.
	 */
	public function update_item( $item_type, $id, $data );

	/**
	 * Remove given item from indexing.
	 *
	 * @param string $item_type
	 * @param int    $id
	 *
	 * @return string Status of remove operation.
	 */
	public function remove_item( $item_type, $id );

	/**
	 * Send a batch of items to the API.
	 *
	 * @param string  $items_type
	 * @param array[] $items
	 *
	 * @return string Status of items sent to API.
	 */
	public function send_batch( $items_type, array $items );

	/**
	 * Remove all post types from the API.
	 *
	 * @return string Status of the operation.
	 */
	public function remove_types();

	/**
	 * Replace real index with temp index
	 *
	 * @param string $index_name
	 */
	public function replace_index( $index_name );
}
