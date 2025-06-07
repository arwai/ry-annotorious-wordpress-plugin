// ry-annotorious/assets/js/public/script.js

console.log("ry-annotorious-public-js loaded.");

jQuery(document).ready(function($) {

    // Check if AnnotoriousViewerConfig is available and has images
    if (typeof AnnotoriousViewerConfig !== 'undefined' && AnnotoriousViewerConfig.images && AnnotoriousViewerConfig.images.length > 0) {
        console.log("Initializing OpenSeadragon and Annotorious...");

        const viewerId = AnnotoriousViewerConfig.id;
        const images = AnnotoriousViewerConfig.images; // This array already contains 'post_id' (attachment ID)
        const currentPostId = AnnotoriousViewerConfig.currentPostId; // This is the main post ID

        // Ensure AnnotoriousVars and its ajax_url property are available
        if (typeof AnnotoriousVars === 'undefined' || typeof AnnotoriousVars.ajax_url === 'undefined') {
            console.error("AnnotoriousVars or AnnotoriousVars.ajax_url is not defined. AJAX calls will fail.");
            // Optionally, you might want to display an error message in the viewer div
            const osdContainerError = document.getElementById(viewerId);
            if (osdContainerError) {
                osdContainerError.innerHTML = '<p style="color:red; padding:10px;">Configuration error: AJAX URL not available. Annotations will not work.</p>';
            }
            return; // Stop script execution if critical AJAX URL is missing
        }
        const ajaxUrl = AnnotoriousVars.ajax_url;

        // Get custom OSD options from WordPress settings, default to an empty object if not present
        const customOsdOptions = AnnotoriousViewerConfig.osdOptions || {};
        console.log("Custom OSD Options from WP Settings:", customOsdOptions);

        // Get custom Annotorious options from WordPress settings. Ensure it's an object, default to empty object if not present or not an object
        const customAnnoOptions = (typeof AnnotoriousViewerConfig.annoOptions === 'object' && AnnotoriousViewerConfig.annoOptions !== null) 
                                  ? AnnotoriousViewerConfig.annoOptions 
                                  : {}; 
        console.log("Custom Annotorious Options from WP Settings:", customAnnoOptions);


        console.log("Viewer ID:", viewerId);
        console.log("Images for OSD:", images);
        console.log("Current Main Post ID:", currentPostId);
        console.log("AJAX URL (from AnnotoriousVars):", ajaxUrl);

        // --- CRITICAL CHECK: Verify OSD container exists in DOM ---
        const osdContainer = document.getElementById(viewerId);
        if (!osdContainer) {
            console.error("OpenSeadragon container element not found with ID:", viewerId);
            return; // Stop script execution here if container is missing
        } else {
            console.log("OpenSeadragon container found:", osdContainer);
        }

        // --- Check if OpenSeadragon is available globally ---
        if (typeof OpenSeadragon === 'undefined') {
            console.error("OpenSeadragon library is not loaded or not available globally.");
            // Display error in the viewer div
            osdContainer.innerHTML = '<p style="color:red; padding:10px;">Error: OpenSeadragon library not loaded. Viewer cannot start.</p>';
            return; // Stop script execution
        } else {
            console.log("OpenSeadragon library detected.");
        }

        // --- Prepare OpenSeadragon Configuration ---
        // Base options that are always set by the plugin (id and tileSources)
        const baseOsdOptions = {
            id: viewerId,
            tileSources: images.map(img => ({
                type: img.type, // 'type' and 'url' should be present in your 'images' objects
                url: img.url
            }))
        };

        // Merge the custom options from WP settings with the base options.
        // User-defined options are applied first, then our base options are applied.
        // This ensures that if a user accidentally includes 'id' or 'tileSources' in their JSON,
        // the plugin's required values will correctly override them.
        const finalOsdOptions = {
            ...customOsdOptions, // User-defined options from WP settings
            ...baseOsdOptions    // Plugin-defined mandatory options (id, tileSources)
        };
        
        // For absolute certainty, explicitly re-assign 'id' and 'tileSources' from baseOsdOptions.
        // This protects against any unusual behavior with object spreading or if the user provides these keys.
        finalOsdOptions.id = baseOsdOptions.id;
        finalOsdOptions.tileSources = baseOsdOptions.tileSources;

        console.log("Final OSD Configuration to be used:", finalOsdOptions);

        // OpenSeadragon Initialization using the final merged options
        let osdViewer; // Declare osdViewer here to be accessible in catch block
        try {
            osdViewer = OpenSeadragon(finalOsdOptions);
            console.log("OpenSeadragon viewer object created:", osdViewer);
        } catch (e) {
            console.error("Error initializing OpenSeadragon with the provided options:", e);
            if (osdContainer) {
                osdContainer.innerHTML = `<p style="color:red; padding:10px;">Error initializing OpenSeadragon viewer. Please check console and plugin settings. Details: ${e.message}</p>`;
            }
            return; // Stop script execution
        }


        // --- Check if Annotorious is available globally for v2 init ---
        if (typeof OpenSeadragon.Annotorious === 'undefined') { // V2 check
             console.error("Annotorious plugin for OpenSeadragon is not loaded or not available globally.");
             // Display error in the viewer div
             osdContainer.innerHTML = '<p style="color:red; padding:10px;">Error: Annotorious library not loaded. Annotations will not work.</p>';
             return; // Stop script execution
        } else {
            console.log("OpenSeadragon.Annotorious plugin detected.");
        }

        // MODIFIED: Initialize Annotorious with the customAnnoOptions
        let anno; 
        try {
            // Pass the customAnnoOptions (which is an object) to the initializer
            anno = OpenSeadragon.Annotorious(osdViewer, customAnnoOptions); 
            console.log("Annotorious initialized on OpenSeadragon (v2) with options:", customAnnoOptions);
        } catch (e) {
            console.error("Error initializing Annotorious with the provided options:", e);
            if (osdContainer) {
                let errorMsg = `<p style="color:red; padding:10px;">Error initializing Annotorious. Details: ${e.message}</p>`;
                osdContainer.innerHTML = osdContainer.innerHTML.includes('Error:') ? osdContainer.innerHTML + errorMsg : errorMsg;
            }
            return; // Stop if Annotorious fails
        }

        // // Initialize Annotorious on the OpenSeadragon viewer (Annotorious v2 initialization)
        // const anno = OpenSeadragon.Annotorious(osdViewer); // V2 init
        // console.log("Annotorious initialized on OpenSeadragon (v2).");

        // --- Function to load annotations via AJAX ---
        function loadAnnotationsFromBackend(attachmentId) {
            console.log("Attempting AJAX load of annotations for attachment ID:", attachmentId);
            
            // CRITICAL: Clear existing annotations from the viewer BEFORE loading new ones
            anno.setAnnotations([]); 

            if (!attachmentId || attachmentId <= 0) {
                console.warn("No valid attachment ID provided for loading annotations. Skipping AJAX call.");
                return;
            }

            $.ajax({
                url: ajaxUrl,
                data: {
                    action: 'anno_get',
                    attachment_id: attachmentId // SEND THE SPECIFIC ATTACHMENT ID
                },
                dataType: 'json',
                success: function(annotations) {
                    console.log("Loaded annotations for attachment ID", attachmentId, ":", annotations);
                    if (Array.isArray(annotations)) {
                       anno.setAnnotations(annotations);
                    } else {
                       console.warn("Loaded annotations are not an array for attachment ID", attachmentId, ":", annotations);
                       // If format is wrong, ensure annotations are cleared
                       anno.setAnnotations([]); 
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Error loading annotations via AJAX for attachment ID", attachmentId, ":", status, error, xhr.responseText);
                }
            });
        }

        // --- Event handler for initial image load (first image in sequence) ---
        osdViewer.addHandler('open', function() {
            console.log("OpenSeadragon 'open' event fired: Image(s) loaded into viewer.");
            
            let currentImageAttachmentId = 0;
            const currentPage = osdViewer.currentPage(); 

            // Get the attachment ID for the currently open image from our localized data
            if (images && images[currentPage]) {
                currentImageAttachmentId = images[currentPage].post_id; // 'post_id' here is the attachment ID
                console.log(`Current OSD Image Attachment ID (from localized data) for page ${currentPage}: ${currentImageAttachmentId}`);
            } else {
                console.warn(`Could not find pre-loaded attachment ID for page ${currentPage}.`);
            }
            
            // Load annotations for the initially displayed image
            if (currentImageAttachmentId > 0) {
                loadAnnotationsFromBackend(currentImageAttachmentId);
            }
        });

        // --- Event handler for page changes (navigating through the sequence) ---
        osdViewer.addHandler('page-change', function(event) {
            console.log("OpenSeadragon 'page-change' event fired. New page index:", event.page);
            let newImageAttachmentId = 0;

            // Get the attachment ID for the newly displayed image from our localized data
            if (images && images[event.page]) {
                newImageAttachmentId = images[event.page].post_id; // 'post_id' here is the attachment ID
                console.log(`New OSD Image Attachment ID (from localized data) for page ${event.page}: ${newImageAttachmentId}`);
            } else {
                console.warn(`Could not find pre-loaded attachment ID for new page ${event.page}.`);
            }

            // Load annotations for the newly displayed image
            if (newImageAttachmentId > 0) {
                loadAnnotationsFromBackend(newImageAttachmentId);
            }
        });


        // Save new/updated annotations (v2 event callback signature)
        anno.on('createAnnotation', function(annotation) { // V2 event signature
            console.log("Annotation created. Target Source:", annotation.target.source); // Debug log
            $.ajax({
                url: ajaxUrl,
                method: 'POST',
                data: {
                    action: 'anno_add',
                    annotation: JSON.stringify(annotation) 
                },
                dataType: 'json', // Expect JSON response
                success: function(response) {
                    console.log("Annotation created/saved response:", response);
                },
                error: function(xhr, status, error) {
                    console.error("Error saving annotation:", status, error, xhr.responseText);
                }
            });
        });

        anno.on('updateAnnotation', function(annotation) { // V2 event signature
            console.log("Annotation updated. Target Source:", annotation.target.source); // Debug log
            $.ajax({
                url: ajaxUrl,
                method: 'POST',
                data: {
                    action: 'anno_update',
                    annotation: JSON.stringify(annotation),
                    annotationid: annotation.id // 'id' is the Annotorious-generated UUID
                },
                dataType: 'json', // Expect JSON response
                success: function(response) {
                    console.log("Annotation updated response:", response);
                },
                error: function(xhr, status, error) {
                    console.error("Error updating annotation:", status, error, xhr.responseText);
                }
            });
        });

        anno.on('deleteAnnotation', function(annotation) { // V2 event signature
            console.log("Annotation deleted. Target Source:", annotation.target.source); // Debug log
            $.ajax({
                url: ajaxUrl,
                method: 'POST',
                data: {
                    action: 'anno_delete',
                    annotation: JSON.stringify(annotation), // Send the full annotation object
                    annotationid: annotation.id // 'id' is the Annotorious-generated UUID
                },
                dataType: 'json', // Expect JSON response
                success: function(response) {
                    console.log("Annotation deleted response:", response);
                },
                error: function(xhr, status, error) {
                    console.error("Error deleting annotation:", status, error, xhr.responseText);
                }
            });
        });

    } else {
        console.log("No AnnotoriousViewerConfig or images found for OpenSeadragon, or viewer ID missing.");
    }
});