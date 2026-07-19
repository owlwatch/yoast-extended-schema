<?php
namespace OW\YoastExtendedSchema\Modifiers;

use OW\YoastExtendedSchema\Contracts\ModifierInterface;
use OW\YoastExtendedSchema\Contracts\ResolverInterface;

class CrossReferenceModifier implements ModifierInterface
{
	public function supports( string $modifier ) {
		return preg_match( '/^xref\s*:\s*[a-z0-9_]+\s*:\s*.+$/i', trim( $modifier ) ) === 1;
	}

	public function apply( mixed $value, string $modifier, \WP_Post $post, ResolverInterface $resolver ) {
		if ( preg_match( '/^xref\s*:\s*([a-z0-9_]+)\s*:\s*(.+)$/i', trim( $modifier ), $matches ) !== 1 ) {
			return $value;
		}

		$reference_post = $resolver->get_reference_post( $value );
		if ( ! $reference_post ) {
			return '';
		}

		return $resolver->resolve_source_value( strtolower( $matches[1] ), trim( $matches[2] ), $reference_post );
	}
}
