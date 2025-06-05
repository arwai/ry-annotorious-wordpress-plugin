<?php

class Annotorious {
    public $filter_called;
    private $table_name;
    private $history_table_name;

    const META_POST_DISPLAY_MODE = '_ry_annotorious_post_display_mode';
    const OPTION_DEFAULT_NEW_POST_MODE = 'ry_annotorious_default_new_post_mode';
    const META_SET_FIRST_AS_FEATURED = '_ry_annotorious_set_first_as_featured';
    // NEW: Option key to store active post types
    const OPTION_ACTIVE_POST_TYPES = 'ry_annotorious_active_post_types';

    function __construct() {
        global $wpdb;

        $this->table_name = $wpdb->prefix . 'annotorious_data';
        $this->history_table_name = $wpdb->prefix . 'annotorious_history';

        add_action( 'wp_enqueue_scripts', array( $this, 'load_scripts' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'load_admin_scripts' ) );
        add_action( 'admin_init', array( $this, 'ry_annotorious_settings_init' ) );
        add_action( 'admin_menu', array( $this, 'ry_annotorious_add_settings_page' ) );
        add_action( 'add_meta_boxes', array( $this, 'ry_annotorious_add_all_metaboxes' ) );
        add_action( 'save_post', array( $this, 'save_ry_annotorious_images_metabox' ), 10, 2 );
        add_filter( 'the_content', array( $this , 'content_filter' ), 20 );

        // AJAX actions (assuming they are general and don't need post type restriction at this level)
        add_action( 'wp_ajax_nopriv_anno_get', array( $this, 'anno_get') );
        add_action( 'wp_ajax_anno_get', array( $this, 'anno_get' ) );
        add_action( 'wp_ajax_nopriv_anno_add', array( $this, 'anno_add') );
        add_action( 'wp_ajax_anno_add', array( $this, 'anno_add' ) );
        add_action( 'wp_ajax_nopriv_anno_delete', array( $this, 'anno_delete') );
        add_action( 'wp_ajax_anno_delete', array( $this, 'anno_delete' ) );
        add_action( 'wp_ajax_nopriv_anno_update', array( $this, 'anno_update') );
        add_action( 'wp_ajax_anno_update', array( $this, 'anno_update' ) );
        add_action( 'wp_ajax_get_annotorious_history', array( $this, 'get_annotorious_history' ) );
        add_action( 'wp_ajax_nopriv_get_annotorious_history', array( $this, 'get_annotorious_history' ) );

        $this->filter_called = 0;
    }

    /**
     * Helper function to get currently active post types for the plugin.
     * @return array Array of active post type slugs.
     */
    private function get_active_post_types() {
        // Default to 'post' and 'page' if the option isn't set or is empty
        $active_types = get_option( self::OPTION_ACTIVE_POST_TYPES, array( 'post', 'page' ) );
        return !empty($active_types) ? $active_types : array( 'post', 'page' );
    }

    /*************************************
    * --- Settings API Functions ---
    *************************************/

    public function ry_annotorious_settings_init() {
        // Setting for default viewer mode for new posts
        register_setting(
            'ry_annotorious_options_group',
            self::OPTION_DEFAULT_NEW_POST_MODE,
            array(
                'type'              => 'string',
                'sanitize_callback' => array( $this, 'sanitize_display_mode_option' ),
                'default'           => 'metabox_viewer',
            )
        );

        // NEW: Setting for active post types
        register_setting(
            'ry_annotorious_options_group',                     // Option group
            self::OPTION_ACTIVE_POST_TYPES,                     // Option name
            array(                                              // Args
                'type'              => 'array',
                'sanitize_callback' => array( $this, 'sanitize_active_post_types_option' ),
                'default'           => array( 'post', 'page' ), // Default active post types
            )
        );

        add_settings_section(
            'ry_annotorious_settings_section_main',
            'Openseadragon with Annotorious Global Settings',
            array($this, 'ry_annotorious_settings_section_main_callback'),
            'ry-annotorious-settings'
        );

        add_settings_field(
            'ry_annotorious_default_new_post_mode_field',
            'Default Viewer Mode for New Posts/Pages',
            array( $this, 'ry_annotorious_default_new_post_mode_callback' ),
            'ry-annotorious-settings',
            'ry_annotorious_settings_section_main'
        );

        // NEW: Settings field for selecting active post types
        add_settings_field(
            'ry_annotorious_active_post_types_field',       // ID
            'Activate Plugin for Post Types',               // Title
            array( $this, 'ry_annotorious_active_post_types_callback' ), // Callback to render the field
            'ry-annotorious-settings',                      // Page slug
            'ry_annotorious_settings_section_main'          // Section ID
        );
    }

