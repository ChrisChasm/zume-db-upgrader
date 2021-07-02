<?php
/**
 * Plugin Name: Zume DB Upgrade
 * Plugin URI: https://github.com/DiscipleTools/disciple-tools-one-page-extension
 * Description: One page extension of Disciple Tools
 * Version:  0.1.0
 * Author URI: https://github.com/DiscipleTools
 * GitHub Plugin URI: https://github.com/DiscipleTools/disciple-tools-one-page-extension
 * Requires at least: 4.7.0
 * (Requires 4.7+ because of the integration of the REST API at 4.7 and the security requirements of this milestone version.)
 * Tested up to: 5.3
 *
 * @package Disciple_Tools
 * @link    https://github.com/DiscipleTools
 * @license GPL-2.0 or later
 *          https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) { exit; } // Exit if accessed directly

if ( ! function_exists( 'zume_db_upgrade' ) ) {
    function zume_db_upgrade() {
        return Zume_DB_Upgrade::instance();
    }
}
add_action( 'after_setup_theme', 'zume_db_upgrade' );


/**
 * Class Zume_DB_Upgrade
 */
class Zume_DB_Upgrade {

    public $token = 'zume_db_upgrade';
    public $title = 'Zume DB Upgrade';
    public $permissions = 'manage_options';
    public $limit = 50;

