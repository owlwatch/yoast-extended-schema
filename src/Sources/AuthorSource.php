<?php
namespace OW\YoastExtendedSchema\Sources;

use OW\YoastExtendedSchema\Contracts\ResolverInterface;
use OW\YoastExtendedSchema\Contracts\VariableSourceInterface;

class AuthorSource implements VariableSourceInterface
{
	public function get_name() {
		return 'author';
	}

	public function resolve( string $key, \WP_Post $post, ResolverInterface $resolver ) {
		$key = trim( strtolower( $key ) );
		if ( $key === '' ) {
			return '';
		}

		$author = get_userdata( (int) $post->post_author );
		if ( ! $author ) {
			return '';
		}

		switch ( $key ) {
			case 'display_name':
				return $author->display_name;

			case 'url':
				return get_author_posts_url( $author->ID );
		}

		return '';
	}
}
