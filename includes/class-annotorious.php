<?php

class Annotorious {
    public $filter_called;

    function __construct() {
        // Frontend scripts and styles
        add_action( 'wp_enqueue_scripts', array( $this, 'load_scripts' ) );

        // Admin scripts and styles
        add_action( 'admin_enqueue_scripts', array( $this, 'load_admin_scripts' ) );

        // Metabox for image collection
        add_action( 'add_meta_boxes', array( $this, 'add_ry_annotorious_images_metabox' ) );
        add_action( 'save_post', array( $this, 'save_ry_annotorious_images_metabox' ), 10, 2 );

        // AJAX actions for annotations
        // Hooks for anno_get, anno_add, etc. methods.
        add_action( 'wp_ajax_nopriv_anno_get', array( $this, 'anno_get') );
        add_action( 'wp_ajax_anno_get', array( $this, 'anno_get' ) );
        add_action( 'wp_ajax_nopriv_anno_add', array( $this, 'anno_add') );
        add_action( 'wp_ajax_anno_add', array( $this, 'anno_add' ) );
        add_action( 'wp_ajax_nopriv_anno_delete', array( $this, 'anno_delete') );
        add_action( 'wp_ajax_anno_delete', array( $this, 'anno_delete' ) );
        add_action( 'wp_ajax_nopriv_anno_update', array( $this, 'anno_update') );
        add_action( 'wp_ajax_anno_update', array( $this, 'anno_update' ) );

        // Content filter for frontend display
        add_filter( 'the_content', array( $this , 'content_filter' ) );

        $this->filter_called = 0;
    }

    // --- Script & Style Loading Functions ---

    function load_scripts()
    {
        // Get the current queried object. This is the most robust way to get the post/page object.
        $current_object = get_queried_object();
        
        // Initialize variables for localization. These will be used if a valid post is found.
        $post_id_for_localization = 0;
        $image_sources = [];
        $viewer_id = ''; 

        // Ensure $current_object is a valid WP_Post instance and has an ID
        if ( $current_object instanceof WP_Post && isset( $current_object->ID ) ) {
            $post_id_for_localization = $current_object->ID;
            $viewer_id = 'openseadragon-viewer-' . $post_id_for_localization;

            // Fetch and prepare image sources for OpenSeadragon
            $image_ids_json = get_post_meta($post_id_for_localization, '_ry_annotorious_image_ids', true);
            $image_ids = json_decode($image_ids_json, true);

            if (!empty($image_ids) && is_array($image_ids)) {
                foreach ($image_ids as $id) {
                    $image_url_array = wp_get_attachment_image_src($id, 'full');
                    if ($image_url_array && is_array($image_url_array) && !empty($image_url_array[0])) {
                        $image_sources[] = [
                            'type' => 'image',
                            'url'  => $image_url_array[0],
                        ];
                    }
                }
            }
        }
        
        // Prepare the main viewer configuration to be localized for public/script.js
        $viewer_config = [
            'id'            => $viewer_id, 
            'images'        => $image_sources,
            'currentPostId' => $post_id_for_localization,
        ];
        
        // Enqueue Annotorious Core CSS
        wp_register_style( 'ry-annotorious-css', RY_ANNOTORIOUS_URL . 'assets/css/annotorious/annotorious.min.css');
        wp_enqueue_style( 'ry-annotorious-css' );

        // Enqueue Annotorious Core JS (v2.7.0, compatible with openseadragon-annotorious.min.js)
        wp_register_script( 'ry-annotorious-core-js', RY_ANNOTORIOUS_URL . 'assets/js/annotorious/annotorious.min.js', array(), '2.7.0', true );
        wp_enqueue_script( 'ry-annotorious-core-js' );

        // OpenSeadragon JS (your v5.0.1)
        wp_register_script( 'openseadragon-js', RY_ANNOTORIOUS_URL . 'assets/js/openseadragon/openseadragon.min.js', array(), '5.0.1', true ); 
        wp_enqueue_script( 'openseadragon-js' );

        // Annotorious OpenSeadragon Plugin JS (v2.7.17 - corrected filename)
        wp_register_script( 'ry-annotorious-osd-plugin-js', RY_ANNOTORIOUS_URL . 'assets/js/annotorious/openseadragon-annotorious.min.js', array( 'ry-annotorious-core-js', 'openseadragon-js' ), '2.7.17', true ); // <-- Corrected filename
        wp_enqueue_script( 'ry-annotorious-osd-plugin-js' );

        // Your custom public JS
        wp_register_script('ry-annotorious-public-js', RY_ANNOTORIOUS_URL . 'assets/js/public/script.js', array('jquery', 'ry-annotorious-osd-plugin-js'),'1.1', true);
        wp_enqueue_script('ry-annotorious-public-js');

        // Localize the main viewer configuration for public/script.js
        wp_localize_script( 'ry-annotorious-public-js', 'AnnotoriousViewerConfig', $viewer_config );
    
        // Localize general AJAX variables for public/script.js (AnnotoriousVars)
        $data = array(
            'post_id'    => $post_id_for_localization, 
            'plugin_url' => RY_ANNOTORIOUS_URL,
            'ajax_url'   => admin_url( 'admin-ajax.php' )
        );
        if (is_user_logged_in()) {  $data['loggedin'] = true;  } else { $data['loggedin'] = false; }
        wp_localize_script( 'ry-annotorious-public-js', 'AnnotoriousVars', $data );
    }


