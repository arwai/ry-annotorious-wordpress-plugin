<?php

class Annotorious {
    public $filter_called;
    private $table_name; // For current annotations
    private $history_table_name; // NEW: For annotation history

    // Post Meta Key for display mode
    const META_POST_DISPLAY_MODE = '_ry_annotorious_post_display_mode';

    function __construct() {
        global $wpdb;

        $this->table_name = $wpdb->prefix . 'annotorious_data';
        $this->history_table_name = $wpdb->prefix . 'annotorious_history';

        // Frontend scripts and styles
        add_action( 'wp_enqueue_scripts', array( $this, 'load_scripts' ) );

        // Admin scripts and styles
        add_action( 'admin_enqueue_scripts', array( $this, 'load_admin_scripts' ) );

        // Settings API (can be kept for future global settings)
        add_action( 'admin_init', array( $this, 'ry_annotorious_settings_init' ) );
        add_action( 'admin_menu', array( $this, 'ry_annotorious_add_settings_page' ) );

        // Metabox for image collection & display mode
        add_action( 'add_meta_boxes', array( $this, 'ry_annotorious_add_all_metaboxes' ) );
        add_action( 'save_post', array( $this, 'save_ry_annotorious_images_metabox' ), 10, 2 );

        // AJAX actions for annotations (CRUD)
        add_action( 'wp_ajax_nopriv_anno_get', array( $this, 'anno_get') );
        add_action( 'wp_ajax_anno_get', array( $this, 'anno_get' ) );
        add_action( 'wp_ajax_nopriv_anno_add', array( $this, 'anno_add') );
        add_action( 'wp_ajax_anno_add', array( $this, 'anno_add' ) );
        add_action( 'wp_ajax_nopriv_anno_delete', array( $this, 'anno_delete') );
        add_action( 'wp_ajax_anno_delete', array( $this, 'anno_delete' ) );
        add_action( 'wp_ajax_nopriv_anno_update', array( $this, 'anno_update') );
        add_action( 'wp_ajax_anno_update', array( $this, 'anno_update' ) );

        // NEW: AJAX action for retrieving annotation history
        add_action( 'wp_ajax_get_annotorious_history', array( $this, 'get_annotorious_history' ) );
        add_action( 'wp_ajax_nopriv_get_annotorious_history', array( $this, 'get_annotorious_history' ) );

        // Content filter for frontend display
        add_filter( 'the_content', array( $this , 'content_filter' ), 20 ); // Priority 20 to run after some other filters

        $this->filter_called = 0;
    }

    /*************************************
    * --- Settings API Functions (for potential future global settings) ---
    *************************************/

    public function ry_annotorious_settings_init() {
        // Example: Register a global setting if you need one in the future
        /*
        register_setting(
            'ry_annotorious_options_group',
            'ry_annotorious_global_setting_example',
            array(
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => '',
            )
        );
        */

        add_settings_section(
            'ry_annotorious_settings_section_main',
            'RY Annotorious Global Settings',
            array($this, 'ry_annotorious_settings_section_main_callback'), // Optional description callback
            'ry-annotorious-settings'
        );

        /*
        add_settings_field(
            'ry_annotorious_global_setting_example_field',
            'Global Setting Example',
            array( $this, 'ry_annotorious_global_setting_example_callback' ),
            'ry-annotorious-settings',
            'ry_annotorious_settings_section_main'
        );
        */
    }

    public function ry_annotorious_settings_section_main_callback() {
        echo '<p>Configure any global settings for the RY Annotorious plugin here. The primary display choice (default viewer vs. Gutenberg block) is now managed per post/page within its edit screen.</p>';
    }

    /*
    public function ry_annotorious_global_setting_example_callback() {
        $option = get_option( 'ry_annotorious_global_setting_example', '' );
        ?>
        <input type="text" id="ry_annotorious_global_setting_example"
               name="ry_annotorious_global_setting_example"
               value="<?php echo esc_attr( $option ); ?>" class="regular-text" />
        <p class="description">Description for this example global setting.</p>
        <?php
    }
    */

