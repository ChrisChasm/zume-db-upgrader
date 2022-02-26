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
                <th>
                    <h1>Upgrade Sessions</h1>
                </th>
            </tr>
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
                    function nextpage() {
                        location.href = "<?php echo admin_url() ?>admin.php?page=<?php echo esc_attr( $this->token )  ?>&loop=true&step=0&nonce=<?php echo wp_create_nonce( 'loop'.get_current_user_id() ) ?>";
                    }
                    setTimeout( "nextpage()", 1500 );
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
        dt_write_log('Begin');
        global $wpdb;
        // Get all records to process
        $zume_groups_raw = $wpdb->get_results(
            "SELECT user_id, meta_key as group_key, meta_value
                    FROM wp_usermeta 
                    WHERE meta_key LIKE 'zume_group_%'
                    AND ( meta_value LIKE '%\"session_9\";b:1%' OR meta_value LIKE '%\"session_10\";b:1%' );
            ", ARRAY_A );
        $trainings_raw = $wpdb->get_results(
            "
                    SELECT pm.post_id, pm.meta_value as group_key, 
                    s1.meta_value as session_1,  
                    s2.meta_value as session_2,
                    s3.meta_value as session_3,  
                    s4.meta_value as session_4,  
                    s5.meta_value as session_5,  
                    s6.meta_value as session_6,  
                    s7.meta_value as session_7,  
                    s8.meta_value as session_8,  
                    s9.meta_value as session_9,    
                    s10.meta_value as session_10
                    FROM wp_3_postmeta pm
                    LEFT JOIN wp_3_postmeta s1 ON pm.post_id=s1.post_id AND s1.meta_key = 'zume_sessions' AND s1.meta_value = 'session_1'
                    LEFT JOIN wp_3_postmeta s2 ON pm.post_id=s2.post_id AND s2.meta_key = 'zume_sessions' AND s2.meta_value = 'session_2'
                    LEFT JOIN wp_3_postmeta s3 ON pm.post_id=s3.post_id AND s3.meta_key = 'zume_sessions' AND s3.meta_value = 'session_3'
                    LEFT JOIN wp_3_postmeta s4 ON pm.post_id=s4.post_id AND s4.meta_key = 'zume_sessions' AND s4.meta_value = 'session_4'
                    LEFT JOIN wp_3_postmeta s5 ON pm.post_id=s5.post_id AND s5.meta_key = 'zume_sessions' AND s5.meta_value = 'session_5'
                    LEFT JOIN wp_3_postmeta s6 ON pm.post_id=s6.post_id AND s6.meta_key = 'zume_sessions' AND s6.meta_value = 'session_6'
                    LEFT JOIN wp_3_postmeta s7 ON pm.post_id=s7.post_id AND s7.meta_key = 'zume_sessions' AND s7.meta_value = 'session_7'
                    LEFT JOIN wp_3_postmeta s8 ON pm.post_id=s8.post_id AND s8.meta_key = 'zume_sessions' AND s8.meta_value = 'session_8'
                    LEFT JOIN wp_3_postmeta s9 ON pm.post_id=s9.post_id AND s9.meta_key = 'zume_sessions' AND s9.meta_value = 'session_9'
                    LEFT JOIN wp_3_postmeta s10 ON pm.post_id=s10.post_id AND s10.meta_key = 'zume_sessions' AND s10.meta_value = 'session_10'
                    WHERE pm.meta_key = 'zume_group_id';
            ", ARRAY_A );

        $trainings = [];
        $zume_groups = [];
        foreach( $trainings_raw as $item ) {
            $trainings[$item['group_key']] = $item;
        }
        foreach( $zume_groups_raw as $item ) {
            $zume_groups[$item['group_key']] = $item;
        }
        foreach( $zume_groups as $group ) {
            if ( isset( $trainings[$group['group_key']] ) ) {
                $k = $group['group_key'];
                $t = $trainings[$k];
                $z = unserialize( $group['meta_value'] );

                if ( empty($t['session_1'] ) && ! empty( $z['session_1'] ) ) {
                    add_post_meta( $t['post_id'], 'zume_sessions', 'session_1', false );
                }
                if ( empty($t['session_2'] ) && ! empty( $z['session_2'] ) ) {
                    add_post_meta( $t['post_id'], 'zume_sessions', 'session_2', false );
                }
                if ( empty( $t['session_3'] ) && ! empty( $z['session_3'] ) ) {
                    add_post_meta( $t['post_id'], 'zume_sessions', 'session_3', false );
                }
                if ( empty($t['session_4'] ) && ! empty( $z['session_4'] ) ) {
                    add_post_meta( $t['post_id'], 'zume_sessions', 'session_4', false );
                }
                if ( empty($t['session_5'] ) && ! empty( $z['session_5'] ) ) {
                    add_post_meta( $t['post_id'], 'zume_sessions', 'session_5', false );
                }
                if ( empty($t['session_6'] ) && ! empty( $z['session_6'] ) ) {
                    add_post_meta( $t['post_id'], 'zume_sessions', 'session_6', false );
                }
                if ( empty( $t['session_7'] ) && ! empty( $z['session_7'] ) ) {
                    add_post_meta( $t['post_id'], 'zume_sessions', 'session_7', false );
                }
                if ( empty($t['session_8'] ) && ! empty( $z['session_8'] ) ) {
                    add_post_meta( $t['post_id'], 'zume_sessions', 'session_8', false );
                }
                if ( empty( $t['session_9'] ) && ! empty( $z['session_9'] ) ) {
                    add_post_meta( $t['post_id'], 'zume_sessions', 'session_9', false );
                }
                if ( empty( $t['session_10'] ) && ! empty( $z['session_10'] ) ) {
                    add_post_meta( $t['post_id'], 'zume_sessions', 'session_10', false );
                }

            }
        }

        dt_write_log('End');

    }

    public function run_task( $result ) {

        /* @todo insert upgrade task */


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