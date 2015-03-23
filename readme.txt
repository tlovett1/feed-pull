=== Feed Pull ===
Contributors: tlovett1, 10up
Donate link: http://www.taylorlovett.com
Tags: feeds, curation tool, syndication, rss feed, rss, atom feed, feed pull, feed reader, xml
Requires at least: 3.0
Tested up to: 4.2
Stable tag: 0.2.5
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

Fork the plugin on [Github](http://github.com/tlovett1/feed-pull)

== Installation ==

1. Upload and activate the plugin.
1. A "Feed Pull" settings page has been added as a sub-item of the "Settings" admin menu.
1. Make sure "Pull feeds" is set to yes. Feeds are syndicated using WordPress cron. This means your content will be
pulled in the background. By default feed pulls occur once per hour.

= Configure a Feed =

Super awesome configuration instructions are available on [Github](http://github.com/tlovett1/feed-pull).

== Changelog ==

= 0.2.5 =
* Use {{ }} style Backbone templates in case of ASP style PHP tags
* Fix some minor preview action css
* Clean up commenting

= 0.2.4 =
* Add action after handling a post for additional handling of post meta. Props [sc0ttclark](https://github.com/sc0ttkclark).

= 0.2.3 =
* .pot file for translation.

= 0.2.2 =
* Fix category tagging issue where syndicated posts were tagged as Uncategorized even if other categories were chosen.

= 0.2.0 =
* Properly update posts - fixes multiple bugs
* Manual option for clearing deleted post cache

= 0.1.6 =
* Taxonomy field mapping
* Don't repull deleted posts

= 0.1.5 =
Custom namespacing
* Unit tests
* Random bug fixes

= 0.1.4 =
* Fix post guid lookup

= 0.1.3 =
* Tiny bug fixes

= 0.1.2 =
* Tiny bug fixes
* Refresh logging meta box on single pull

= 0.1.1 =
* Tiny bug fixes

= 0.1.0 =
* Plugin release
