=== Feed Pull ===
Contributors: tlovett1
Donate link: http://www.taylorlovett.com
Tags: feeds, syndication, rss feed, rss, atom feed, feed pull, feed reader
Requires at least: 3.0
Tested up to: 3.9
Stable tag: 0.1.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Automatically pull feed content as posts into your WordPress site. Create and update posts when your feeds change.

== Description ==

This is a simple WordPress plugin that is largely based off of Automattic's Syndication plugin. Easily setup feeds to
be syndicated into WordPress. You can map feed attributes to post fields or post meta. Syndicated content can be mapped
to any post type you want. The plugin comes with a convenient settings page so you can configure things like when feeds
are syndicated.

= Feed Pull vs. Syndication =

Feed Pull takes a lot of concepts from Syndication. Syndication is a more advanced plugin that offers more than just
feed pulling. Here are some differences between the two plugins:

* Feed Pull is a plugin for pulling content out of XML feeds. Syndication has feed pulling as well as content pushing features.
* Feed Pull has a much friendlier feed management screen. Both Syndication and Feed Pull use the WordPress post edit, screen, however Feed Pull offers a much cleaner experience from the way options are presented to the instructions provided. At the moment Feed Pull does not have all the features of Syndication such as taxonomy and constant field mapping. However, Feed Pull probably has everything you need and is very extensible.
* Feed Pull has far superior error logging to Syndication.
* Feed Pull allows you to schedule content pulling in the future.
* Feed Pull allows you to do manual pulls using AJAX rather than a sometimes frustrating one-time cron job.

== Installation ==

1. Upload and activate the plugin.
1. A "Feed Pull" settings page has been added as a sub-item of the "Settings" admin menu.
1. Make sure "Pull feeds" is set to yes. Feeds are syndicated using WordPress cron. This means your content will be
pulled in the background. By default feed pulls occur once per hour.

= Configure a Feed =

1. After activating the plugin, you should now see a "Source Feeds" menu item in your admin menu. Add a new source
feed.
1. Enter the URL of any XML feed in the feed url input box.
1. Enter an XPath to the posts within your feed. This tells the plugin where each piece of content lives in your
feed.  For most RSS feeds, channel/item will do just fine. Here is a bland tutorial if you want to learn more about
XPath: [http://www.w3schools.com/XPath/](http://www.w3schools.com/XPath/)
1. Setup the new content to your liking. The defaults are good to start with.
1. Finally, we need to map fields in the feed to your new posts. Title and GUID are required. Title is
self-explanatory. A GUID is a unique identifier for posts. Items within your feed should have some sort of GUID or
permalink that you can map to the post GUID. GUID's allow the plugin to determine if a piece of content has already
been syndicated or not.

== Changelog ==
= 0.1.1 =
Tiny bug fixes

= 0.1.0 =
Plugin release
