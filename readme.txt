=== File Version Manager ===
Contributors: rileysandborg
Donate link: https://example.com/
Tags: version, file, manager, version manager
Requires at least: 6.6
Tested up to: 6.6
Stable tag: 0.11.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

File Version Manager is a slimmed version of WP Filebase Pro. File Version Manager allows you to manage your file versions without needing to replace every instance of the file on your site.

== Description ==

File Version Manager is a slimmed version of WP Filebase Pro. File Version Manager allows you to manage your file versions without needing to replace every instance of the file on your site.

== Frequently Asked Questions ==

= Do you have a question? =

Reach out to me at riley@sandb.org

== Screenshots ==

1. This screen shot description corresponds to screenshot-1.(png|jpg|jpeg|gif). Screenshots are stored in the /assets directory.
2. This is the second screen shot

== Changelog ==

= 0.11.0 =
* Updated the migration method to include file and category exclusion from the file browser
* Updated toggle template to use the exclusion settings

= 0.10.2 =
* Fixed toggle template not displaying if there were no files in the parent category
* Fix a memory issue if the id was set to 0 in the toggle shortcode

= 0.10.1 =
* Added a new template: Toggle

= 0.10.0 =
* Added a new table to handle file-category relationships
* Updated edit file UI to reflect changes

= 0.9.14 =
* Fixed an issue where a migration would cause an error if there were more than 3 files with the same name.

= 0.9.7 =
* Added prepared statements to all database queries to prevent SQL injection