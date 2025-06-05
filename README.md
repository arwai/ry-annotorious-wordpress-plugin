Openseadragon-Annotorious for Wordpress

Integrate the Openseadragon viewer with Annotorious image annotation library directly into your WordPress posts, pages, and/or other custom posts. This plugin allows you to add interactive annotation capabilities to images within your content that is loaded onto an Openseadragon viewer, enabling users to comment on, tag and discuss specific areas of an image.

Description

(Openseadragon-Annotorious for Wordpress) integrates the popular Openseadragon viewer with Annotorious JavaScript library into your WordPress environment. With this plugin, you can transform static images into dynamic, collaborative annotation canvases.

Key Features:

    1.  Create, edit, delete annotations on images with comments, tags.
    2.  Persistent Annotations: Annotations are saved and loaded, ensuring that your collaborative efforts are preserved.
    3.  Annotation edit history are stored in separate custom table.
    4.  Option for Openseadragon viewer to be automatically generated onto post or load onto Gutenberg block (tba)
    5.  Open Source: Built upon the Openseadragon (v5.0.1) and Annotorious (ver. 2.7.27, not v3) library.
    6.  Annotations are linked to image, will be associated even if same image is used in different post.
    7.  Multi-image upload, sortable.
    8.  Openseadragon viewer in sequence mode.
    9.  Option to use first loaded image as Featured Image for the post.
    10. Option to choose which post-type to apply plugin, including custom posts.
    11. Openseadragon configuration options in WP admin area.

Planned Features:
    1.  Create gutenberg blocks for:
        A.  block to display annotations.
        B.  block to display annotation edit history.
        C.  block to add toggle for edit lock.
        D.  block to show/hide annotation on image.
        E.  separate block for Openseadragon Viewer- option for viewer width/height, load configuration options separately?
    2.  Cloudflare R2 storage for images, or other AWS storage. (use separate plugin?)
    3.  Move Openseadragon options onto admin page?
    4.  Link button to open up an Openseadragon viewer with the images that are loaded on the page?
    5.  Gallery view for the loaded images instead of default Openseadragon viewer?
    6.  Link tags or categories on annotorious to wordpress categories.
    7.  Add "creator" to annotations
    8.  Add Annotorious config to admin page


Known Issues:
    1.  after image upload, thumbnail not loading dynamically. need to resfresh page. (low priority)
    2.  annotations not mapping properly proportional onto mobile view. (high priority).
    3.



Installation:

    1.  Download the plugin: You can clone the repository or download the ZIP file from GitHub.
    2.  Upload to WordPress:
        A.  Go to your WordPress admin dashboard.
        B.  Navigate to Plugins > Add New.
        C.  Click on the Upload Plugin button.
        D.  Choose the downloaded ZIP file and click Install Now.
    3.  Activate the plugin: Once installed, click Activate Plugin.

Usage:

XXXXX

Development This plugin is currently under development.

Changelog

1.0.0 (Initial Release)

    Initial integration of Annotorious library to Openseadragon viewer.
    Basic saving and loading of annotations.

Credits

    Author: Arwai
    Annotorious Library: https://annotorious.github.io/
    OpenSeadragon Library: https://openseadragon.github.io/ License This project is licensed under the MIT License - see the LICENSE file for details. (Note: You need to create a LICENSE file in your repository if you haven't already and specify the MIT License content within it.)

