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
    public $limit = 30;

    /**  Singleton */
    private static $_instance = null;
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }
    public function __construct() {
        if ( is_admin() ) {
            add_action( "admin_menu", array( $this, "register_menu" ) );
        }
    }
    public function register_menu() {
        add_menu_page( 'Upgade Training Locations', 'Zume DB Upgrade', $this->permissions, $this->token, [ $this, 'content' ], 'dashicons-admin-generic', 59 );
    }
    public function content() {

        if ( !current_user_can( $this->permissions ) ) { // manage dt is a permission that is specific to Disciple Tools and allows admins, strategists and dispatchers into the wp-admin
            wp_die( 'You do not have sufficient permissions to access this page.' );
        }

        ?>
        <!-- Box -->
        <table class="widefat striped">
            <thead>
            <tr><th>Upgrade Contact Locations from Zume user records</th></tr>
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
        global $wpdb;

        $results = $wpdb->get_results(
            "SELECT pm.post_id, pm1.meta_value as location, pm2.meta_value as user_id
                    FROM wp_3_postmeta pm
                    LEFT JOIN wp_3_postmeta pm1 ON pm.post_id=pm1.post_id AND pm1.meta_key = 'location_grid_meta'
                    LEFT JOIN wp_3_postmeta pm2 ON pm.post_id=pm2.post_id AND pm2.meta_key = 'zume_training_id'
                    WHERE pm.meta_key = 'overall_status' AND pm.meta_value = 'registered_only' AND pm1.meta_value IS NULL AND pm2.meta_value IS NOT NULL
                    ;"
            , ARRAY_A );

        $total_count = count( $results );

        $loop_count = 0;
        $processed_count = 0;
        foreach( $results as $index => $result ) {
            $loop_count++;
            if ( $loop_count < $step ) {
                continue;
            }

            $processed_count++;

            $this->run_task( $result );

            if ( $processed_count > $this->limit ) {
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
            function nextpage() {
                location.href = "<?php echo admin_url() ?>admin.php?page=<?php echo esc_attr( $this->token )  ?>&loop=true&step=<?php echo esc_attr( $loop_count ) ?>&nonce=<?php echo wp_create_nonce( 'loop'.get_current_user_id() ) ?>";
            }
            setTimeout( "nextpage()", 1500 );
        </script>
        <?php
    }

    public function run_task( $result ) {

        $user_id = $result['user_id'];
        if ( empty( $user_id ) ) {
            return;
        }
        $contact_post_id = $result['post_id'];

        $user_ip_location = get_user_meta( $user_id, 'zume_location_grid_meta_from_ip', true );
        $user_profile_location = get_user_meta( $user_id, 'location_grid_meta', true );
        $user_recent_ip = get_user_meta( $user_id, 'zume_recent_ip', true );

        $contact_post = DT_Posts::get_post( 'contacts', $contact_post_id, false, false );
        if ( is_wp_error( $contact_post ) ) {
            dt_write_log('Could not find training');
            return;
        }

        // skip the location if already set
        if ( isset( $contact_post['location_grid_meta'] ) && ! empty( $contact_post['location_grid_meta'] ) ) {
//            dt_write_log('Has a location: ' . $contact_post_id );
            return;
        }

        // set the location of the training group
        if ( isset( $user_profile_location['lng'] ) && ! empty( $user_profile_location['lng'] ) ) {
            // check if user provided user location
//            dt_write_log('$user_profile_location');
            $fields['location_grid_meta'] = [
                "values" => [
                    [
                        'lng' => $user_profile_location['lng'],
                        'lat' => $user_profile_location['lat'],
                        'label' => $user_profile_location['label'],
                        'level' => ''
                    ]
                ]
            ];
        } else if ( isset( $user_ip_location['lng'] ) && ! empty( $user_ip_location['lng'] ) ) {
            // check if ip location already parsed
//            dt_write_log('$user_ip_location');
            $fields['location_grid_meta'] = [
                "values" => [
                    [
                        'lng' => $user_ip_location['lng'],
                        'lat' => $user_ip_location['lat'],
                        'label' => $user_ip_location['label'],
                        'level' => ''
                    ]
                ]
            ];
        }  else if ( $user_recent_ip ) {
            // check if ip address available to be parsed.
            dt_write_log('Only has an ip address. Needs converted.');
            return;
        } else {
            dt_write_log('No location info found');
            return;
        }

        dt_write_log( $contact_post_id );
//        dt_write_log( $fields );

        $record = DT_Posts::update_post( 'contacts', $contact_post_id, $fields, false, false );
        if ( ! is_wp_error( $record ) ) {
            dt_write_log('Updated location: ' . $contact_post_id );
//            dt_write_log($record);
        } else {
            dt_write_log('Failed to update record: ' . $contact_post_id );
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