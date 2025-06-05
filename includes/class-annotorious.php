<?php

class Annotorious {
    public $filter_called;
    private $table_name; // For current annotations
    private $history_table_name; // For annotation history

    // Post Meta Key for display mode
    const META_POST_DISPLAY_MODE = '_ry_annotorious_post_display_mode';
    // Global Option Key for default new post mode
    const OPTION_DEFAULT_NEW_POST_MODE = 'ry_annotorious_default_new_post_mode';
    // Post Meta Key for the "set first as featured" checkbox
    const META_SET_FIRST_AS_FEATURED = '_ry_annotorious_set_first_as_featured';
    // Option key to store active post types
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
     * Get the active post types from the options.
     * If the option is not set or is empty, default to 'post' and 'page'.
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

    // Callback function to render the checkboxes for active post types
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
        // Only proceed for singular views of active post types
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
                wp_register_script( 'openseadragon-js', RY_ANNOTORIOUS_URL . 'assets/js/openseadragon/openseadragon.min.js', array(), '5.0.1', true );
                wp_enqueue_script( 'openseadragon-js' );

                wp_register_script( 'ry-annotorious-osd-plugin-js', RY_ANNOTORIOUS_URL . 'assets/js/annotorious/openseadragon-annotorious.min.js', array( 'ry-annotorious-core-js', 'openseadragon-js' ), '2.7.17', true );
                wp_enqueue_script( 'ry-annotorious-osd-plugin-js' );

                wp_register_script('ry-annotorious-public-js', RY_ANNOTORIOUS_URL . 'assets/js/public/script.js', array('jquery', 'ry-annotorious-osd-plugin-js'),'1.1', true);
                wp_enqueue_script('ry-annotorious-public-js');

                $viewer_config = [
                    'id'            => $viewer_id,
                    'images'        => $image_sources,
                    'currentPostId' => $current_post_id,
                ];
                wp_localize_script( 'ry-annotorious-public-js', 'AnnotoriousViewerConfig', $viewer_config );

