<?php
namespace OW\YoastExtendedSchema\Contracts;

interface ResolverInterface
{
	public function normalize_scalar_value( mixed $value );

	public function resolve_source_value( string $source, string $key, \WP_Post $post );

	public function get_reference_post( mixed $value );

	public function format_schema_datetime( mixed $value );
}
