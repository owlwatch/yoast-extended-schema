<?php
namespace OW\YoastExtendedSchema\Modifiers;

use OW\YoastExtendedSchema\Contracts\ModifierInterface;
use OW\YoastExtendedSchema\Contracts\ResolverInterface;

class WordsModifier implements ModifierInterface
{
	public function supports( string $modifier ) {
		return preg_match( '/^words\s*:\s*\d+$/i', trim( $modifier ) ) === 1;
	}

	public function apply( mixed $value, string $modifier, \WP_Post $post, ResolverInterface $resolver ) {
		preg_match( '/^words\s*:\s*(\d+)$/i', trim( $modifier ), $matches );
		$text_value = wp_strip_all_tags( $resolver->normalize_scalar_value( $value ), true );

		return wp_trim_words( $text_value, (int) $matches[1], '' );
	}
}
