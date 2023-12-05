<?php
/**
* Forminator Plugin Validations
*
* @author The Jitu
* @copyright 2023 The Jitu
* @license GPL-2.0-or-later
*
* @wordpress-plugin
* Plugin Name: Forminator Plugin Validations
* Plugin URI: https://github.com/Wyllymk/Forminator-Plugin-Validations
* Description: Plugin which adds support for limit minimum Forminator input fields characters.
* Version: 1.0.0
* Author: The Jitu
* Author URI: https://thejitu.com
* Text Domain: forminator-plugin-validations
* License: GPL v2 or later
* License URI: http://www.gnu.org/licenses/gpl-2.0.txt
*/

if ( ! defined( 'ABSPATH' ) ) { exit; } elseif ( defined( 'WP_CLI' ) && WP_CLI ) { return; }

add_action( 'after_setup_theme', 'wpmudev_forminator_minimum_input_field_length', 100 );

function wpmudev_forminator_minimum_input_field_length() {
	if ( class_exists( 'Forminator' ) ) {
		class WPMUDEV_Forminator_Textarea_Required_Length{
			private $field_ids = ['name-1' => 2, 'name-2' => 2, 'name-3' => 2, 'name-4' => 3, 'phone-1' => 10, 'textarea-1' => 4, 'textarea-2' => 4];// Enter field ids: {field-id} => {minimum characters}, e.g: ["text-1" => 15, "textarea-1" => 30]
			private $form_ids = [];//add list form ids here, default this will apply for all forms
			private $exclude_form_ids = [];//add list exclude form ids here, [345, 456]

			private $form_fields;
			private $activated_on_this_form;
			private $current_form_id;
			private $invalid_input;
			public function __construct()
			{
				add_action( 'forminator_form_post_message', array( $this, 'should_activate_on_this_form') );
				add_filter( 'forminator_custom_form_submit_errors', array( $this, 'check_input_length' ), 10, 3 );
				add_filter( 'forminator_custom_form_invalid_form_message', array( $this, 'custom_message' ) );
				add_filter( 'forminator_field_markup', array( $this, 'maybe_add_min_length_attr'), 10, 2 ); //Supports phone field as well now
			}


			public function get_fields( $form_id ){
				if( ! $this->form_fields ){
					$custom_form = Forminator_Form_Model::model()->load( $form_id );
					$form_fields = $custom_form->get_fields();
					foreach( $form_fields as $field ){
						$this->form_fields[ $field->slug ] = $field->__get('field_label');
					}
				}
				return $this->form_fields;
			}

			public function should_activate_on_this_form( $form_id ){
				$this->current_form_id = $form_id;
				if( ! isset( $this->activated_on_this_form[ $form_id ] ) ){
					$this->activated_on_this_form[ $form_id ] = true;
					if( $this->form_ids ){
						if( ! in_array( $form_id, $this->form_ids ) ){
							$this->activated_on_this_form[ $form_id ] = null;
						}
					}elseif( $this->exclude_form_ids && in_array( $form_id, $this->exclude_form_ids ) ){
						$this->activated_on_this_form[ $form_id ] = null;
					}
				}

				return $this->activated_on_this_form[ $form_id ];
			}

			public function get_field_label( $field_id, $form_id ){
				$form_fields = $this->get_fields( $form_id );
				return isset( $form_fields[ $field_id ] ) ? $form_fields[ $field_id ] : $field_id;
			}

			public function check_input_length( $submit_errors, $form_id, $field_data_array ){
				if( $submit_errors || ! $this->should_activate_on_this_form( $form_id ) ){
					return $submit_errors;
				}

				if( ! empty( $this->field_ids ) ){
					foreach( $field_data_array as $field ){
						if( isset( $this->field_ids[ $field['name'] ] ) && $this->field_ids[ $field['name'] ] > strlen( $field['value'] ) ){
							$submit_errors[ $field['name'] ] = $field['name'];

							$this->invalid_input = array(
								'field' => $this->get_field_label( $field['name'], $form_id ),
								'minlength' => $this->field_ids[ $field['name'] ]
							);
							break;
						}
					}
				}

				return $submit_errors;
			}

			public function maybe_add_min_length_attr( $html, $field ){
				if( $this->current_form_id && isset( $this->activated_on_this_form[ $this->current_form_id ] ) && isset( $this->field_ids[ $field['element_id'] ] ) ){
					$name = 'name="'. $field['element_id'] .'"';
					$html = str_replace( $name, $name .' minlength="'. $this->field_ids[ $field['element_id'] ] .'"', $html );
				}
				return $html;
			}

			public function custom_message($message){
				if( $this->invalid_input ){
					$message = sprintf('You need to insert at least %d characters in the %s field', $this->invalid_input['minlength'], $this->invalid_input['field'] );
					$this->invalid_input = null;
					$this->form_fields = null;
				}
				return $message;
			}
		}
		$run = new WPMUDEV_Forminator_Textarea_Required_Length;
	}
}