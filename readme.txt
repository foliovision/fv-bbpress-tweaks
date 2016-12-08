=== FV bbPress Tweaks ===
Contributors: FolioVision
Tags: comments, spam
Requires at least: 3.0.1
Tested up to: 3.5.1
Stable tag: 4.3
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Adds moderation and pretty URL structure to your bbPress forums.

== Description ==

Features:

* Guest posting - any forum posts posted with a known email address get associated to the WP user, any posts with a new email address get a new user account. Enable in Tools -> FV BBPress Tweaks.
* Forum moderation - any new forum posts or topics get into moderation. Only admins and the original posters can see the pending posts. Enable in Settings -> FV bbPress Tweaks.
* Nicer permalinks - get rid of /support/topic/{topic-slug} /support/forum/{forum-name} and start using hierarchical /support/{forum-name}/{sub-forum-name}/{topic-slug} structure!

Known bugs:

* You can open the topic editing from front-end for pending topics
* When you delete a reply, the pending replies no longer appear, just open the topic again with its original URL

== Installation ==

The plugin installs just like any other WordPress plugin. Check Tools -> FV BBPress Tweaks and Settings -> FV bbPress Tweaks.

== Screenshots ==

No screenshots yet.

== Changelog ==

= 0.2.8 =

* Accessing a pending topic now also shows login form or no access message if the user is not permitted to access it
* Fix for showing pending replies in topic view after some reply was deleted (?view=all)
* Partial fix for redirection from ?p={reply id}
* Adding notification and fixing error when bbPress plugin is turned off
* Added option for posting comments directly into Forums

= 0.2.7 =

* First public release
* Akismet - disabling for logged in users
* Fix when bbPress is inactive
* Fix for rewrite rules going wrong
* Hiding reply and topic edit log for guests
* Setting to check "Notify me of follow-up replies via email" by default
* Fixed avatars URLs for not logged in users

= 0.2.6 =

* Closed topics now open properly
* Support for original forum/topic URLs fixed
* Removing annyoing little gravatars

= 0.2.4.1 =

* Removing core "Stick (To Front)" and "Spam" topic and reply moderation links.
* Removing plugin's "Approve all by author" topic and reply moderation link

= 0.2.4 =

* Fixes for topic and reply editing and trashing.

= 0.2.3 =

* Fix for reply edit and merge links - not using our custom URLs there for better compatibility

= 0.2.2 =

* "Limit guest access" now hides the user profile (redirects to forum homepage)

= 0.2.1 =

* Fixing cooperation with bbPress FV Antispam module.

= 0.2 =

* Added debug log into wp_mail-{code}.log
* Adding setting for forum from address
* Automatically subscribing guest users to topics which they post or post into
* Fix for displaying of pending topic to its poster after posted
* Fix for replies to pending topics to trigger notifications
* Fix for reply links
* Fix for wp-admin reply list
* Improving notificaiton subject and content
* Removing the note about reply being published to its author
* Sending all new topics and replies to a set address, not just pending

= 0.1 =
* First attempt
