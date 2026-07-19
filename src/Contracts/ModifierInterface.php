<?php
namespace OW\YoastExtendedSchema\Contracts;

interface ModifierInterface
{
	public function supports( string $modifier );

	public function apply( mixed $value, string $modifier, \WP_Post $post, ResolverInterface $resolver );
}
