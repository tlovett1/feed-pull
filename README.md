Feed Pull [![Dockunit Status](https://dockunit.io/svg/tlovett1/feed-pull?master)](https://dockunit.io/projects/tlovett1/feed-pull#master)
==============

This is a simple WordPress plugin that is largely based off of Automattic's Syndication plugin. Easily setup feeds to
be syndicated into WordPress. You can map feed attributes to post fields or post meta. Syndicated content can be mapped
to any post type you want. The plugin comes with a convenient settings page so you can configure things like when feeds
are syndicated.

## Feed Pull vs. Syndication

Feed Pull takes a lot of concepts from Syndication. Syndication is a more advanced plugin that offers more than just
feed pulling. Here are some differences between the two plugins
* Feed Pull is a plugin for pulling content out of XML feeds. Syndication has feed pulling as well as content
pushing features
* Feed Pull has a much friendlier feed management screen. Both Syndication and Feed Pull use the WordPress post edit,
screen, however Feed Pull offers a much cleaner experience from the way options are presented to the instructions
provided. At the moment Feed Pull does not have all the features of Syndication such as taxonomy and constant
field mapping. However, Feed Pull probably has everything you need and is very extensible.
* Feed Pull has far superior error logging to Syndication.
* Feed Pull allows you to schedule content pulling in the future.
* Feed Pull allows you to do manual pulls using AJAX rather than a sometimes frustrating one-time cron job.

## Setup Instructions

1. Upload and activate the plugin.
1. A "Feed Pull" settings page has been added as a sub-item of the "Settings" admin menu.
1. Make sure "Pull feeds" is set to yes. Feeds are syndicated using WordPress cron. This means your content will be
pulled in the background. By default feed pulls occur once per hour.

## Configure a Feed

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
been syndicated or not. There is an in-depth section on field mapping below.

## Atom Feeds and Custom Namespaces

Sometimes feeds make use of prefixes on certain elements. This happens for a variety of reasons; one is to avoid
conflicts. Prefixes should have a namespace defined somewhere using an "xmlns" attribute. Feed Pull lets you
define custom namespaces. This feature is advanced and not needed in most cases.

However, certain feeds, like the Atom feeds outputted by WordPress define a document level namespace without a
prefix. This is totally fine except it makes XPath queries difficult. Feed Pull should automatically detect if
this situation is happening and create a namespace with the prefix "default" and the url "http://www.w3.org/2005/Atom".
You should use the prefix in your XPath queries. For example, instead of "//feed/entry", your query should be
"//default:feed/default:entry". Instead of "title" in your field map, your query should probably be "default:title".

You can learn more about namespaces here: [http://www.w3schools.com/xml/xml_namespaces.asp](http://www.w3schools.com/xml/xml_namespaces.asp)

## Field Mapping

When configuring a source feed, you need to tell Feed Pull which XML nodes map to where within WordPress. Here are the current
mapping types supported by the plugin:

1. Post Field (Map content to fields within the post table)
1. Post Meta (Map content to fields within the post_meta table that refer to the post being created)
1. Taxonomy (Map content to terms in any taxonomy that relate to the post being created)

Let's see these mapping types in action. Here is a super simple feed structure:
```xml
<channel>
   <item>
      <title>Post Title!</title>
      <guid>http://yoursite.com/post-title</guid>
      <copyright>CNN.com</copyright>
      <tag>United States</tag>
      <tag>Politics</tag>
   </item>
   <item>
      <title>Another Post!</title>
      <guid>http://yoursite.com/another-post</guid>
      <copyright>CNN.com</copyright>
      <tag>Celebrities</tag>
   </item>
</channel>
```

As you can see our simple XML document contains two "items". We installed Feed Pull because we want to create posts within
WordPress for each of those items. Now we need to map XML nodes to places within each new post being created. Within the Field Mapping meta box
there are two required mappings: Title and GUID. Therefore we MUST pick XML nodes in our feed to map to these things. For post_title,
our "Source Field" will be "title". For guid, our "Source Field" will be guid. Simple right?

Now we can map whatever nodes we want to post meta in our feed. For educationally purposes, let's say we want to map
"copyright" to post meta for each new post being created. To do this our "Source Field" would be "copyright". Our new
post location can be named whatever we want; let's say "post_copyright". For "Mapping Type", we choose "Post Meta".

Like post meta there are no required taxonomy mappings. Let's create one anyway! We want the "tag" nodes in our feed
to map to the "post_tag" taxonomy in WordPress. Therefore we create a new field mapping row with "tag" as "Source Field",
"post_tag" as "New Post Location", and "Taxonomy" as "Mapping Type".

## Development

### Setup

Follow the configuration instructions above to setup the plugin.

### Testing

Within the terminal change directories to the plugin folder. Initialize your testing environment by running the
following command:

For VVV users:
```
bash bin/install-wp-tests.sh wordpress_test root root localhost latest
```

For VIP Quickstart users:
```
bash bin/install-wp-tests.sh wordpress_test root '' localhost latest
```

where:

* ```wordpress_test``` is the name of the test database (all data will be deleted!)
* ```root``` is the MySQL user name
* ```root``` is the MySQL user password (if you're running VVV). Blank if you're running VIP Quickstart.
* ```localhost``` is the MySQL server host
* ```latest``` is the WordPress version; could also be 3.7, 3.6.2 etc.


Run the plugin test suite:

```bash
phpunit
```

##### Dockunit

This plugin contains a valid [Dockunit](https://www.npmjs.com/package/dockunit) file for running unit tests across a variety of environments locally (PHP 5.2 and 5.5). You can use Dockunit (after installing it via npm) by running:

```bash
dockunit
```

### Issues

If you identify any errors or have an idea for improving the plugin, please [open an issue](https://github.com/tlovett1/feed-pull/issues?state=open).


## License

Feed Pull is free software; you can redistribute it and/or modify it under the terms of the [GNU General Public License](http://www.gnu.org/licenses/gpl-2.0.html) as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.

