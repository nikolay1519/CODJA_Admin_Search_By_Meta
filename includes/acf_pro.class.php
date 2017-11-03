<?php

	class Codja_ASM_Acf_Pro {

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
			$groups = $this->getGroups();

			if ( $groups != false ) {
				foreach ( $groups as $group_id => $group ) {
					foreach ( $post_types as $post_type ) {
						if ( isset( $group['rules'] ) && $this->checkRules( $group['rules'], $post_type ) ) {
							if ( ! isset( $fields[ $post_type ] ) ) {
								$fields[ $post_type ] = array();
							}

							$fields[ $post_type ] = array_merge( $fields[ $post_type ], $group['fields'] );
						}
					}
				}
			}

			return $fields;
		}

		private function getGroups() {
			global $wpdb;

			$groups = array();

			$posts = $wpdb->get_results("SELECT ID, post_content FROM " . $wpdb->prefix . "posts
											WHERE post_type = 'acf-field-group' AND post_status = 'publish'");

			if ($posts != false) {
				foreach ($posts as $post) {
					$options = maybe_unserialize($post->post_content);
					$rule_groups = $options['location'];

					foreach ( $rule_groups as $group_no => $rules ) {
						if ( ! isset( $groups[ $post->ID ]['fields'] ) ) {
							$groups[ $post->ID ]['fields'] = array();
						}

						foreach ( $rules as $rule_no => $rule ) {
							if ( $rule['param'] == 'post_type' ) {
								$groups[ $post->ID ]['rules'][ $group_no ][ $rule_no ] = array(
									'operator' => $rule['operator'],
									'value' => $rule['value']
								);
							}
						}
					}
				}

				if ( $groups != false ) {
					$posts = $wpdb->get_results("SELECT ID, post_excerpt AS field, post_parent AS group_id
													FROM " . $wpdb->prefix . "posts
													WHERE post_type = 'acf-field' AND post_parent IN (".implode( ', ', array_keys( $groups ) ).")");

					if ( $posts != false ) {
						foreach ( $posts as $post ) {
							if ( isset( $groups[ $post->group_id ] ) ) {
								$groups[ $post->group_id ]['fields'][] = $post->field;
							}
						}
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

		private function getFieldsOfGroup( $group_id ) {
			global $wpdb;
			$fields = array();

			$posts = $wpdb->get_results("SELECT ID, post_excerpt AS field FROM " . $wpdb->prefix . "posts WHERE post_type = 'acf-field' AND post_parent = ".intval($group_id));

			if ($posts != false) {
				foreach ($posts as $post) {
					$fields[] = $post->field;
				}
			}

			return $fields;
		}
	}