<?php
/**
 * Plugin Name: WooCommerce Later Delivery On Demand
 * Description: Adds an option to checkout to ask if order delivery (or pickup) should be later on demand and after which expected date.
 * Author: Erik Molenaar
 * Version: 0.1
 */

 
// Exit if accessed directly
if ( ! defined ( 'ABSPATH' ) ) exit;


// The delivery option tied to an action hook
add_action ( 'woocommerce_review_order_after_shipping', 'emnl_later_delivery_on_demand' );
function emnl_later_delivery_on_demand() {

    echo '<tr class="emnl-later-delivery">';
        echo '<td colspan="2">';

            $chosen_shipping_method_id = WC()->session->get('chosen_shipping_methods')[0];
            $available_shipping_methods = WC()->session->get('shipping_for_package_0')['rates'];

            // Loop thru all available shipping methods to find a match for the chosen shipping method ID
            foreach ( $available_shipping_methods as $method_id => $rate ) {

                if ( $chosen_shipping_method_id == $method_id ) {

                    $chosen_shipping_method_label = $rate->label;
                    break;

                }

            }

            // Define verbs in case of shipping method: pick up instead of delivery
            if ( stristr ( $chosen_shipping_method_label, 'afhalen' ) ) {

                $later_delivery_verb1 = 'afhaal';
                $later_delivery_verb2 = 'afhalen';
                $later_delivery_verb3 = 'afhaling';

            } else {

                $later_delivery_verb1 = 'bezorg';
                $later_delivery_verb2 = 'bezorgen';
                $later_delivery_verb3 = 'bezorging';
                
            }

            // Retrieve shipping method transit times to calculate their respective planning times
            if ( stristr ( $chosen_shipping_method_label, 'pakketpost' ) ) {

                // Pakketpost
                if ( defined ( 'EMNL_UNILIVING_SHIPPING_TIME_STOCK_PRODUCTS_PAKKETPOST_IN_DAYS' ) ) {
                    $later_delivery_planning_days = EMNL_UNILIVING_SHIPPING_TIME_STOCK_PRODUCTS_PAKKETPOST_IN_DAYS;
                }

            } elseif ( stristr ( $chosen_shipping_method_label, 'bezorging op afspraak' ) ) {

                // Bezorgen op afspraak
                if ( defined ( 'EMNL_UNILIVING_SHIPPING_TIME_STOCK_PRODUCTS_NO_PAKKETPOST_IN_DAYS' ) ) {
                    $later_delivery_planning_days = EMNL_UNILIVING_SHIPPING_TIME_STOCK_PRODUCTS_NO_PAKKETPOST_IN_DAYS;
                }

            } elseif ( stristr ( $chosen_shipping_method_label, 'afhalen na afspraak' ) ) {

                // Afhalen
                $later_delivery_planning_days = 1;

            } else {
                
                // Fallback
                $later_delivery_planning_days = 14;

            }

            // Convert planning days to a nice human readable sentence            
            if ( $later_delivery_planning_days === 1 ) {
                $later_delivery_planning_text =  $later_delivery_planning_days . ' werkdag';
            } elseif ( $later_delivery_planning_days <= 5 ) {
                $later_delivery_planning_text =  $later_delivery_planning_days . ' werkdagen';
            } else {
                    
                $later_delivery_planning_days_unrounded = $later_delivery_planning_days / 7;
                $later_delivery_planning_weeks = ceil ( $later_delivery_planning_days_unrounded );
                $later_delivery_planning_text = $later_delivery_planning_weeks . ' weken';

            }

            $chosen = WC()->session->get('emnl-later-delivery-checkbox-selection');
            $chosen = empty ( $chosen ) ? WC()->checkout->get_value('emnl-later-delivery-checkbox') : $chosen;
            $chosen = empty ( $chosen ) ? false : $chosen;
            
            // Create the later delivery checkbox
            woocommerce_form_field ( 'emnl-later-delivery-checkbox', array (
                'type'     => 'checkbox',
                'class'    => array ( 'emnl-later-delivery-checkbox' ),
                'label'    => __( 'Bestelling later op afroep ' . $later_delivery_verb2, 'woocommerce' ),
                'required' => false,
                'default' => $chosen
                ), $chosen
            );

            // The element with date field and further instructions (hidden if checkbox is NOT checked)
            echo '<div id="emnl-later-delivery-wrapper">';
            
                // Create the later delivery instructions
                echo '<p>';    
                echo '<small>';
                echo 'Let op: uw bestelling komt op "op afroep". ';
                echo 'Wij nemen in principe géén contact met u op voor het maken van een ' . $later_delivery_verb1 .  'afspraak. ';
                echo 'U dient z.s.m. of <strong><u>uiterlijk ' . $later_delivery_planning_text . ' ';
                echo 'vóór de gewenste ' . $later_delivery_verb1 . 'datum</u></strong> ';
                echo 'contact met ons op te nemen om een ' . $later_delivery_verb1 . 'afspraak in te plannen.';
                echo '</small>';
                echo '</p>';

                // Create the later delivery date field
                woocommerce_form_field ( 'emnl-later-delivery-date', array (
                    'type'     => 'date',
                    'class'    => array ( 'emnl-later-delivery-date' ),
                    'label'    => __( ucwords ( $later_delivery_verb3 ) . ' moet (naar verwachting) plaatsvinden vanaf:' ),
                    'required' => true,
                    )
                );
                
            echo '</div>';

        echo '</td>';
    echo '</tr>';

    // CSS to initially hide the wrapper
    ?>
    <style type="text/css">
        #emnl-later-delivery-wrapper { display: none; }
    </style>
    <?php

    // jQuery to unhide the wrapper when the checkbox is ticked
    ?>
    <script>
    jQuery(document).ready(function($){
        $('.emnl-later-delivery-checkbox input[type="checkbox"]').on('click', function() {
            $('#emnl-later-delivery-wrapper').slideToggle();
        });  
    });
    </script>
    <?php

}