    public function sanitize_display_mode_option( $input ) {
        $valid_options = array( 'metabox_viewer', 'gutenberg_block' );
        if ( in_array( $input, $valid_options, true ) ) {
            return $input;
        }
        return 'metabox_viewer';
    }

    // NEW: Sanitize callback for the active post types option
    public function sanitize_active_post_types_option( $input ) {
        $sanitized_input = array();
        if ( is_array( $input ) ) {
            // Get all registered public post type names for validation
            $all_registered_post_types = get_post_types( array( 'public' => true ), 'names' );
            foreach ( $input as $post_type_slug ) {
                $slug = sanitize_key( $post_type_slug );
                if ( in_array( $slug, $all_registered_post_types, true ) && $slug !== 'attachment' ) { // Ensure it's a valid public post type (and not attachment)
                    $sanitized_input[] = $slug;
                }
            }
        }
        // If nothing is selected, default back to 'post' and 'page' to avoid breaking the plugin entirely.
        // Or, allow it to be empty if you want the user to be able to deactivate it everywhere.
        // For this example, let's ensure 'post' and 'page' if empty.
        return !empty($sanitized_input) ? $sanitized_input : array('post', 'page');
    }


    public function ry_annotorious_settings_section_main_callback() {
        echo '<p>Configure global settings for the RY Annotorious plugin. The primary display choice (Default Viewer vs. Gutenberg Block) for individual posts/pages is managed on its edit screen. Below, you can set the <strong>default mode for newly created posts/pages</strong> and <strong>select which post types the plugin activates for</strong>.</p>';
    }

    public function ry_annotorious_default_new_post_mode_callback() {
        $option_value = get_option( self::OPTION_DEFAULT_NEW_POST_MODE, 'metabox_viewer' );
        ?>
        <fieldset>
            <legend class="screen-reader-text"><span><?php _e( 'Default Viewer Mode for New Posts/Pages', 'ry-annotorious' ); ?></span></legend>
            <label>
                <input type="radio" name="<?php echo esc_attr( self::OPTION_DEFAULT_NEW_POST_MODE ); ?>" value="metabox_viewer" <?php checked( $option_value, 'metabox_viewer' ); ?> />
                <?php _e( 'Default Viewer (uses images from "Annotorious Image Collection" metabox)', 'ry-annotorious' ); ?>
            </label>
            <br />
            <label>
                <input type="radio" name="<?php echo esc_attr( self::OPTION_DEFAULT_NEW_POST_MODE ); ?>" value="gutenberg_block" <?php checked( $option_value, 'gutenberg_block' ); ?> />
                <?php _e( 'Gutenberg Block (manual placement via block editor)', 'ry-annotorious' ); ?>
            </label>
            <p class="description">
                <?php _e( 'This will be the pre-selected viewer mode when you create a new post or page.', 'ry-annotorious' ); ?>
            </p>
        </fieldset>
        <?php
    }

    // NEW: Callback function to render the checkboxes for active post types
    public function ry_annotorious_active_post_types_callback() {
        $saved_options = $this->get_active_post_types(); // Use helper to get defaults

        // Get all public post types, excluding 'attachment'
        $post_types = get_post_types( array( 'public' => true ), 'objects' );
        ?>
        <fieldset>
            <legend class="screen-reader-text"><span><?php _e( 'Activate for Post Types', 'ry-annotorious' ); ?></span></legend>
            <?php foreach ( $post_types as $post_type ) : ?>
                <?php
                // Skip 'attachment' post type explicitly if it wasn't excluded by query args effectively for some reason
                if ( $post_type->name === 'attachment' ) {
                    continue;
                }
                ?>
                <label for="ry_annotorious_pt_<?php echo esc_attr( $post_type->name ); ?>">
                    <input type="checkbox"
                           name="<?php echo esc_attr( self::OPTION_ACTIVE_POST_TYPES ); ?>[]"
                           id="ry_annotorious_pt_<?php echo esc_attr( $post_type->name ); ?>"
                           value="<?php echo esc_attr( $post_type->name ); ?>"
                           <?php checked( in_array( $post_type->name, $saved_options, true ) ); ?> />
                    <?php echo esc_html( $post_type->labels->name ); ?> (<code><?php echo esc_html($post_type->name); ?></code>)
                </label><br />
            <?php endforeach; ?>
            <p class="description">
                <?php _e( 'Select the post types where OpenSeadragon-Annotorious functionality (metaboxes, viewer) should be active. If none are selected, it defaults to Posts and Pages.', 'ry-annotorious' ); ?>
            </p>
        </fieldset>
        <?php
    }


