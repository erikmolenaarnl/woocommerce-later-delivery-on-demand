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
            WC()->session->set('emnl-later-delivery-date', '');
            WC()->session->set('emnl-later-delivery-checkbox-selection', false);
        }


        if ($checked && isset($posted_data['emnl-later-delivery-date']) && !empty($posted_data['emnl-later-delivery-date'])) {
            WC()->session->set('emnl-later-delivery-date', $posted_data['emnl-later-delivery-date']);
            $date = $posted_data['emnl-later-delivery-date'];
        } else {
            //reset the date session data
            WC()->session->set('emnl-later-delivery-date', '');
            $date = '';

        }
    }




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
        ob_start();
        echo '<p>';
        echo '<small>';
        echo 'Let op: uw bestelling komt op "op afroep". ';
        echo 'Wij nemen in principe géén contact met u op voor het maken van een ' . $later_delivery_verb1 . 'afspraak. ';
        echo 'U dient z.s.m. of <strong><u>uiterlijk ' . $later_delivery_planning_text . ' ';
        echo 'vóór de gewenste ' . $later_delivery_verb1 . 'datum</u></strong> ';
        echo 'contact met ons op te nemen om een ' . $later_delivery_verb1 . 'afspraak in te plannen.';
        echo '</small>';
        echo '</p>';
        $delivery_instructions = ob_get_clean();
        echo $delivery_instructions;
        //calculate min date within 7 days
        $min_date = date('Y-m-d', strtotime(date('Y-m-d') . '+7 days'));

        woocommerce_form_field('emnl-later-delivery-date', array(
            'type' => 'date',
            'custom_attributes' => array('min' => $min_date),
            'value' => array('min' => $min_date),
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

add_action('woocommerce_checkout_create_order_shipping_item', 'emnl_modify_shipping_method_title', 10, 4);
/**
 * @brief modify shipping method title to include delivery date
 * @param $item
 * @param $package_key
 * @param $package
 * @param $order
 *
 */
function emnl_modify_shipping_method_title($item, $package_key, $package, $order)
{
    if (isset($_POST['emnl-later-delivery-checkbox']) && isset($_POST['emnl-later-delivery-date']) && !empty($_POST['emnl-later-delivery-date'])) {

        $formatted_date = date_i18n(get_option('date_format'), strtotime($_POST['emnl-later-delivery-date']));
        $item->set_name($item->get_name() . ' (op afroep vanaf ' . strtolower($formatted_date) . ')');
    }


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
        $validate_date = explode('-', $_POST['emnl-later-delivery-date']);
        $min_date = date('Y-m-d', strtotime(date('Y-m-d') . '+7 days'));

        if (
            !is_array($validate_date) || count($validate_date) !== 3 || !checkdate($validate_date[1], $validate_date[2], $validate_date[0])
            || strtotime($min_date) > strtotime($_POST['emnl-later-delivery-date'])
        ) {


            $errors->add('requirements', __("Please fill in a valid date which is minimal 7 days in the future!", "woocommerce"));




        } else {
            //all good , save the date to session
            WC()->session->set('emnl-later-delivery-date', $_POST['emnl-later-delivery-date']);

        }

    }


}




add_action('woocommerce_email_order_meta', 'emnl_email_output_delivery_instructions', 20,2);

/**
 * @brief output delivery instructions in relevant emails
 * @param $order
 * @param $sent_to_admin
 */
function emnl_email_output_delivery_instructions($order,$sent_to_admin)
{
    if ($order && !$sent_to_admin && strpos($order->get_shipping_method(),'op afroep') !== false ) {

        //generate message

        // Define verbs in case of shipping method: pick up instead of delivery
        if (stristr($order->get_shipping_method(), 'afhalen')) {

            $later_delivery_verb1 = 'afhaal';
            $later_delivery_verb2 = 'afhalen';
            $later_delivery_verb3 = 'afhaling';

        } else {

            $later_delivery_verb1 = 'bezorg';
            $later_delivery_verb2 = 'bezorgen';
            $later_delivery_verb3 = 'bezorging';

        }

        // Retrieve shipping method transit times to calculate their respective planning times
        if (stristr($order->get_shipping_method(), 'pakketpost')) {

            // Pakketpost
            if (defined('EMNL_UNILIVING_SHIPPING_TIME_STOCK_PRODUCTS_PAKKETPOST_IN_DAYS')) {
                $later_delivery_planning_days = EMNL_UNILIVING_SHIPPING_TIME_STOCK_PRODUCTS_PAKKETPOST_IN_DAYS;
            }

        } elseif (stristr($order->get_shipping_method(), 'bezorging op afspraak')) {

            // Bezorgen op afspraak
            if (defined('EMNL_UNILIVING_SHIPPING_TIME_STOCK_PRODUCTS_NO_PAKKETPOST_IN_DAYS')) {
                $later_delivery_planning_days = EMNL_UNILIVING_SHIPPING_TIME_STOCK_PRODUCTS_NO_PAKKETPOST_IN_DAYS;
            }

        } elseif (stristr($order->get_shipping_method(), 'afhalen na afspraak')) {

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

        // Create the later delivery instructions
        ob_start();
        echo '<p>';
        echo '<small>';
        echo 'Let op: uw bestelling komt op "op afroep". ';
        echo 'Wij nemen in principe géén contact met u op voor het maken van een ' . $later_delivery_verb1 . 'afspraak. ';
        echo 'U dient z.s.m. of <strong><u>uiterlijk ' . $later_delivery_planning_text . ' ';
        echo 'vóór de gewenste ' . $later_delivery_verb1 . 'datum</u></strong> ';
        echo 'contact met ons op te nemen om een ' . $later_delivery_verb1 . 'afspraak in te plannen.';
        echo '</small>';
        echo '</p>';
        $delivery_instructions = ob_get_clean();
        echo $delivery_instructions;

    }
}
