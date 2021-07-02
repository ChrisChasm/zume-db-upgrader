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
        $wp_theme = wp_get_theme();
        $is_theme_dt = strpos( $wp_theme->get_template(), "disciple-tools-theme" ) !== false || $wp_theme->name === "Disciple Tools";
        if ( !$is_theme_dt ){
            return false;
        }
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
    public $working_languages = [];

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
            $this->working_languages = get_option('dt_working_languages');
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
        $total_count = $wpdb->get_var( "SELECT COUNT(*) as count FROM wp_zume_logging" );
        // Get all records to process
        $results = $wpdb->get_results( "SELECT * FROM wp_zume_logging", ARRAY_A );

        $loop_count = 0;
        $processed_count = 0;
        foreach( $results as $index => $result ) {
            $loop_count++;
            if ( $loop_count < $step ) {
                continue;
            }

            $processed_count++;

            // check if already upgraded. if so, skip. Insert the marker to check for.
//            if ( /* @todo insert marker test here*/ get_user_meta( $result['user_id'], 'zume_location_grid_from_ip', true ) ){
//                continue;
//            }

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
        global $wpdb;
        $skip = [
            'logged_out', 'logged_in', 'update_profile', 'added_affiliate_key', 'activate_group', 'coleader_invitation_response', 'delete_group', 'deleted'
        ];
        if ( in_array( $result['action'], $skip ) ) {
            return;
        }

        $created_date = $result['created_date'];
        $user_id = $result['user_id'];

        // get user location information
        $location_grid_meta = get_user_meta( $user_id, 'zume_location_grid_meta_from_ip', true );
        if ( $location_grid_meta ) {
            $lng = $location_grid_meta['lng'];
            $lat = $location_grid_meta['lat'];
            $level = $location_grid_meta['level'];
            $label = $location_grid_meta['label'];
            $grid_id = $location_grid_meta['grid_id'];


        } else {
            $lng = 0;
            $lat = 0;
            $level = '';
            $label = '';
            $grid_id = '';
        }

        if ( $grid_id ) {
            $country_name = $wpdb->get_var("
            SELECT lgc.name 
            FROM $wpdb->dt_location_grid lg
            LEFT JOIN $wpdb->dt_location_grid lgc ON lg.admin0_grid_id=lgc.grid_id
            WHERE lg.grid_id = {$grid_id}
        ");
        } else {
            $country_name = '';
        }

        $page = $result['page'];

        $lang_code = get_user_meta( $user_id, 'zume_language', true );
        if( isset( $this->working_languages[$lang_code] ) ) {
            $language = $lang_code;
            $language_name = $this->working_languages[$lang_code]['label'];
        } else {
            $language =  'en';
            $language_name = 'English';
        }

        $action = '';
        $session = '';
        $category = '';
        $group_size = 1;
        if ( $this->startsWith( $result['action'], 'registered'  ) ) {
            $action = 'zume_training';
            $session = '';
            $category = 'joining';
        }
        else if ( $this->startsWith( $result['action'], 'session'  ) && 'course' === $page && $this->startsWith( $result['meta'], 'group'  )) {
            $session = str_replace('_', '', substr( $result['action'], -2, 2 ) );
            $action = 'leading_' . $session;
            $group_size = str_replace('_', '', substr( $result['meta'], -2, 2 ) );
            $category = 'leading';
        }
        else if ( $this->startsWith( $result['action'], 'session'  ) && 'course' === $page && $this->startsWith( $result['meta'], 'member'  )) {
            $session = str_replace('_', '', substr( $result['action'], -2, 2 ) );
            $action = 'leading_' . $session;
            $group_size = str_replace('_', '', substr( $result['meta'], -2, 2 ) );
            $category = 'leading';
        }
        else if ( $this->startsWith( $result['action'], 'session'  ) && 'course' === $page && $this->startsWith( $result['meta'], 'explore'  )) {
            $session = str_replace('_', '', substr( $result['action'], -2, 2 ) );
            $action = 'leading_' . $session;
            $group_size = 1;
            $category = 'studying';
        }
        else if ( $this->startsWith( $result['action'], 'create_group'  ) ) {
            $session = '';
            $action = 'starting_group';
            $group_size = 1;
            $category = 'leading';
        }
        else if ( $this->startsWith( $result['action'], 'update_three_month_plan'  ) ) {
            $action = 'updated_3_month';
            $session = '';
            $category = 'committing';
            $group_size = 1;
        }
        else {
            dt_write_log('DID NOT FIND ACTION');
            dt_write_log($result['id']);
            return;
        }

        $payload = maybe_serialize( [
            'language_code' => $language,
            'language_name' => $language_name,
            'session' => $session,
            'group_size' => $group_size,
            'note' => '',
            'location_type' => 'ip',
            'country' => $country_name,
            'unique_id' => dt_create_unique_key(),
        ] );

        $site_id = 'be51c701162c8b66d285b9831644bcf3432ecee4bb44fb891ad07d3b0b90948a';
        $timestamp = strtotime( $created_date );
        $hash = dt_create_unique_key();

        $wpdb->query("
        INSERT INTO wp_3_dt_movement_log
        (
         site_id,
         site_record_id,
         site_object_id,
         action,
         category,
         lng,
         lat,
         level,
         label,
         grid_id,
         payload,
         timestamp,
         hash
        )
        VALUES
        (
         '$site_id',
         NULL,
         NULL,
         '$action',
         '$category',
         '$lng',
         '$lat',
         '$level',
         '$label',
         '$grid_id',
         '$payload',
         '$timestamp',
         '$hash'
        )
        ");

        dt_write_log($wpdb->last_query);


    }
    public function startsWith ($string, $startString)
    {
        $len = strlen($startString);
        return (substr($string, 0, $len) === $startString);
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