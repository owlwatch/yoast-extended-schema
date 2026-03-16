<?php
namespace OW\YoastExtendedSchema\Modifiers;

use OW\YoastExtendedSchema\Contracts\ModifierInterface;
use OW\YoastExtendedSchema\Contracts\ResolverInterface;

class SchemaDateModifier implements ModifierInterface
{
	public function supports( $modifier ) {
		return preg_match( '/^(schema_date|schema_datetime|iso8601)$/i', trim( $modifier ) ) === 1;
	}

	public function apply( $value, $modifier, \WP_Post $post, ResolverInterface $resolver ) {
		return $resolver->format_schema_datetime( $value );
	}
}
