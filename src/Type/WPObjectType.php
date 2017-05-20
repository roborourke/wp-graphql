<?php
namespace WPGraphQL\Type;

use ElasticSearch\Exception;
use GraphQL\Type\Definition\ObjectType;
use WPGraphQL\Data\DataSource;
use WPGraphQL\Types;
use WPGraphQL\AppContext;
use GraphQL\Type\Definition\ResolveInfo;

/**
 * Class WPObjectType
 *
 * Object Types should extend this class to take advantage of the helper methods
 * and consistent filters.
 *
 * @package WPGraphQL\Type
 * @since   0.0.5
 */
class WPObjectType extends ObjectType {

	/**
	 * Holds the $prepared_fields definition for the PostObjectType
	 *
	 * @var $fields
	 */
	private static $prepared_fields;

	/**
	 * Holds the node_interface definition allowing WPObjectTypes
	 * to easily define themselves as a node type by implementing
	 * self::$node_interface
	 *
	 * @var $node_interface
	 * @since 0.0.5
	 */
	private static $node_interface;

	/**
	 * WPObjectType constructor.
	 *
	 * @since 0.0.5
	 */
	public function __construct( $config ) {
		parent::__construct( $config );
	}

	/**
	 * node_interface
	 *
	 * This returns the node_interface definition allowing
	 * WPObjectTypes to easily implement the node_interface
	 *
	 * @return array|\WPGraphQL\Data\node_interface
	 * @since 0.0.5
	 */
	public static function node_interface() {

		if ( null === self::$node_interface ) {
			$node_interface       = DataSource::get_node_definition();
			self::$node_interface = $node_interface['nodeInterface'];
		}

		return self::$node_interface;

	}

	/**
	 * prepare_fields
	 *
	 * This function sorts the fields and applies a filter to allow for easily
	 * extending/modifying the shape of the Schema for the type.
	 *
	 * @param array  $fields
	 * @param string $type_name
	 *
	 * @return mixed
	 * @since 0.0.5
	 */
	public static function prepare_fields( $fields, $type_name ) {

		if ( null === self::$prepared_fields ) {
			self::$prepared_fields = [];
		}

		if ( empty( self::$prepared_fields[ $type_name ] ) ) {
			$fields = apply_filters( "graphql_{$type_name}_fields", $fields );
			ksort( $fields );
			self::$prepared_fields[ $type_name ] = $fields;
		}

		return ! empty( self::$prepared_fields[ $type_name ] ) ? self::$prepared_fields[ $type_name ] : null;

	}

	/**
	 * Adds the meta fields for this $object_type registered using
	 * register_meta().
	 *
	 * @param array $fields
	 * @param string $object_type
	 * @return array
	 * @throws Exception If a meta key is the same as a default key warn the dev.
	 */
	public static function add_meta_fields( $fields, $object_type ) {
		// Get registered meta info.
		$meta_keys = get_registered_meta_keys( $object_type );
		if ( ! empty( $meta_keys ) ) {
			foreach ( $meta_keys as $key => $field_args ) {
				if ( isset( $fields[ $key ] ) ) {
					throw new Exception( sprintf( 'Post meta key "%s" is a reserved word.', $key ) );
				}
				if ( ! $field_args['show_in_rest'] ) {
					continue;
				}
				$fields[ $key ] = array(
					'type'        => self::resolve_meta_type( $field_args['type'], $field_args['single'] ),
					'description' => $field_args['description'],
					'resolve'     => function( \WP_Post $post, $args, AppContext $context, ResolveInfo $info ) use ( $key, $field_args ) {
						return get_post_meta( $post->ID, $key, $field_args['single'] );
					},
				);
			}
		}

		return $fields;
	}

	/**
	 * Resolves REST API types to meta data types.
	 *
	 * @param \GraphQL\Type\Definition\AbstractType $type
	 * @param bool $single
	 * @return mixed
	 */
	public static function resolve_meta_type( $type, $single = true ) {
		switch ( $type ) {
			case 'integer':
				$type = Types::int();
				break;
			case 'float':
				$type = Types::float();
				break;
			case 'boolean':
				$type = Types::boolean();
				break;
			default:
				$type = Types::string();
		}

		return $single ? $type : Types::list_of( $type );
	}

}
