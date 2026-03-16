<?php
namespace OW\YoastExtendedSchema;

use Yoast\WP\SEO\Generators\Schema\Abstract_Schema_Piece;

class AdHoc extends Abstract_Schema_Piece
{
	private const VARIABLE_PATTERN = '/\{\{\s*(field|meta|yoast|author)\s*:\s*([^\|\}]+?)\s*(?:\|\s*([^\}]+?)\s*)?\}\}/';

	public $identifier = 'adhoc';

	private function decode_schema_json( $schema_json ) {
		if ( ! is_string( $schema_json ) || trim( $schema_json ) === '' ) {
			return null;
		}

		$schema = json_decode( $schema_json, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return null;
		}

		return $schema;
	}

	private function is_singular_context() {
		return is_singular() && isset( $this->context->post ) && $this->context->post instanceof \WP_Post;
	}

	private function get_singular_post() {
		if ( ! $this->is_singular_context() ) {
			return null;
		}

		return $this->context->post;
	}

	private function normalize_scalar_value( $value ) {
		if ( is_null( $value ) ) {
			return '';
		}

		if ( is_bool( $value ) ) {
			return $value ? '1' : '0';
		}

		if ( is_scalar( $value ) ) {
			return (string) $value;
		}

		return wp_json_encode( $value );
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

	private function get_yoast_variable_value( $key, \WP_Post $post ) {
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

		if ( isset( $this->context->presentation ) && is_object( $this->context->presentation ) ) {
			switch ( $key ) {
				case 'title':
					return $this->context->presentation->title ?? '';

				case 'description':
					return $this->context->presentation->meta_description ?? '';
			}
		}

		return '';
	}

	private function get_author_variable_value( $key, \WP_Post $post ) {
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

	private function get_reference_post( $value ) {
		if ( $value instanceof \WP_Post ) {
			return $value;
		}

		if ( is_numeric( $value ) ) {
			$post = get_post( (int) $value );
			return $post instanceof \WP_Post ? $post : null;
		}

		if ( is_object( $value ) && isset( $value->ID ) && is_numeric( $value->ID ) ) {
			$post = get_post( (int) $value->ID );
			return $post instanceof \WP_Post ? $post : null;
		}

		if ( is_array( $value ) ) {
			if ( isset( $value['ID'] ) && is_numeric( $value['ID'] ) ) {
				$post = get_post( (int) $value['ID'] );
				return $post instanceof \WP_Post ? $post : null;
			}

			if ( isset( $value['id'] ) && is_numeric( $value['id'] ) ) {
				$post = get_post( (int) $value['id'] );
				return $post instanceof \WP_Post ? $post : null;
			}
		}

		return null;
	}

	private function format_schema_datetime( $value ) {
		$value = trim( $this->normalize_scalar_value( $value ) );
		if ( $value === '' ) {
			return '';
		}

		$timezone = wp_timezone();
		$datetime = date_create_immutable_from_format( 'Y-m-d H:i:s', $value, $timezone );

		if ( ! $datetime ) {
			try {
				$datetime = new \DateTimeImmutable( $value, $timezone );
			}
			catch ( \Exception $exception ) {
				return $value;
			}
		}

		return $datetime->setTimezone( $timezone )->format( 'c' );
	}

	private function get_variable_value( $source, $key, \WP_Post $post ) {
		$key = trim( $key );
		if ( $key === '' ) {
			return '';
		}

		if ( $source === 'field' ) {
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

		if ( $source === 'meta' ) {
			return get_post_meta( $post->ID, $key, true );
		}

		if ( $source === 'yoast' ) {
			return $this->get_yoast_variable_value( $key, $post );
		}

		if ( $source === 'author' ) {
			return $this->get_author_variable_value( $key, $post );
		}

		return '';
	}

	private function apply_variable_modifier( $value, $modifier, \WP_Post $post ) {
		$modifier = trim( $modifier );
		if ( $modifier === '' ) {
			return $value;
		}

		$text_value = wp_strip_all_tags( $this->normalize_scalar_value( $value ), true );

		if ( preg_match( '/^words\s*:\s*(\d+)$/i', $modifier, $matches ) === 1 ) {
			return wp_trim_words( $text_value, (int) $matches[1], '' );
		}

		if ( preg_match( '/^chars\s*:\s*(\d+)$/i', $modifier, $matches ) === 1 ) {
			$length = (int) $matches[1];
			if ( $length <= 0 ) {
				return '';
			}

			return function_exists( 'mb_substr' ) ? mb_substr( $text_value, 0, $length ) : substr( $text_value, 0, $length );
		}

		if ( preg_match( '/^(schema_date|schema_datetime|iso8601)$/i', $modifier ) === 1 ) {
			return $this->format_schema_datetime( $value );
		}

		if ( preg_match( '/^xref\s*:\s*(field|meta|yoast|author)\s*:\s*(.+)$/i', $modifier, $matches ) === 1 ) {
			$reference_post = $this->get_reference_post( $value );
			if ( ! $reference_post ) {
				return '';
			}

			return $this->get_variable_value( strtolower( $matches[1] ), trim( $matches[2] ), $reference_post );
		}

		switch ( strtolower( $modifier ) ) {
			default:
				return $value;
		}
	}

	private function resolve_variable_match( array $match, \WP_Post $post ) {
		$value = $this->get_variable_value( $match[1], $match[2], $post );
		$modifiers = isset( $match[3] ) ? array_map( 'trim', explode( '|', $match[3] ) ) : [];

		foreach ( $modifiers as $modifier ) {
			$value = $this->apply_variable_modifier( $value, $modifier, $post );
		}

		return $value;
	}

	private function resolve_schema_value( $value, \WP_Post $post ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $key => $item ) {
				$value[ $key ] = $this->resolve_schema_value( $item, $post );
			}

			return $value;
		}

		if ( ! is_string( $value ) || strpos( $value, '{{' ) === false ) {
			return $value;
		}

		if ( preg_match( self::VARIABLE_PATTERN, $value, $full_match ) === 1 && $full_match[0] === $value ) {
			return $this->resolve_variable_match( $full_match, $post );
		}

		return preg_replace_callback(
			self::VARIABLE_PATTERN,
			function( $match ) use ( $post ) {
				return $this->normalize_scalar_value( $this->resolve_variable_match( $match, $post ) );
			},
			$value
		);
	}

	private function resolve_schema_variables( $schema ) {
		$post = $this->get_singular_post();
		if ( ! $post ) {
			return $schema;
		}

		return $this->resolve_schema_value( $schema, $post );
	}

	private function get_custom_schema( $id )
	{
		static $cache = [];
		if( isset($cache[$id]) ){
			return $cache[$id];
		}

		$schema = null;
		if( function_exists('get_field') ){
			/**
			 * @var string
			 */
			$schema_json = get_field( 'custom_schema', $id );
			$schema = $this->decode_schema_json( $schema_json );

		}

		$cache[$id] = $schema;
		return $schema;
	}

	private function get_options_schema( $post_type, $location )
	{
		static $cache = [];
		$key = $post_type . '|' . $location;
		if ( array_key_exists( $key, $cache ) ) {
			return $cache[$key];
		}

		$schema = null;
		if ( function_exists('get_field') ){
			$rows = get_field( 'post_type_custom_schema', 'option' );
			if ( is_array( $rows ) ) {
				foreach ( $rows as $row ) {
					if ( empty( $row['post_type'] ) || $row['post_type'] !== $post_type ) {
						continue;
					}

					$schema = $this->decode_schema_json( $row[ $location ] ?? '' );
					if ( ! empty( $schema ) ) {
						break;
					}
				}
			}
		}

		$cache[$key] = $schema;
		return $schema;
	}

	private function get_singular_post_type()
	{
		$post = $this->get_singular_post();
		if ( $post ) {
			return $post->post_type;
		}

		return null;
	}

	private function get_archive_post_type()
	{
		if ( is_post_type_archive() ) {
			$post_type = get_query_var( 'post_type' );
			if ( is_array( $post_type ) ) {
				$post_type = reset( $post_type );
			}
			if ( ! empty( $post_type ) ) {
				return $post_type;
			}
		}

		if ( is_home() ) {
			return 'post';
		}

		return null;
	}

	private function get_schema()
	{
		$singular_post_type = $this->get_singular_post_type();
		if ( $singular_post_type ) {
			$post_schema = $this->get_custom_schema( $this->context->post->ID );
			if ( ! empty( $post_schema ) ) {
				return $post_schema;
			}

			$options_schema = $this->get_options_schema( $singular_post_type, 'singular' );
			if ( ! empty( $options_schema ) ) {
				return $options_schema;
			}
		}

		$archive_post_type = $this->get_archive_post_type();
		if ( $archive_post_type ) {
			$options_schema = $this->get_options_schema( $archive_post_type, 'archive' );
			if ( ! empty( $options_schema ) ) {
				return $options_schema;
			}
		}

		return null;
	}

	/**
	 * Determines whether or not a piece should be added to the graph.
	 *
	 * @return bool
	 */
	public function is_needed() {
		return ! empty( $this->get_schema() );
	}

	/**
	 * Returns Article data.
	 *
	 * @return array Article data.
	 */
	public function generate() {
		return $this->resolve_schema_variables( $this->get_schema() );
	}
}
