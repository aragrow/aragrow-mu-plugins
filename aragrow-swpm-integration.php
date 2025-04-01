<?php
/**
 * Plugin Name:       Aragrow SWPM Integration
 * Plugin URI:        https://aragrow.me/plugins/aragrow-swpm-integration
 * Description:       Integration Settins for the Simple WP Membership plugin.
 * Author:            David Aragó - ARAGROW,LLC
 * Author URI:        https://aragrow.me
 * Version:           1.1.4
 * Text Domain:       aragrow-swpm-Integration
 * Domain Path:       /assets/languages
 *
*/

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

class Aragrow_SWPM_Integration_MU_Plugins
{

    private static $instance;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct()
    {

        //error_log(__CLASS__.'::'.__FUNCTION__);
        $this->init();

    }

    // Callback function to load plugin
    public function init()
    {
        //error_log(__CLASS__.'::'.__FUNCTION__);

        add_action('admin_enqueue_scripts', [$this, 'admin_enqueue' ]);
        add_filter('woocommerce_prevent_admin_access', [ $this, 'allow_subscriber_admin_access' ], 10, 2);

    }

    function admin_enqueue() {
        if (!wp_script_is('font-awesome', 'enqueued')) {
            wp_enqueue_style('font-awesome', 'https://use.fontawesome.com/releases/v6.0.0/css/all.css', array(), '6.0.0');
        }

        if (!wp_script_is('google-recaptcha', 'enqueued')) {
            wp_enqueue_script( 'google-recaptcha', 'https://www.google.com/recaptcha/api.js', array(), null, true );
        }
    }


/**
     * It hooks into the 'woocommerce_prevent_admin_access' filter, which WooCommerce uses to determine whether to 
     * redirect users away from the admin area.
     * 
     * For all other users, it returns the original $prevent_access value, maintaining WooCommerce's default behavior.
    */
    public function allow_subscriber_admin_access($prevent_access, $redirect=null) {
          
            if (class_exists('SimpleWpMembership')) {
                $user = wp_get_current_user();
                if (in_array('subscriber', (array) $user->roles)) {
                    return false; // Grant access, which is the default for future used.
                }
            }
        
        return $prevent_access; // Default WooCommerce behavior
    }
}

//error_log('Instantiating Aragrow-MU-Plugin()');
new Aragrow_SWPM_Integration_MU_Plugins();

class Aragrow_SWPM_Integration_With_WooCommerce
{

