=== NodeThemes Image Optimizer ===
Contributors: nodethemes
Tags: webp, image optimization, compress images, convert images, performance
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.4.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Convert JPEG and PNG images to lightweight WebP with one click from your Media Library, on your own server. No API keys or limits.

== Description ==

NodeThemes Image Optimizer makes your website faster by converting images to modern WebP format, entirely on your own server. No API keys, no monthly limits, no third-party services.

**Features**

**Free**

* One-click WebP conversion from the Media Library (JPEG and PNG), up to 50 images in the free version
* Originals are kept; WebP copies are served automatically on your public pages
* Adjustable WebP quality (10-100)
* Per-image savings shown in the Media Library
* Works with GD (WebP) or Imagick

**Pro version (separate add-on, optional)**

A separate Pro add-on is available from [nodethemes.com](https://nodethemes.com/) with automatic optimization of new uploads, bulk optimization of your existing Media Library, replace mode, and maximum width/height and file size limits. The plugin on WordPress.org is fully functional on its own and never downloads or runs code from external servers.

Designed and developed by [NodeThemes](https://nodethemes.com/).

== Installation ==

1. Upload the plugin folder to /wp-content/plugins/ or install the ZIP from Plugins > Add New > Upload Plugin.
2. Activate the plugin.
3. Go to Media > NodeThemes Optimizer and choose your settings.
4. To optimize an image, open Media > Library (list view) and click "Optimize" next to it, or use the button in the image details.

== Frequently Asked Questions ==

= Does it need an external service? =
No. Everything runs on your server using WordPress's built-in image editors.

= Which mode should I choose? =
"Replace with WebP" saves the most disk space. "Keep originals" is non-destructive and safest with page builders.

= Always back up before bulk-optimizing in Replace mode. =
Replace mode deletes the original files after a successful conversion.

== Changelog ==

= 1.4.1 =
* Updates are now delivered from nodethemes.com: new versions appear in Dashboard → Updates and can be installed with one click or with auto-updates.

= 1.4.0 =
* The free version can now optimize up to 50 images manually. Unlimited optimization is part of the Pro add-on.
* New "Free version usage" card with a progress bar.

= 1.3.0 =
* Images are now optimized manually from the Media Library; automatic optimization of new uploads is part of the Pro add-on.

= 1.2.0 =
* Removed license activation and remote Pro module download from the WordPress.org version.
* Pro features are now offered as a separate add-on.
* Code-standard fixes (wp_delete_file, plugin header, readme).

= 1.1.0 =
* Keep-originals mode is now the default.

= 1.0.0 =
* Initial release.
