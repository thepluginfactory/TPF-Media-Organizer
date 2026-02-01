=== TPF Media Organizer ===
Contributors: thepluginfactory
Tags: media library, folders, media folders, organize media, media management
Requires at least: 5.8
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Organize your WordPress media library into folders for easier management. Create virtual folders, drag-and-drop organization, and filter media by folder.

== Description ==

**TPF Media Organizer** brings folder organization to your WordPress media library. Say goodbye to endless scrolling through a cluttered media library!

= Features =

* **Virtual Folders** - Create unlimited folders without moving actual files
* **Hierarchical Structure** - Nest folders within folders for better organization
* **Drag & Drop** - Simply drag media items into folders
* **Bulk Operations** - Move multiple files at once using bulk actions
* **Media Modal Integration** - Filter by folder when inserting media into posts
* **Folder Counts** - See how many items are in each folder at a glance
* **List View Column** - View and manage folder assignments in list view
* **Right-Click Menu** - Rename or delete folders with a right-click

= Use Cases =

* **Blog Media** - Organize images by blog post category or topic
* **Product Images** - Keep product photos organized by product line
* **Client Files** - Separate media by client or project
* **Document Types** - Create folders for documents, images, videos, etc.
* **Date-Based Organization** - Create year/month folders if you prefer

= How It Works =

TPF Media Organizer uses WordPress taxonomies to create virtual folder assignments. Your actual media files stay in their original locations (wp-content/uploads), so:

* Existing URLs remain intact
* No risk of broken images
* Works with any existing media
* Can be disabled without losing files

= More Plugins from The Plugin Factory =

Discover our other quality WordPress plugins:

* **TPF Starter Slider** - Beautiful, responsive sliders
* **FB Call Now** - Floating call button for mobile
* **FB Message Now** - Contact form popup
* **FB Download Now** - Gated file downloads
* And more!

Visit [thepluginfactory.com](https://thepluginfactory.com) for our complete collection.

== Installation ==

1. Upload the `tpf-media-organizer` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Media > Library to start organizing your media into folders
4. Configure settings under Media Organizer > Settings

== Frequently Asked Questions ==

= Will this move my actual media files? =

No. TPF Media Organizer creates virtual folder assignments using WordPress taxonomies. Your files stay in their original locations and all existing URLs continue to work.

= Can I have nested folders? =

Yes! You can create folders within folders for hierarchical organization. Just right-click on a folder and select "New Folder" to add a subfolder.

= What happens to my media if I deactivate the plugin? =

Nothing. Your media files remain untouched. Only the folder assignments will no longer be visible. If you reactivate the plugin, your folders and assignments will still be there.

= Does this work with the block editor? =

Yes! When you insert media using the block editor's media modal, you can filter by folder to quickly find the image you need.

= Can I assign media to multiple folders? =

Currently, each media item can be in one folder at a time. We may add multi-folder support in a future version.

= Is this compatible with other media library plugins? =

TPF Media Organizer should work alongside most other plugins. If you experience any conflicts, please let us know in the support forum.

== Screenshots ==

1. Media Library with folder sidebar
2. Drag and drop media into folders
3. Right-click context menu for folder management
4. Folder filter in media modal
5. Settings page
6. Bulk actions dropdown with folder options

== Changelog ==

= 1.0.4 =
* Fixed folder counts not updating when items are assigned to folders
* Force WordPress to recount terms after assignment

= 1.0.3 =
* Fixed folder filtering in grid view - items now properly filter when clicking folders
* Fixed drag-and-drop not updating folder counts correctly
* Grid view now uses AJAX filtering instead of page reload

= 1.0.2 =
* Fixed "invalid taxonomy" error when creating folders

= 1.0.1 =
* Fixed sidebar overlapping media content
* Fixed uncategorized count showing 0 when all media is uncategorized

= 1.0.0 =
* Initial release
* Virtual folder organization for media library
* Drag and drop support
* Hierarchical folder structure
* Media modal folder filter
* Bulk actions for folder assignment
* Right-click context menu
* Settings page for customization

== Upgrade Notice ==

= 1.0.0 =
Initial release of TPF Media Organizer.
