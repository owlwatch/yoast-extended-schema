<?php
namespace OW\YoastExtendedSchema\Sources;

use OW\YoastExtendedSchema\Contracts\ResolverInterface;
use OW\YoastExtendedSchema\Contracts\VariableSourceInterface;

class FieldSource implements VariableSourceInterface
{
	public function get_name() {
		return 'field';
	}

	public function resolve( $key, \WP_Post $post, ResolverInterface $resolver ) {
		$key = trim( $key );
		if ( $key === '' ) {
			return '';
		}

		switch ( $key ) {
			case 'permalink':
				return get_permalink( $post );

			case 'excerpt':
				return $this->get_post_excerpt_value( $post );

			case 'featured_image':
				$thumbnail_id = get_post_thumbnail_id( $post );
				if ( ! $thumbnail_id ) {
					return '';
				}

				return wp_get_attachment_image_url( $thumbnail_id, 'full' ) ?: '';
		}

		return get_post_field( $key, $post->ID, 'raw' );
	}

	private function get_post_excerpt_value( \WP_Post $post ) {
		if ( has_excerpt( $post ) ) {
			return $post->post_excerpt;
		}

		$content = $post->post_content;
		$content = strip_shortcodes( $content );
		$content = excerpt_remove_blocks( $content );
		$content = apply_filters( 'the_content', $content );
		$content = str_replace( ']]>', ']]&gt;', $content );
		$content = wp_strip_all_tags( $content, true );

		return wp_trim_words( $content, 55, '' );
	}
}
