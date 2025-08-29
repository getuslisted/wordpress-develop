=== Live Color Editor ===
Contributors: jules
Tags: color, editor, live editor, css, style, hex, rgb
Requires at least: 5.0
Tested up to: 6.0
Stable tag: 1.0.0
Requires PHP: 7.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A plugin to find and replace colors on your WordPress site live.

== Description ==

Live Color Editor is a powerful tool for developers and site administrators who want to quickly test or make color changes across their entire WordPress site.

This plugin allows you to:
*   **Find & Replace Colors**: The core of the plugin allows you to specify a color (e.g., #435342) and replace it with a new color everywhere it appears on your site.
*   **Live Frontend Editing**: A collapsible overlay is available for logged-in administrators on the frontend, allowing you to change colors and see the results instantly after a refresh.
*   **Admin Settings Page**: A comprehensive settings page in the WordPress dashboard to manage all your color mappings.
*   **Auto-Scan for Colors**: Don't know the exact hex code? Use the "Scan" feature to automatically find all colors on your homepage and add them to the editor.
*   **Track Your Changes**: An activity log records every change you make, showing who made the change and when.
*   **Undo with Confidence**: Made a mistake? The activity log allows you to undo any change with a single click.

== Installation ==

1. Upload the `live-color-editor` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to the "Live Color Editor" menu in your WordPress dashboard to start adding color mappings.
4. To use the live editor, log in as an administrator and visit your site's frontend. An editor toggle will appear on the right side of the screen.

== Frequently Asked Questions ==

= Does this plugin change my theme files or database content? =

No. The plugin works by filtering the final HTML of your site just before it's displayed. It does not modify your theme files or your post/page content directly. The only data it saves to your database is the list of color mappings you define and the activity log.

= Will this slow down my site? =

The plugin is designed to be lightweight. The color replacement is done with a fast string replacement function. For sites with a very large number of replacements, some minimal performance impact is possible, but it should be negligible for most use cases.

== Changelog ==

= 1.0.0 =
* Initial release.