    /**  Singleton */
    private static $_instance = null;
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }
    /**
     * Constructor function.
     * @access  public
     * @since   0.1.0
     */
    public function __construct() {
        if ( is_admin() ) {
            add_action( "admin_menu", array( $this, "register_menu" ) );
        }
    } // End __construct()


    /**
     * Loads the subnav page
     * @since 0.1
     */
    public function register_menu() {
        add_menu_page( 'Zume DB Upgrade', 'Zume DB Upgrade', $this->permissions, $this->token, [ $this, 'content' ], 'dashicons-admin-generic', 59 );
    }

    /**
     * Builds page contents
     * @since 0.1
     */
    public function content() {

        if ( !current_user_can( $this->permissions ) ) { // manage dt is a permission that is specific to Disciple Tools and allows admins, strategists and dispatchers into the wp-admin
            wp_die( 'You do not have sufficient permissions to access this page.' );
        }

        ?>
        <!-- Box -->
        <table class="widefat striped">
            <thead>
            <tr>
                <th><p style="max-width:450px"></p>
                    <p><a class="button" id="upgrade_button" href="<?php echo esc_url( trailingslashit( admin_url() ) ) ?>admin.php?page=<?php echo esc_attr( $this->token ) ?>&loop=true" disabled="true">Upgrade Ip Address Info</a></p>
                </th>
            </tr>
            </thead>
            <tbody>
            <?php
            /* disable button */
            if ( ! isset( $_GET['loop'] ) ) {
                ?>
                <script>
                    jQuery(document).ready(function(){
                        jQuery('#upgrade_button').removeAttr('disabled')
                    })

                </script>
                <?php
            }
            /* Start loop & add spinner */
            if ( isset( $_GET['loop'] ) && ! isset( $_GET['step'] ) ) {
                ?>
                <tr>
                    <td><img src="<?php echo esc_url( get_theme_file_uri() ) ?>/spinner.svg" width="30px" alt="spinner" /></td>
                </tr>
                <script type="text/javascript">
                    <!--
                    function nextpage() {
                        location.href = "<?php echo admin_url() ?>admin.php?page=<?php echo esc_attr( $this->token )  ?>&loop=true&step=0&nonce=<?php echo wp_create_nonce( 'loop'.get_current_user_id() ) ?>";
                    }
                    setTimeout( "nextpage()", 1500 );
                    //-->
                </script>
                <?php
            }

            /* Loop */
            if ( isset( $_GET['loop'], $_GET['step'], $_GET['nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'loop'.get_current_user_id() ) ) {
                $step = sanitize_text_field( wp_unslash( $_GET['step'] ) );
                $this->run_loop( $step );
            }

            ?>
            </tbody>
        </table>
        <?php
    }

    public function run_loop( $step ){
        global $wpdb;
        $total_count = $wpdb->get_var( "SELECT COUNT(*) as count FROM $wpdb->usermeta WHERE meta_key = 'zume_raw_location_from_ip'" );
        $results = $wpdb->get_results( "SELECT * FROM $wpdb->usermeta WHERE meta_key = 'zume_raw_location_from_ip'", ARRAY_A );

        $loop_count = 0;
        $processed_count = 0;
        foreach( $results as $index => $result ) {
            $loop_count++;
            if ( $loop_count < $step ) {
                continue;
            }

            $processed_count++;

            if ( get_user_meta( $result['user_id'], 'zume_location_grid_from_ip', true ) ){ // repetition check
                continue;
            }

            $this->run_task( $result );

            if ( $processed_count > 100 ) {
                break;
            }
        }

        if ( $loop_count >= $total_count  ) {
            return;
        }

        ?>
        <tr>
            <td><img src="<?php echo esc_url( get_theme_file_uri() ) ?>/spinner.svg" width="30px" alt="spinner" /></td>
        </tr>
        <script type="text/javascript">
            <!--
            function nextpage() {
                location.href = "<?php echo admin_url() ?>admin.php?page=<?php echo esc_attr( $this->token )  ?>&loop=true&step=<?php echo esc_attr( $loop_count ) ?>&nonce=<?php echo wp_create_nonce( 'loop'.get_current_user_id() ) ?>";
            }
            setTimeout( "nextpage()", 1500 );
            //-->
        </script>
        <?php
    }

    public function run_task( $result ) {
        if ( empty( $result['meta_value'] ) ){
            return;
        }

        $ip_results = unserialize( $result['meta_value'] );
        $user_id = $result['user_id'];

        if ( isset( $ip_results['ip'] ) &&  ! empty( $ip_results['ip'] ) && isset( $ip_results['country_name'] ) &&  ! empty( $ip_results['v'] )  ) {
            $country = DT_Ipstack_API::parse_raw_result( $ip_results, 'country_name' );
            $region = DT_Ipstack_API::parse_raw_result( $ip_results, 'region_name' );
            $city = DT_Ipstack_API::parse_raw_result( $ip_results, 'city' );

            $address = '';
            if( ! empty($country) ) {
                $address = $country;
            }
            if( ! empty($region) ) {
                $address = $region . ', ' . $address;
            }
            if( ! empty($city) ) {
                $address = $city . ', ' . $address;
            }

            update_user_meta( $user_id, 'zume_address_from_ip', $address ); // location grid id only

            $location_grid_meta = DT_Ipstack_API::convert_ip_result_to_location_grid_meta( $ip_results );
            update_user_meta( $user_id, 'zume_location_grid_meta_from_ip', $location_grid_meta ); // location grid meta array
            update_user_meta( $user_id, 'zume_location_grid_from_ip', $location_grid_meta['grid_id'] ); // location grid id only
        }
        else {
            $ip_address = get_user_meta( $user_id, 'zume_recent_ip', true );
            $ip_results = DT_Ipstack_API::geocode_ip_address( $ip_address );
            update_user_meta( $user_id, 'zume_raw_location_from_ip', $ip_results );

            if ( class_exists( 'DT_Ipstack_API' ) ) {
                $country = DT_Ipstack_API::parse_raw_result( $ip_results, 'country_name' );
                $region = DT_Ipstack_API::parse_raw_result( $ip_results, 'region_name' );
                $city = DT_Ipstack_API::parse_raw_result( $ip_results, 'city' );

                $address = '';
                if( ! empty($country) ) {
                    $address = $country;
                }
                if( ! empty($region) ) {
                    $address = $region . ', ' . $address;
                }
                if( ! empty($city) ) {
                    $address = $city . ', ' . $address;
                }

                update_user_meta( $user_id, 'zume_address_from_ip', $address ); // location grid id only

                $location_grid_meta = DT_Ipstack_API::convert_ip_result_to_location_grid_meta( $ip_results );
                update_user_meta( $user_id, 'zume_location_grid_meta_from_ip', $location_grid_meta ); // location grid meta array
                update_user_meta( $user_id, 'zume_location_grid_from_ip', $location_grid_meta['grid_id'] ); // location grid id only

            }
        }

    }

    /**
     * Magic method to output a string if trying to use the object as a string.
     *
     * @since  0.1
     * @access public
     * @return string
     */
    public function __toString() {
        return $this->token;
    }

    /**
     * Magic method to keep the object from being cloned.
     *
     * @since  0.1
     * @access public
     * @return void
     */
    public function __clone() {
        _doing_it_wrong( __FUNCTION__, esc_html('Whoah, partner!'), '0.1' );
    }

    /**
     * Magic method to keep the object from being unserialized.
     *
     * @since  0.1
     * @access public
     * @return void
     */
    public function __wakeup() {
        _doing_it_wrong( __FUNCTION__, esc_html('Whoah, partner!'), '0.1' );
    }

    /**
     * Magic method to prevent a fatal error when calling a method that doesn't exist.
     *
     * @since  0.1
     * @access public
     * @return null
     */
    public function __call( $method = '', $args = array() ) {
        // @codingStandardsIgnoreLine
        _doing_it_wrong( __FUNCTION__, esc_html('Whoah, partner!'), '0.1' );
        unset( $method, $args );
        return null;
    }
}