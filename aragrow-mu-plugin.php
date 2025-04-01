<?php
/**
 * Plugin Name:       Shake Before Using 
 * Plugin URI:        https://aragrow.me/plugins/shake-before-using
 * Description:       "Shake Before Using" is designed to kickstart your WordPress website by implementing essential best practices.
 * Requires at least: 5.5
 * Requires PHP:      7.0
 * Author:            Aragrow
 * Author URI:        https://aragrow.me
 * Version:           1.1.4
 * Text Domain:       aragrow-mu-plugin
 * Domain Path:       /assets/languages
 *
 * Aragrow-MU-Plugins is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * any later version.
 *
 * Aragrow-Base is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * For GNU General Public License, see <https://www.gnu.org/licenses/>.
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Define a constant for the plugin's base directory. This makes the code more readable and easier to maintain.
defined( 'ARAGROW_CUSTOM_INTEGRATION_MENU' ) or define( 'ARAGROW_CUSTOM_INTEGRATION_MENU', 'aragrow-custom-integrations' );


class Aragrow_MU_Plugins
{

    /**
     * Primary class constructor.
     *
     * @since 1.2.2
     */
    public function __construct()
    {

        //error_log(__CLASS__.'::'.__FUNCTION__);
        $this->init();

    }

    // Callback function to load plugin
    public function init()
    {
        //error_log(__CLASS__.'::'.__FUNCTION__);
     
        add_action('wp_head', [ $this, 'add_custom_meta_tags' ]);

        // Add a filter to totally disable listing of users.
        add_filter('rest_endpoints', [ $this, 'disable_rest_api_by_user' ]);

        // Add a filter to disable the listing of users by user authenticated.
        add_filter('rest_authentication_errors', [ $this, 'disable_rest_api_by_user' ]);

        add_action('admin_enqueue_scripts', [$this, 'add_font_awesome' ]);

        add_action('wp_dashboard_setup', [ $this, 'clear_dashboard' ], 998);

        add_action('admin_menu', array($this, 'add_custom_integrations_menu'));

    }

    function add_font_awesome() {
        if (!wp_script_is('font-awesome', 'enqueued')) {
            wp_enqueue_style('font-awesome', 'https://use.fontawesome.com/releases/v6.0.0/css/all.css', array(), '6.0.0');
        }
    }

    function add_captcha_v3(){
        if (!wp_script_is('google-recaptcha', 'enqueued')) {
            wp_enqueue_script( 'google-recaptcha', 'https://www.google.com/recaptcha/api.js', array(), null, true );
        }
    }

    // Callback function to disable REST API access
    public function disable_rest_api($endpoints)
    {
        if ( isset( $endpoints['/wp/v2/users'] ) ) {
            unset( $endpoints['/wp/v2/users'] );
        }
        if ( isset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] ) ) {
            unset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
        }
        return $endpoints;
    }

    // Callback function to disable REST API access
    public function disable_rest_api_by_user($access)
    {
        //var_dump('AragrowBase->'.__FUNCTION__);
        //error_log('AragrowBase->'.__FUNCTION__);
         // Check if the user is not logged in or has the 'external_user' role
        if (!is_user_logged_in() || (is_user_logged_in() && current_user_can('external_user'))) {
            // Return a custom error response
            return new WP_Error(
                'rest_disabled',
                __('Unable to process the request.', 'aragrow-base'),
                array('status' => rest_authorization_required_code())
            );
        }

        // If the user is logged in and does not have the 'external_user' role, allow access
        return $access;
    }

    public function add_custom_meta_tags() {
        $tagline = get_bloginfo('description');
        echo "<meta name='description' content='$tagline'>";
        // Add more meta tags as needed
    }

    public function clear_dashboard() {
        global $wp_meta_boxes;
        $wp_meta_boxes['dashboard'] = array();
    }

    public function add_custom_integrations_menu() {
        add_menu_page(
            'Custom Integrations', // Page title
            'Custom Integrations', // Menu title
            'manage_options', // Capability required to see this menu
            ARAGROW_CUSTOM_INTEGRATION_MENU, // Menu slug
            array($this, 'render_main_page'), // Callback function to render the page
            'dashicons-admin-generic', // Icon (you can change this)
            25 // Position in the menu
        );

    }

    public function render_main_page() {
        ?>
        <div class="wrap">
            <h1>Custom Integrations</h1>
            <p>Welcome to the Custom Integrations dashboard. Select a submenu to manage specific integrations.</p>
        </div>
        <?php
    }


}
//error_log('Instantiating Aragrow-MU-Plugin()');
new Aragrow_MU_Plugins();