    public function ry_annotorious_add_settings_page() {
        add_options_page(
            'RY Annotorious Settings',
            'RY Annotorious',
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
                settings_fields( 'ry_annotorious_options_group' ); // Group name for settings
                do_settings_sections( 'ry-annotorious-settings' ); // Page slug
                // submit_button( 'Save Global Settings' ); // Uncomment if you have global settings
                ?>
            </form>
        </div>
        <?php
    }


    /*************************************
    * --- Script & Style Loading Functions ---
    *************************************/

    function load_scripts(){
        // Only proceed for singular posts/pages
        if ( ! is_singular() ) {
            return;
        }

        $current_post_id = get_the_ID();
        if ( ! $current_post_id ) {
            return;
        }

        // Get the display mode for THIS post (default to 'metabox_viewer')
        $post_display_mode = get_post_meta( $current_post_id, self::META_POST_DISPLAY_MODE, true );
        if ( empty( $post_display_mode ) ) {
            $post_display_mode = 'metabox_viewer'; // Default if not set
        }

        // Only load default viewer scripts if 'metabox_viewer' is selected for this post
        if ( 'metabox_viewer' === $post_display_mode ) {
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

            // Only enqueue and localize if there are images for this post's default viewer
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
        // If $post_display_mode is 'gutenberg_block', the block itself will handle its script enqueueing.
    }


    function load_admin_scripts($hook_suffix) {
        // Only load on post edit screens
        if (in_array($hook_suffix, array('post.php', 'post-new.php'))) {
            wp_register_script('admin-js', RY_ANNOTORIOUS_URL . 'assets/js/admin/admin.js', array('jquery'),'1.12', true);
            wp_enqueue_script('admin-js');
            wp_enqueue_media(); // For the image uploader in the metabox
        }
    }

    /*************************************
    * --- Content Filter ---
    *************************************/

    function content_filter($content) {
        // Only proceed for singular posts/pages, in the main loop and query
        if ( !is_singular() || !in_the_loop() || !is_main_query() ) {
            return $content;
        }

        $current_post_id = get_the_ID();
        if ( ! $current_post_id ) {
            return $content;
        }

        // Get the display mode for THIS post
        $post_display_mode = get_post_meta( $current_post_id, self::META_POST_DISPLAY_MODE, true );
        if ( empty( $post_display_mode ) ) {
            $post_display_mode = 'metabox_viewer'; // Default
        }

        // If Gutenberg block mode is selected for this post, or if filter has already run, do not auto-insert.
        if ( 'gutenberg_block' === $post_display_mode || $this->filter_called > 0) {
            return $content;
        }

        // Proceed with default viewer logic (auto-insert via content filter from metabox images)
        if ( 'metabox_viewer' === $post_display_mode ) {
            $image_ids_json = get_post_meta( $current_post_id, '_ry_annotorious_image_ids', true );
            $image_ids = json_decode( $image_ids_json, true );

            if ( !empty( $image_ids ) && is_array( $image_ids ) ) {
                // Ensure we only add it once, even if the_content is called multiple times by a theme/plugin.
                if ( $this->filter_called === 0 ) {
                    $this->filter_called++;
                    $viewer_id = 'openseadragon-viewer-' . $current_post_id;
                    $viewer_html = '<div id="' . esc_attr( $viewer_id ) . '" style="width: 100%; height: 600px; background-color: #f0f0f0; border: 1px solid #ccc;" class="ry-annotorious-default-viewer-container"></div>';
                    return $viewer_html . $content; // Prepend viewer
                }
            }
        }
        return $content;
    }



    /*************************************
    * --- Metabox Functions ---
    *************************************/

    /**
     * Register both metaboxes.
     * This function is hooked to 'add_meta_boxes'.
     */
    function ry_annotorious_add_all_metaboxes() { // Renamed for clarity
        // 1. Metabox for Viewer Display Mode (on the side)
        add_meta_box(
            'ry-annotorious-display-mode-metabox',                    // ID
            __( 'Annotorious Viewer Mode', 'ry-annotorious' ),        // Title
            array( $this, 'render_ry_annotorious_display_mode_metabox' ), // Callback
            array( 'post', 'page' ),                                  // Screen (post types)
            'side',                                                   // Context (position)
            'default'                                                 // Priority
        );

        // 2. Metabox for Image Collection (in the normal/main content area)
        add_meta_box(
            'ry-annotorious-image-collection-metabox',                // ID
            __( 'Annotorious Image Collection', 'ry-annotorious' ),   // Title
            array( $this, 'render_ry_annotorious_image_collection_metabox' ), // Callback
            array( 'post', 'page' ),                                  // Screen (post types)
            'normal',                                                 // Context
            'high'                                                    // Priority
        );
    }

    /**
     * Render the metabox for Viewer Display Mode options.
     */
    function render_ry_annotorious_display_mode_metabox( $post ) {
        // Nonce field should ideally be in each metabox if they could be saved independently
        // or if there's any doubt. However, since WordPress saves all metaboxes in one go
        // and our save function 'save_ry_annotorious_images_metabox' handles both,
        // one nonce in the primary metabox (image collection) is usually sufficient.
        // For clarity and robustness, especially if you ever separate save logic,
        // you can add a nonce here too. If you keep one save function and one nonce name,
        // ensure that nonce name is used.

        // wp_nonce_field( 'ry_annotorious_images_metabox_nonce', 'ry_annotorious_images_nonce_display_mode' ); // Example of a separate nonce

        // Get saved display mode for this post
        $current_display_mode = get_post_meta( $post->ID, self::META_POST_DISPLAY_MODE, true );
        if ( empty( $current_display_mode ) ) {
            $current_display_mode = 'metabox_viewer'; // Default
        }
        ?>
        <div id="ry-annotorious-options-container">
            <p>
                <label>
                    <input type="radio" name="<?php echo esc_attr( self::META_POST_DISPLAY_MODE ); ?>" value="metabox_viewer" <?php checked( $current_display_mode, 'metabox_viewer' ); ?> />
                    <?php _e( 'Default Viewer', 'ry-annotorious' ); ?>
                </label>
                <br />
                <small class="description"><?php _e( 'Uses images from the "Annotorious Image Collection" metabox below.', 'ry-annotorious' ); ?></small>
            </p>
            <p>
                <label>
                    <input type="radio" name="<?php echo esc_attr( self::META_POST_DISPLAY_MODE ); ?>" value="gutenberg_block" <?php checked( $current_display_mode, 'gutenberg_block' ); ?> />
                    <?php _e( 'Gutenberg Block', 'ry-annotorious' ); ?>
                </label>
                <br/>
                <small class="description"><?php _e( 'Manual placement via Gutenberg block.', 'ry-annotorious' ); ?></small>
            </p>
        </div>
        <?php
    }

    /**
     * Render the metabox for Image Collection.
     */
    function render_ry_annotorious_image_collection_metabox( $post ) {
        // This is the primary nonce for saving both pieces of data.
        wp_nonce_field( 'ry_annotorious_images_metabox_save', 'ry_annotorious_images_nonce' );

        $image_ids_json = get_post_meta( $post->ID, '_ry_annotorious_image_ids', true );
        $image_ids = json_decode( $image_ids_json, true );
        if ( ! is_array( $image_ids ) ) {
            $image_ids = array();
        }
        ?>
        <div id="ry-annotorious-images-collection-container">
            <p class="description">
                <?php _e( 'Select images from the media library. These will be used if "Default Viewer" mode is active (selected in the sidebar).', 'ry-annotorious' ); ?>
            </p>
            <ul class="ry-annotorious-image-list">
                <?php
                if ( ! empty( $image_ids ) ) {
                    foreach ( $image_ids as $image_id ) {
                        $image_thumb_url = wp_get_attachment_image_url( $image_id, 'thumbnail' );
                        if ( $image_thumb_url ) {
                            echo '<li data-id="' . esc_attr( $image_id ) . '">';
                            echo '<img src="' . esc_url( $image_thumb_url ) . '" style="max-width:100px; max-height:100px; display:block;" />';
                            echo '<a href="#" class="ry-annotorious-remove-image dashicons dashicons-trash" title="Remove image"></a>';
                            echo '</li>';
                        }
                    }
                }
                ?>
            </ul>
            <p>
                <a href="#" class="button button-secondary ry-annotorious-add-images-button">
                    <?php _e( 'Add/Select Images', 'ry-annotorious' ); ?>
                </a>
                <input type="hidden" id="ry_annotorious_image_ids_field" name="_ry_annotorious_image_ids" value="<?php echo esc_attr( $image_ids_json ); ?>" />
            </p>
        </div>
        <style>
            /* Styles can remain largely the same, or be moved to a separate admin CSS file */
            #ry-annotorious-images-collection-container .ry-annotorious-image-list { display: flex; flex-wrap: wrap; list-style: none; margin: 0; padding: 0; }
            #ry-annotorious-images-collection-container .ry-annotorious-image-list li { position: relative; width: 100px; height: 100px; margin: 5px; border: 1px solid #ccc; display: flex; align-items: center; justify-content: center; overflow: hidden; }
            #ry-annotorious-images-collection-container .ry-annotorious-image-list li img { max-width: 100%; max-height: 100%; object-fit: contain; }
            #ry-annotorious-images-collection-container .ry-annotorious-remove-image { position: absolute; top: 0; right: 0; background: rgba(255,0,0,0.7); color: white; padding: 3px; cursor: pointer; line-height: 1; text-decoration: none; }
        </style>
        <?php
    }

    /**
     * Save data from both metaboxes.
     * This function is hooked to 'save_post'.
     */
    function save_ry_annotorious_images_metabox( $post_id, $post ) { // Function name can remain
        // Verify nonce (using the nonce name from the image collection metabox)
        if ( ! isset( $_POST['ry_annotorious_images_nonce'] ) || ! wp_verify_nonce( $_POST['ry_annotorious_images_nonce'], 'ry_annotorious_images_metabox_save' ) ) {
            return $post_id;
        }
        // Check autosave
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return $post_id;
        }
        // Check permissions
        $post_type = get_post_type_object( $post->post_type );
        if ( ! current_user_can( $post_type->cap->edit_post, $post_id ) ) {
            return $post_id;
        }

        // Save Display Mode for the post (from the side metabox)
        if ( isset( $_POST[self::META_POST_DISPLAY_MODE] ) ) {
            $display_mode = sanitize_text_field( $_POST[self::META_POST_DISPLAY_MODE] );
            if ( in_array( $display_mode, array( 'metabox_viewer', 'gutenberg_block' ) ) ) {
                update_post_meta( $post_id, self::META_POST_DISPLAY_MODE, $display_mode );
            } else {
                update_post_meta( $post_id, self::META_POST_DISPLAY_MODE, 'metabox_viewer' );
            }
        } else {
             update_post_meta( $post_id, self::META_POST_DISPLAY_MODE, 'metabox_viewer' );
        }

        // Sanitize and save the image IDs (from the main metabox)
        if ( isset( $_POST['_ry_annotorious_image_ids'] ) ) {
            $image_ids_json = wp_unslash( $_POST['_ry_annotorious_image_ids'] );
            $image_ids = json_decode( $image_ids_json, true );

            if ( is_array( $image_ids ) ) {
                $sanitized_image_ids = array_map( 'intval', $image_ids );
                update_post_meta( $post_id, '_ry_annotorious_image_ids', json_encode( $sanitized_image_ids ) );
            } else {
                 delete_post_meta( $post_id, '_ry_annotorious_image_ids' );
            }
        } else {
            delete_post_meta( $post_id, '_ry_annotorious_image_ids' );
        }
    }


    /*************************************
    * ajax get annotations
    *************************************/
    function anno_get() {
        global $wpdb; // Make $wpdb accessible
        $attachment_id = isset($_GET['attachment_id']) ? intval($_GET['attachment_id']) : 0; 

        if (empty($attachment_id)) {
            echo wp_json_encode(array('success' => false, 'message' => 'Missing attachment_id.'));
            wp_die();
        }

        header('Content-Type: application/json');

        $all_annotations = []; 

        // Retrieve annotations from the custom table for the specific attachment_id
        $results = $wpdb->get_results( 
            $wpdb->prepare( "SELECT annotation_data FROM {$this->table_name} WHERE attachment_id = %d", $attachment_id ), 
            ARRAY_A 
        );
        
        if ( ! empty( $results ) ) {
            foreach ( $results as $row ) {
                $decoded_annotation = json_decode( $row['annotation_data'], true );
                if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded_annotation ) ) {
                    $all_annotations[] = $decoded_annotation;
                }
            }
        }
        
