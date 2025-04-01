<?php
/**
 * Plugin Name:       Aragrow WooCommerce and PDF Invoices Integration
 * Plugin URI:        https://aragrow.me/plugins/aragrow-woo-integration
 * Description:       Integration Settins for the WooCommerce and PDF Invoice plugins.
 * Author:            David Aragó - ARAGROW,LLC
 * Author URI:        https://aragrow.me
 * Version:           1.1.4
 * Text Domain:       aragrow-woo-Integration
 * Domain Path:       /assets/languages
 *
*/

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}
use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;

class Aragrow_WOO_Integration_MU_Plugins
{

    public function __construct()
    {

        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
      //  add_filter('woocommerce_before_order_itemmeta', [$this, 'custom_admin_order_item_quantity_input'], 10, 2);
      // add_action('woocommerce_before_order_item_quantity_save', [$this, 'allow_decimal_quantities_in_orders'], 10, 2);
      //  add_filter('woocommerce_order_item_quantity_html', [$this, 'display_decimal_quantities_in_admin'], 10, 2);
      //  add_action('admin_menu', [$this, 'add_manual_payments_submenu']);
        add_action('add_meta_boxes', [ $this, 'add_manual_payment_meta_box' ]);
        // Save meta box data
        add_action('woocommerce_process_shop_order_meta', [ $this,'save_manual_payment_data' ], 10, 2);
      //  add_action('woocommerce_admin_order_data_after_order_details', [$this, 'display_manual_payment_details']);

        // Add Custom Field
        add_action('show_user_profile', [ $this, 'add_custom_field_ein_ssn' ]);  // Profile
        add_action('edit_user_profile', [ $this, 'add_custom_field_ein_ssn' ]);  // Admin
        
        add_action('woocommerce_admin_order_data_after_billing_address' , [ $this, 'add_timekeeping_invoice_field_to_order' ]);

        // Save Custom Field
        add_action('personal_options_update', [ $this, 'ein_ssn_save_user_field' ]); // Profile
        add_action('edit_user_profile_update', [ $this, 'ein_ssn_save_user_field' ]); // Admin

        add_action('woocommerce_process_shop_order_meta', [ $this, 'save_timekeeping_invoice_field' ]);

        // Mostrar el campo en la lista de usuarios
        add_filter('manage_users_columns', [ $this, 'ein_ssn_add_user_column' ]);
        add_action('manage_users_custom_column', [ $this, 'ein_ssn_show_user_column_value' ], 10, 3 );

        // Modify the order line headers
        add_filter('wpo_wcpdf_get_aragrow_time_invoice_template_table_headers', [$this, 'custom_aragrow_time_invoice_table_headers'], 10, 2);
        add_filter('wpo_wcpdf_get_aragrow_invoice_template_table_headers', [$this, 'custom_aragrow_invoice_table_headers'], 10, 2);
    }

