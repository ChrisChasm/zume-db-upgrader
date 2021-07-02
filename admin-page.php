<?php
/**
 * Plugin Name: Zume DB Upgrader
 * Plugin URI: https://github.com/ChrisChasm/zume-db-upgrader
 * Description: Reusable upgrader for Zume maintenance
 * Version:  0.1.0
 * Author URI: https://github.com/DiscipleTools
 * GitHub Plugin URI: https://github.com/ChrisChasm/zume-db-upgrader
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
                    <p><a class="button" id="upgrade_button" href="<?php echo esc_url( trailingslashit( admin_url() ) ) ?>admin.php?page=<?php echo esc_attr( $this->token ) ?>&loop=true" disabled="true">Upgrade</a></p>
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
        /**
         * The loop will skip early records until it reaches the step start number, then it will process 100, and then
         * it will start a new loop sending the new start number.
         */
        global $wpdb;
        // Get total count of records to process
        $total_count = $wpdb->get_var( "SELECT COUNT(*) as count FROM $wpdb->usermeta WHERE meta_key LIKE 'zume_group%'" ); // @todo replace
        // Get all records to process
        $results = $wpdb->get_results( "SELECT * FROM $wpdb->usermeta WHERE meta_key LIKE 'zume_group%'", ARRAY_A ); // @todo replace

        $loop_count = 0;
        $processed_count = 0;
        foreach( $results as $index => $result ) {
            $loop_count++;
            if ( $loop_count < $step ) {
                continue;
            }

            $processed_count++;

            // check if already upgraded. if so, skip. Insert the marker to check for.
            if ( /* @todo insert marker test here*/ false ){
                continue;
            }

            $this->run_task( $result );

            if ( $processed_count > 10000 ) {
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

        $array = maybe_unserialize( $result['meta_value'] );
//        dt_write_log($result['meta_key']);

        $owner_id = $result['user_id'];

        if ( $array['session_9'] || $array['session_10'] ) {
            if ( ! empty( $array['coleaders'] ) ) {
                foreach( $array['coleaders'] as $email ) {
                    $user = get_user_by('email', $email );

                    if ( ! $user ) {
                        continue;
                    }

                    dt_write_log( $email );

                    if ( ! get_user_meta( $user->ID, 'zume_training_complete', true ) ) {
                        dt_write_log( 'zume_training_complete' );
                        update_user_meta( $user->ID, 'zume_training_complete', $result['meta_key'] );
                    }

                    if ( ! get_user_meta( $user->ID, 'zume_address_from_ip', true ) ) {
                        $value = get_user_meta( $owner_id, 'zume_address_from_ip', true );
                        if ( ! $value ) {
                            dt_write_log($email .  ' : zume_address_from_ip' );
                            update_user_meta( $user->ID, 'zume_address_from_ip', $value );
                        }
                    }

                    if ( ! get_user_meta( $user->ID, 'zume_location_grid_meta_from_ip', true ) ) {
                        $value = get_user_meta( $owner_id, 'zume_location_grid_meta_from_ip', true );
                        if ( ! $value ) {
                            dt_write_log( $email .  ' : zume_location_grid_meta_from_ip' );
                            update_user_meta( $user->ID, 'zume_location_grid_meta_from_ip', $value );
                        }
                    }
                    if ( ! get_user_meta( $user->ID, 'zume_location_grid_from_ip', true ) ) {
                        $value = get_user_meta( $owner_id, 'zume_location_grid_from_ip', true );
                        if ( ! $value ) {
                            dt_write_log( $email .  ' : zume_location_grid_from_ip' );
                            update_user_meta( $user->ID, 'zume_location_grid_from_ip', $value );
                        }
                    }
                    if ( ! get_user_meta( $user->ID, 'zume_recent_ip', true ) ) {
                        $value = get_user_meta( $owner_id, 'zume_recent_ip', true );
                        if ( ! $value ) {
                            dt_write_log( $email .  ' : zume_recent_ip' );
                            update_user_meta( $user->ID, 'zume_recent_ip', $value );
                        }
                    }
                    if ( ! get_user_meta( $user->ID, 'zume_raw_location_from_ip', true ) ) {
                        $value = get_user_meta( $owner_id, 'zume_raw_location_from_ip', true );
                        if ( ! $value ) {
                            dt_write_log( $email .  ' : zume_raw_location_from_ip' );
                            update_user_meta( $user->ID, 'zume_raw_location_from_ip', $value );
                        }
                    }


                }

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