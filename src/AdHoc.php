<?php
namespace OW\YoastExtendedSchema;

use Yoast\WP\SEO\Generators\Schema\Abstract_Schema_Piece;

class AdHoc extends Abstract_Schema_Piece
{

	public $identifier = 'adhoc';

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
			$schema = json_decode( $schema_json, true );

		}

		$cache[$id] = $schema;
		return $schema;
	}

	/**
	 * Determines whether or not a piece should be added to the graph.
	 *
	 * @return bool
	 */
	public function is_needed() {
		
		if ( $this->context->indexable->object_type !== 'post' ) {
			return false;
		}

		// lets check to see if we have a custom schema.org
		return $this->get_custom_schema( $this->context->post->ID ) != '';
	}

	/**
	 * Returns Article data.
	 *
	 * @return array Article data.
	 */
	public function generate() {

		$schema = $this->get_custom_schema($this->context->post->ID);
		return $schema;
	}
}