    // Helper function to get all product IDs in the TIMEGROW category
    function is_timegrow_product_id($id) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        $args = array(
            'ID' => $id,
            'category' => array('timegrow'),
            'limit' => 1, // Retrieve all products
        );
        $product = wc_get_products($args);
        if ($product) return true;
        else return false;
    }

    function custom_admin_order_item_quantity_input($item_id, $item) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        // Only run this on the order edit page
        if (!is_admin() || !isset($_GET['post']) || get_post_type($_GET['post']) !== 'shop_order') {
            return;
        }
    
        // Remove the default quantity field
        remove_action('woocommerce_before_order_itemmeta', array('WC_Meta_Box_Order_Items', 'output_item_quantity_input'), 10);
    
        // Output our custom quantity field
        ?>
        <div class="edit" style="display: none;">
            <input type="number"
                   step="any"
                   min="0"
                   name="order_item_qty[<?php echo esc_attr($item_id); ?>]"
                   placeholder="0"
                   value="<?php echo esc_attr($item->get_quantity()); ?>"
                   data-qty="<?php echo esc_attr($item->get_quantity()); ?>"
                   size="8"
                   class="quantity"
                   style="width: 80px; padding: 5px;"
            />
        </div>
        <?php
    }   

    function allow_decimal_quantities_in_orders($item_id, $item) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        if (isset($_POST['order_item_qty'][$item_id])) {
            $quantity = floatval($_POST['order_item_qty'][$item_id]);
            $item->set_quantity($quantity); // Save as a decimal
        }
    }
    
    function display_decimal_quantities_in_admin($quantity_html, $item) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        $quantity = $item->get_quantity();
        return number_format((float) $quantity, 2, '.', ''); // Display with 2 decimal places
    }
    
    function add_manual_payments_submenu() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        add_submenu_page(
            'woocommerce',
            'Manual Payments',
            'Manual Payments',
            'manage_woocommerce',
            'manual-payments',
            [$this, 'manual_payments_page_callback']
        );
    }

    function manual_payments_page_callback() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        echo '<div class="wrap">';
        echo '<h1>Manual Payments</h1>';
        echo '<p>This is the Manual Payments page content.</p>';
        echo '</div>';
    }

    function add_manual_payment_meta_box() {
        $screen = wc_get_container()->get(CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled()
            ? wc_get_page_screen_id('shop-order')
            : 'shop_order';
    
        error_log('Adding manual payment meta box to screen: ' . $screen);
        add_meta_box(
            'manual_payment_meta_box',
            __('Manual Payment Details', 'textdomain'),
            [ $this, 'render_manual_payment_meta_box' ],
            $screen,
            'side',
            'high'
        );
    }

    function render_manual_payment_meta_box($post_or_order_object) {
        // Get the order object whether HPOS is enabled or not
        $order = ($post_or_order_object instanceof WP_Post)
            ? wc_get_order($post_or_order_object->ID)
            : $post_or_order_object;
    
        if (!$order) return;
    
        // Retrieve existing values
        $payment_date = $order->get_meta('_payment_date');
        $payment_amount = $order->get_meta('_payment_amount');
        $payment_type = $order->get_meta('_payment_type');
    
        // Display fields
        echo '<div class="manual-payment-fields">';
        
        // Payment Date
        echo '<p><label for="payment_date">' . __('Payment Date:', 'textdomain') . '</label>';
        echo '<input type="date" id="payment_date" name="payment_date" value="' . esc_attr($payment_date) . '" /></p>';
        
        // Payment Amount
        echo '<p><label for="payment_amount">' . __('Amount:', 'textdomain') . '</label>';
        echo '<input type="number" step="0.01" id="payment_amount" name="payment_amount" value="' . esc_attr($payment_amount) . '" /></p>';
        
        // Payment Type
        echo '<p><label for="payment_type">' . __('Type:', 'textdomain') . '</label>';
        echo '<select id="payment_type" name="payment_type">';
        echo '<option value="ACH"' . selected($payment_type, 'ACH', false) . '>ACH</option>';
        echo '<option value="Transference"' . selected($payment_type, 'Transference', false) . '>Transference</option>';
        echo '<option value="Cash"' . selected($payment_type, 'Cash', false) . '>Cash</option>';
        echo '</select></p>';
        
        echo '</div>';
    }

    function save_manual_payment_data($order_id, $post) {
        $order = wc_get_order($order_id);
        
        if (isset($_POST['payment_date'])) {
            $order->update_meta_data('_payment_date', sanitize_text_field($_POST['payment_date']));
        }
        
        if (isset($_POST['payment_amount'])) {
            $order->update_meta_data('_payment_amount', floatval($_POST['payment_amount']));
        }
        
        if (isset($_POST['payment_type'])) {
            $order->update_meta_data('_payment_type', sanitize_text_field($_POST['payment_type']));
        }
        
        $order->save();
    }

    function display_manual_payment_details($order) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        $order_id = $order->get_id();
        
        $payment_date = get_post_meta($order_id, '_payment_date', true);
        $payment_amount = get_post_meta($order_id, '_payment_amount', true);
        $payment_type = get_post_meta($order_id, '_payment_type', true);
    
        echo '<div style="margin-top:10px;">';
        echo '<h3>' . __('Manual Payment Details') . '</h3>';
        echo '<p><strong>' . __('Payment Date:') . '</strong> ' . esc_html($payment_date) .'<br />';
        echo ' <strong>' . __('Payment Amount:') . '</strong> $' . esc_html(number_format((float)$payment_amount, 2)) .'<br />';
        echo ' <strong>' . __('Payment Type:') . '</strong> ' . esc_html($payment_type) . '</p>';
        echo '</div>';
    }

    function add_custom_field_ein_ssn($user) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        $timegrow_ein_ssn = get_user_meta($user->ID, 'timegrow_ein_ssn', true);
        ?>
        <h3><?php _e('INE/SSN'); ?></h3>
        <table class="form-table">
            <tr>
                <th><label for="billing_dni_nie_CIF"><?php _e('Number'); ?></label></th>
                <td>
                    <input type="text" name="timegrow_ein_ssn" id="billing_dni_nie_CIF" 
                        value="<?php echo esc_attr($timegrow_ein_ssn); ?>" class="regular-text">
                    <p class="description"><?php _e('Introduce the EIN or SSN of the Client.'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    // Guardar el campo DNI/NIE/CIF cuando se actualiza el usuario
    function ein_ssn_save_user_field($user_id) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        if (!current_user_can('edit_user', $user_id)) {
            return false;
        }

        if (isset($_POST['timegrow_ein_ssn'])) {
            update_user_meta($user_id, '_timegrow_ein_ssn', sanitize_text_field($_POST['timegrow_ein_ssn']));
        }
    }

    // Mostrar el campo en la lista de usuarios
    function ein_ssn_add_user_column($columns) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        $columns['_timegrow_ein_ssn'] = __('EIN SSN');
        return $columns;
    }

    function ein_ssn_show_user_column_value($value, $column_name, $user_id) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        if ($column_name == 'timegrow_ein_ssn') {
            return get_user_meta($user_id, '_timegrow_ein_ssn', true);
        }
        return $value;
    }

    // Add custom field to admin order page
    public function add_timekeeping_invoice_field_to_order($order) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        woocommerce_wp_checkbox(array(
            'id'          => 'timekeeping_invoice',
            'label'       => 'Is Hours Invoice?',
            'description' => '',
            'value'       => get_post_meta($order->get_id(), '_timekeeping_invoice', true),
        ));
    }
    // Save custom field value

    function save_timekeeping_invoice_field($order_id) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        $is_timekeeping = isset($_POST['timekeeping_invoice']) ? 'yes' : 'no';
        update_post_meta($order_id, '_timekeeping_invoice', $is_timekeeping);
    }

    function custom_aragrow_time_invoice_table_headers($headers, $document) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        $headers = array(
            'product'  => __('Project', 'woocommerce'),
            'quantity' => __('Hours', 'woocommerce'),
            'price'    => __('Rate', 'woocommerce'),
            'total'    => __('Total', 'woocommerce'),
        );
        return $headers;
    }

    function custom_aragrow_invoice_table_headers($headers, $document) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        $headers = array(
            'product'  => __('Product', 'woocommerce'),
            'quantity' => __('Quantity', 'woocommerce'),
            'price'    => __('Price', 'woocommerce'),
            'total'    => __('Total', 'woocommerce'),
        );
        return $headers;
    }
}

New Aragrow_WOO_Integration_MU_Plugins();
