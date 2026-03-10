<?php
namespace OW\YoastExtendedSchema;

use Yoast\WP\SEO\Generators\Schema\Abstract_Schema_Piece;

class AdHoc extends Abstract_Schema_Piece
{

	public $identifier = 'adhoc';

	private function decode_schema_json( $schema_json ) {
		if ( ! is_string( $schema_json ) || trim( $schema_json ) === '' ) {
			return null;
		}

		$schema = json_decode( $schema_json, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return null;
		}

		return $schema;
	}

	private function get_custom_schema( $id )
	{
		static $cache = [];
		if( isset($cache[$id]) ){
			return $cache[$id];
		}

		$schema = null;
		if( function_exists('get_field') ){
			/**
			 * @var string
			 */
			$schema_json = get_field( 'custom_schema', $id );
			$schema = $this->decode_schema_json( $schema_json );

		}

		$cache[$id] = $schema;
		return $schema;
	}

	private function get_options_schema( $post_type, $location )
	{
		static $cache = [];
		$key = $post_type . '|' . $location;
		if ( array_key_exists( $key, $cache ) ) {
			return $cache[$key];
		}

		$schema = null;
		if ( function_exists('get_field') ){
			$rows = get_field( 'post_type_custom_schema', 'option' );
			if ( is_array( $rows ) ) {
				foreach ( $rows as $row ) {
					if ( empty( $row['post_type'] ) || $row['post_type'] !== $post_type ) {
						continue;
					}

					$schema = $this->decode_schema_json( $row[ $location ] ?? '' );
					if ( ! empty( $schema ) ) {
						break;
					}
				}
			}
		}

		$cache[$key] = $schema;
		return $schema;
	}

	private function get_singular_post_type()
	{
		if ( is_singular() && isset( $this->context->post ) ) {
			return $this->context->post->post_type;
		}

		return null;
	}

	private function get_archive_post_type()
	{
		if ( is_post_type_archive() ) {
			$post_type = get_query_var( 'post_type' );
			if ( is_array( $post_type ) ) {
				$post_type = reset( $post_type );
			}
			if ( ! empty( $post_type ) ) {
				return $post_type;
			}
		}

		if ( is_home() ) {
			return 'post';
		}

		return null;
	}

	private function get_schema()
	{
		$singular_post_type = $this->get_singular_post_type();
		if ( $singular_post_type ) {
			$post_schema = $this->get_custom_schema( $this->context->post->ID );
			if ( ! empty( $post_schema ) ) {
				return $post_schema;
			}

			$options_schema = $this->get_options_schema( $singular_post_type, 'singular' );
			if ( ! empty( $options_schema ) ) {
				return $options_schema;
			}
		}

		$archive_post_type = $this->get_archive_post_type();
		if ( $archive_post_type ) {
			$options_schema = $this->get_options_schema( $archive_post_type, 'archive' );
			if ( ! empty( $options_schema ) ) {
				return $options_schema;
			}
		}

		return null;
	}

	/**
	 * Determines whether or not a piece should be added to the graph.
	 *
	 * @return bool
	 */
	public function is_needed() {
		return ! empty( $this->get_schema() );
	}

	/**
	 * Returns Article data.
	 *
	 * @return array Article data.
	 */
	public function generate() {
		return $this->get_schema();
	}
}
