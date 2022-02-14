<?php
/**
 * Plugin Name: Zume DB Upgrader
 * Plugin URI: https://github.com/ChrisChasm/zume-db-upgrader
 * Description: Reusable upgrader for Zúme maintenance
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
    public $title = 'Zúme Upgrader';
    public $page_title = 'User Upgrade';
    public $page_version = 'v1';
    public $permissions = 'manage_options';
    public $limit = 50;

    public $group_ids = [];
    public $post_ids = [];
    public $user_ids = [];

    public $inc = 0;

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
        add_menu_page( $this->page_title . ' ' . $this->page_version, $this->title,  $this->permissions, $this->token, [ $this, 'content' ], 'dashicons-admin-generic', 59 );
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
                <th><h2><?php echo $this->page_title . ' ' . $this->page_version ?></h2></th>
            </tr>
            <tr>
                <th>
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
                    //<!--
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
        // Get all records to process

        $keys = $wpdb->get_results(
            "SELECT um.user_id, pm.post_id, um.meta_key as group_key, um.meta_value as group_value, u.user_email
                    FROM wp_usermeta um
                    LEFT JOIN wp_users u ON u.ID=um.user_id
                    LEFT JOIN wp_3_postmeta pm ON um.user_id=pm.meta_value AND pm.meta_key = 'zume_training_id'
                    WHERE um.meta_key LIKE 'zume_group%'
                    ORDER BY um.user_id DESC
                    ", ARRAY_A );
        foreach( $keys as $key ) {
            $this->group_ids[$key['group_key']] = $key;
        }

        $results = $this->group_ids;
        if ( empty( $results ) ) {
            return;
        }

        $total_count = count( $results );
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
            function nextpage() {
                location.href = "<?php echo admin_url() ?>admin.php?page=<?php echo esc_attr( $this->token )  ?>&loop=true&step=<?php echo esc_attr( $loop_count ) ?>&nonce=<?php echo wp_create_nonce( 'loop'.get_current_user_id() ) ?>";
            }
            setTimeout( "nextpage()", 1500 );
        </script>
        <?php
    }

    public function run_task( $result ) {
        global $wpdb;

        // test if completed training
        $group = unserialize( $result['group_value'] );
        if ( $group['session_9'] || $group['session_10'] ) {
            dt_write_log( $group['group_name'] . ' (' . $result['user_id'] . ') completed training.' );
        } else {
            return; // did not complete training
        }


        /***********************************************************************************
         * Training Record Connection
         */
        $user_id = $result['user_id'];
        $group_key = $result['group_key'];
        $training_post_id = $wpdb->get_var("SELECT post_id FROM wp_3_postmeta WHERE meta_value = '$group_key' ");
        if ( empty( $training_post_id ) ) {
            dt_write_log('No training post id found');
            return;
        }

        $training_record = DT_Posts::get_post( 'trainings', $training_post_id, false, false );
        if ( is_wp_error( $training_record ) ) {
            dt_write_log('No training record id found');
            return;
        }

        /***********************************************************************************
         * User Record Connection
         */

        // test if user has a contact record
        if ( empty( $result['post_id'] ) ) {
            $contact_record = $this->create_contact_record_for_user( $user_id );
            if ( $contact_record ) {
                $post_id = $contact_record;
            } else {
                dt_write_log('could not create contact');
                return;
            }
        } else {
            $post_id = $result['post_id' ];
        }

        // connection training to contact record and make a leader
        if ( isset( $training_record['members'] ) &&  empty( $training_record['members'] ) ) {
            // create connection to user contact record
            $p2p = [
                "members" => [
                    "values" => [
                        [ "value" => $post_id ],
                    ]
                ]
            ];
            DT_Posts::update_post( 'trainings', $training_record['ID'], $p2p, true, false );
        } else {
            $not_connected = true;
            foreach( $training_record['members'] as $leader ) {
                if ( $leader['ID'] === $post_id ) {
                    $not_connected = false;
                }
            }
            if ( ! $not_connected ) {
                $p2p = [
                    "members" => [
                        "values" => [
                            [ "value" => $post_id ],
                        ]
                    ]
                ];
                DT_Posts::update_post( 'trainings', $training_record['ID'], $p2p, true, false );
            }
        }

        if ( isset( $training_record['leaders'] ) &&  empty( $training_record['leaders'] ) ) {
            // create connection to user contact record
            $p2p = [
                "leaders" => [
                    "values" => [
                        [ "value" => $post_id ],
                    ]
                ]
            ];
            DT_Posts::update_post( 'trainings', $training_record['ID'], $p2p, true, false );
        } else {
            $not_connected = true;
            foreach( $training_record['leaders'] as $leader ) {
                if ( $leader['ID'] === $post_id ) {
                    $not_connected = false;
                }
            }
            if ( ! $not_connected ) {
                $p2p = [
                    "leaders" => [
                        "values" => [
                            [ "value" => $post_id ],
                        ]
                    ]
                ];
                DT_Posts::update_post( 'trainings', $training_record['ID'], $p2p, true, false );
            }
        }

        /***********************************************************************************
         * Coleader Record Connection
         */

        // check if coleaders exist and have contact records
        if ( ! empty( $group['coleaders'] ) ) {
            dt_write_log('Coleaders ' . count( $group['coleaders'] ) );

            foreach( $group['coleaders'] as $coleader ) {
                dt_write_log( $coleader );

                $coleader_user = get_user_by('email', $coleader);
                if ( $coleader_user /* has a user account */) {
                    $co_user_id = $coleader_user->ID;
                    $coleader_contact_id = $wpdb->get_var("SELECT post_id FROM wp_3_postmeta WHERE meta_key = 'zume_training_id' AND meta_value = $co_user_id ");
                    if ( ! $coleader_contact_id ) {
                        $new_contact = $this->create_contact_record_for_user( $coleader_user->ID );
                        if ( is_wp_error( $new_contact ) || ! $new_contact ) {
                            dt_write_log( 'failed to create contact' );
                            continue;
                        }
                        $coleader_contact_id = $new_contact;
                    }
                } else {
                    // search for contact record by email
                    $coleader_contacts = $wpdb->get_results(
                        "SELECT pm.post_id, pm1.meta_value as status, pm2.meta_value as reason
                                FROM wp_3_postmeta pm
                                LEFT JOIN wp_3_postmeta pm1 ON pm.post_id=pm1.post_id AND pm1.meta_key = 'overall_status'
                                LEFT JOIN wp_3_postmeta pm2 ON pm2.post_id=pm.post_id AND pm2.meta_key = 'reason_closed'
                                WHERE pm.meta_key LIKE 'contact_email%' AND pm.meta_value = '$coleader'
                                AND NOT (pm1.meta_value = 'closed' AND pm2.meta_value = 'duplicate') 
                                ORDER BY status;
                     ", ARRAY_A );
                    if ( empty( $coleader_contacts) ) {
                        // create contact record by email
                        $coleader_contact_id =  $this->create_contact_record_for_coleader( $coleader, $user_id );
                        if ( ! $coleader_contact_id ) {
                            continue;
                        }
                    } else {
                        $coleader_contact_id = $coleader_contacts[0]['post_id'];
                    }
                }

                if ( empty( $coleader_contact_id ) ) {
                    dt_write_log('$coleader_contact_id not found');
                    continue;
                }
                dt_write_log($coleader_contact_id);

                // connect contact record to training as member.
                if ( isset( $training_record['members'] ) &&  empty( $training_record['members'] ) ) {
                    // create connection to user contact record
                    $p2p = [
                        "members" => [
                            "values" => [
                                [ "value" => $coleader_contact_id ],
                            ]
                        ]
                    ];
                    DT_Posts::update_post( 'trainings', $training_record['ID'], $p2p, true, false );
                    dt_write_log('added connection');
                } else {
                    $not_connected = true;
                    foreach( $training_record['members'] as $leader ) {
                        if ( $leader['ID'] === $coleader_contact_id ) {
                            $not_connected = false;
                        }
                    }
                    if ( ! $not_connected ) {
                        $p2p = [
                            "members" => [
                                "values" => [
                                    [ "value" => $coleader_contact_id ],
                                ]
                            ]
                        ];
                        DT_Posts::update_post( 'trainings', $training_record['ID'], $p2p, true, false );
                        dt_write_log('added connection');
                    }
                }

            }
        }
    }

    public function create_contact_record_for_user( $user_id ) {
        // has location
        $fields = [];
        $fields['sources'] = [
            "values" => [
                [ "value" => "zume_training" ],  //add new, or make sure it exists
            ]
        ];
        $fields['assigned_to'] = 0;
        $fields['overall_status'] = 'reporting_only';
        $fields['leader_milestones'] = [
            "values" => [
                [ "value" => "trained" ],
                [ "value" => "practicing" ],
            ]
        ];

        $user = get_user_by( 'id', $user_id );
        $fields['contact_email'] = [
            [ "value" => $user->user_email ]
        ];

        if ( ! ( empty( $user->first_name ) && empty( $user->last_name ) ) ) {
            $title = $user->first_name . ' ' . $user->last_name;
        } else if ( ! empty( $user->first_name ) ) {
            $title = $user->first_name;
        } else if ( ! empty( $user->display_name ) ) {
            $title = $user->display_name;
        } else {
            $title = $user->user_email;
        }
        $fields['title'] = $title;

        $fields['zume_training_id'] = $user_id;

        $fields['language_preference'] = get_user_meta( $user_id, 'zume_language', true );
        $fields['zume_foreign_key'] = get_user_meta( $user_id, 'zume_foreign_key', true );
        if ( empty( $fields['zume_foreign_key'] ) ) {
            $fields['zume_foreign_key'] = dt_create_unique_key();
        }

        $location = get_user_meta( $user_id, 'zume_location_grid_meta_from_ip', true );
        if ( isset( $location['lng'] ) ) {
            $fields['location_grid_meta'] = [
                "values" => [
                    'lng' => $location['lng'],
                    'lat' => $location['lat'],
                    'label' => $location['label'],
                    'level' => 10
                ]
            ];

        }

        $contact_id = DT_Posts::create_post( 'contacts', $fields, true, false );
        if ( is_wp_error( $contact_id ) ) {
            dt_write_log('Error creating record ');
            dt_write_log($fields);
            return false;
        } else {
            return $contact_id['ID'];
        }
    }

    public function create_contact_record_for_coleader( $email, $user_id ) {
        if ( empty( $email ) ) {
            dt_write_log('No email found ');
            return false;
        }
        // has location
        $fields = [];
        $fields['sources'] = [
            "values" => [
                [ "value" => "zume_training" ],  //add new, or make sure it exists
            ]
        ];
        $fields['tags'] = [
            "values" => [
                [ "value" => "Non User Zume Member" ],  //add new, or make sure it exists
            ]
        ];
        $fields['assigned_to'] = 0;
        $fields['overall_status'] = 'reporting_only';
        $fields['leader_milestones'] = [
            "values" => [
                [ "value" => "trained" ],
                [ "value" => "practicing" ],
            ]
        ];

        $user = get_user_by( 'email', $email );
        if ( $user ) {
            if ( ! ( empty( $user->first_name ) && empty( $user->last_name ) ) ) {
                $title = $user->first_name . ' ' . $user->last_name;
            } else if ( ! empty( $user->first_name ) ) {
                $title = $user->first_name;
            } else if ( ! empty( $user->display_name ) ) {
                $title = $user->display_name;
            } else {
                $title = $user->user_email;
            }
        } else {
            $title = $email;
        }

        $fields['title'] = $title;


        $fields['contact_email'] = [
            [ "value" => $email ]
        ];

//        $fields['zume_training_id'] = $user_id;

        $fields['language_preference'] = get_user_meta( $user_id, 'zume_language', true );
//        $fields['zume_foreign_key'] = get_user_meta( $user_id, 'zume_foreign_key', true );
//        if ( empty( $fields['zume_foreign_key'] ) ) {
//            $fields['zume_foreign_key'] = dt_create_unique_key();
//        }

        $location = get_user_meta( $user_id, 'zume_location_grid_meta_from_ip', true );
        if ( isset( $location['lng'] ) ) {
            $fields['location_grid_meta'] = [
                "values" => [
                    'lng' => $location['lng'],
                    'lat' => $location['lat'],
                    'label' => $location['label'],
                    'level' => 10
                ]
            ];

        }

        $contact_id = DT_Posts::create_post( 'contacts', $fields, true, false );
        if ( is_wp_error( $contact_id ) ) {
            dt_write_log('Error creating record ');
            dt_write_log($fields);
            return false;
        } else {
            return $contact_id['ID'];
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