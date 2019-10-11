<?php
/**
 * Plugin Name: WooCommerce Later Delivery On Demand
 * Description: Adds an option to checkout to ask if order delivery (or pickup) should be later on demand and after which expected date.
 * Author: Erik Molenaar
 *
 * Version: 0.1
 */


// Exit if accessed directly
if (!defined('ABSPATH')) exit;



add_action('woocommerce_review_order_after_shipping', 'emnl_later_delivery_on_demand');
/**
 * @brief The delivery option tied to an action hook , outputting delivery fields on checkout after shipping
 */
function emnl_later_delivery_on_demand()
{

    echo '<tr class="emnl-later-delivery">';
    echo '<td colspan="2">';

    $chosen_shipping_method_id = WC()->session->get('chosen_shipping_methods')[0];
    $available_shipping_methods = WC()->session->get('shipping_for_package_0')['rates'];

    // Loop thru all available shipping methods to find a match for the chosen shipping method ID
    foreach ($available_shipping_methods as $method_id => $rate) {

        if ($chosen_shipping_method_id == $method_id) {

            $chosen_shipping_method_label = $rate->label;
            break;

        }

    }

    // Define verbs in case of shipping method: pick up instead of delivery
    if (stristr($chosen_shipping_method_label, 'afhalen')) {

        $later_delivery_verb1 = 'afhaal';
        $later_delivery_verb2 = 'afhalen';
        $later_delivery_verb3 = 'afhaling';

    } else {

        $later_delivery_verb1 = 'bezorg';
        $later_delivery_verb2 = 'bezorgen';
        $later_delivery_verb3 = 'bezorging';

    }

    // Retrieve shipping method transit times to calculate their respective planning times
    if (stristr($chosen_shipping_method_label, 'pakketpost')) {

        // Pakketpost
        if (defined('EMNL_UNILIVING_SHIPPING_TIME_STOCK_PRODUCTS_PAKKETPOST_IN_DAYS')) {
            $later_delivery_planning_days = EMNL_UNILIVING_SHIPPING_TIME_STOCK_PRODUCTS_PAKKETPOST_IN_DAYS;
        }

    } elseif (stristr($chosen_shipping_method_label, 'bezorging op afspraak')) {

        // Bezorgen op afspraak
        if (defined('EMNL_UNILIVING_SHIPPING_TIME_STOCK_PRODUCTS_NO_PAKKETPOST_IN_DAYS')) {
            $later_delivery_planning_days = EMNL_UNILIVING_SHIPPING_TIME_STOCK_PRODUCTS_NO_PAKKETPOST_IN_DAYS;
        }

    } elseif (stristr($chosen_shipping_method_label, 'afhalen na afspraak')) {

        // Afhalen
        $later_delivery_planning_days = 1;

    } else {

        // Fallback
        $later_delivery_planning_days = 14;

    }

    // Convert planning days to a nice human readable sentence
    if ($later_delivery_planning_days === 1) {
        $later_delivery_planning_text = $later_delivery_planning_days . ' werkdag';
    } elseif ($later_delivery_planning_days <= 5) {
        $later_delivery_planning_text = $later_delivery_planning_days . ' werkdagen';
    } else {

        $later_delivery_planning_days_unrounded = $later_delivery_planning_days / 7;
        $later_delivery_planning_weeks = ceil($later_delivery_planning_days_unrounded);
        $later_delivery_planning_text = $later_delivery_planning_weeks . ' weken';

    }

    $posted_data_string = isset($_POST['post_data']) ? wp_unslash($_POST['post_data']) : '';

    $checked = WC()->session->get('emnl-later-delivery-checkbox-selection');
    $date = is_null(WC()->session->get('emnl-later-delivery-date')) ? '' : WC()->session->get('emnl-later-delivery-date');

    if (!empty($posted_data_string)) {
        parse_str($posted_data_string, $posted_data);
        if (isset($posted_data['emnl-later-delivery-checkbox'])) {
            $checked = true;
            WC()->session->set('emnl-later-delivery-checkbox-selection', true);

        } else {
            $checked = false;
            WC()->session->set('emnl-later-delivery-checkbox-selection', false);
        }


        if (isset($posted_data['emnl-later-delivery-date']) && !empty($posted_data['emnl-later-delivery-date'])) {

            $validate_date = explode('/', $posted_data['emnl-later-delivery-date']);
            if (is_array($validate_date) && count($validate_date) === 3 && checkdate($validate_date[0], $validate_date[1], $validate_date[2])) {
                WC()->session->set('emnl-later-delivery-date', $posted_data['emnl-later-delivery-date']);
                $date = $posted_data['emnl-later-delivery-date'];
            }

        }
    }


    //enqueue datepicker script
    wp_enqueue_script('jquery-ui-datepicker');


    // Create the later delivery checkbox
    woocommerce_form_field('emnl-later-delivery-checkbox', array(
        'type' => 'checkbox',
        'class' => array('emnl-later-delivery-checkbox'),
        'label' => __('Bestelling later op afroep ' . $later_delivery_verb2, 'woocommerce'),
        'required' => false,
        'default' => $checked
    ), $checked
    );

    //JQuery is not used to show /hide datepicker field, the field is only rendered when checkbox is checked (since we need to update the values in session )
    if ($checked) {
        // The element with date field and further instructions (hidden if checkbox is NOT checked)
        echo '<div id="emnl-later-delivery-wrapper">';

        // Create the later delivery instructions
        echo '<p>';
        echo '<small>';
        echo 'Let op: uw bestelling komt op "op afroep". ';
        echo 'Wij nemen in principe géén contact met u op voor het maken van een ' . $later_delivery_verb1 . 'afspraak. ';
        echo 'U dient z.s.m. of <strong><u>uiterlijk ' . $later_delivery_planning_text . ' ';
        echo 'vóór de gewenste ' . $later_delivery_verb1 . 'datum</u></strong> ';
        echo 'contact met ons op te nemen om een ' . $later_delivery_verb1 . 'afspraak in te plannen.';
        echo '</small>';
        echo '</p>';


        //include required jQuery to activate datepicker field
        echo '
    <script>
        jQuery(function($){
            var datePicker = $("#emnl-later-delivery-date").datepicker({
                   minDate: new Date(),
                   onSelect: function(dateText, inst) { 
                                $(document.body).trigger("update_checkout");
                            }
            });

        });
    </script>';

        woocommerce_form_field('emnl-later-delivery-date', array(
            'type' => 'text',
            'class' => array('form-row-wide'),
            'id' => 'emnl-later-delivery-date',
            'label' => __(ucwords($later_delivery_verb3) . ' moet (naar verwachting) plaatsvinden vanaf:'),
            'required' => true,
        ),
            $date);

        echo '</div>';

    }
    echo '</td>';
    echo '</tr>';


}


