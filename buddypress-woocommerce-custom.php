<?php
/**
 * Plugin Name: BuddyPress WooCommerce Custom Integration
 * Description: A custom plugin to integrate BuddyPress with WooCommerce categories and restrict activity based on product purchase.
 * Version: 1.0
 * Author: Your Name
 */

// Prevent direct access
if ( !defined( 'ABSPATH' ) ) {
    exit;
}

// Add WooCommerce categories to BuddyPress post form
function add_woocommerce_categories_to_bp_form() {
    if ( bp_is_active( 'activity' ) && function_exists( 'wc_get_product_category_list' ) ) {
        $categories = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
        echo '<select name="woocommerce_category">';
        foreach ( $categories as $category ) {
            echo '<option value="' . esc_attr( $category->term_id ) . '">' . esc_html( $category->name ) . '</option>';
        }
        echo '</select>';
    }
}
add_action( 'bp_activity_post_form_options', 'add_woocommerce_categories_to_bp_form' );

// Save the selected WooCommerce category with the BuddyPress activity
function save_bp_activity_woocommerce_category( $content, $user_id, $activity_id ) {
    if ( isset( $_POST['woocommerce_category'] ) ) {
        $category_id = sanitize_text_field( $_POST['woocommerce_category'] );
        bp_activity_update_meta( $activity_id, 'woocommerce_category', $category_id );
    }
}
add_action( 'bp_activity_posted_update', 'save_bp_activity_woocommerce_category', 10, 3 );

// Restrict activity page based on login status
function restrict_activity_page_access() {
    if ( bp_is_activity_component() && !is_user_logged_in() ) {
        wp_redirect( wp_login_url() );
        exit;
    }
}
add_action( 'template_redirect', 'restrict_activity_page_access' );

// Get purchased categories for a user
function get_user_purchased_categories( $user_id ) {
    $order_ids = wc_get_orders( array(
        'customer_id' => $user_id,
        'status' => 'completed',
        'return' => 'ids',
    ) );

    $categories = array();
    foreach ( $order_ids as $order_id ) {
        $order = wc_get_order( $order_id );
        foreach ( $order->get_items() as $item ) {
            $product_id = $item->get_product_id();
            $terms = get_the_terms( $product_id, 'product_cat' );
            if ( $terms && ! is_wp_error( $terms ) ) {
                foreach ( $terms as $term ) {
                    $categories[] = $term->term_id;
                }
            }
        }
    }
    return array_unique( $categories );
}

// Filter activity posts by purchased category
function filter_activity_posts_by_purchase( $args ) {
    if ( is_user_logged_in() ) {
        $user_id = get_current_user_id();
        $purchased_categories = get_user_purchased_categories( $user_id );

        if ( !empty( $purchased_categories ) ) {
            $args['meta_query'] = array(
                array(
                    'key' => 'woocommerce_category',
                    'value' => $purchased_categories,
                    'compare' => 'IN'
                )
            );
        } else {
            // Hide all posts if no purchased categories
            $args['meta_query'] = array(
                array(
                    'key' => 'woocommerce_category',
                    'value' => 'none',
                    'compare' => 'NOT IN'
                )
            );
        }
    }
    return $args;
}
add_filter( 'bp_activity_get', 'filter_activity_posts_by_purchase' );

// Filter Better Messages based on purchased categories
function filter_better_messages_by_purchase( $user_ids ) {
    if ( is_user_logged_in() ) {
        $user_id = get_current_user_id();
        $purchased_categories = get_user_purchased_categories( $user_id );

        if ( !empty( $purchased_categories ) ) {
            $valid_user_ids = array();
            foreach ( $user_ids as $id ) {
                $user_purchased_categories = get_user_purchased_categories( $id );
                if ( array_intersect( $purchased_categories, $user_purchased_categories ) ) {
                    $valid_user_ids[] = $id;
                }
            }
            return $valid_user_ids;
        } else {
            return array(); // Return empty if no purchased categories
        }
    }
    return $user_ids;
}
add_filter( 'better_messages_valid_recipients', 'filter_better_messages_by_purchase' );

?>