        echo wp_json_encode($all_annotations); 
        wp_die();
    }



    /*************************************
    * ajax add annotation (MODIFIED for history)
    *************************************/
    function anno_add() {
        global $wpdb;
        $annotation_json = isset($_POST['annotation']) ? wp_unslash($_POST['annotation']) : ''; 

        if (empty($annotation_json)) {
            echo wp_json_encode(array('success' => false, 'message' => 'Annotation data missing.'));
            wp_die();
        }

        $annotation = json_decode($annotation_json, true); 

        if (json_last_error() !== JSON_ERROR_NONE) {
            echo wp_json_encode(array('success' => false, 'message' => 'Invalid JSON data.', 'error_code' => json_last_error_msg()));
            wp_die();
        }

        $image_url = '';
        if (isset($annotation['target']['source'])) {
            $image_url = $annotation['target']['source'];
        }

        if (empty($image_url)) {
            echo wp_json_encode(array('success' => false, 'message' => 'Annotation target source URL missing.'));
            wp_die();
        }

        $attachment_id = attachment_url_to_postid($image_url);

        if (empty($attachment_id)) {
            echo wp_json_encode(array('success' => false, 'message' => 'Could not find attachment ID for source URL: ' . $image_url));
            wp_die();
        }

        $annotation_id_from_annotorious = isset($annotation['id']) ? sanitize_text_field($annotation['id']) : '';

        if (empty($annotation_id_from_annotorious)) {
            echo wp_json_encode(array('success' => false, 'message' => 'Annotorious ID (UUID) missing from annotation data.'));
            wp_die();
        }

        // Sanitize annotation body content
        if (isset($annotation['body'][0]['value'])) {
            $annotation['body'][0]['value'] = wp_kses_post($annotation['body'][0]['value']);
        }
        
        // --- LOG 'CREATED' ACTION TO HISTORY TABLE ---
        $current_user_id = get_current_user_id(); // Get current user ID (0 if not logged in)
        
        $logged_history = $wpdb->insert(
            $this->history_table_name,
            array(
                'annotation_id_from_annotorious' => $annotation_id_from_annotorious,
                'attachment_id'                  => $attachment_id,
                'action_type'                    => 'created',
                'annotation_data_snapshot'       => wp_json_encode($annotation), // Store the full new annotation
                'user_id'                        => $current_user_id,
            ),
            array( '%s', '%d', '%s', '%s', '%d' )
        );
        // --- END HISTORY LOGGING ---

        $inserted = $wpdb->insert(
            $this->table_name,
            array(
                'annotation_id_from_annotorious' => $annotation_id_from_annotorious,
                'attachment_id'                  => $attachment_id,
                'annotation_data'                => wp_json_encode($annotation),
            ),
            array( '%s', '%d', '%s' )
        );

        if ($inserted !== false) {
            echo wp_json_encode(array('success' => true, 'message' => 'Annotation added successfully.', 'database_id' => $wpdb->insert_id, 'history_logged' => (bool)$logged_history));
        } else {
            // If main insert fails, consider logging an error or rolling back history insert if it was successful.
            echo wp_json_encode(array('success' => false, 'message' => 'Failed to add annotation to custom table.', 'wpdb_error' => $wpdb->last_error));
        }
        wp_die();
    }

    /************************************
    * ajax delete annotation (MODIFIED for history)
    ************************************/
    function anno_delete() {
        global $wpdb;
        $annoid = isset($_POST['annotationid']) ? sanitize_text_field($_POST['annotationid']) : '';
        $annotation_json = isset($_POST['annotation']) ? wp_unslash($_POST['annotation']) : '';

        if (empty($annoid) || empty($annotation_json)) {
            echo wp_json_encode(array('success' => false, 'message' => 'Missing annotationid or annotation data.'));
            wp_die();
        }

        $annotation = json_decode($annotation_json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo wp_json_encode(array('success' => false, 'message' => 'Invalid JSON data (delete).', 'error_code' => json_last_error_msg()));
            wp_die();
        }

        $image_url = '';
        if (isset($annotation['target']['source'])) {
            $image_url = $annotation['target']['source'];
        }
        $attachment_id = attachment_url_to_postid($image_url);

        if (empty($attachment_id)) {
            echo wp_json_encode(array('success' => false, 'message' => 'Could not find attachment ID for source URL (delete): ' . $image_url));
            wp_die();
        }
        
        // --- LOG 'DELETED' ACTION TO HISTORY TABLE ---
        $current_user_id = get_current_user_id();

        // Retrieve the annotation data BEFORE deletion to save its snapshot in history
        $existing_annotation_row = $wpdb->get_row(
            $wpdb->prepare( "SELECT annotation_data FROM {$this->table_name} WHERE annotation_id_from_annotorious = %s AND attachment_id = %d", $annoid, $attachment_id ),
            ARRAY_A
        );
        
        $logged_history = false;
        if ( ! empty( $existing_annotation_row ) ) {
            $logged_history = $wpdb->insert(
                $this->history_table_name,
                array(
                    'annotation_id_from_annotorious' => $annoid,
                    'attachment_id'                  => $attachment_id,
                    'action_type'                    => 'deleted',
                    'annotation_data_snapshot'       => $existing_annotation_row['annotation_data'], // Snapshot of what was deleted
                    'user_id'                        => $current_user_id,
                ),
                array( '%s', '%d', '%s', '%s', '%d' )
            );
        }
        // --- END HISTORY LOGGING ---

        $deleted = $wpdb->delete(
            $this->table_name,
            array(
                'annotation_id_from_annotorious' => $annoid,
                'attachment_id' => $attachment_id
            ),
            array( '%s', '%d' )
        );

        if ($deleted === false) {
            echo wp_json_encode(array('success' => false, 'message' => 'Failed to delete annotation from custom table.', 'wpdb_error' => $wpdb->last_error));
        } elseif ($deleted === 0) {
            echo wp_json_encode(array('success' => false, 'message' => 'Annotation not found or already deleted (ID: ' . $annoid . ').', 'history_logged' => (bool)$logged_history));
        } else {
            echo wp_json_encode(array('success' => true, 'message' => 'Annotation deleted successfully.', 'history_logged' => (bool)$logged_history));
        }
        wp_die();
    }

    /************************************
    * ajax update annotation (MODIFIED for history)
    ************************************/
    function anno_update() {
        global $wpdb;
        $annoid = isset($_POST['annotationid']) ? sanitize_text_field($_POST['annotationid']) : '';
        $annotation_json = isset($_POST['annotation']) ? wp_unslash($_POST['annotation']) : '';

        if (empty($annoid) || empty($annotation_json)) {
            echo wp_json_encode(array('success' => false, 'message' => 'Missing annotationid or annotation data.'));
            wp_die();
        }

        $annotation = json_decode($annotation_json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            echo wp_json_encode(array('success' => false, 'message' => 'Invalid JSON data (update).', 'error_code' => json_last_error_msg()));
            wp_die();
        }

        $image_url = '';
        if (isset($annotation['target']['source'])) {
            $image_url = $annotation['target']['source'];
        }
        $attachment_id = attachment_url_to_postid($image_url);

        if (empty($attachment_id)) {
            echo wp_json_encode(array('success' => false, 'message' => 'Could not find attachment ID for source URL (update): ' . $image_url));
            wp_die();
        }

        // Sanitize annotation body content
        if (isset($annotation['body'][0]['value'])) {
            $annotation['body'][0]['value'] = wp_kses_post($annotation['body'][0]['value']);
        }
        
        $updated = $wpdb->update(
            $this->table_name,
            array(
                'annotation_data' => wp_json_encode($annotation), // Store the updated JSON object
            ),
            array(
                'annotation_id_from_annotorious' => $annoid,
                'attachment_id' => $attachment_id
            ),
            array( '%s' ),
            array( '%s', '%d' )
        );

        // --- LOG 'UPDATED' ACTION TO HISTORY TABLE ---
        $current_user_id = get_current_user_id();
        $logged_history = false;
        // Log history only if the update actually occurred (row found and changed or row found and no change but it implies existence)
        if ($updated !== false) { // If update query didn't fail
            $logged_history = $wpdb->insert(
                $this->history_table_name,
                array(
                    'annotation_id_from_annotorious' => $annoid,
                    'attachment_id'                  => $attachment_id,
                    'action_type'                    => 'updated',
                    'annotation_data_snapshot'       => wp_json_encode($annotation), // Store the full updated annotation
                    'user_id'                        => $current_user_id,
                ),
                array( '%s', '%d', '%s', '%s', '%d' )
            );
        }
        // --- END HISTORY LOGGING ---

        if ($updated === false) {
            echo wp_json_encode(array('success' => false, 'message' => 'Failed to update annotation in custom table.', 'wpdb_error' => $wpdb->last_error));
        } elseif ($updated === 0) {
            // Updated can be 0 if the data was identical or if the record was not found
            echo wp_json_encode(array('success' => false, 'message' => 'Annotation not found or no changes made (ID: ' . $annoid . ').', 'history_logged' => (bool)$logged_history));
        } else {
            echo wp_json_encode(array('success' => true, 'message' => 'Annotation updated successfully.', 'history_logged' => (bool)$logged_history));
        }
        wp_die();
    }

    /************************************
    * NEW: ajax get annotation history
    ************************************/
    function get_annotorious_history() {
        global $wpdb;
        $attachment_id = isset($_GET['attachment_id']) ? intval($_GET['attachment_id']) : 0;
        $annotation_id = isset($_GET['annotation_id']) ? sanitize_text_field($_GET['annotation_id']) : ''; // Optional: for specific annotation history

        if (empty($attachment_id) && empty($annotation_id)) {
            echo wp_json_encode(array('success' => false, 'message' => 'Missing attachment_id or annotation_id.'));
            wp_die();
        }

        header('Content-Type: application/json');

        $history_records = [];
        $query_params = [];
        $where_clauses = [];

        if ( ! empty( $attachment_id ) ) {
            $where_clauses[] = 'attachment_id = %d';
            $query_params[] = $attachment_id;
        }

        if ( ! empty( $annotation_id ) ) {
            $where_clauses[] = 'annotation_id_from_annotorious = %s';
            $query_params[] = $annotation_id;
        }
        
        $where_sql = implode( ' AND ', $where_clauses );

        if (empty($where_sql)) { // Should not happen with above checks, but as a fallback
            echo wp_json_encode(array('success' => false, 'message' => 'Invalid query parameters.'));
            wp_die();
        }

        // Fetch history records, ordered by timestamp
        $sql = "SELECT id, annotation_id_from_annotorious, attachment_id, action_type, annotation_data_snapshot, user_id, action_timestamp FROM {$this->history_table_name} WHERE {$where_sql} ORDER BY action_timestamp DESC";
        $results = $wpdb->get_results( 
            $wpdb->prepare( $sql, $query_params ), 
            ARRAY_A 
        );

        if ( ! empty( $results ) ) {
            foreach ( $results as $row ) {
                // Get user info for display
                $user_info = get_userdata( $row['user_id'] );
                $username = $user_info ? $user_info->display_name : 'Guest/Unknown';

                // Decode snapshot for client-side use if needed, but send as string for now
                // $decoded_snapshot = json_decode($row['annotation_data_snapshot'], true);
                // if (json_last_error() !== JSON_ERROR_NONE) { $decoded_snapshot = null; }

                $history_records[] = [
                    'id'               => (int) $row['id'],
                    'annotationId'     => $row['annotation_id_from_annotorious'],
                    'attachmentId'     => (int) $row['attachment_id'],
                    'actionType'       => $row['action_type'],
                    'annotationData'   => $row['annotation_data_snapshot'], // Send as string; JS can parse if needed
                    'userId'           => (int) $row['user_id'],
                    'userName'         => $username,
                    'timestamp'        => $row['action_timestamp'],
                ];
            }
        }
        
        echo wp_json_encode(array('success' => true, 'history' => $history_records)); 
        wp_die();
    }
}