console.log("admin.js file has loaded and is executing."); // Verification log
window.addEventListener('load', function() {
    console.log("Window load event fired.");
});

jQuery(document).ready(function($) {

    var media_frame; // This will hold the WordPress media frame instance
    var $imageContainer = $('#ry-annotorious-images-container');
    var $imageList = $imageContainer.find('.ry-annotorious-image-list');
    var $hiddenField = $('#ry_annotorious_image_ids_field');

    // Function to render an individual image item in the list
    function renderImageItem(id, url) {
        var html = '<li data-id="' + id + '">';
        html += '<img src="' + url + '" style="max-width:100px; max-height:100px; display:block;" />';
        html += '<a href="#" class="ry-annotorious-remove-image dashicons dashicons-trash" title="Remove image"></a>';
        html += '</li>';
        return html;
    }

    // Opens the WordPress media uploader for multiple selections
    $imageContainer.on('click', '.ry-annotorious-add-images-button', function(event) {
        event.preventDefault();

        console.log("Add/Select Images button clicked!"); // Click verification log

        // If the media frame already exists, reopen it.
        if (media_frame) {
          media_frame.open();
          return;
        }

        // Create the media frame.
        media_frame = wp.media.frames.file_frame = wp.media({
          title: $(this).text(), // Use button text as title
          button: {
            text: 'Select Images'
          },
          multiple: true // Allow multiple image selection
        });

        // When images are selected, run a callback.
        media_frame.on( 'select', function() {
            var selection = media_frame.state().get('selection');
            var current_ids = JSON.parse($hiddenField.val() || '[]'); // Get existing IDs from hidden field

            selection.each(function(attachment) {
                // Add new attachments to the list and to the array, avoiding duplicates
                var id = attachment.id;
                // Check if ID already exists in current_ids array
                if ($.inArray(id, current_ids) === -1) {
                    current_ids.push(id);
                    $imageList.append(renderImageItem(id, attachment.url));
                }
            });

            // Update the hidden field with the new set of IDs as a JSON string
            $hiddenField.val(JSON.stringify(current_ids));
        });

        // Finally, open the modal
        media_frame.open();
    });

    // Remove image from the list
    $imageContainer.on('click', '.ry-annotorious-remove-image', function(event) {
        event.preventDefault();
        var $listItem = $(this).closest('li');
        var removedId = $listItem.data('id');

        // Remove from current_ids array
        var current_ids = JSON.parse($hiddenField.val() || '[]');
        current_ids = $.grep(current_ids, function(value) {
            return value != removedId; // Filter out the removed ID
        });

        // Update hidden field and remove from DOM
        $hiddenField.val(JSON.stringify(current_ids));
        $listItem.remove();
    });

});