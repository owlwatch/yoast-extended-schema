<?php
namespace OW\YoastExtendedSchema\Sources;

use OW\YoastExtendedSchema\Contracts\ResolverInterface;
use OW\YoastExtendedSchema\Contracts\VariableSourceInterface;

class YoastSource implements VariableSourceInterface
{
	public function get_name() {
		return 'yoast';
	}

	public function resolve( $key, \WP_Post $post, ResolverInterface $resolver ) {
		$key = trim( strtolower( $key ) );
		if ( $key === '' ) {
			return '';
		}

		if ( function_exists( 'YoastSEO' ) ) {
			$meta_surface = \YoastSEO()->meta ?? null;
			if ( $meta_surface && method_exists( $meta_surface, 'for_post' ) ) {
				$meta = $meta_surface->for_post( $post->ID );
				if ( $meta ) {
					switch ( $key ) {
						case 'title':
							return $meta->title ?? '';

						case 'description':
							return $meta->description ?? $meta->meta_description ?? '';
					}
				}
			}
		}

		return '';
	}
}
