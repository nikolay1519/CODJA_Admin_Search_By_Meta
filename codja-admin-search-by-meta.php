<?php

	/**
	 * Plugin Name: CODJA Admin Search By Meta
	 * Description: Search by any meta for any post type
	 * Version: 1.0.0
	 * Author: CODJA
	 * Text Domain: cj-asm
	 * Domain Path: /languages/
	 */

	if ( !defined( 'ABSPATH' ) ) {
		exit;
	}

	define('CJ_ASM_VERSION', '1.0');
	define('CJ_ASM_DIR', plugin_dir_path(__FILE__));
	define('CJ_ASM_URL', plugin_dir_url(__FILE__));

	register_activation_hook( __FILE__, array( 'Codja_Admin_Search_By_Meta', 'activation' ) );
	register_deactivation_hook( __FILE__, array( 'Codja_Admin_Search_By_Meta', 'deactivation' ) );
	register_uninstall_hook(__FILE__, array( 'Codja_Admin_Search_By_Meta', 'uninstall' ) );

	class Codja_Admin_Search_By_Meta {

		private static $instance = null;
		private $settings = array();

		public static function getInstance() {
			if (null === self::$instance) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		private function __clone() {}

		private function __construct() {
			if ( is_admin() ) {
				load_plugin_textdomain( 'cj-asm', false, basename( dirname( __FILE__ ) )  .'/languages' );

				$this->loadSettings();

				add_action( 'admin_menu', array( $this, 'adminMenu' ) );
				add_action( 'manage_posts_extra_tablenav', array( $this, 'printSearchForm' ) );
				add_filter( 'pre_get_posts', array($this, 'preGetPost'));
			}
		}

		public function preGetPost( $query ) {
			if ( ! is_admin() ) return $query;
			if ( ! $query->is_main_query() ) return $query;

			if ( isset( $_GET['cj_asm_s'] ) ) {
				$s = sanitize_text_field( $_GET['cj_asm_s'] );

				if ( $s != false ) {
					$post_type = $query->get('post_type');

					if (
						in_array( $post_type, $this->settings['post_types'] ) &&
					    isset( $this->settings['meta_keys'][ $post_type ] ) &&
					    ! empty( $this->settings['meta_keys'][ $post_type ] )
					) {
						// Add meta_query to global query
						$meta_query = array(
							'relation' => 'OR'
						);

						foreach ( $this->settings['meta_keys'][ $post_type ] as $key ) {
							$meta_query[] = array(
								'key' => $key,
								'value' => $s,
								'compare' => 'LIKE'
							);
						}

						// If meta_query exists merge old and new meta_query
						$meta_query_old = $query->get('meta_query');
						if ( $meta_query_old != false) {
							if ( isset( $meta_query_old['relation'] ) && $meta_query_old['relation'] == 'OR' ) {
								$meta_query_new = $meta_query_old;
								$meta_query_new[] = $meta_query;

								$query->set('meta_query', $meta_query_new);
							} elseif ( isset( $meta_query_old['relation'] ) && $meta_query_old['relation'] == 'AND' ) {
								$meta_query_new = array(
									'relation' => 'OR'
								);

								$meta_query_new[] = $meta_query_old;
								$meta_query_new[] = $meta_query;

								$query->set('meta_query', $meta_query_new);
							} else {
								$meta_query_new = $meta_query_old;
								$meta_query_new['relation'] = 'OR';
								$meta_query_new[] = $meta_query;

								$query->set('meta_query', $meta_query_new);
							}
						} else {
							$query->set('meta_query', $meta_query);
						}
					}
				}
			}

			return $query;
		}

		public function printSearchForm( $which ) {
			if ( $which == 'top' ) {
				$post_type = get_current_screen()->post_type;

				if (
					in_array( $post_type, $this->settings['post_types'] ) &&
					isset( $this->settings['meta_keys'][ $post_type ] ) &&
					! empty( $this->settings['meta_keys'][ $post_type ] )
				) {
					$s = isset($_GET['cj_asm_s']) ? sanitize_text_field( $_GET['cj_asm_s'] ) : '';

					require(CJ_ASM_DIR . '/templates/search_box.php');
				}
			}
		}

		private function getAllFields( $post_types ) {
			// If ACF plugin is active get registred fields
			if ( class_exists( 'acf' ) ) {
				$acfFields = $this->getAcfFields( $post_types );
			}

			// Get setted meta_keys from DB
			$fields = $this->getSimpleFields( $post_types );

			// Merge ACF and simple meta_keys
			if (isset($acfFields)) {
				foreach ($acfFields as $key => $fieldArr) {
					if (isset($fields[$key])) {
						$fields[$key] = array_values(array_unique(array_merge($fields[$key], $fieldArr)));
					} else {
						$fields[$key] = $fieldArr;
					}
				}
			}

			return $fields;
		}

		private function getSimpleFields( $post_types ) {
			global $wpdb;
			$fields = array();

			foreach ( $post_types as $post_type ) {
				$fields[$post_type] = array();
				$meta_keys = $wpdb->get_results("SELECT DISTINCT(meta_key) AS k FROM " .$wpdb->prefix . "postmeta pm
													INNER JOIN " . $wpdb->prefix . "posts p ON p.ID = pm.post_id
													WHERE p.post_type = '".$wpdb->_escape($post_type)."'", ARRAY_A);

				foreach ($meta_keys as $meta_key) {
					// Do not take protected meta_keys (with _ at beginning of the key)
					if (mb_strpos($meta_key['k'], '_') !== 0) {
						$fields[$post_type][] = $meta_key['k'];
					}
				}
			}

			return $fields;
		}

		private function getAcfFields( $post_types ) {
			$fields = array();
			$acfFields = $this->getAllAcfFields();

			foreach ( $acfFields as $field ) {
				foreach ( $post_types as $post_type ) {
					if ( $this->checkAcfRules( $field['rules'], $post_type ) ) {
						if ( ! isset( $fields[ $post_type ] ) ) {
							$fields[ $post_type ] = array();
						}

						$fields[ $post_type ] = array_merge( $fields[ $post_type ], $field['fields'] );
					}
				}
			}

			return $fields;
		}

		private function checkAcfRules( $rules_groups, $post_type ) {
			$match = false;

			if ( $rules_groups != false) {
				foreach ( $rules_groups as $rules ) {

					foreach ( $rules as $rule ) {

						$match = $this->checkAcfRule( $rule, $post_type );

						if ( $match === false ) break;

					}

					if ( $match === true ) break;
				}
			}

			return $match;
		}

		private function checkAcfRule( $rule, $post_type ) {
			$match = false;

			if ( $rule['operator'] == '==' ) {
				$match = ( $post_type === $rule['value'] );
			} elseif ( $rule['operator'] == '!=' ) {
				$match = ( $post_type !== $rule['value'] );
			}

			return $match;
		}

		private function getAllAcfFields() {
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

		public function adminMenu() {
			add_options_page(
				__('Admin Search By Meta', 'cj-asm'),
				__('Admin Search By Meta', 'cj-asm'),
				'manage_options',
				'cj-asm',
				array($this, 'renderSettingsPage')
			);
		}

		public function renderSettingsPage() {
			if ( isset( $_POST['cj_asm_settings'] ) ) {
				if ( current_user_can( 'manage_options' ) ) {
					check_admin_referer( 'cj_asm_settings' );

					$this->saveSettings();
				}
			}

			require_once(CJ_ASM_DIR . '/templates/settings_page.php');
		}

		private function loadSettings() {
			$this->settings = $this->getSettings();
		}

		private function getSettings() {
			return get_option( 'cj_asm_settings' );
		}

		private function updateSettings( $settings ) {
			update_option( 'cj_asm_settings', $settings );
			$this->settings = $settings;
		}

		private function saveSettings() {
			$new_settings = array(
				'post_types' => array(),
				'meta_keys' => array()
			);

			if ( isset( $_POST['cj_asm_settings']['post_types'] ) ) {
				foreach ( $_POST['cj_asm_settings']['post_types'] as $value ) {
					$key = sanitize_key( $value );
					$new_settings['post_types'][] = $key;
					$new_settings['meta_keys'][ $key ] = array();
				}
			}

			if ( isset( $_POST['cj_asm_settings']['meta_keys'] ) ) {
				foreach ( $_POST['cj_asm_settings']['meta_keys'] as $key => $fields ) {
					$post_type = sanitize_key( $key );
					if ( isset( $new_settings['meta_keys'][ $post_type ] ) ) {
						foreach ( $fields as $field ) {
							$meta_key = sanitize_key( $field );
							$new_settings['meta_keys'][ $post_type ][] = $meta_key;
						}
					}
				}
			}

			$this->updateSettings( $new_settings );
		}

		public static function activation() {
			if ( ! current_user_can( 'activate_plugins' ) ) return;

			$defaultSettings = array(
				'post_types' => array( 'post' ),
				'meta_keys' => array(
					'post' => array()
				)
			);

			add_option( 'cj_asm_settings', $defaultSettings );
		}

		public static function deactivation() {}

		public static function uninstall() {
			if ( ! current_user_can( 'activate_plugins' ) ) return;

			delete_option( 'cj_asm_settings' );
		}
	}

	Codja_Admin_Search_By_Meta::getInstance();