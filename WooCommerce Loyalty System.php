<?php
/**
 * Plugin Name: WooCommerce Loyalty System (Vatrice)
 * Description: Earn points on orders, redeem for coupons.
 * Version: 1.0
 * Author: Maja
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! function_exists( 'custom_order_statistics_shortcode' ) ) {
    function custom_order_statistics_shortcode( $atts ) {
        if ( ! is_user_logged_in() ) {
            return '<p>Morate biti prijavljeni.</p>';
        }
        $user_id = get_current_user_id();
        $user = wp_get_current_user();
        $available_vatrice = (int) get_user_meta( $user_id, '_available_vatrice', true );
        ob_start();
        ?>
        <div class="order-statistics">
            <p>Zdravo, <?php echo esc_html( $user->display_name ); ?>!</p>
            <p>Imate trenutno <strong><?php echo esc_html( $available_vatrice ); ?></strong> 🔥 vatrica.</p>
            <p>Vatrice iz prethodne porudžbine biće dodate čim obradimo Vašu porudžbinu.</p>
        </div>
        <?php
        return ob_get_clean();
    }
    add_shortcode( 'order_statistics', 'custom_order_statistics_shortcode' );
}

if ( ! function_exists( 'assign_vatrice_after_purchase' ) ) {
    function assign_vatrice_after_purchase( $order_id ) {
        $order = wc_get_order( $order_id );
        $user_id = $order->get_user_id();
        if ( $user_id ) {
            $new_vatrice = 0;
            foreach ( $order->get_items() as $item ) {
                $new_vatrice += ( $item->get_total() < 5000 ) ? 50 : 100;
            }
            $current_vatrice = (int) get_user_meta( $user_id, '_available_vatrice', true );
            $current_vatrice += $new_vatrice;
            update_user_meta( $user_id, '_available_vatrice', $current_vatrice );
            wc_add_notice( 'Osvojili ste ' . $new_vatrice . ' 🔥 vatrica!', 'success' );
        }
    }
    add_action( 'woocommerce_order_status_completed', 'assign_vatrice_after_purchase' );
}

if ( ! function_exists( 'add_vatrice_to_cart_item_name' ) ) {
    function add_vatrice_to_cart_item_name( $product_name, $cart_item, $cart_item_key ) {
        $product_price = $cart_item['line_total'];
        $vatrice = ( $product_price < 5000 ) ? 50 : 100;
        return $product_name . '<br><span style="color: red;">🔥 ' . $vatrice . '</span>';
    }
    add_filter( 'woocommerce_cart_item_name', 'add_vatrice_to_cart_item_name', 10, 3 );
}

if ( ! function_exists( 'fire_coupon_shortcode' ) ) {
    function fire_coupon_shortcode( $atts ) {
        if ( ! is_user_logged_in() ) {
            return '<p>Morate biti prijavljeni.</p>';
        }
        $user_id = get_current_user_id();
        $available_vatrice = (int) get_user_meta( $user_id, '_available_vatrice', true );
        ob_start();
        ?>
        <div class="fire-coupon-generator">
            <p>🔥 Trenutno imate <strong><?php echo esc_html( $available_vatrice ); ?></strong> vatrica (1 vatrica = 1 RSD popusta).</p>
            <?php if ( $available_vatrice > 0 ) : ?>
                <form method="post" action="">
                    <input type="hidden" name="fire_coupon_action" value="generate">
                    <input type="submit" name="apply_fire_coupon" value="🔥 Iskoristi vatrice za kupon">
                </form>
            <?php endif; ?>
        </div>
        <?php
        if ( isset( $_POST['apply_fire_coupon'] ) && isset( $_POST['fire_coupon_action'] ) ) {
            $action = sanitize_text_field( $_POST['fire_coupon_action'] );
            if ( 'generate' === $action ) {
                $coupon_code = generate_fire_coupon_for_user( $user_id );
                if ( $coupon_code ) {
                    echo '<p>✅ Vaš kupon: <strong>' . esc_html( $coupon_code ) . '</strong></p>';
                } else {
                    echo '<p>❌ Nije moguće generisati kupon.</p>';
                }
            }
        }
        return ob_get_clean();
    }
    add_shortcode( 'fire_coupon_generator', 'fire_coupon_shortcode' );
}

function generate_fire_coupon_for_user( $user_id ) {
    $total_vatrice = (int) get_user_meta( $user_id, '_available_vatrice', true );
    if ( $total_vatrice <= 0 ) {
        return false;
    }
    $coupon_code = 'VATRICA-' . strtoupper( wp_generate_password( 8, false ) );
    $coupon = new WC_Coupon();
    $coupon->set_code( $coupon_code );
    $coupon->set_discount_type( 'fixed_cart' );
    $coupon->set_amount( $total_vatrice );
    $coupon->set_individual_use( true );
    $coupon->set_usage_limit( 1 );
    $coupon->save();
    update_user_meta( $user_id, '_available_vatrice', 0 );
    return $coupon_code;
}