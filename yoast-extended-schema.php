<?php

/**
 * Yoast Extended Schema
 *
 * Add a way to add a custom schema on a per-post basis
 *
 * @since             1.0.0
 * @package           OW\YoastExtendedSchema
 *
 * @wordpress-plugin
 * Plugin Name:       Yoast Extended Schema
 * Description:       Add a way to add a custom schema on a per-post basis
 * Version:           1.0.0
 * Requires PHP:      8.0
 * Author:            Owl Watch
 * Author URI:        https://owlwatch.com
 * License:           GPL-3.0
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.txt
 * Text Domain:       ow-yoast-extended-schema
 * Domain Path:       /languages
 * Network:			  true
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OW\YoastExtendedSchema;

function load_plugin_classes() {
	static $loaded = false;
	if ( $loaded ) {
		return;
	}

	$files = [
		__DIR__ . '/src/Contracts/ResolverInterface.php',
		__DIR__ . '/src/Contracts/VariableSourceInterface.php',
		__DIR__ . '/src/Contracts/ModifierInterface.php',
		__DIR__ . '/src/Sources/FieldSource.php',
		__DIR__ . '/src/Sources/MetaSource.php',
		__DIR__ . '/src/Sources/YoastSource.php',
		__DIR__ . '/src/Sources/AuthorSource.php',
		__DIR__ . '/src/Modifiers/WordsModifier.php',
		__DIR__ . '/src/Modifiers/CharsModifier.php',
		__DIR__ . '/src/Modifiers/SchemaDateModifier.php',
		__DIR__ . '/src/Modifiers/CrossReferenceModifier.php',
		__DIR__ . '/src/GraphMerger.php',
		__DIR__ . '/src/AdHoc.php',
	];

	foreach ( $files as $file ) {
		include_once $file;
	}

	$loaded = true;
}

function add_adhoc_schema_piece( array $graph_pieces, \Yoast\WP\SEO\Context\Meta_Tags_Context $context )
{
	load_plugin_classes();
	$graph_pieces[] = new AdHoc();
	return $graph_pieces;
}

add_filter('wpseo_schema_graph_pieces', 'OW\\YoastExtendedSchema\\add_adhoc_schema_piece', 10, 2);

function merge_adhoc_schema( array $graph )
{
	load_plugin_classes();
	return ( new GraphMerger() )->merge( $graph );
}

add_filter( 'wpseo_schema_graph', 'OW\\YoastExtendedSchema\\merge_adhoc_schema', 20, 1 );

function register_options_page()
{
	if ( function_exists( 'acf_add_options_page' ) ) {
		acf_add_options_page([
			'page_title' => 'Yoast Extended Schema',
			'menu_title' => 'Yoast Extended Schema',
			'menu_slug'  => 'yoast-extended-schema',
			'capability' => 'manage_options',
			'redirect'   => false,
		]);
	}
}
add_action( 'acf/init', 'OW\\YoastExtendedSchema\\register_options_page' );

// lets add save_json and load_json 
function acf_json_load( array $paths )
{
	$paths[] = __DIR__ . '/config/acf';
	return $paths;
}
add_filter( 'acf/settings/load_json', 'OW\\YoastExtendedSchema\\acf_json_load', 10, 1);

function acf_json_save( string $path )
{
	// we only want to save the following groups
	return __DIR__ . '/config/acf';
}
add_filter( 'acf/settings/save_json/key=group_6717ac50631cd', 'OW\\YoastExtendedSchema\\acf_json_save', 100, 1);
add_filter( 'acf/settings/save_json/key=group_679b0ad1a1c2e', 'OW\\YoastExtendedSchema\\acf_json_save', 100, 1);