// jQuery - Ajax script
add_action ( 'wp_footer', 'emnl_later_delivery_script' );
function emnl_later_delivery_script() {
    
    // Only checkout page
    if ( ! is_checkout() ) { return; }
    
    ?>
    <script type="text/javascript">
    jQuery( function($){
        $('form.checkout').on('change', 'input[name=emnl-later-delivery-checkbox]', function(e){
            e.preventDefault();
            var p = $(this).val();
            $.ajax({
                type: 'POST',
                url: wc_checkout_params.ajax_url,
                data: {
                    'action': 'woo_get_ajax_data',
                    'emnl-later-delivery-checkbox': p,
                },
                success: function (result) {
                    $('body').trigger('update_checkout');
                    console.log('response: '+result); // just for testing | TO BE REMOVED
                },
                error: function(error){
                    console.log(error); // just for testing | TO BE REMOVED
                }
            });
        });
    });
    </script>
    <?php

}

// Php Ajax (receiving request and saving to WC session)
add_action(  'wp_ajax_woo_get_ajax_data', 'woo_get_ajax_data' );
add_action ( 'wp_ajax_nopriv_woo_get_ajax_data', 'woo_get_ajax_data' );
function woo_get_ajax_data() {

    if ( isset($_POST['emnl-later-delivery-checkbox']) ){

        $later_delivery_checkbox = sanitize_key( $_POST['emnl-later-delivery-checkbox'] );
        WC()->session->set('emnl-later-delivery-checkbox-selection', $later_delivery_checkbox );
        echo json_encode ( $later_delivery_checkbox );

    }

    die(); // Alway at the end (to avoid server error 500)

}


// Validate when submitting order
add_action('woocommerce_after_checkout_validation', 'custom_checkout_field_validation_process', 20, 2 );
function custom_checkout_field_validation_process( $data, $errors ) {

    // Check if set, if not add an error.
    if ( isset($_POST['emnl-later-delivery-checkbox']) && empty($_POST['emnl-later-delivery-checkbox']) )
        $errors->add( 'requirements', __( "Please fill in a date!", "woocommerce" ) );

}

// WORK IN PROGRESS -> Save the New Checkout Fields Upon Successful Order
// add_action( 'woocommerce_checkout_update_order_meta', 'wordimpress_custom_checkout_field_update_order_meta' );
function wordimpress_custom_checkout_field_update_order_meta( $order_id ) {

    //check if $_POST has our custom fields
    if ( $_POST['inscription_checkbox'] ) {
        //It does: update post meta for this order
        update_post_meta( $order_id, 'Inscription Option', esc_attr( $_POST['inscription_checkbox'] ) );
    }
    if ( $_POST['inscription_textbox'] ) {
        update_post_meta( $order_id, 'Inscription Text', esc_attr( $_POST['inscription_textbox'] ) );
    }
}

// WORK IN PROGRESS -> Add the Custom Field Data to Order Emails
// add_filter( 'woocommerce_email_order_meta_keys', 'wordimpress_checkout_field_order_meta_keys' );
function wordimpress_checkout_field_order_meta_keys( $keys ) {

    //Check if Book in Cart
    $book_in_cart = wordimpress_is_conditional_product_in_cart( 117 );

    //Only if book in cart
    if ( $book_in_cart === true ) {

        $keys[] = 'Inscription Option';
        $keys[] = 'Inscription Text';
    }

    return $keys;
}