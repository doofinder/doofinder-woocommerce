<?php

namespace Doofinder\WC\Data_Feed;

defined( 'ABSPATH' ) or die;

class Feed_XML {

	/**
	 * Blog information, to be displayed before all items.
	 *
	 * @var array
	 */
	public $header = array();

	/**
	 * List of items to be displayed in the feed.
	 *
	 * @var array
	 */
	public $items = array();

	/**
	 * Print the feed.
	 *
	 * @since 1.0.0
	 * @param bool $open  Should the feed contain opening tags and header?
	 * @param bool $close Should the feed contain closing tags?
	 */
	public function render( $open, $close ) {
		if ( true === $open ) {
			$this->open();
		}

		$this->items();

		if ( true == $close ) {
			$this->close();
		}
	}

	/**
	 * Display the opening tags and header of the feed.
	 *
	 * @since 1.0.0
	 */
	private function open() {
		echo '<?xml version="1.0" encoding="UTF-8"?><rss version="2.0"><channel>';

		foreach( $this->header as $name => $value ) {
			$this->add_node( $name, $value );
		}
	}

	/**
	 * Display the closing tags of the feed.
	 *
	 * @since 1.0.0
	 */
	private function close() {
		echo '</channel></rss>';
	}

	/**
	 * Print feed items.
	 *
	 * @since 1.0.0
	 */
	private function items() {
		foreach ( $this->items as $item ) {
			echo '<item>';

			foreach ( $item as $field => $value ) {
				$this->add_node( $field, $value );
			}

			echo '</item>';
		}
	}

	/**
	 * Print an XML node.
	 *
	 * @since 1.0.0
	 * @param string $name  Name of the node (tag).
	 * @param string $value Value of the node.
	 */
	private function add_node( $name, $value ) {
		echo "<$name>";
		$this->cdata( $value );
		echo "</$name>";
	}

	/**
	 * Print given content wrapped as CData.
	 *
	 * @since 1.0.0
	 * @param string $value Content to print.
	 */
	private function cdata( $value ) {
		echo "<![CDATA[$value]]>";
	}
}
