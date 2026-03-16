<?php
namespace OW\YoastExtendedSchema\Contracts;

interface ModifierInterface
{
	public function supports( $modifier );

	public function apply( $value, $modifier, \WP_Post $post, ResolverInterface $resolver );
}