    public function ry_annotorious_add_settings_page() {
        add_options_page(
            'Openseadragon with Annotorious Settings',
            'Openseadragon-Annotorious',
            'manage_options',
            'ry-annotorious-settings',
            array( $this, 'ry_annotorious_settings_page_html' )
        );
    }

    public function ry_annotorious_settings_page_html() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields( 'ry_annotorious_options_group' );
                do_settings_sections( 'ry-annotorious-settings' );
                submit_button( 'Save Settings' );
                ?>
            </form>
        </div>
        <?php
    }


    /*************************************
    * --- Script & Style Loading Functions ---
    *************************************/

    function load_scripts(){
        $active_post_types = $this->get_active_post_types();
        // MODIFIED: Only proceed for singular views of active post types
        if ( ! is_singular( $active_post_types ) ) {
            return;
        }

        $current_post_id = get_the_ID(); // This is now safe as is_singular(active_types) passed
        if ( ! $current_post_id ) { // Should not happen if is_singular is true
            return;
        }

        $post_display_mode = get_post_meta( $current_post_id, self::META_POST_DISPLAY_MODE, true );
        if ( empty( $post_display_mode ) ) {
            $post_display_mode = get_option( self::OPTION_DEFAULT_NEW_POST_MODE, 'metabox_viewer' );
        }

        if ( 'metabox_viewer' === $post_display_mode ) {
            // ... (rest of your script loading logic for metabox_viewer)
            $image_sources = [];
            $viewer_id = 'openseadragon-viewer-' . $current_post_id;

            $image_ids_json = get_post_meta( $current_post_id, '_ry_annotorious_image_ids', true );
            $image_ids = json_decode( $image_ids_json, true );

            if ( !empty( $image_ids ) && is_array( $image_ids ) ) {
                foreach ( $image_ids as $id ) {
                    $image_url_array = wp_get_attachment_image_src( $id, 'full' );
                    if ( $image_url_array && is_array( $image_url_array ) && !empty( $image_url_array[0] ) ) {
                        $image_sources[] = [
                            'type'    => 'image',
                            'url'     => $image_url_array[0],
                            'post_id' => $id
                        ];
                    }
                }
            }

            if ( !empty( $image_sources ) ) {
                wp_register_style( 'ry-annotorious-css', RY_ANNOTORIOUS_URL . 'assets/css/annotorious/annotorious.min.css');
                wp_enqueue_style( 'ry-annotorious-css' );
                wp_register_script( 'ry-annotorious-core-js', RY_ANNOTORIOUS_URL . 'assets/js/annotorious/annotorious.min.js', array(), '2.7.0', true );
                wp_enqueue_script( 'ry-annotorious-core-js' );
                // ... and so on for other scripts
                 wp_register_script( 'openseadragon-js', RY_ANNOTORIOUS_URL . 'assets/js/openseadragon/openseadragon.min.js', array(), '5.0.1', true );
                wp_enqueue_script( 'openseadragon-js' );

                wp_register_script( 'ry-annotorious-osd-plugin-js', RY_ANNOTORIOUS_URL . 'assets/js/annotorious/openseadragon-annotorious.min.js', array( 'ry-annotorious-core-js', 'openseadragon-js' ), '2.7.17', true );
                wp_enqueue_script( 'ry-annotorious-osd-plugin-js' );

                wp_register_script('ry-annotorious-public-js', RY_ANNOTORIOUS_URL . 'assets/js/public