<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'CR_Review_Discount_Settings' ) ):

	class CR_Review_Discount_Settings {

		/**
		* @var CR_Settings_Admin_Menu The instance of the settings admin menu
		*/
		protected $settings_menu;

		/**
		* @var string The slug of this tab
		*/
		protected $tab;

		/**
		* @var array The fields for this tab
		*/
		protected $settings;

		public function __construct( $settings_menu ) {
			$this->settings_menu = $settings_menu;

			$this->tab = 'review_discount';

			add_filter( 'ivole_settings_tabs', array( $this, 'register_tab' ) );
			add_action( 'ivole_settings_display_' . $this->tab, array( $this, 'display' ) );
			add_action( 'ivole_save_settings_' . $this->tab, array( $this, 'save' ) );
			add_action( 'admin_head', array( $this, 'add_admin_js' ) );

			add_action( 'woocommerce_admin_field_htmltext_coupon', array( $this, 'show_htmltext_coupon' ) );
			add_action( 'woocommerce_admin_field_coupon_tiers_table', array( 'CR_Discount_Tiers', 'show_coupon_tiers_table' ) );

			// array_filter with one argument will filter empty values from the array
			add_action( 'woocommerce_admin_settings_sanitize_option_ivole_coupon__product_ids', 'array_filter', 10, 1 );
			add_action( 'woocommerce_admin_settings_sanitize_option_ivole_coupon__exclude_product_ids', 'array_filter', 10, 1 );
			add_action( 'woocommerce_admin_settings_sanitize_option_ivole_email_body_coupon', array( $this, 'save_htmltext_coupon' ), 10, 3 );
			add_action( 'woocommerce_admin_settings_sanitize_option_ivole_coupon_tiers', array( 'CR_Discount_Tiers', 'save_coupon_tiers_table' ), 10, 3 );

			add_action( 'wp_ajax_woocommerce_json_search_coupons', array( $this, 'woocommerce_json_search_coupons' ) );
			add_action( 'wp_ajax_ivole_send_test_email_coupon', array( $this, 'send_test_email' ) );

			add_action( 'views_edit-shop_coupon', array( $this, 'coupons_quick_link' ), 20 );
			add_filter( 'parse_query', array( $this, 'coupons_quick_link_filter'), 20 );
		}

		public function register_tab( $tabs ) {
			$tabs[$this->tab] = __( 'Review for Discount', 'customer-reviews-woocommerce' );
			return $tabs;
		}

		public function display() {
			$this->init_settings();

			WC_Admin_Settings::output_fields( $this->settings );
		}

		public function save() {
			$this->init_settings();

			if( ! empty( $_POST ) && isset( $_POST['ivole_email_body_coupon'] ) ) {
				if( empty( preg_replace( '#\s#isUu', '', html_entity_decode( $_POST['ivole_email_body_coupon'] ) ) ) ) {
					WC_Admin_Settings::add_error( __( '\'Email Body\' field cannot be empty', 'customer-reviews-woocommerce' ) );
					$_POST['ivole_email_body_coupon'] = get_option( 'ivole_email_body_coupon' );
				}
			}

			WC_Admin_Settings::save_fields( $this->settings );
		}

		protected function init_settings() {
			$tmp_terms = sprintf(
				__( 'Customize CusRev settings for sending discount coupons to customers who left reviews. By enabling and using this feature, you agree to the <a href="%s" target="_blank">terms and conditions</a>.', 'customer-reviews-woocommerce' ),
				'https://www.cusrev.com/terms.html'
			);
			$this->settings = array(
				array(
					'title' => __( 'Review for Discount', 'customer-reviews-woocommerce' ),
					'type' => 'title',
					'desc' => $tmp_terms,
					'id' => 'ivole_coupon_options_selector'
				),
				array(
					'title'   => __( 'Enable Review for Discount', 'customer-reviews-woocommerce' ),
					'desc'    => __( 'Enable generation of discount coupons for customers who provide reviews.', 'customer-reviews-woocommerce' ),
					'id'      => 'ivole_coupon_enable',
					'default' => 'no',
					'type'    => 'checkbox'
				),
				array(
					'title'    => __( 'BCC Address', 'customer-reviews-woocommerce' ),
					'type'     => 'text',
					'desc'     => __( 'Add a BCC recipient for emails with discount coupon. It can be useful to verify that emails are being sent properly.', 'customer-reviews-woocommerce' ),
					'default'  => '',
					'id'       => 'ivole_coupon_email_bcc',
					'css'      => 'min-width:300px;',
					'desc_tip' => true
				),
				array(
					'title'    => __( 'Reply-To Address', 'customer-reviews-woocommerce' ),
					'type'     => 'text',
					'desc'     => __( 'Add a Reply-To address for emails with discount coupons. If customers decide to reply to automatic emails, their replies will be sent to this address.', 'customer-reviews-woocommerce' ),
					'default'  => get_option( 'admin_email' ),
					'id'       => 'ivole_coupon_email_replyto',
					'css'      => 'min-width:300px;',
					'desc_tip' => true
				),
				array(
					'id'       => 'ivole_coupon_tiers',
					'type'     => 'coupon_tiers_table'
				),
				array(
					'type' => 'sectionend',
					'id'   => 'ivole_coupon_options_selector'
				),
				array(
					'title' => __( 'Email Template', 'customer-reviews-woocommerce' ),
					'type'  => 'title',
					/* translators: %s is a special symbol that will be replaced with the name of the website; please keep it as is */
					'desc'  => sprintf( __( 'Adjust template of the email that will be sent to customers. If you enable <b>advanced</b> email templates in your account on %s website, they will <b>override</b> the email template below.', 'customer-reviews-woocommerce' ), '<a href="https://www.cusrev.com/login.html" target="_blank" rel="noopener noreferrer">CusRev</a>' ),
					'id'    => 'ivole_options_email_coupon'
				),
				array(
					'title'    => __( 'Email Subject', 'customer-reviews-woocommerce' ),
					'type'     => 'text',
					'desc'     => __( 'Subject of the email that will be sent to customers.', 'customer-reviews-woocommerce' ),
					'default'  => '[{site_title}] Discount Coupon for You',
					'id'       => 'ivole_email_subject_coupon',
					'class'    => 'cr-admin-settings-wide-text',
					'desc_tip' => true
				),
				array(
					'title'    => __( 'Email Heading', 'customer-reviews-woocommerce' ),
					'type'     => 'text',
					'desc'     => __( 'Heading of the email that will be sent to customers.', 'customer-reviews-woocommerce' ),
					'default'  => 'Thank You for Leaving a Review',
					'id'       => 'ivole_email_heading_coupon',
					'class'    => 'cr-admin-settings-wide-text',
					'desc_tip' => true
				),
				array(
					'title'    => __( 'Email Body', 'customer-reviews-woocommerce' ),
					'type'     => 'htmltext_coupon',
					'desc'     => __( 'Body of the email that will be sent to customers.', 'customer-reviews-woocommerce' ),
					'id'       => 'ivole_email_body_coupon',
					'desc_tip' => true
				),
				array(
					'title' => __( 'Email Color 1', 'customer-reviews-woocommerce' ),
					'type' => 'text',
					'id' => 'ivole_email_coupon_color_bg',
					'default' => '#0f9d58',
					'desc' => __( 'Background color for heading of the email and review button.', 'customer-reviews-woocommerce' ),
					'desc_tip' => true
				),
				array(
					'title'    => __( 'Email Color 2', 'customer-reviews-woocommerce' ),
					'type'     => 'text',
					'id'       => 'ivole_email_coupon_color_text',
					'default'  => '#ffffff',
					'desc'     => __( 'Text color for heading of the email and review button.', 'customer-reviews-woocommerce' ),
					'desc_tip' => true
				),
				array(
					'type' => 'sectionend',
					'id' => 'ivole_options_email_coupon'
				),
				array(
					'title' => __( 'Email Testing', 'customer-reviews-woocommerce' ),
					'type'  => 'title',
					/* translators: %s is a special symbol that will be replaced with the name of the website; please keep it as is */
					'desc'  => sprintf( __( 'Send a test email to verify settings for discount coupons. If you enable <b>advanced</b> email templates in your account on %s website, they will <b>override</b> the email template above.', 'customer-reviews-woocommerce' ), '<a href="https://www.cusrev.com/login.html" target="_blank" rel="noopener noreferrer">CusRev</a>' ),
					'id'    => 'cr_options_email_coupon_test'
				),
				array(
					'title'       => __( 'Photos/videos uploaded', 'customer-reviews-woocommerce' ),
					'type'        => 'select',
					'is_option'		=> false,
					'desc'        => __( 'Simulate sending of different coupons depending on how many photos/videos a customer attached to their review. This field can be changed without saving changes.', 'customer-reviews-woocommerce' ),
					'default'     => '0',
					'is_option' 	=> false,
					'id'          => 'cr_email_test_media_count',
					'css'         => 'width:100px;',
					'desc_tip'    => true,
					'options'			=> array(
						'0' => '0',
						'1' => '1',
						'2' => '2',
						'3' => '3',
						'4' => '4',
						'5' => '5'
					)
				),
				array(
					'title'       => __( 'Send Test To', 'customer-reviews-woocommerce' ),
					'type'        => 'emailtest',
					'desc'        => __( 'Send a test email to this address. You must save changes before sending a test email.', 'customer-reviews-woocommerce' ),
					'default'     => '',
					'placeholder' => 'Email address',
					'id'          => 'ivole_email_test_coupon',
					'css'         => 'min-width:300px;',
					'desc_tip'    => true
				),
				array(
					'type' => 'sectionend',
					'id' => 'cr_options_email_coupon_test'
				),
			);
		}

		public function add_admin_js() {
			if ( $this->settings_menu->is_this_page() && $this->settings_menu->get_current_tab() === 'review_reminder' ){
				// add warning text about coupons dynamically above the email body
				$is_coupon_enabled = WC_Admin_Settings::get_option( 'ivole_coupon_enable' );
				?>
				<style>
				li.select2-selection__choice[title=""] {
					display: none;
				}
				#coupon_notification > td{
					padding:0 !important;
				}
				#coupon_notification > td span{
					max-width: 680px !important;
					padding:10px;
					margin-left:10px;
					display:inline-block;
					background-color: #ffff00;
				}
				</style>
				<script type="text/javascript">
				var coupon_notification_html = "<tr valign='top' <?php if ( $is_coupon_enabled != 'yes' ) echo "style='display:none;'"; ?> id='coupon_notification'><th></th><td><span><strong>";
				coupon_notification_html += "<?php echo __( 'Discounts for customers who provide reviews are enabled. Don’t forget to mention it in this email to increase the number of reviews.', 'customer-reviews-woocommerce' ) ?>";
				coupon_notification_html += "</strong></span></td></tr>";

				jQuery(document).ready(function(){
					jQuery('#ivole_email_heading').parent().parent().after(coupon_notification_html);
				});
				</script>
				<?php
			} elseif ( $this->is_this_tab() ) {
				?>
				<style>
				li.select2-selection__choice[title=""] {
					display: none;
				}
				</style>
				<script id='cr-coupon-scripts' type="text/javascript">
				jQuery(document).ready(function(){

					jQuery('#mainform').submit(function(){
						let returnValue = true;
						jQuery('.cr-coupon-to-use').each( function() {
							if( false === checkExistingCoupon( jQuery( this ) ) ) {
								returnValue = false;
							}
						});
						return returnValue;
					});

					jQuery('.cr-coupon-to-use').each( function() {
						updateTiers( jQuery( this ) )
					});

					jQuery('.cr-coupon-to-use').on('change', function() {
						updateTiers( jQuery( this ) );
					});

					// display or hide fields of discount tiers depending on the coupon type
					function updateTiers( reference ) {
						let tierClasses = reference.closest('td.cr-coupon-tiers-table-td').attr('class');
						tierClasses = '.' + tierClasses.replace(/\s/g, '.');
						switch ( reference.val() ) {
							case 'static':
								jQuery('.cr-coupon-tiers-table .cr-coupon-settings' + tierClasses).removeClass('cr-no-coupon');
								jQuery('.cr-coupon-tiers-table .cr-coupon-settings' + tierClasses).removeClass('cr-new-coupon');
								jQuery('.cr-coupon-tiers-table .cr-coupon-settings' + tierClasses).addClass('cr-existing-coupon');
								break;
							case 'dynamic':
								jQuery('.cr-coupon-tiers-table .cr-coupon-settings' + tierClasses).removeClass('cr-no-coupon');
								jQuery('.cr-coupon-tiers-table .cr-coupon-settings' + tierClasses).removeClass('cr-existing-coupon');
								jQuery('.cr-coupon-tiers-table .cr-coupon-settings' + tierClasses).addClass('cr-new-coupon');
								break;
							default:
								jQuery('.cr-coupon-tiers-table .cr-coupon-settings' + tierClasses).removeClass('cr-new-coupon');
								jQuery('.cr-coupon-tiers-table .cr-coupon-settings' + tierClasses).removeClass('cr-existing-coupon');
								jQuery('.cr-coupon-tiers-table .cr-coupon-settings' + tierClasses).addClass('cr-no-coupon');
								break;
						}
					};

					// trigger an error when no existing coupon is specified when saving the settings
					function checkExistingCoupon( reference ) {
						if ( reference.val() == 'static' && jQuery('#ivole_coupon_enable:checked').length > 0 ) {
							let tierClasses = reference.closest('td.cr-coupon-tiers-table-td').prop('class');
							tierClasses = '.' + tierClasses.replace(/\s/g, '.');
							let referenceName = reference.prop('name');
							referenceName = referenceName.slice(referenceName.length - 1);
							var v = jQuery('.cr-coupon-tiers-table .cr-coupon-settings' + tierClasses + ' .cr-existing-coupon-field select').val();
							if (parseInt(v) + '' != v + '' || parseInt(v) == 0) {
								let errorMessage = "<?php echo __( 'Please select an existing coupon for Discount Tier %s.', 'customer-reviews-woocommerce' ); ?>";
								errorMessage = errorMessage.replace(/%s/g, referenceName);
								alert( errorMessage );
								return false;
							}
						}
						return true;
					};

				});

				jQuery( function( $ ) {
					function getEnhancedSelectFormatString() {
						return {
							'language': {
								errorLoading: function() {
									// Workaround for https://github.com/select2/select2/issues/4355 instead of i18n_ajax_error.
									return wc_enhanced_select_params.i18n_searching;
								},
								inputTooLong: function(args) {
									var overChars = args.input.length - args.maximum;
									if (1 === overChars) {
										return wc_enhanced_select_params.i18n_input_too_long_1;
									}

									return wc_enhanced_select_params.i18n_input_too_long_n.replace('%qty%', overChars);
								},
								inputTooShort: function(args) {
									var remainingChars = args.minimum - args.input.length;

									if (1 === remainingChars) {
										return wc_enhanced_select_params.i18n_input_too_short_1;
									}

									return wc_enhanced_select_params.i18n_input_too_short_n.replace('%qty%', remainingChars);
								},
								loadingMore: function() {
									return wc_enhanced_select_params.i18n_load_more;
								},
								maximumSelected: function(args) {
									if (args.maximum === 1) {
										return wc_enhanced_select_params.i18n_selection_too_long_1;
									}

									return wc_enhanced_select_params.i18n_selection_too_long_n.replace('%qty%', args.maximum);
								},
								noResults: function() {
									return wc_enhanced_select_params.i18n_no_matches;
								},
								searching: function() {
									return wc_enhanced_select_params.i18n_searching;
								}
							}
						};
					}

					try {
						// Ajax coupon search box
						$(':input.wc-coupon-search').filter(':not(.enhanced)').each(function () {
							var select2_args = {
								allowClear: $(this).data('allow_clear') ? true : false,
								placeholder: $(this).data('placeholder'),
								minimumInputLength: $(this).data('minimum_input_length') ? $(this).data('minimum_input_length') : '3',
								escapeMarkup: function (m) {
									return m;
								},
								ajax: {
									url: wc_enhanced_select_params.ajax_url,
									dataType: 'json',
									delay: 250,
									data: function (params) {
										return {
											term: params.term,
											action: $(this).data('action') || 'woocommerce_json_search_coupons',
											security: wc_enhanced_select_params.search_products_nonce,
											exclude: $(this).data('exclude'),
											include: $(this).data('include'),
											limit: $(this).data('limit')
										};
									},
									processResults: function (data) {
										var terms = [];
										if (data) {
											$.each(data, function (id, text) {
												terms.push({id: id, text: text});
											});
										}
										return {
											results: terms
										};
									},
									cache: true
								}
							};

							select2_args = $.extend(select2_args, getEnhancedSelectFormatString());

							$(this).select2(select2_args).addClass('enhanced');

							if ($(this).data('sortable')) {
								var $select = $(this);
								var $list = $(this).next('.select2-container').find('ul.select2-selection__rendered');

								$list.sortable({
									placeholder: 'ui-state-highlight select2-selection__choice',
									forcePlaceholderSize: true,
									items: 'li:not(.select2-search__field)',
									tolerance: 'pointer',
									stop: function () {
										$($list.find('.select2-selection__choice').get().reverse()).each(function () {
											var id = $(this).data('data').id;
											var option = $select.find('option[value="' + id + '"]')[0];
											$select.prepend(option);
										});
									}
								});
							}
						});
					} catch(err) {
						// If select2 failed (conflict?) log the error but don't stop other scripts breaking.
						window.console.log( err );
					}
				});
				</script>
				<?php
			}
		}

		public function is_this_tab() {
			return $this->settings_menu->is_this_page() && ( $this->settings_menu->get_current_tab() === $this->tab );
		}

		/**
		* Custom field type for body email
		*/
		public function show_htmltext_coupon( $value ) {
			$tmp = Ivole_Admin::ivole_get_field_description( $value );
			$tooltip_html = $tmp['tooltip_html'];
			$description = $tmp['description'];
			$default_text = Ivole_Email::$default_body_coupon;

			$body = wp_kses_post( WC_Admin_Settings::get_option( $value['id'], $default_text ) );
			$settings = array (
				'teeny'         => true,
				'editor_css'    => '<style>#wp-ivole_email_body_coupon-wrap {max-width: 700px !important;}</style>',
				'textarea_rows' => 20
			);
			?>
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label for="<?php echo esc_attr( $value['id'] ); ?>"><?php echo esc_html( $value['title'] ); ?></label>
					<?php echo $tooltip_html; ?>
				</th>
				<td class="forminp forminp-<?php echo sanitize_title( $value['type'] ) ?>">
					<?php echo $description; ?>
					<?php wp_editor( $body, 'ivole_email_body_coupon', $settings );
					echo '<div>';
					echo '<p style="font-weight:bold;margin-top:1.5em;font-size=1em;">' . __( 'Variables', 'customer-reviews-woocommerce' ) . '</p>';
					echo '<p>' . __( 'You can use the following variables in the email:', 'customer-reviews-woocommerce' ) . '</p>';
					echo '<p><strong>{site_title}</strong> - ' . __( 'The title of your WordPress website.', 'customer-reviews-woocommerce' ) . '</p>';
					echo '<p><strong>{customer_first_name}</strong> - ' . __( 'The first name of the customer who purchased from your store.', 'customer-reviews-woocommerce' ) . '</p>';
					echo '<p><strong>{customer_last_name}</strong> - ' . __( 'The last name of the customer who purchased from your store.', 'customer-reviews-woocommerce' ) . '</p>';
					echo '<p><strong>{customer_name}</strong> - ' . __( 'The full name of the customer who purchased from your store.', 'customer-reviews-woocommerce' ) . '</p>';
					echo '<p><strong>{coupon_code}</strong> - ' . __( 'The code of coupon for discount.', 'customer-reviews-woocommerce' ).'</p>';
					echo '<p><strong>{discount_amount}</strong> - ' . __( 'Amount of the coupon (e.g., $10 or 11% depending on type of the coupon).', 'customer-reviews-woocommerce' ).'</p>';
					echo '</div>';
					?>
				</td>
			</tr>
			<?php
		}

		/**
		* Custom field type for body of coupon email
		*/
		public function save_htmltext_coupon( $value, $option, $raw_value ) {
			return wp_kses_post( $raw_value );
		}

		/**
		* Show a quick link to coupons generated by this plugin on the standard WooCommerce coupons admin page.
		*/
		public function coupons_quick_link( $views ) {
			global $wp_query;

			if ( is_admin() ) {
				$query = array(
					'post_type'    => 'shop_coupon',
					'post_status'  => array( 'publish' ),
					'meta_key'     => '_ivole_auto_generated',
					'meta_value'   => 1,
					'meta_compare' => 'NOT EXISTS'
				);

				$result = new WP_Query( $query );

				if ( $result->found_posts > 0 ) {
					$class = ( '_ivole_auto_generated' == $wp_query->query_vars['meta_key'] ) ? ' class="current"' : '';
					$views['ivole'] = '<a href="' . admin_url( 'edit.php?post_type=shop_coupon&ivole_coupon=0' ) . '"' . $class . '>' . __( 'Manually Published', 'customer-reviews-woocommerce' ) . ' <span class="count">(' . $result->found_posts . ')</span></a>';
				}
			}

			return $views;
		}

		/**
		* Parse "ivole_coupon" GET parameter and adjust WP Query to show only coupons generated by this plugin.
		*/
		public function coupons_quick_link_filter( $query ) {
			if ( is_admin() && array_key_exists( 'post_type', $query->query ) && 'shop_coupon' == $query->query['post_type'] ) {
				$qv = &$query->query_vars;
				if ( isset( $_GET['ivole_coupon'] ) ) {
					$qv['post_status'] = 'publish';
					$qv['meta_key'] = '_ivole_auto_generated';
					$qv['meta_value'] = 1;
					$qv['meta_compare'] = 'NOT EXISTS';
				}
			}
		}

		/**
		* Ajax action callback for enhanced select box for existing coupuns
		*/
		public function woocommerce_json_search_coupons(){
			global $wpdb;

			$term = stripslashes( $_GET['term'] . '%' );
			if ( empty( $term ) ) {
				wp_die();
			}

			$data_store = WC_Data_Store::load( 'coupon' );
			$all = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM $wpdb->posts
					WHERE post_title LIKE %s AND post_type = 'shop_coupon' AND post_status = 'publish'
					ORDER BY post_date DESC;",
					$term
				),
				ARRAY_A
			);

			$coupons = array();
			$today = time();
			foreach ( $all as $coupon ) {
				$expires = get_post_meta( $coupon['ID'], 'date_expires', true );
				$email_array = get_post_meta( $coupon['ID'], 'customer_email', true );
				if ( ( intval( $expires ) > $today || intval( $expires ) == 0 ) &&
				( ! is_array( $email_array ) || count( $email_array ) == 0 ) ) {
					$coupons[ $coupon['ID'] ] = rawurldecode( stripslashes( $coupon['post_title'] ) );
				}
			}

			wp_send_json( $coupons );
		}

		/**
		* Ajax callback  that sends testing email
		*/
		public function send_test_email() {
			global $q_config;

			$email = strval( $_POST['email'] );
			$media_count = intval( $_POST['media_count'] );
			$q_language = $_POST['q_language'];
			// integration with qTranslate
			if ( $q_language >= 0 ) {
				$q_config['language'] = $q_language;
			}

			$db_settings = get_option( 'ivole_coupon_tiers', false );
			if( $db_settings and is_array( $db_settings ) ) {
				$tier_w_coupon = 0;
				$compare_count = -1;
				foreach( CR_Discount_Tiers::$tiers as $tier ) {
					if( in_array( $db_settings[CR_Discount_Tiers::$tiers_settings['cr_coupon_type']][$tier], array( 'dynamic', 'static' ) ) &&
				 		$media_count >= $db_settings[CR_Discount_Tiers::$tiers_settings['cr_media_count']][$tier] &&
						$compare_count < $db_settings[CR_Discount_Tiers::$tiers_settings['cr_media_count']][$tier] ) {
							$compare_count = $db_settings[CR_Discount_Tiers::$tiers_settings['cr_media_count']][$tier];
							$tier_w_coupon = $tier;
					}
				}
				if( 0 < $tier_w_coupon ) {
					$discount_string = '';
					$coupon_type = $db_settings[CR_Discount_Tiers::$tiers_settings['cr_coupon_type']][$tier_w_coupon];
					if ( $coupon_type === 'static' ) {
						$coupon_id = intval( $db_settings[CR_Discount_Tiers::$tiers_settings['cr_existing_coupon']][$tier_w_coupon] );
						if ( get_post_type( $coupon_id ) == 'shop_coupon' && get_post_status( $coupon_id ) == 'publish' ) {
							$coupon_code = get_post_field( 'post_title', $coupon_id );
							$discount_type = get_post_meta( $coupon_id, 'discount_type', true );
							$discount_amount = intval( get_post_meta( $coupon_id, 'coupon_amount', true ) );
							if ( $discount_type == 'percent' ) {
								$discount_string = $discount_amount . '%';
							} else {
								$discount_string = trim( strip_tags( CR_Email_Func::cr_price( $discount_amount,  array( 'currency' => get_option( 'woocommerce_currency' ) ) ) ) );
							}
						} else {
							$coupon_code = "<strong>NO_COUPON_SET</strong>";
							$discount_string = "<strong>NO_AMOUNT_SET</strong>";
						}
					} else {
						$discount_type = $db_settings[CR_Discount_Tiers::$tiers_settings['cr_coupon__discount_type']][$tier_w_coupon];
						$discount_amount = intval( $db_settings[CR_Discount_Tiers::$tiers_settings['cr_coupon__coupon_amount']][$tier_w_coupon] );
						if ( $discount_type === "percent" && $discount_amount > 0 ){
							$discount_string = $discount_amount . "%";
						} elseif ( $discount_amount > 0 ) {
							$discount_string = trim(
								strip_tags( CR_Email_Func::cr_price( $discount_amount, array( 'currency' => get_option( 'woocommerce_currency' ) ) ) )
							);
						}
						$prefix = $db_settings[CR_Discount_Tiers::$tiers_settings['cr_coupon_prefix']][$tier_w_coupon];
						$coupon_code = strtoupper( $prefix . uniqid( 'TEST' ) );
					}
				} else {
					/* translators: %d is a special symbol that will be replaced with the count of uploaded media files */
					wp_send_json( array( 'code' => 95, 'message' => sprintf( __( 'Coupons are not enabled in any of the discount tiers for reviews with %d uploaded media file(s)', 'customer-reviews-woocommerce' ), $media_count ) ) );
				}
			} else {
				wp_send_json( array( 'code' => 96, 'message' => __( 'Please re-save settings and try again', 'customer-reviews-woocommerce' ) ) );
			}

			if ( filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
				$e = new Ivole_Email_Coupon();
				$result = $e->triggerTest( null, $email, $coupon_code, $discount_string );
				if ( is_array( $result ) && count( $result )  > 1 && 2 === $result[0] ) {
					wp_send_json( array( 'code' => 2, 'message' => $result[1] ) );
				} elseif( is_array( $result ) && count( $result )  > 1 && 100 === $result[0] ) {
					wp_send_json( array( 'code' => 100, 'message' => $result[1] ) );
				}
				elseif( 0 === $result ) {
					wp_send_json( array( 'code' => 0, 'message' => '' ) );
				} elseif( 1 === $result ) {
					wp_send_json( array( 'code' => 1, 'message' => '' ) );
				} elseif( 13 === $result ) {
					wp_send_json( array( 'code' => 13, 'message' => '' ) );
				}
			} else {
				wp_send_json( array( 'code' => 99, 'message' => '' ) );
			}

			wp_send_json( array( 'code' => 98, 'message' => '' ) );
		}

	}

endif;