add_action('wp_footer', 'emnl_later_delivery_script');
/**
 * @brief the initial javascript required to activate checkbox
 */
function emnl_later_delivery_script()
{

    // Only checkout page but not the order received page
    if (!is_checkout() || is_order_received_page()) {
        return;
    }

    ?>
    <script type="text/javascript">

        jQuery(document).ready(function ($) {
            $(document.body).on('updated_checkout', function () {

                $('input[name=emnl-later-delivery-checkbox]').on('change', function () {
                    $(document.body).trigger('update_checkout');
                });
            });
        })

    </script>
    <?php

}



add_action('woocommerce_after_checkout_validation', 'custom_checkout_field_validation_process', 20, 2);
/**
 * @brief Validate when submitting order
 * @param $data
 * @param $errors
 */
function custom_checkout_field_validation_process($data, $errors)
{

    // Check if set, if not add an error.
    if (isset($_POST['emnl-later-delivery-checkbox']) && empty($_POST['emnl-later-delivery-date']))
        $errors->add('requirements', __("Please fill in a date!", "woocommerce"));


    if (isset($_POST['emnl-later-delivery-date']) && !empty($_POST['emnl-later-delivery-date'])) {
        $validate_date = explode('/', $_POST['emnl-later-delivery-date']);
        if (!is_array($validate_date) || count($validate_date) !== 3 || !checkdate($validate_date[0], $validate_date[1], $validate_date[2])) {
            $errors->add('requirements', __("Please fill in a valid date!", "woocommerce"));

        }
    }


}


add_action('woocommerce_checkout_update_order_meta', 'wordimpress_custom_checkout_field_update_order_meta');
/**
 * @brief Save the New Checkout Fields Upon Successful Order , only if the delivery later option was selected
 * @param $order_id
 */
function wordimpress_custom_checkout_field_update_order_meta($order_id)
{
    //check if $_POST has our custom fields, and save them if true
    $order = wc_get_order($order_id);
    if ( $order && isset($_POST['emnl-later-delivery-checkbox']) && isset($_POST['emnl-later-delivery-date']) && !empty($_POST['emnl-later-delivery-date'])) {
        $order->update_meta_data('emnl_later_delivery_date', $_POST['emnl-later-delivery-date']);
        $order->save();
    }
}

 add_filter( 'woocommerce_email_order_meta_fields', 'enml_email_order_meta_fields' ,10,3);
/**
 * @brief Add the Custom Field Data to Order Emails
 * @param $fields
 * @param $sent_to_admin
 * @param $order
 * @return array
 */
function enml_email_order_meta_fields($fields, $sent_to_admin, $order)
{
    if($order){
        $delivery_date_later = $order->get_meta('emnl_later_delivery_date', true);

        if(is_array($fields) && !empty($delivery_date_later)){

            $fields[] = array('label' => __('Deliver later','emnl-later-delivery-on-demand') ,
                'value' => __('Yes','emnl-later-delivery-on-demand')
            );

            $fields[] = array('label' => __('Delivery date','emnl-later-delivery-on-demand') ,
                'value' => date(get_option('date_format'), strtotime($delivery_date_later))
            );
        }

    }


    return $fields;

}



add_action('add_meta_boxes_shop_order', 'enml_later_delivery_order_editor_info_meta_box');

/**
 * @brief add the metabox to order editor page
 */
function enml_later_delivery_order_editor_info_meta_box(){


    global $post;
    if(is_null($post)) return;
    $order = wc_get_order($post->ID);

    if($order){

        $delivery_date_later = $order->get_meta('emnl_later_delivery_date', true);


        if(!empty($delivery_date_later)){

        add_meta_box('emnl_later_delivery_date_metabox_output',
        __('Delivery date information', 'emnl_later_delivery_date'),
        'emnl_later_delivery_date_metabox_output',
        'shop_order',
        'side',
        'high'
    );
        }

    }

}

/**
 * @brief output order editor metabox for delivery later content
 */
function emnl_later_delivery_date_metabox_output(){
    global $post;
    if(is_null($post)) return;
    $order = wc_get_order($post->ID);

    if($order){
        $delivery_date_later = $order->get_meta('emnl_later_delivery_date', true);

        if(!empty($delivery_date_later)){

            echo  __('Deliver later','emnl-later-delivery-on-demand') .' : '.__('Yes','emnl-later-delivery-on-demand').'<br/>';
            echo   __('Delivery date','emnl-later-delivery-on-demand') .' : '.date(get_option('date_format'), strtotime($delivery_date_later));
        }



    }

}
