<?php
namespace OW\YoastExtendedSchema\Contracts;

interface ResolverInterface
{
	public function normalize_scalar_value( $value );

	public function resolve_source_value( $source, $key, \WP_Post $post );

	public function get_reference_post( $value );

	public function format_schema_datetime( $value );
}
