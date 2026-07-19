<?php
namespace OW\YoastExtendedSchema;

class GraphMerger
{
	private const MERGE_KEY = '$merge';

	public function merge( array $graph ) {
		$base_graph = [];
		$extensions = [];

		foreach ( $graph as $node ) {
			if ( ! is_array( $node ) || ! array_key_exists( self::MERGE_KEY, $node ) ) {
				$base_graph[] = $node;
				continue;
			}

			$should_merge = ! empty( $node[ self::MERGE_KEY ] );
			unset( $node[ self::MERGE_KEY ] );
			if ( ! $should_merge ) {
				$base_graph[] = $node;
				continue;
			}

			$extensions[] = $node;
		}

		foreach ( $extensions as $extension ) {
			$matches = $this->find_matches( $base_graph, $extension );
			if ( count( $matches ) !== 1 ) {
				$base_graph[] = $extension;
				continue;
			}

			$match_index = reset( $matches );
			$base_graph[ $match_index ] = $this->merge_nodes( $base_graph[ $match_index ], $extension );
		}

		return array_values( $base_graph );
	}

	private function find_matches( array $graph, array $extension ) {
		if ( ! empty( $extension['@id'] ) && is_string( $extension['@id'] ) ) {
			return array_keys(
				array_filter(
					$graph,
					static function( mixed $node ) use ( $extension ) {
						return is_array( $node ) && ( $node['@id'] ?? null ) === $extension['@id'];
					}
				)
			);
		}

		$extension_types = $this->normalize_types( $extension['@type'] ?? null );
		if ( empty( $extension_types ) ) {
			return [];
		}

		return array_keys(
			array_filter(
				$graph,
				function( mixed $node ) use ( $extension_types ) {
					if ( ! is_array( $node ) ) {
						return false;
					}

					$node_types = $this->normalize_types( $node['@type'] ?? null );
					return ! empty( array_intersect( $extension_types, $node_types ) );
				}
			)
		);
	}

	private function normalize_types( mixed $types ) {
		if ( is_string( $types ) && $types !== '' ) {
			return [ $types ];
		}

		if ( ! is_array( $types ) ) {
			return [];
		}

		return array_values(
			array_filter(
				$types,
				static function( mixed $type ) {
					return is_string( $type ) && $type !== '';
				}
			)
		);
	}

	private function merge_nodes( array $original, array $extension ) {
		foreach ( $extension as $key => $value ) {
			if (
				isset( $original[ $key ] ) &&
				is_array( $original[ $key ] ) &&
				is_array( $value ) &&
				! $this->is_list_array( $original[ $key ] ) &&
				! $this->is_list_array( $value )
			) {
				$original[ $key ] = $this->merge_nodes( $original[ $key ], $value );
				continue;
			}

			$original[ $key ] = $value;
		}

		return $original;
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
}