    private static $instance;
    private $products = [];

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Primary class constructor.
     *
     * @since 1.2.2
     */
    public function __construct()
    {
       
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'save_mappings'));

        add_action('swpm_front_end_profile_edited', 'create_woocommerce_customer',1,10);

    }

    public function missing_dependencies_notice() {
        if(WP_DEBUG) error_log('Executing ' . __CLASS__ . '::' . __METHOD__);
        ?>
        <div class="notice notice-error">
            <p><strong>Custom Integrations:</strong> The following required plugins are missing or inactive:</p>
            <ul>
                <?php if (!class_exists('SimpleWpMembership')): ?>
                    <li>Simple Membership Plugin</li>
                <?php endif; ?>
                <?php if (!class_exists('WooCommerce')): ?>
                    <li>WooCommerce</li>
                <?php endif; ?>
                <?php if (!class_exists('WPO_WCPDF')): ?>
                    <li>WooCommerce PDF Invoices & Packing Slips</li>
                <?php endif; ?>
            </ul>
        </div>
        <?php
    }

    public function create_woocommerce_customer($member_info) {
        if(WP_DEBUG) error_log('Executing ' . __METHOD__);

        $member_id = get_current_user_id();
        $customer_id = get_user_meta($member_id, 'wc_customer_id', true);

        error_log(print_r($member_info,true));
        exit;
        
        if ($customer_id) return;

        $customer = new WC_Customer();
        
        try {
            $customer->set_email($member->email);
            $customer->set_first_name($member->first_name);
            $customer->set_last_name($member->last_name);
            
            // Add additional fields from SWPM
            $customer->add_meta_data('swpm_member_id', $member->member_id);
            $customer->add_meta_data('membership_level', $member->membership_level);
            
            // Set billing address if available
            if (isset($member->address)) {
                $customer->set_billing_address_1($member->address);
            }
            
            $customer_id = $customer->save();
            
            return $customer_id;
            
        } catch (Exception $e) {
            error_log('SWPM to WooCommerce Customer Error: ' . $e->getMessage());
            return false;
        }
    }

    function update_woocommerce_customer($member_info) {
        if(WP_DEBUG) error_log('Executing ' . __METHOD__);
        error_log(print_r($_POST,true));
        error_log(print_r($member_info,true));
        $member_id = get_current_user_id();
        // Get WooCommerce customer ID
        $customer_id = get_user_meta($member_id, 'wc_customer_id', true);
        
        if (!$customer_id) {
            return; // No associated WooCommerce customer
        }
        
        $customer = new WC_Customer($customer_id);
        
        // Update customer information
        $customer->set_first_name($member_info['first_name']);
        $customer->set_last_name($member_info['last_name']);
        $customer->set_email($member_info['email']);
        
        // Update billing information if available
        if (isset($member_info['address'])) {
            $customer->set_billing_address_1($member_info['address']);
        }
        if (isset($member_info['city'])) {
            $customer->set_billing_city($member_info['city']);
        }
        if (isset($member_info['state'])) {
            $customer->set_billing_state($member_info['state']);
        }
        if (isset($member_info['country'])) {
            $customer->set_billing_country($member_info['country']);
        }
        if (isset($member_info['zip'])) {
            $customer->set_billing_postcode($member_info['zip']);
        }
        
        // Save customer data
        $customer->save();
        
        // Trigger WooCommerce update customer action
        do_action('woocommerce_update_customer', $customer_id, $customer);
    }
    

    public function create_membership_order($customer_id, $membership_level) {
        if(WP_DEBUG) error_log('Executing ' . __CLASS__ . '::' . __METHOD__);
        $order = wc_create_order(array(
            'customer_id' => $customer_id,
            'status'      => 'pending',
        ));
        
        $wc_product = get_option('swpm_wc_product_mappings');

        
        if ($wc_product) {
            $order->add_product($wc_product['$membership_level'], 1);
        }

        // Set order meta
        $order->update_meta_data('_swpm_membership_order', 'yes');
        $order->update_meta_data('_swpm_membership_level', $wc_product['post_title']);
        
        // Calculate totals
        $order->calculate_totals();
        $order->save();

        return $order->get_id();
    }

    public function generate_invoice($order_id) {
        if(WP_DEBUG) error_log('Executing ' . __CLASS__ . '::' . __METHOD__);
        if (!class_exists('WC_PDF_Invoices')) return false;

        try {
            // Get invoice from PDF plugin
            $invoice = wcpdf_get_invoice($order_id);
            
            // Generate and email invoice
            $invoice->generate();
            $invoice->send_email();
            
            return true;
            
        } catch (Exception $e) {
            error_log('Invoice Generation Error: ' . $e->getMessage());
            return false;
        }   
    }

    public function get_membership_product_id($level_id) {
        // Implement logic to get WooCommerce product ID associated with membership level
        // This could be stored in options or custom table
        return $this->products[$level_id];
    }

    public function add_admin_menu() {
    
        // Example submenu page
        add_submenu_page(
            ARAGROW_CUSTOM_INTEGRATION_MENU, // Parent slug
            'SWPM-WooCommerce', // Page title
            'SM & Woo', // Menu title
            'manage_options', // Capability
            'aragrow-swpm-woocommerce-integration', // Menu slug
            array($this, 'render_swpm_woocommerce_page') // Callback function
        );
    }

    public function render_swpm_woocommerce_page() {
     
        // ../simple-membership/classes/class.swpm-utils-membership-level.php
        $levels = SwpmMembershipLevelUtils::get_all_membership_levels_in_array();
       
        $products = wc_get_products(array(
            'category' => ['simple-member-plans'],
            'status' => 'publish',
            'limit' => -1,
            'return' => 'objects'
        ));
        $mappings = get_option('swpm_wc_product_mappings', array());
    
        ?>
        <div class="wrap">
            <h1>WP Membership Level and WooCommerce Product Mappings</h1>
            
            <form method="post">
                <?php wp_nonce_field('swpm_wc_mapping_nonce', 'swpm_wc_mapping_nonce'); ?>
                <strong>To integrate the Simple Member with WooCommerce:</strong>
                <ol>
                    <li> create a product category with the slug ('simple-member-plans'),</li>
                    <li> for each membership level create a product and associate to the above category,</li>
                    <li> use the form below to associate the SM Level to the WOO product.</li>
                </ol>

                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><strong>Membership Level</strong></th>
                            <th><strong>Associated Product</strong></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($levels as $index => $level) : ?>
                        <tr>
                            <td>
                                <?php echo esc_html($level); ?>
                                <input type="hidden" name="levels[]" value="<?php echo $index; ?>">
                            </td>
                            <td>
                                <select name="product[<?php echo $index; ?>]" class="regular-text">
                                    <option value="0">— Select Product —</option>
                                    <?php foreach ($products as $product) : ?>
                                        <option value="<?php echo $product->get_id(); ?>" 
                                            <?php selected($mappings[$index] ?? 0, $product->get_id()); ?>>
                                            <?php echo esc_html($product->get_name()); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php submit_button('Save Mappings'); ?>
            </form>
        </div>
        <?php
    }

    public function save_mappings() {
        if (!isset($_POST['swpm_wc_mapping_nonce']) || 
            !wp_verify_nonce($_POST['swpm_wc_mapping_nonce'], 'swpm_wc_mapping_nonce') ||
            !current_user_can('manage_options')) {
            return;
        }

        $mappings = array();
        if (isset($_POST['levels']) && isset($_POST['product'])) {
            foreach ($_POST['levels'] as $level_id) {
                $product_id = isset($_POST['product'][$level_id]) ? 
                    absint($_POST['product'][$level_id]) : 0;
                
                if ($product_id > 0) {
                    $mappings[sanitize_key($level_id)] = $product_id;
                }
            }
        }

        update_option('swpm_wc_product_mappings', $mappings);
        add_settings_error(
            'swpm_wc_mappings',
            'settings_updated',
            'Mappings saved successfully!',
            'success'
        );
    }

}
//error_log('Instantiating Aragrow-MU-Plugin()');
new Aragrow_SWPM_Integration_With_WooCommerce();