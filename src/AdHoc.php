<?php
namespace OW\YoastExtendedSchema;

use OW\YoastExtendedSchema\Contracts\ModifierInterface;
use OW\YoastExtendedSchema\Contracts\ResolverInterface;
use OW\YoastExtendedSchema\Contracts\VariableSourceInterface;
use OW\YoastExtendedSchema\Modifiers\CharsModifier;
use OW\YoastExtendedSchema\Modifiers\CrossReferenceModifier;
use OW\YoastExtendedSchema\Modifiers\SchemaDateModifier;
use OW\YoastExtendedSchema\Modifiers\WordsModifier;
use OW\YoastExtendedSchema\Sources\AuthorSource;
use OW\YoastExtendedSchema\Sources\FieldSource;
use OW\YoastExtendedSchema\Sources\MetaSource;
use OW\YoastExtendedSchema\Sources\YoastSource;
use Yoast\WP\SEO\Generators\Schema\Abstract_Schema_Piece;

class AdHoc extends Abstract_Schema_Piece implements ResolverInterface
{
	private const VARIABLE_PATTERN = '/\{\{\s*([a-z0-9_]+)\s*:\s*([^\|\}]+?)\s*(?:\|\s*([^\}]+?)\s*)?\}\}/i';
	private const CONDITIONAL_KEY = '$if';

	public $identifier = 'adhoc';

	/**
	 * @var VariableSourceInterface[]
	 */
	private $sources = [];

	/**
	 * @var ModifierInterface[]
	 */
	private $modifiers = [];

	private $registries_initialized = false;

	/**
	 * Unique marker used while recursively removing values whose conditions fail.
	 *
	 * @var object|null
	 */
	private $omitted_value = null;

	public function register_source( VariableSourceInterface $source ) {
		$this->sources[ strtolower( $source->get_name() ) ] = $source;
	}

	public function register_modifier( ModifierInterface $modifier ) {
		$this->modifiers[] = $modifier;
	}

	public function normalize_scalar_value( mixed $value ) {
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

	public function get_reference_post( mixed $value ) {
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

	public function format_schema_datetime( mixed $value ) {
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

	public function resolve_source_value( string $source, string $key, \WP_Post $post ) {
		$this->initialize_registries();

		$source = strtolower( trim( $source ) );
		$key = trim( $key );
		if ( $source === '' || $key === '' ) {
			return '';
		}

		if ( ! isset( $this->sources[ $source ] ) ) {
			return '';
		}

		return $this->sources[ $source ]->resolve( $key, $post, $this );
	}

	private function initialize_registries() {
		if ( $this->registries_initialized ) {
			return;
		}

		$this->register_source( new FieldSource() );
		$this->register_source( new MetaSource() );
		$this->register_source( new YoastSource() );
		$this->register_source( new AuthorSource() );

		$this->register_modifier( new WordsModifier() );
		$this->register_modifier( new CharsModifier() );
		$this->register_modifier( new SchemaDateModifier() );
		$this->register_modifier( new CrossReferenceModifier() );

		$this->registries_initialized = true;
	}

	private function decode_schema_json( mixed $schema_json ) {
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

	private function apply_variable_modifier( mixed $value, string $modifier, \WP_Post $post ) {
		$modifier = trim( $modifier );
		if ( $modifier === '' ) {
			return $value;
		}

		$this->initialize_registries();

		foreach ( $this->modifiers as $registered_modifier ) {
			if ( $registered_modifier->supports( $modifier ) ) {
				return $registered_modifier->apply( $value, $modifier, $post, $this );
			}
		}

		return $value;
	}

	private function resolve_variable_match( array $match, \WP_Post $post ) {
		$value = $this->resolve_source_value( $match[1], $match[2], $post );
		$modifiers = isset( $match[3] ) ? array_map( 'trim', explode( '|', $match[3] ) ) : [];

		foreach ( $modifiers as $modifier ) {
			$value = $this->apply_variable_modifier( $value, $modifier, $post );
		}

		return $value;
	}

	private function get_omitted_value() {
		if ( $this->omitted_value === null ) {
			$this->omitted_value = new \stdClass();
		}

		return $this->omitted_value;
	}

	private function condition_is_met( mixed $value ) {
		if ( is_string( $value ) ) {
			return trim( $value ) !== '' && trim( $value ) !== '0';
		}

		return ! empty( $value );
	}

	private function is_list_array( array $value ) {
		$expected_key = 0;
		foreach ( $value as $key => $item ) {
			if ( $key !== $expected_key ) {
				return false;
			}
			$expected_key++;
		}

		return true;
	}

	private function resolve_schema_value( mixed $value, \WP_Post $post ) {
		if ( is_array( $value ) ) {
			$is_list = $this->is_list_array( $value );

			if ( array_key_exists( self::CONDITIONAL_KEY, $value ) ) {
				$condition = $this->resolve_schema_value( $value[ self::CONDITIONAL_KEY ], $post );
				unset( $value[ self::CONDITIONAL_KEY ] );

				if ( $condition === $this->get_omitted_value() || ! $this->condition_is_met( $condition ) ) {
					return $this->get_omitted_value();
				}
			}

			foreach ( $value as $key => $item ) {
				$resolved_item = $this->resolve_schema_value( $item, $post );
				if ( $resolved_item === $this->get_omitted_value() ) {
					unset( $value[ $key ] );
					continue;
				}

				$value[ $key ] = $resolved_item;
			}

			return $is_list ? array_values( $value ) : $value;
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

	private function resolve_schema_variables( mixed $schema ) {
		$post = $this->get_singular_post();
		if ( ! $post ) {
			return $schema;
		}

		$resolved_schema = $this->resolve_schema_value( $schema, $post );
		return $resolved_schema === $this->get_omitted_value() ? [] : $resolved_schema;
	}

	private function get_custom_schema( int $id )
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

	private function get_options_schema( string $post_type, string $location )
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
