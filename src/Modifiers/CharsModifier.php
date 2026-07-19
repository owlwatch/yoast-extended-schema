<?php
namespace OW\YoastExtendedSchema\Modifiers;

use OW\YoastExtendedSchema\Contracts\ModifierInterface;
use OW\YoastExtendedSchema\Contracts\ResolverInterface;

class CharsModifier implements ModifierInterface
{
	public function supports( string $modifier ) {
		return preg_match( '/^chars\s*:\s*\d+$/i', trim( $modifier ) ) === 1;
	}

	public function apply( mixed $value, string $modifier, \WP_Post $post, ResolverInterface $resolver ) {
		preg_match( '/^chars\s*:\s*(\d+)$/i', trim( $modifier ), $matches );
		$length = (int) $matches[1];
		if ( $length <= 0 ) {
			return '';
		}

		$text_value = wp_strip_all_tags( $resolver->normalize_scalar_value( $value ), true );

		return function_exists( 'mb_substr' ) ? mb_substr( $text_value, 0, $length ) : substr( $text_value, 0, $length );
	}
}
