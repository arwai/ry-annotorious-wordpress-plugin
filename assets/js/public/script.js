// ry-annotorious/assets/js/public/script.js

console.log("ry-annotorious-public-js loaded.");

jQuery(document).ready(function($) {

    // Check if AnnotoriousViewerConfig is available and has images
    if (typeof AnnotoriousViewerConfig !== 'undefined' && AnnotoriousViewerConfig.images && AnnotoriousViewerConfig.images.length > 0) {
        console.log("Initializing OpenSeadragon and Annotorious...");

        const viewerId = AnnotoriousViewerConfig.id;
        const images = AnnotoriousViewerConfig.images;
        const currentPostId = AnnotoriousViewerConfig.currentPostId;
        const ajaxUrl = AnnotoriousVars.ajax_url;

        console.log("Viewer ID:", viewerId);
        console.log("Images for OSD:", images);
        console.log("Current Post ID:", currentPostId);
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
            tileSources: images.map(img => ({
                type: img.type,
                url: img.url
                // Add additional OSD tile source properties if needed (e.g., width, height, tileSize for DZI)
            }))
        });

        console.log("OpenSeadragon viewer object created:", osdViewer);

        osdViewer.addHandler('open', function() {
            console.log("OpenSeadragon 'open' event fired: Image(s) loaded into viewer.");
            
            // Log the URL of the currently opened image in OSD
            let currentImageOsdUrl = '';
            const currentPage = osdViewer.currentPage(); // Get current page index
            const item = osdViewer.world.getItemAt(currentPage); // Get current item object

            if (item && item.source && item.source.url) { // For multi-page sequence
                currentImageOsdUrl = item.source.url;
                console.log(`OSD Viewer Current Image URL (Page ${currentPage}):`, currentImageOsdUrl);
            } else if (osdViewer.source && osdViewer.source.url) { // Fallback for non-sequence or single image
                currentImageOsdUrl = osdViewer.source.url;
                console.log("OSD Viewer Current Image URL (Single Image):", currentImageOsdUrl);
            } else {
                console.warn("Could not determine OSD Viewer Current Image URL.");
            }

            // --- Check Annotorious's perceived current image source (THIS IS THE CODE BEFORE V3 UPGRADE) ---
            if (anno.getCurrentImageSource) { 
                const currentAnnoSource = anno.getCurrentImageSource(); 
                console.log("Annotorious's perceived current image source (anno.getCurrentImageSource()):", currentAnnoSource);
                
                // CRITICAL COMPARISON LOG
                if (currentImageOsdUrl && currentAnnoSource && currentImageOsdUrl !== currentAnnoSource) {
                    console.error("URL MISMATCH DETECTED between OSD and Annotorious:", { OSD: currentImageOsdUrl, Annotorious: currentAnnoSource });
                } else if (currentImageOsdUrl && currentAnnoSource) {
                    console.log("URL MATCH: OSD and Annotorious sources match.");
                }
            } else {
                console.warn("Annotorious.getCurrentImageSource() method not available (check plugin version/installation).");
            }
        });
        osdViewer.addHandler('open-failed', function(event) {
            console.error("OpenSeadragon 'open-failed' event fired: Failed to load image(s). Event data:", event);
        });

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
        function loadAnnotationsFromBackend() {
            console.log("Attempting AJAX load of annotations (direct call).");
            $.ajax({
                url: ajaxUrl,
                data: {
                    action: 'anno_get',
                    post_id: currentPostId 
                },
                dataType: 'json',
                success: function(annotations) {
                    console.log("Loaded annotations:", annotations);
                    if (Array.isArray(annotations)) {
                       anno.setAnnotations(annotations);
                    } else {
                       console.warn("Loaded annotations are not an array:", annotations);
                       anno.setAnnotations([]); // Clear existing if format is wrong
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Error loading annotations via AJAX:", status, error, xhr.responseText);
                }
            });
        }

        // CRITICAL: Call the loading function directly after Annotorious is initialized
        loadAnnotationsFromBackend();

        // Keep the 'ready' event handler as it's standard
        anno.on('ready', function() {
            console.log("Annotorious 'ready' event fired. (This is a fallback/delayed event.)");
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