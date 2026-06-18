<?php

namespace BalanceTesting\Migration\CopyToExcercise;

use BalanceTesting\Exercise\ExerciseIdentifier;

defined( 'ABSPATH' ) || exit;

/**
 * Copies a test post and all of its meta/taxonomies into excercise.
 */
class PostCopier {

	/**
	 * Meta keys that must not be copied to the new post.
	 *
	 * @var string[]
	 */
	private const EXCLUDED_META_KEYS = array(
		'_edit_lock',
		'_edit_last',
		'_wp_old_slug',
		'_wp_trash_meta_status',
		'_wp_trash_meta_time',
		'_bt_copied_from_test_id',
	);

	/**
	 * @param int $test_id Source test post ID.
	 * @return int|\WP_Error New excercise post ID.
	 */
	public function copy( int $test_id ) {
		$source = get_post( $test_id );

		if ( ! $source || 'test' !== $source->post_type ) {
			return new \WP_Error( 'bt_invalid_source', __( 'Invalid test post.', 'balance-testing' ) );
		}

		$exercise_id = wp_insert_post(
			array(
				'post_type'    => 'excercise',
				'post_status'  => 'draft',
				'post_title'   => $source->post_title,
				'post_content' => $source->post_content,
				'post_excerpt' => $source->post_excerpt,
				'menu_order'   => (int) $source->menu_order,
				'post_author'  => get_current_user_id() ?: (int) $source->post_author,
			),
			true
		);

		if ( is_wp_error( $exercise_id ) ) {
			return $exercise_id;
		}

		$acf_field_names = $this->get_acf_field_names( $test_id );

		$this->copy_taxonomies( $test_id, (int) $exercise_id );
		$this->copy_meta( $test_id, (int) $exercise_id, $acf_field_names );
		$this->copy_featured_image( $test_id, (int) $exercise_id );
		$this->copy_acf_fields( $test_id, (int) $exercise_id );

		update_post_meta( (int) $exercise_id, '_bt_copied_from_test_id', $test_id );

		$identifier = ExerciseIdentifier::get( $test_id );
		if ( '' !== $identifier ) {
			ExerciseIdentifier::set( (int) $exercise_id, $identifier );
		}

		return (int) $exercise_id;
	}

	/**
	 * Copy every taxonomy term currently assigned to the source post.
	 *
	 * @param int $from_id Source post ID.
	 * @param int $to_id   Target post ID.
	 */
	private function copy_taxonomies( int $from_id, int $to_id ): void {
		$taxonomies = get_post_taxonomies( $from_id );

		if ( empty( $taxonomies ) ) {
			return;
		}

		foreach ( $taxonomies as $taxonomy ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}

			$terms = wp_get_object_terms(
				$from_id,
				$taxonomy,
				array(
					'fields' => 'ids',
				)
			);

			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				continue;
			}

			wp_set_object_terms( $to_id, array_map( 'intval', $terms ), $taxonomy, false );
		}
	}

	/**
	 * @param int      $from_id         Source post ID.
	 * @param int      $to_id           Target post ID.
	 * @param string[] $acf_field_names ACF field names handled separately.
	 */
	private function copy_meta( int $from_id, int $to_id, array $acf_field_names ): void {
		$meta = get_post_meta( $from_id );

		if ( ! is_array( $meta ) ) {
			return;
		}

		$acf_keys = $this->get_acf_reference_keys( $acf_field_names );

		foreach ( $meta as $meta_key => $values ) {
			if ( in_array( $meta_key, self::EXCLUDED_META_KEYS, true ) ) {
				continue;
			}

			if ( in_array( $meta_key, $acf_field_names, true ) || in_array( $meta_key, $acf_keys, true ) ) {
				continue;
			}

			if ( 0 === strpos( $meta_key, '_oembed_' ) ) {
				continue;
			}

			foreach ( (array) $values as $value ) {
				add_post_meta( $to_id, $meta_key, maybe_unserialize( $value ) );
			}
		}
	}

	/**
	 * @param int $from_id Source post ID.
	 * @param int $to_id   Target post ID.
	 */
	private function copy_featured_image( int $from_id, int $to_id ): void {
		$thumbnail_id = (int) get_post_thumbnail_id( $from_id );

		if ( $thumbnail_id > 0 ) {
			set_post_thumbnail( $to_id, $thumbnail_id );
		}
	}

	/**
	 * Copy all ACF fields from field groups attached to the test post type.
	 *
	 * @param int $from_id Source post ID.
	 * @param int $to_id   Target post ID.
	 */
	private function copy_acf_fields( int $from_id, int $to_id ): void {
		if ( ! function_exists( 'get_field' ) || ! function_exists( 'update_field' ) ) {
			return;
		}

		$fields = $this->get_acf_fields_for_post( $from_id );

		foreach ( $fields as $field ) {
			$this->copy_acf_field( $field, $from_id, $to_id );
		}
	}

	/**
	 * @param array<string, mixed> $field   ACF field array.
	 * @param int                  $from_id Source post ID.
	 * @param int                  $to_id   Target post ID.
	 */
	private function copy_acf_field( array $field, int $from_id, int $to_id ): void {
		if ( empty( $field['name'] ) ) {
			return;
		}

		if ( ! empty( $field['sub_fields'] ) && is_array( $field['sub_fields'] ) ) {
			foreach ( $field['sub_fields'] as $sub_field ) {
				$this->copy_acf_field( $sub_field, $from_id, $to_id );
			}
			return;
		}

		$value = get_field( $field['name'], $from_id, false );

		if ( null === $value || false === $value || '' === $value || array() === $value ) {
			return;
		}

		if ( ! empty( $field['key'] ) ) {
			update_field( $field['key'], $value, $to_id );
			return;
		}

		update_field( $field['name'], $value, $to_id );
	}

	/**
	 * @param int $post_id Post ID.
	 * @return string[] Field names.
	 */
	private function get_acf_field_names( int $post_id ): array {
		$names  = array();
		$fields = $this->get_acf_fields_for_post( $post_id );

		foreach ( $fields as $field ) {
			if ( ! empty( $field['name'] ) ) {
				$names[] = (string) $field['name'];
			}
		}

		return array_values( array_unique( $names ) );
	}

	/**
	 * @param string[] $field_names ACF field names.
	 * @return string[] Reference meta keys (e.g. _images).
	 */
	private function get_acf_reference_keys( array $field_names ): array {
		$keys = array();

		foreach ( $field_names as $field_name ) {
			$keys[] = '_' . $field_name;
		}

		return $keys;
	}

	/**
	 * @param int $post_id Post ID.
	 * @return array<int, array<string, mixed>>
	 */
	private function get_acf_fields_for_post( int $post_id ): array {
		if ( ! function_exists( 'acf_get_field_groups' ) || ! function_exists( 'acf_get_fields' ) ) {
			return array();
		}

		$groups = acf_get_field_groups(
			array(
				'post_type' => 'test',
			)
		);

		$fields = array();

		foreach ( $groups as $group ) {
			if ( empty( $group['key'] ) ) {
				continue;
			}

			$group_fields = acf_get_fields( $group['key'] );

			if ( ! is_array( $group_fields ) ) {
				continue;
			}

			$fields = array_merge( $fields, $group_fields );
		}

		if ( empty( $fields ) && function_exists( 'get_field_objects' ) ) {
			$objects = get_field_objects( $post_id, false );

			if ( is_array( $objects ) ) {
				$fields = array_values( $objects );
			}
		}

		return $fields;
	}
}
