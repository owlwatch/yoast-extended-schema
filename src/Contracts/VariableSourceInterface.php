<?php
namespace OW\YoastExtendedSchema\Contracts;

interface VariableSourceInterface
{
	public function get_name();

	public function resolve( string $key, \WP_Post $post, ResolverInterface $resolver );
}
