<?php
/**
 * Plugin Name: iOnline Custom Plugin v2
 * Plugin URI: https://ionline.com.au/
 * Description: Salt Flakes Order Management
 * Version: 2.0.0
 * Author: iOnline Pty Ltd
 * Author URI: https://ionline.com.au/
 **/

// add 4 checkboxes to the user meta, Monday, Tuesday, Thursday, Friday
add_action( 'show_user_profile', 'add_user_meta_fields' );
add_action( 'edit_user_profile', 'add_user_meta_fields' );
function add_user_meta_fields( $user ) {
?>
    <h3>Delivery Days</h3>
    <table class="form-table">
        <tr>
            <th><label for="monday">Monday</label></th>
            <td>
                <input type="checkbox" name="monday" id="monday" value="1" <?php if (esc_attr( get_the_author_meta( 'monday', $user->ID ) ) == 1) echo 'checked="checked"'; ?> />
            </td>
        </tr>
        <tr>
            <th><label for="tuesday">Tuesday</label></th>
            <td>
                <input type="checkbox" name="tuesday" id="tuesday" value="1" <?php if (esc_attr( get_the_author_meta( 'tuesday', $user->ID ) ) == 1) echo 'checked="checked"'; ?> />
            </td>
        </tr>
        <tr>
            <th><label for="Wednesday">Wednesday</label></th>
            <td>
                <input type="checkbox" name="wednesday" id="wednesday" value="1" <?php if (esc_attr( get_the_author_meta( 'wednesday', $user->ID ) ) == 1) echo 'checked="checked"'; ?> />
            </td>
        </tr>
        <tr>
            <th><label for="thursday">Thursday</label></th>
            <td>
                <input type="checkbox" name="thursday" id="thursday" value="1" <?php if (esc_attr( get_the_author_meta( 'thursday', $user->ID ) ) == 1) echo 'checked="checked"'; ?> />
            </td>
        </tr>
        <tr>
            <th><label for="friday">Friday</label></th>
            <td>
                <input type="checkbox" name="friday" id="friday" value="1" <?php if (esc_attr( get_the_author_meta( 'friday', $user->ID ) ) == 1) echo 'checked="checked"'; ?> />
            </td>
        </tr>
    </table>
<?php
}

// save the user meta
add_action( 'personal_options_update', 'save_user_meta_fields' );
add_action( 'edit_user_profile_update', 'save_user_meta_fields' );

function save_user_meta_fields( $user_id ) {
    if ( !current_user_can( 'edit_user', $user_id ) ) { return false; }
    update_user_meta( $user_id, 'monday', $_POST['monday'] );
    update_user_meta( $user_id, 'tuesday', $_POST['tuesday'] );
    update_user_meta( $user_id, 'wednesday', $_POST['wednesday'] );
    update_user_meta( $user_id, 'thursday', $_POST['thursday'] );
    update_user_meta( $user_id, 'friday', $_POST['friday'] );
}

// on the slug called '123' add some jQery to the footer
add_action( 'wp_footer', 'add_jquery_to_footer' );
function add_jquery_to_footer() {
    if ( is_page( 'wholesale-ordering' ) ) {
        ?>
        <script>
            jQuery(document).ready(function($) {
                setInterval(function() {
                    if (jQuery('body > div.elementor.elementor-363.elementor-motion-effects-parent > section > div.elementor-container.elementor-column-gap-default > div > div > div > div > div > div > div.form-header > div > div > div.form-row.VTUX.multiple-column.column-4').is(':visible'))
                        jQuery('body > div.elementor.elementor-363.elementor-motion-effects-parent > section > div.elementor-container.elementor-column-gap-default > div > div > div > div > div > div > div.form-header > div > div > div.form-row.VTUX.multiple-column.column-4').hide();
                }, 1000);
            });
        </script>
        <?php
    }
}

// mark all oh hold orders as processing
add_action( 'woocommerce_thankyou', 'mark_on_hold_orders_as_processing', 10, 1 );
function mark_on_hold_orders_as_processing( $order_id ) {
    if ( ! $order_id ) {
        return;
    }
    $order = wc_get_order( $order_id );
    if ( $order->has_status( 'on-hold' ) ) {
        $order->update_status( 'processing' );
    }
}

// add text to the bottom of the processing order emails
add_action( 'woocommerce_email_order_details', 'add_text_to_processing_order_emails', 10, 4 );
function add_text_to_processing_order_emails( $order, $sent_to_admin, $plain_text, $email ) {
    echo '<p><strong>IMPORTANT:</strong> All claims must be made on the day of delivery.</p>';
}
