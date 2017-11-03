<?php

	class Codja_ASM_Acf_Free {

		private static $instance = null;

		public static function getInstance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		private function __clone() {}
		private function __construct() {}

		public function getFields( $post_types ) {
			$fields = array();
			$acfFields = $this->getAllFields();

			foreach ( $acfFields as $field ) {
				foreach ( $post_types as $post_type ) {
					if ( $this->checkRules( $field['rules'], $post_type ) ) {
						if ( ! isset( $fields[ $post_type ] ) ) {
							$fields[ $post_type ] = array();
						}

						$fields[ $post_type ] = array_merge( $fields[ $post_type ], $field['fields'] );
					}
				}
			}

			return $fields;
		}

		private function getAllFields() {
			global $wpdb;
			$fields = $wpdb->get_results("SELECT pm.post_id, pm.meta_key, pm.meta_value
											FROM " . $wpdb->prefix . "posts p, " . $wpdb->prefix . "postmeta pm
											WHERE p.post_type = 'acf' AND p.ID = pm.post_id", ARRAY_A);

			$groups = array();

			foreach ( $fields as $field ) {

				if ( ! isset( $groups[$field['post_id']] ) ) {
					$groups[$field['post_id']] = array();
				}

				if ( mb_strpos( $field['meta_key'], 'field_' ) === 0 ) {
					$fieldData = maybe_unserialize( $field['meta_value'] );

					if ( ! isset( $groups[ $field['post_id'] ]['fields'] ) ) {
						$groups[ $field['post_id'] ]['fields'] = array();
					}

					$groups[ $field['post_id'] ]['fields'][] = $fieldData['name'];
				}

				if ( $field['meta_key'] == 'rule' ) {
					$rule = maybe_unserialize( $field['meta_value'] );

					if ( ! isset( $groups[ $field['post_id'] ]['rules'] ) ) {
						$groups[ $field['post_id'] ]['rules'] = array();
					}

					if ( $rule['param'] == 'post_type') {

						$groups[ $field['post_id'] ]['rules'][ $rule['group_no'] ][ $rule['order_no'] ] = array(
							'operator' => $rule['operator'],
							'value' => $rule['value']
						);

					}
				}
			}

			return $groups;
		}

		private function checkRules( $rules_groups, $post_type ) {
			$match = false;

			if ( $rules_groups != false) {
				foreach ( $rules_groups as $rules ) {

					foreach ( $rules as $rule ) {

						$match = $this->checkRule( $rule, $post_type );

						if ( $match === false ) break;

					}

					if ( $match === true ) break;
				}
			}

			return $match;
		}

		private function checkRule( $rule, $post_type ) {
			$match = false;

			if ( $rule['operator'] == '==' ) {
				$match = ( $post_type === $rule['value'] );
			} elseif ( $rule['operator'] == '!=' ) {
				$match = ( $post_type !== $rule['value'] );
			}

			return $match;
		}
	}