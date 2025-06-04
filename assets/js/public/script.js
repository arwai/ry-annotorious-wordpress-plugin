// ry-annotorious/assets/js/public/script.js

console.log("ry-annotorious-public-js loaded.");

jQuery(document).ready(function($) {

    // Check if AnnotoriousViewerConfig is available and has images
    if (typeof AnnotoriousViewerConfig !== 'undefined' && AnnotoriousViewerConfig.images && AnnotoriousViewerConfig.images.length > 0) {
        console.log("Initializing OpenSeadragon and Annotorious...");

        const viewerId = AnnotoriousViewerConfig.id;
        const images = AnnotoriousViewerConfig.images; // This array already contains 'post_id' (attachment ID)
        const currentPostId = AnnotoriousViewerConfig.currentPostId; // This is the main post ID, not the attachment ID for current OSD image
        const ajaxUrl = AnnotoriousVars.ajax_url;

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
            return; // Stop script execution
        } else {
            console.log("OpenSeadragon library detected.");
        }

        // OpenSeadragon Configuration
        const osdViewer = OpenSeadragon({
            id: viewerId,
            prefixUrl: "https://openseadragon.github.io/openseadragon/images/", // Path to OpenSeadragon icons
            sequenceMode: true, // Enable viewing multiple images in a sequence
            showSequenceControl: true,
            showReferenceStrip: true,
            gestureSettingsMouse: {
                clickToZoom: false, // Enable click to zoom
                dblClickToZoom: true, // Enable double-click to zoom
                pinchToZoom: true, // Enable pinch to zoom on touch devices
                scrollToZoom: true,
                flickEnabled: true // Enable flicking to navigate through images
             }, // Enable scroll wheel zoom}
             showRotationControl: true, // Show rotation control
             navPrevNextWrap: true, // Enable wrapping for next/previous navigation
            tileSources: images.map(img => ({
                type: img.type,
                url: img.url
                // OpenSeadragon doesn't directly need 'post_id' here, but we'll use it from the 'images' array.
            }))
        });
        console.log("OpenSeadragon viewer object created:", osdViewer);

        // --- Check if Annotorious is available globally for v2 init ---
        if (typeof OpenSeadragon.Annotorious === 'undefined') { // V2 check
             console.error("Annotorious plugin for OpenSeadragon is not loaded or not available globally.");
             return; // Stop script execution
        } else {
            console.log("OpenSeadragon.Annotorious plugin detected.");
        }

        // Initialize Annotorious on the OpenSeadragon viewer (Annotorious v2 initialization)
        const anno = OpenSeadragon.Annotorious(osdViewer); // V2 init
        console.log("Annotorious initialized on OpenSeadragon (v2).");

        // --- Function to load annotations via AJAX ---
        // MODIFIED: This function now accepts the specific image's attachment ID
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
                currentImageAttachmentId = images[currentPage].post_id;
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
                newImageAttachmentId = images[event.page].post_id;
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
                success: function(response) {
                    console.log("Annotation created/saved:", response);
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
                    annotationid: annotation.id
                },
                success: function(response) {
                    console.log("Annotation updated:", response);
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
                    annotation: JSON.stringify(annotation),
                    annotationid: annotation.id
                },
                success: function(response) {
                    console.log("Annotation deleted:", response);
                },
                error: function(xhr, status, error) {
                    console.error("Error deleting annotation:", status, error, xhr.responseText);
                }
            });
        });

    } else {
        console.log("No AnnotoriousViewerConfig or images found for OpenSeadragon.");
    }
});