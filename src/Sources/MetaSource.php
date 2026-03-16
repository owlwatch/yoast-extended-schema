<?php
namespace OW\YoastExtendedSchema\Sources;

use OW\YoastExtendedSchema\Contracts\ResolverInterface;
use OW\YoastExtendedSchema\Contracts\VariableSourceInterface;

class MetaSource implements VariableSourceInterface
{
	public function get_name() {
		return 'meta';
	}

	public function resolve( $key, \WP_Post $post, ResolverInterface $resolver ) {
		$key = trim( $key );
		if ( $key === '' ) {
			return '';
		}

		return get_post_meta( $post->ID, $key, true );
	}
}