    function load_admin_scripts() {
        // This log confirms the function is executing
        // error_log('Annotorious: load_admin_scripts() is executing. Admin JS URL: ' . RY_ANNOTORIOUS_URL . 'assets/js/admin/admin.js');
        
        // Your custom admin JS
        wp_register_script('admin-js', RY_ANNOTORIOUS_URL . 'assets/js/admin/admin.js', array('jquery'),'1.12', true); // Removed wp-media dependency for user's specific environment
        wp_enqueue_script('admin-js');

        // Also enqueue the core WordPress media scripts and styles.
        wp_enqueue_media();
    }


    // --- Content Filter ---
    function content_filter($content) {
        global $post; // This is a filter, $post should be set.

        // Ensure $post is a valid object before accessing ID
        if ( ! ( $post instanceof WP_Post ) || ! isset($post->ID) ) {
            return $content; // If no valid post, return original content
        }

        // Check if there are any images associated with the post (relying on meta)
        $image_ids_json = get_post_meta($post->ID, '_ry_annotorious_image_ids', true);
        $image_ids = json_decode($image_ids_json, true);

        if (!empty($image_ids) && is_array($image_ids)) {
            $viewer_id = 'openseadragon-viewer-' . $post->ID; 

            // Output the OpenSeadragon viewer container
            $viewer_html = '<div id="' . esc_attr($viewer_id) . '" style="width: 100%; height: 600px; background-color: #f0f0f0; border: 1px solid #ccc;"></div>';
            
            return $viewer_html . $content; 
        }
        else {
            return $content; 
        }
    }

    // --- Metabox Functions ---

    function add_ry_annotorious_images_metabox() {
        add_meta_box(
            'ry-annotorious-images-metabox',
            __( 'Annotorious Image Collection', 'ry-annotorious' ),
            array( $this, 'render_ry_annotorious_images_metabox' ),
            array( 'post', 'page' ),
            'normal',
            'high'
        );
    }

