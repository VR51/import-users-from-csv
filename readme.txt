=== Import Users from CSV ===
Contributors: sorich87, andrewza, vr51
Tags: user, users, csv, batch, import, importer, admin
Requires at least: 3.1
Requires PHP: 5.6
Tested up to: 5.8
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html


Import users from a CSV into WordPress

== Description ==

This plugin allows you to import users from an uploaded CSV file. It will add users with basic information as well as meta fields and user role.

You can also choose to send a notification to the new users and to display password nag on user login.

This is the enahnced forked version downloaded from https://github.com/VR51/import-users-from-csv

[Check out my other free plugins.](https://profiles.wordpress.org/users/andrewza/)

= Features =

* Imports all users fields
* Imports user meta
* Update existing users by specifying ID field
* Allows setting user role
* Sends new user notification (if the option is selected)
* Shows password nag on user login (if the option is selected)

For feature request and bug reports, [please use the forums](https://wordpress.org/support/plugin/import-users-from-csv).
Code contributions are welcome [on Github](https://github.com/andrewlimaza/import-users-from-csv).

WARNING: When used with PMPro and LearnDash, do not set a PMPro membership restriction for a course unless you want manually enrolled course participants who are not in the selected PMPro membership group(s) to be automatically purged from their LearnDash course when CSV imports run. I (Lee/VR51) have raised a ticket with PMPro about this bug/feature of PMPro's LearnDash integration. This issue is present in all versions of Import Users from CSV and probably all user import plugins/options.

== Installation ==

For an automatic installation through WordPress:

1. Go to the 'Add New' plugins screen in your WordPress admin area
1. Search for 'Import Users from CSV'
1. Click 'Install Now' and activate the plugin
1. Upload your CSV file in the 'Users' menu, under 'Import From CSV'


For a manual installation via FTP:

1. Upload the `import-users-from-csv` directory to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' screen in your WordPress admin area
1. Upload your CSV file in the 'Users' menu, under 'Import From CSV'


To upload the plugin through WordPress, instead of FTP:

1. Upload the downloaded zip file on the 'Add New' plugins screen (see the 'Upload' tab) in your WordPress admin area and activate.
1. Upload your CSV file in the 'Users' menu, under 'Import From CSV'

== Frequently Asked Questions ==

= How to use? =

Click on the 'Import From CSV' link in the 'Users' menu, choose your CSV file, choose if you want to send a notification email to new users and if you want the password nag to be displayed when they login, then click 'Import'.

Each row in your CSV file should represent a user; each column identifies user data or meta data.
If a column name matches a field in the user table, data from this column is imported in that field; if not, data is imported in a user meta field with the name of the column.

Look at the example.csv file in the plugin directory to have a better understanding of how the your CSV file should be organized.
You can try importing that file and look at the result.

= Credits =
Thanks to Ulrich Sossou for initially creating this plugin. Be sure to [check out his other WordPress plugins](https://profiles.wordpress.org/sorich87/) or [GitHub profile](https://github.com/sorich87).

== Screenshots ==

1. User import screen

== Changelog ==

= 1.0.4.1 =
* Minor tweak: set the site address as the HTTP referrer. May prevent requests being blocked by 3rd party servers.

= 1.0.4 =
* Separated 'Update user if username or email exists' into 2 options: Update if username exists, Update if email exists.
* Added option to record success messages when users are imported successfully. Messages are stored in the Errorlog (to be renamed)
* Improved admin settings page: Fixed error in WP notice display; Added verbiage to explain prioritsation order for imported user matching against existing users: ID, username then email address.
* Added Reschedule button to the import scheduler
* Added Run Now button to the import scheduler
* Added server time information to the admin page. Displays after Export Users link.
* Hinted at above: Added beta user export button. Presently this is a very basic feature that is in its embryonic stages.
* Security: Added test to admin page init. Only WP users with Manage Options capabilities can see the import admin page. Will switch this to test for current user is admin.

= 1.0.3.1 =
* Fixed bug in import scheduler

= 1.0.3 =
* Added log file deletion button.
* Added import last run timestamps.
* Adjusted texts.

= 1.0.2 =
* Added remote file import scheduler.

= 1.0.1.1 =
* Added link to read the Error Log to admin settings page
* Set option 'Update user when a username or email exists' to pre-checked
* TO DO: Add option to schedule imports. See https://gist.github.com/VR51/bd90a1dabfa32a90a122ff8760fd0fd3

= 1.0.1 =
* Fixed timeout bug on import.
* Improved settings area layout.
* General code refactor and improved security.
* Screenshot update.

= 1.0.0 =
* Fixed bug where importing fields with "0" value doesn't work
* Added option to update existing users by username or email

= 0.5.1 =
* Removed example plugin file to avoid invalid header error on
installation

= 0.5 =
* Changed code to allow running import from another plugin

= 0.4 =
* Switched to RFC 4180 compliant library for CSV parsing
* Introduced IS_IU_CSV_DELIMITER constant to allow changing the CSV delimiter
* Improved memory usage by reading the CSV file line by line
* Fixed bug where any serialized CSV column content is serialized again
on import

= 0.3.2 =
* Fixed php notice when importing

= 0.3.1 =
* Don't process empty columns in the csv file

= 0.3 =
* Fixed bug where password field was overwritten for existing users
* Use fgetcsv instead of str_getcsv
* Don't run insert or update user function when only user ID was
provided (performance improvement)
* Internationalization
* Added display name to example csv file

= 0.2.2 =
* Added role to example file
* Fixed bug with users not imported when no user meta is set

= 0.2.1 =
* Added missing example file
* Fixed bug with redirection after csv processing
* Fixed error logging
* Fixed typos in documentation
* Other bug fixes

= 0.2 =
* First public release.
* Code cleanup.
* Added readme.txt.

= 0.1 =
* First release.

== Upgrade Notice ==

= 1.0.1 =
* Security and performance improvements.

= 0.5.1 =
* Installation error fix.

= 0.5 =
* Code improvement for easier integration with another plugin.

= 0.4 =
* RFC 4180 compliance, performance improvement and bug fix.

= 0.3 =
* Bug fix, performance improvement and internationalization.

= 0.2.2 =
* Fix bug with users import when no user meta is set.

= 0.2.1 =
* Various bug fixes and documentation improvements.

= 0.2 =
* Code cleanup. Added readme.txt.

= 0.1 =
* First release.
