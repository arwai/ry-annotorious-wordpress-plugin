jQuery(document).ready(function($) {

    console.log("admin.js file has loaded and is executing."); // Verification log

    // MODIFIED: Corrected selector to match the PHP output for the main container
    var $imageCollectionContainer = $('#ry-annotorious-images-collection-container');

    // If the corrected container isn't found, log an error and exit if necessary.
    if (!$imageCollectionContainer.length) {
        console.error("Image collection container ('#ry-annotorious-images-collection-container') not found. Admin JS for image metabox will not fully initialize.");
        // Depending on other JS, you might want to return here if this container is essential for everything below.
        // For now, we'll allow other parts of the script to try and run if they don't depend on this specific container.
    }

    // These selectors rely on $imageCollectionContainer being correct.
    var $imageList = $imageCollectionContainer.find('.ry-annotorious-image-list');
    var $hiddenField = $('#ry_annotorious_image_ids_field'); // This ID is from PHP, assumed correct.
    var media_frame;

    // Function to render an individual image item in the list
    function renderImageItem(id, thumbnailUrl) { // Changed 'url' to 'thumbnailUrl' for clarity
        var html = '<li data-id="' + parseInt(id, 10) + '">'; // Ensure id is a number
        html += '<img src="' + thumbnailUrl + '" style="max-width:100px; max-height:100px; display:block;" />';
        html += '<a href="#" class="ry-annotorious-remove-image dashicons dashicons-trash" title="Remove image"></a>';
        html += '</li>';
        return html;
    }

    // NEW: Function to update the hidden field after sorting
    function updateImageIdsAfterSort() {
        var imageIds = [];
        if ($imageList.length) { // Ensure image list exists
            $imageList.find('li').each(function() {
                imageIds.push(parseInt($(this).data('id'), 10)); // Ensure IDs are numbers
            });
        }
        $hiddenField.val(JSON.stringify(imageIds)).trigger('change'); // Trigger change for WP dirty forms
        console.log("Hidden field updated after sort:", $hiddenField.val());
    }

    // Initialize jQuery UI Sortable if the image list exists
    if ($imageList.length) {
        $imageList.sortable({
            placeholder: "ry-annotorious-image-placeholder", // CSS class for the placeholder
            opacity: 0.7,
            cursor: 'move',
            stop: function(event, ui) {
                updateImageIdsAfterSort(); // Update the hidden field when sorting stops
            }
        }).disableSelection(); // Optional: to prevent text selection during drag
        console.log("Sortable initialized on .ry-annotorious-image-list");
    } else if ($imageCollectionContainer.length) { // Only log error if main container was found but list wasn't
        console.warn(".ry-annotorious-image-list not found within the collection container. Sortable not initialized.");
    }

    // Opens the WordPress media uploader for multiple selections
    // Ensure this event delegation is on a static parent if $imageCollectionContainer itself might not exist initially
    // For now, assuming $imageCollectionContainer exists if we reach here due to the check above.
    $imageCollectionContainer.on('click', '.ry-annotorious-add-images-button', function(event) {
        event.preventDefault();
        console.log("Add/Select Images button clicked!");

        if (media_frame) {
          media_frame.open();
          return;
        }

        media_frame = wp.media.frames.file_frame = wp.media({
          title: $(this).text(),
          button: {
            text: 'Select Images'
          },
          multiple: true
        });

        media_frame.on( 'select', function() {
            var selection = media_frame.state().get('selection');
            var current_ids = [];
            try {
                // Get existing IDs from hidden field to check for duplicates
                var fieldValue = $hiddenField.val();
                if (fieldValue) {
                    current_ids = JSON.parse(fieldValue);
                }
                if (!Array.isArray(current_ids)) { // Ensure it's an array
                    current_ids = [];
                }
            } catch (e) {
                console.error("Error parsing current_ids from hidden field:", e);
                current_ids = []; // Fallback to empty array on error
            }


            selection.each(function(attachment_obj) {
                var attachment = attachment_obj.toJSON(); // Get all attributes
                var id = parseInt(attachment.id, 10); // Ensure ID is a number

                // MODIFIED: Prefer thumbnail URL for the preview
                var thumbnailUrl = attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;

                // Check if ID already exists in current_ids array (parsed from hidden field)
                if ($.inArray(id, current_ids) === -1) {
                    current_ids.push(id); // Add to our JS array
                    if ($imageList.length) {
                        $imageList.append(renderImageItem(id, thumbnailUrl)); // Append to DOM
                    }
                } else {
                    console.log("Image ID " + id + " already in list. Skipping.");
                }
            });

            // Update the hidden field with the new set of IDs as a JSON string
            $hiddenField.val(JSON.stringify(current_ids)).trigger('change');
            console.log("Hidden field updated after add:", $hiddenField.val());
        });

        media_frame.open();
    });

    // Remove image from the list
    $imageCollectionContainer.on('click', '.ry-annotorious-remove-image', function(event) {
        event.preventDefault();
        var $listItem = $(this).closest('li');
        var removedId = parseInt($listItem.data('id'), 10); // Ensure ID is a number

        // Remove from current_ids array
        var current_ids = [];
        try {
            var fieldValue = $hiddenField.val();
            if (fieldValue) {
                current_ids = JSON.parse(fieldValue);
            }
             if (!Array.isArray(current_ids)) {
                current_ids = [];
            }
        } catch (e) {
            console.error("Error parsing current_ids from hidden field on remove:", e);
            current_ids = [];
        }

        // Filter out the removed ID
        current_ids = $.grep(current_ids, function(value) {
            return value !== removedId;
        });

        // Update hidden field and remove from DOM
        $hiddenField.val(JSON.stringify(current_ids)).trigger('change');
        $listItem.remove();
        console.log("Hidden field updated after remove:", $hiddenField.val());
    });

    console.log("Event handlers for add/remove images attached if container exists.");
});