                $data = array(
                    'post_id'    => $current_post_id,
                    'plugin_url' => RY_ANNOTORIOUS_URL,
                    'ajax_url'   => admin_url( 'admin-ajax.php' )
                );
                if (is_user_logged_in()) {  $data['loggedin'] = true;  } else { $data['loggedin'] = false; }
                wp_localize_script( 'ry-annotorious-public-js', 'AnnotoriousVars', $data );
            }
        }
    }


    function load_admin_scripts($hook_suffix) {
        // Only load on post edit screens for active post types
        if (in_array($hook_suffix, array('post.php', 'post-new.php'))) {
            $current_screen = get_current_screen();
            // Ensure $current_screen is an object and post_type property exists
            if ( $current_screen && isset($current_screen->post_type) && in_array( $current_screen->post_type, $this->get_active_post_types() ) ) {
                wp_register_script('admin-js', RY_ANNOTORIOUS_URL . 'assets/js/admin/admin.js', array('jquery', 'jquery-ui-sortable'),'1.13', true); // Ensure correct version
                wp_enqueue_script('admin-js');
                wp_enqueue_media();

                $custom_css = ".ry-annotorious-image-list li { cursor: move; }";
                $custom_css .= ".ry-annotorious-image-placeholder { background-color: #f0f0f0; border: 1px dashed #ccc; height: 100px; width: 100px; margin: 5px; list-style-type: none; }";
                wp_add_inline_style( 'wp-admin', $custom_css );
            }
        }
    }

    /*************************************
    * --- Content Filter ---
    *************************************/

    function content_filter($content) {
        $active_post_types = $this->get_active_post_types();
        // Only proceed for singular views of active post types, in the main loop and query
        if ( !is_singular( $active_post_types ) || !in_the_loop() || !is_main_query() ) {
            return $content;
        }

        $current_post_id = get_the_ID(); // Safe to call now
        if ( ! $current_post_id ) { // Should not be needed if is_singular passed
            return $content;
        }

        $post_display_mode = get_post_meta( $current_post_id, self::META_POST_DISPLAY_MODE, true );
        if ( empty( $post_display_mode ) ) {
            $post_display_mode = get_option( self::OPTION_DEFAULT_NEW_POST_MODE, 'metabox_viewer' );
        }

        if ( 'gutenberg_block' === $post_display_mode || $this->filter_called > 0) {
            return $content;
        }

        if ( 'metabox_viewer' === $post_display_mode ) {
            // ... (rest of your content filter logic)
            $image_ids_json = get_post_meta( $current_post_id, '_ry_annotorious_image_ids', true );
            $image_ids = json_decode( $image_ids_json, true );

            if ( !empty( $image_ids ) && is_array( $image_ids ) ) {
                if ( $this->filter_called === 0 ) {
                    $this->filter_called++;
                    $viewer_id = 'openseadragon-viewer-' . $current_post_id;
                    $viewer_html = '<div id="' . esc_attr( $viewer_id ) . '" style="width: 100%; height: 600px; background-color: #f0f0f0; border: 1px solid #ccc;" class="ry-annotorious-default-viewer-container"></div>';
                    return $viewer_html . $content;
                }
            }
        }
        return $content;
    }



    /*************************************
    * --- Metabox Functions ---
    *************************************/

    function ry_annotorious_add_all_metaboxes() {
        $active_post_types = $this->get_active_post_types();
        if ( empty( $active_post_types ) ) { // Should be handled by get_active_post_types default
            return;
        }

        add_meta_box(
            'ry-annotorious-display-mode-metabox',
            __( 'Annotorious Viewer Mode', 'ry-annotorious' ),
            array( $this, 'render_ry_annotorious_display_mode_metabox' ),
            $active_post_types, // Use dynamic list
            'side',
            'default'
        );

        add_meta_box(
            'ry-annotorious-image-collection-metabox',
            __( 'Annotorious Image Collection (sortable)', 'ry-annotorious' ),
            array( $this, 'render_ry_annotorious_image_collection_metabox' ),
            $active_post_types, // Use dynamic list
            'normal',
            'high'
        );
    }

    function render_ry_annotorious_display_mode_metabox( $post ) {
        // ... (this function remains the same as your last version)
        $current_display_mode = get_post_meta( $post->ID, self::META_POST_DISPLAY_MODE, true );
        if ( empty( $current_display_mode ) ) {
            $current_display_mode = get_option( self::OPTION_DEFAULT_NEW_POST_MODE, 'metabox_viewer' );
        }
        ?>
        <div id="ry-annotorious-options-container">
            <p><label><input type="radio" name="<?php echo esc_attr( self::META_POST_DISPLAY_MODE ); ?>" value="metabox_viewer" <?php checked( $current_display_mode, 'metabox_viewer' ); ?> /> <?php _e( 'Default Viewer', 'ry-annotorious' ); ?></label><br /><small class="description"><?php _e( 'Uses images from the "Annotorious Image Collection" metabox below.', 'ry-annotorious' ); ?></small></p>
            <p><label><input type="radio" name="<?php echo esc_attr( self::META_POST_DISPLAY_MODE ); ?>" value="gutenberg_block" <?php checked( $current_display_mode, 'gutenberg_block' ); ?> /> <?php _e( 'Gutenberg Block', 'ry-annotorious' ); ?></label><br/><small class="description"><?php _e( 'Manual placement via Gutenberg block.', 'ry-annotorious' ); ?></small></p>
        </div>
        <?php
    }

    function render_ry_annotorious_image_collection_metabox( $post ) {
        wp_nonce_field( 'ry_annotorious_images_metabox_save', 'ry_annotorious_images_nonce' );
        $image_ids_json = get_post_meta( $post->ID, '_ry_annotorious_image_ids', true );
        $image_ids = json_decode( $image_ids_json, true );
        if ( ! is_array( $image_ids ) ) { $image_ids = array(); }
        $set_as_featured = get_post_meta( $post->ID, self::META_SET_FIRST_AS_FEATURED, true );
        ?>
        <div id="ry-annotorious-images-collection-container">
            <p class="description"><?php _e( 'Select images from the media library. Drag and drop to reorder. These will be used if "Default Viewer" mode is active.', 'ry-annotorious' ); ?></p>
            <ul class="ry-annotorious-image-list">
                <?php if ( ! empty( $image_ids ) ) { foreach ( $image_ids as $image_id ) { $image_thumb_url = wp_get_attachment_image_url( $image_id, 'thumbnail' ); if ( $image_thumb_url ) { echo '<li data-id="' . esc_attr( $image_id ) . '"><img src="' . esc_url( $image_thumb_url ) . '" style="max-width:100px; max-height:100px; display:block;" /><a href="#" class="ry-annotorious-remove-image dashicons dashicons-trash" title="Remove image"></a></li>'; } } } ?>
            </ul>
            <p><a href="#" class="button button-secondary ry-annotorious-add-images-button"><?php _e( 'Add/Select Images', 'ry-annotorious' ); ?></a><input type="hidden" id="ry_annotorious_image_ids_field" name="_ry_annotorious_image_ids" value="<?php echo esc_attr( $image_ids_json ); ?>" /></p>
            <p style="margin-bottom: 15px;"><label for="<?php echo esc_attr( self::META_SET_FIRST_AS_FEATURED ); ?>"><input type="checkbox" name="<?php echo esc_attr( self::META_SET_FIRST_AS_FEATURED ); ?>" id="<?php echo esc_attr( self::META_SET_FIRST_AS_FEATURED ); ?>" value="yes" <?php checked( $set_as_featured, 'yes' ); ?> /> <?php _e( 'Use the first image in this collection as the post\'s featured image.', 'ry-annotorious' ); ?></label></p>

        </div>
        <style>#ry-annotorious-images-collection-container .ry-annotorious-image-list li { cursor: move; position: relative; width: 100px; height: 100px; margin: 5px; border: 1px solid #ccc; display: flex; align-items: center; justify-content: center; overflow: hidden; } #ry-annotorious-images-collection-container .ry-annotorious-image-list { display: flex; flex-wrap: wrap; list-style: none; margin: 0; padding: 0; } #ry-annotorious-images-collection-container .ry-annotorious-image-list li img { max-width: 100%; max-height: 100%; object-fit: contain; } #ry-annotorious-images-collection-container .ry-annotorious-remove-image { position: absolute; top: 0; right: 0; background: rgba(255,0,0,0.7); color: white; padding: 3px; cursor: pointer; line-height: 1; text-decoration: none; } .ry-annotorious-image-placeholder { background-color: #f0f0f0; border: 1px dashed #ccc; height: 100px; width: 100px; margin: 5px; list-style-type: none; }</style>
        <?php
    }

    function save_ry_annotorious_images_metabox( $post_id, $post ) {
        // ... (this function remains the same as your last version, including saving all three meta fields)
        if ( ! isset( $_POST['ry_annotorious_images_nonce'] ) || ! wp_verify_nonce( $_POST['ry_annotorious_images_nonce'], 'ry_annotorious_images_metabox_save' ) ) { return $post_id; }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return $post_id; }
        $post_type_object = get_post_type_object( $post->post_type );
        if ( ! $post_type_object || ! current_user_can( $post_type_object->cap->edit_post, $post_id ) ) { return $post_id; }

        // Active post type check (optional here, as metabox shouldn't appear if not active, but good for robustness)
        if (!in_array($post->post_type, $this->get_active_post_types())) {
            return $post_id;
        }

        // Save Display Mode
        $existing_display_mode = get_post_meta( $post_id, self::META_POST_DISPLAY_MODE, true );
        if ( isset( $_POST[self::META_POST_DISPLAY_MODE] ) ) {
            $display_mode = sanitize_text_field( $_POST[self::META_POST_DISPLAY_MODE] );
            if ( in_array( $display_mode, array( 'metabox_viewer', 'gutenberg_block' ) ) ) { update_post_meta( $post_id, self::META_POST_DISPLAY_MODE, $display_mode ); }
            else { $fallback_mode = get_option( self::OPTION_DEFAULT_NEW_POST_MODE, 'metabox_viewer' ); update_post_meta( $post_id, self::META_POST_DISPLAY_MODE, $existing_display_mode ? $existing_display_mode : $fallback_mode );}
        } else { if ( empty( $existing_display_mode ) && !wp_is_post_revision($post_id) && !wp_is_post_autosave($post_id) ) { $global_default = get_option( self::OPTION_DEFAULT_NEW_POST_MODE, 'metabox_viewer' ); update_post_meta( $post_id, self::META_POST_DISPLAY_MODE, $global_default ); } }

        // Save Image IDs
        if ( isset( $_POST['_ry_annotorious_image_ids'] ) ) {
            $image_ids_json = wp_unslash( $_POST['_ry_annotorious_image_ids'] ); $image_ids = json_decode( $image_ids_json, true );
            if ( is_array( $image_ids ) ) { $sanitized_image_ids = array_map( 'intval', $image_ids ); update_post_meta( $post_id, '_ry_annotorious_image_ids', json_encode( array_values($sanitized_image_ids) ) );}
            else { delete_post_meta( $post_id, '_ry_annotorious_image_ids' ); }
        } else { delete_post_meta( $post_id, '_ry_annotorious_image_ids' ); }

        // Save "Set First as Featured" option and update featured image
        $set_first_as_featured_choice = isset( $_POST[self::META_SET_FIRST_AS_FEATURED] ) ? 'yes' : 'no';
        update_post_meta( $post_id, self::META_SET_FIRST_AS_FEATURED, $set_first_as_featured_choice );
        if ( 'yes' === $set_first_as_featured_choice ) {
            $image_ids_for_featured_json = isset( $_POST['_ry_annotorious_image_ids'] ) ? wp_unslash( $_POST['_ry_annotorious_image_ids'] ) : get_post_meta($post_id, '_ry_annotorious_image_ids', true);
            $image_ids_for_featured = json_decode( $image_ids_for_featured_json, true );
            if ( is_array( $image_ids_for_featured ) && ! empty( $image_ids_for_featured ) ) {
                $first_image_id = intval( $image_ids_for_featured[0] );
                if ( $first_image_id > 0 ) {
                    $current_featured_image_id = get_post_thumbnail_id( $post_id );
                    if ( $first_image_id != $current_featured_image_id ) { set_post_thumbnail( $post_id, $first_image_id ); }
                }
            }
        }
    }

    // ... (AJAX functions: anno_get, anno_add, anno_delete, anno_update, get_annotorious_history - kept same)
     /*************************************
    * ajax get annotations
    *************************************/
    function anno_get() {
        global $wpdb; $attachment_id = isset($_GET['attachment_id']) ? intval($_GET['attachment_id']) : 0;
        if (empty($attachment_id)) { echo wp_json_encode(array('success' => false, 'message' => 'Missing attachment_id.')); wp_die(); }
        header('Content-Type: application/json'); $all_annotations = [];
        $results = $wpdb->get_results( $wpdb->prepare( "SELECT annotation_data FROM {$this->table_name} WHERE attachment_id = %d", $attachment_id ), ARRAY_A );
        if ( ! empty( $results ) ) { foreach ( $results as $row ) { $decoded_annotation = json_decode( $row['annotation_data'], true ); if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded_annotation ) ) { $all_annotations[] = $decoded_annotation; } } }
        echo wp_json_encode($all_annotations); wp_die();
    }
    /*************************************
    * ajax add annotation (MODIFIED for history)
    *************************************/
    function anno_add() {
        global $wpdb; $annotation_json = isset($_POST['annotation']) ? wp_unslash($_POST['annotation']) : '';
        if (empty($annotation_json)) { echo wp_json_encode(array('success' => false, 'message' => 'Annotation data missing.')); wp_die(); }
        $annotation = json_decode($annotation_json, true); if (json_last_error() !== JSON_ERROR_NONE) { echo wp_json_encode(array('success' => false, 'message' => 'Invalid JSON data.', 'error_code' => json_last_error_msg())); wp_die(); }
        $image_url = ''; if (isset($annotation['target']['source'])) { $image_url = $annotation['target']['source']; }
        if (empty($image_url)) { echo wp_json_encode(array('success' => false, 'message' => 'Annotation target source URL missing.')); wp_die(); }
        $attachment_id = attachment_url_to_postid($image_url); if (empty($attachment_id)) { echo wp_json_encode(array('success' => false, 'message' => 'Could not find attachment ID for source URL: ' . $image_url)); wp_die(); }
        $annotation_id_from_annotorious = isset($annotation['id']) ? sanitize_text_field($annotation['id']) : ''; if (empty($annotation_id_from_annotorious)) { echo wp_json_encode(array('success' => false, 'message' => 'Annotorious ID (UUID) missing from annotation data.')); wp_die(); }
        if (isset($annotation['body'][0]['value'])) { $annotation['body'][0]['value'] = wp_kses_post($annotation['body'][0]['value']); }
        $current_user_id = get_current_user_id();
        $logged_history = $wpdb->insert( $this->history_table_name, array( 'annotation_id_from_annotorious' => $annotation_id_from_annotorious, 'attachment_id' => $attachment_id, 'action_type' => 'created', 'annotation_data_snapshot' => wp_json_encode($annotation), 'user_id' => $current_user_id, ), array( '%s', '%d', '%s', '%s', '%d' ) );
        $inserted = $wpdb->insert( $this->table_name, array( 'annotation_id_from_annotorious' => $annotation_id_from_annotorious, 'attachment_id' => $attachment_id, 'annotation_data' => wp_json_encode($annotation), ), array( '%s', '%d', '%s' ) );
        if ($inserted !== false) { echo wp_json_encode(array('success' => true, 'message' => 'Annotation added successfully.', 'database_id' => $wpdb->insert_id, 'history_logged' => (bool)$logged_history)); } else { echo wp_json_encode(array('success' => false, 'message' => 'Failed to add annotation to custom table.', 'wpdb_error' => $wpdb->last_error)); }
        wp_die();
    }
    /************************************
    * ajax delete annotation (MODIFIED for history)
    ************************************/
    function anno_delete() {
        global $wpdb; $annoid = isset($_POST['annotationid']) ? sanitize_text_field($_POST['annotationid']) : ''; $annotation_json = isset($_POST['annotation']) ? wp_unslash($_POST['annotation']) : '';
        if (empty($annoid) || empty($annotation_json)) { echo wp_json_encode(array('success' => false, 'message' => 'Missing annotationid or annotation data.')); wp_die(); }
        $annotation = json_decode($annotation_json, true); if (json_last_error() !== JSON_ERROR_NONE) { echo wp_json_encode(array('success' => false, 'message' => 'Invalid JSON data (delete).', 'error_code' => json_last_error_msg())); wp_die(); }
        $image_url = ''; if (isset($annotation['target']['source'])) { $image_url = $annotation['target']['source']; }
        $attachment_id = attachment_url_to_postid($image_url); if (empty($attachment_id)) { echo wp_json_encode(array('success' => false, 'message' => 'Could not find attachment ID for source URL (delete): ' . $image_url)); wp_die(); }
        $current_user_id = get_current_user_id();
        $existing_annotation_row = $wpdb->get_row( $wpdb->prepare( "SELECT annotation_data FROM {$this->table_name} WHERE annotation_id_from_annotorious = %s AND attachment_id = %d", $annoid, $attachment_id ), ARRAY_A );
        $logged_history = false; if ( ! empty( $existing_annotation_row ) ) { $logged_history = $wpdb->insert( $this->history_table_name, array( 'annotation_id_from_annotorious' => $annoid, 'attachment_id' => $attachment_id, 'action_type' => 'deleted', 'annotation_data_snapshot' => $existing_annotation_row['annotation_data'], 'user_id' => $current_user_id, ), array( '%s', '%d', '%s', '%s', '%d' ) ); }
        $deleted = $wpdb->delete( $this->table_name, array( 'annotation_id_from_annotorious' => $annoid, 'attachment_id' => $attachment_id ), array( '%s', '%d' ) );
        if ($deleted === false) { echo wp_json_encode(array('success' => false, 'message' => 'Failed to delete annotation from custom table.', 'wpdb_error' => $wpdb->last_error)); } elseif ($deleted === 0) { echo wp_json_encode(array('success' => false, 'message' => 'Annotation not found or already deleted (ID: ' . $annoid . ').', 'history_logged' => (bool)$logged_history)); } else { echo wp_json_encode(array('success' => true, 'message' => 'Annotation deleted successfully.', 'history_logged' => (bool)$logged_history)); }
        wp_die();
    }
    /************************************
    * ajax update annotation (MODIFIED for history)
    ************************************/
    function anno_update() {
        global $wpdb; $annoid = isset($_POST['annotationid']) ? sanitize_text_field($_POST['annotationid']) : ''; $annotation_json = isset($_POST['annotation']) ? wp_unslash($_POST['annotation']) : '';
        if (empty($annoid) || empty($annotation_json)) { echo wp_json_encode(array('success' => false, 'message' => 'Missing annotationid or annotation data.')); wp_die(); }
        $annotation = json_decode($annotation_json, true); if (json_last_error() !== JSON_ERROR_NONE) { echo wp_json_encode(array('success' => false, 'message' => 'Invalid JSON data (update).', 'error_code' => json_last_error_msg())); wp_die(); }
        $image_url = ''; if (isset($annotation['target']['source'])) { $image_url = $annotation['target']['source']; }
        $attachment_id = attachment_url_to_postid($image_url); if (empty($attachment_id)) { echo wp_json_encode(array('success' => false, 'message' => 'Could not find attachment ID for source URL (update): ' . $image_url)); wp_die(); }
        if (isset($annotation['body'][0]['value'])) { $annotation['body'][0]['value'] = wp_kses_post($annotation['body'][0]['value']); }
        $updated = $wpdb->update( $this->table_name, array( 'annotation_data' => wp_json_encode($annotation) ), array( 'annotation_id_from_annotorious' => $annoid, 'attachment_id' => $attachment_id ), array( '%s' ), array( '%s', '%d' ) );
        $current_user_id = get_current_user_id(); $logged_history = false;
        if ($updated !== false) { $logged_history = $wpdb->insert( $this->history_table_name, array( 'annotation_id_from_annotorious' => $annoid, 'attachment_id' => $attachment_id, 'action_type' => 'updated', 'annotation_data_snapshot' => wp_json_encode($annotation), 'user_id' => $current_user_id, ), array( '%s', '%d', '%s', '%s', '%d' ) ); }
        if ($updated === false) { echo wp_json_encode(array('success' => false, 'message' => 'Failed to update annotation in custom table.', 'wpdb_error' => $wpdb->last_error)); } elseif ($updated === 0) { echo wp_json_encode(array('success' => false, 'message' => 'Annotation not found or no changes made (ID: ' . $annoid . ').', 'history_logged' => (bool)$logged_history)); } else { echo wp_json_encode(array('success' => true, 'message' => 'Annotation updated successfully.', 'history_logged' => (bool)$logged_history)); }
        wp_die();
    }
    /************************************
    * NEW: ajax get annotation history
    ************************************/
    function get_annotorious_history() {
        global $wpdb; $attachment_id = isset($_GET['attachment_id']) ? intval($_GET['attachment_id']) : 0; $annotation_id = isset($_GET['annotation_id']) ? sanitize_text_field($_GET['annotation_id']) : '';
        if (empty($attachment_id) && empty($annotation_id)) { echo wp_json_encode(array('success' => false, 'message' => 'Missing attachment_id or annotation_id.')); wp_die(); }
        header('Content-Type: application/json'); $history_records = []; $query_params = []; $where_clauses = [];
        if ( ! empty( $attachment_id ) ) { $where_clauses[] = 'attachment_id = %d'; $query_params[] = $attachment_id; }
        if ( ! empty( $annotation_id ) ) { $where_clauses[] = 'annotation_id_from_annotorious = %s'; $query_params[] = $annotation_id; }
        $where_sql = implode( ' AND ', $where_clauses ); if (empty($where_sql)) { echo wp_json_encode(array('success' => false, 'message' => 'Invalid query parameters.')); wp_die(); }
        $sql = "SELECT id, annotation_id_from_annotorious, attachment_id, action_type, annotation_data_snapshot, user_id, action_timestamp FROM {$this->history_table_name} WHERE {$where_sql} ORDER BY action_timestamp DESC";
        $results = $wpdb->get_results( $wpdb->prepare( $sql, $query_params ), ARRAY_A );
        if ( ! empty( $results ) ) { foreach ( $results as $row ) { $user_info = get_userdata( $row['user_id'] ); $username = $user_info ? $user_info->display_name : 'Guest/Unknown'; $history_records[] = [ 'id' => (int) $row['id'], 'annotationId' => $row['annotation_id_from_annotorious'], 'attachmentId' => (int) $row['attachment_id'], 'actionType' => $row['action_type'], 'annotationData' => $row['annotation_data_snapshot'], 'userId' => (int) $row['user_id'], 'userName' => $username, 'timestamp' => $row['action_timestamp'], ]; } }
        echo wp_json_encode(array('success' => true, 'history' => $history_records)); wp_die();
    }


} // End class Annotorious