    function render_ry_annotorious_images_metabox( $post ) {
        wp_nonce_field( 'ry_annotorious_images_metabox', 'ry_annotorious_images_nonce' );

        $image_ids_json = get_post_meta( $post->ID, '_ry_annotorious_image_ids', true );
        $image_ids = json_decode( $image_ids_json, true );
        if ( ! is_array( $image_ids ) ) {
            $image_ids = array();
        }
        ?>
        <div id="ry-annotorious-images-container">
            <ul class="ry-annotorious-image-list">
                <?php
                if ( ! empty( $image_ids ) ) {
                    foreach ( $image_ids as $image_id ) {
                        $image_url = wp_get_attachment_url( $image_id );
                        if ( $image_url ) {
                            echo '<li data-id="' . esc_attr( $image_id ) . '">';
                            echo '<img src="' . esc_url( $image_url ) . '" style="max-width:100px; max-height:100px; display:block;" />';
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
            #ry-annotorious-images-container .ry-annotorious-image-list {
                display: flex;
                flex-wrap: wrap;
                list-style: none;
                margin: 0;
                padding: 0;
            }
            #ry-annotorious-images-container .ry-annotorious-image-list li {
                position: relative;
                width: 100px;
                height: 100px;
                margin: 5px;
                border: 1px solid #ccc;
                display: flex;
                align-items: center;
                justify-content: center;
                overflow: hidden;
            }
            #ry-annotorious-images-container .ry-annotorious-image-list li img {
                width: 100%;
                height: 100%;
                object-fit: contain;
            }
            #ry-annotorious-images-container .ry-annotorious-remove-image {
                position: absolute;
                top: 0;
                right: 0;
                background: rgba(255, 0, 0, 0.7);
                color: white;
                padding: 3px;
                cursor: pointer;
                line-height: 1;
                text-decoration: none;
            }
        </style>
        <?php
    }

    function save_ry_annotorious_images_metabox( $post_id, $post ) {
        // Verify nonce
        if ( ! isset( $_POST['ry_annotorious_images_nonce'] ) || ! wp_verify_nonce( $_POST['ry_annotorious_images_nonce'], 'ry_annotorious_images_metabox' ) ) {
            return $post_id;
        }

        // Check autosave
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return $post_id;
        }

        // Check the user's permissions.
        if ( 'page' == $_POST['post_type'] ) {
            if ( ! current_user_can( 'edit_page', $post_id ) ) {
                return $post_id;
            }
        } else {
            if ( ! current_user_can( 'edit_post', $post_id ) ) {
                return $post_id;
            }
        }

        // Sanitize and save the image IDs
        if ( isset( $_POST['_ry_annotorious_image_ids'] ) ) {
            $image_ids_json = wp_kses_post( wp_unslash( $_POST['_ry_annotorious_image_ids'] ) );
            update_post_meta( $post_id, '_ry_annotorious_image_ids', $image_ids_json );
        } else {
            delete_post_meta( $post_id, '_ry_annotorious_image_ids' );
        }
    }


    /*************************************
    * ajax get annotations
    *************************************/
    function anno_get() {
        $sequence_post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0; 

        if (empty($sequence_post_id)) {
            echo wp_json_encode(array('success' => false, 'message' => 'Missing sequence_post_id.'));
            wp_die();
        }

        header('Content-Type: application/json');

        $all_annotations = []; 

        // 1. Get the image attachment IDs associated with this sequence post
        $image_ids_json = get_post_meta($sequence_post_id, '_ry_annotorious_image_ids', true);
        $image_attachment_ids = json_decode($image_ids_json, true);

        if (!empty($image_attachment_ids) && is_array($image_attachment_ids)) {
            foreach ($image_attachment_ids as $attachment_id) {
                // 2. For each attachment ID, get its annotations
                $raw_annotations_for_image = get_post_meta($attachment_id, 'ry_image_annotations', true);
                
                if ( is_string($raw_annotations_for_image) && !empty($raw_annotations_for_image) ) {
                    $decoded_annotations_for_image = json_decode($raw_annotations_for_image, true);
                    if ( is_array($decoded_annotations_for_image) ) {
                        // 3. Add these annotations to the master list
                        $all_annotations = array_merge($all_annotations, $decoded_annotations_for_image);
                    }
                }
            }
        }
        
        echo wp_json_encode($all_annotations); 
        wp_die();
    }


    /*************************************
    * ajax add annotation
    *************************************/
    function anno_add() {
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

        // --- NEW LOGIC: Extract image URL and get attachment ID ---
        $image_url = '';
        if (isset($annotation['target']['source'])) {
            $image_url = $annotation['target']['source'];
        }

        if (empty($image_url)) {
            echo wp_json_encode(array('success' => false, 'message' => 'Annotation target source URL missing.'));
            wp_die();
        }

        // Function to get attachment ID from URL
        $attachment_id = attachment_url_to_postid($image_url);

        if (empty($attachment_id)) {
            echo wp_json_encode(array('success' => false, 'message' => 'Could not find attachment ID for source URL: ' . $image_url));
            wp_die();
        }
        // --- END NEW LOGIC ---

        // Retrieve existing annotations for THIS IMAGE (attachment ID)
        $existing_annotations_raw = get_post_meta($attachment_id, 'ry_image_annotations', true); 
        $meta = []; 
        if ( is_string($existing_annotations_raw) && !empty($existing_annotations_raw) ) {
            $decoded_meta = json_decode($existing_annotations_raw, true);
            if ( is_array($decoded_meta) ) {
                $meta = $decoded_meta;
            }
        }

        // Sanitize annotation body content before adding
        if (isset($annotation['body'][0]['value'])) {
            $annotation['body'][0]['value'] = wp_kses_post($annotation['body'][0]['value']);
        }

        $meta[] = $annotation; 

        // Save updated annotations as JSON string on the ATTACHMENT ID
        $id = update_post_meta( $attachment_id , 'ry_image_annotations', wp_json_encode($meta)); 

        if ($id !== false) {
            echo wp_json_encode(array('success' => true, 'message' => 'Annotation added to attachment ID: ' . $attachment_id, 'meta_id' => $id));
        } else {
            echo wp_json_encode(array('success' => false, 'message' => 'Failed to add annotation to attachment ID: ' . $attachment_id));
        }
        wp_die();
    }

    /************************************
    * ajax delete annotation
    ************************************/
    function anno_delete() {
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

        $existing_annotations_raw = get_post_meta($attachment_id, 'ry_image_annotations', true);
        $meta = [];
        if ( is_string($existing_annotations_raw) && !empty($existing_annotations_raw) ) {
            $decoded_meta = json_decode($existing_annotations_raw, true);
            if ( is_array($decoded_meta) ) {
                $meta = $decoded_meta;
            }
        }

        $found = false;
        foreach($meta as $i => $m) {
          if(isset($m['id']) && $m['id'] === $annoid) { 
            unset($meta[$i]);
            $found = true;
            break; 
          }
        }

        if ($found) {
            $meta = array_values($meta); 
            $id = update_post_meta( $attachment_id , 'ry_image_annotations', wp_json_encode($meta));
            if ($id !== false) {
                 echo wp_json_encode(array('success' => true, 'message' => 'Annotation deleted from attachment ID: ' . $attachment_id, 'meta_id' => $id));
            } else {
                 echo wp_json_encode(array('success' => false, 'message' => 'Failed to delete annotation from attachment ID: ' . $attachment_id));
            }
        } else {
            echo wp_json_encode(array('success' => false, 'message' => 'Annotation not found for attachment ID: ' . $attachment_id));
        }
        wp_die();
    }

    /************************************
    * ajax update annotation
    ************************************/
    function anno_update() {
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

        $existing_annotations_raw = get_post_meta($attachment_id, 'ry_image_annotations', true);
        $meta = [];
        if ( is_string($existing_annotations_raw) && !empty($existing_annotations_raw) ) {
            $decoded_meta = json_decode($existing_annotations_raw, true);
            if ( is_array($decoded_meta) ) {
                $meta = $decoded_meta;
            }
        }

        $found = false;
        foreach($meta as $i=>$m) {
          if(isset($m['id']) && $m['id'] === $annoid) {
            if (isset($annotation['body'][0]['value'])) {
                $annotation['body'][0]['value'] = wp_kses_post($annotation['body'][0]['value']);
            }
            $meta[$i] = $annotation;
            $found = true;
            break;
          }
        }

        if ($found) {
            $id = update_post_meta( $attachment_id , 'ry_image_annotations', wp_json_encode($meta));
            if ($id !== false) {
                echo wp_json_encode(array('success' => true, 'message' => 'Annotation updated on attachment ID: ' . $attachment_id, 'meta_id' => $id));
            } else {
                echo wp_json_encode(array('success' => false, 'message' => 'Failed to update annotation on attachment ID: ' . $attachment_id));
            }
        } else {
            echo wp_json_encode(array('success' => false, 'message' => 'Annotation not found for attachment ID: ' . $attachment_id));
        }
        wp_die();
    }
}