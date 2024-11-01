=== Templatedia ===
Contributors: Viper007Bond
Donate link: http://www.viper007bond.com/donate/
Tags: templates, post, posts, page, pages
Requires at least: 2.0
Stable tag: trunk

Create and manage dynamic templates for use in posts and pages.

== Description ==

Do you find yourself constantly copying and pasting code from post to post or from page to page? Well then this plugin may be exactly what you were looking for.

Templatedia allows you to create dynamic templates and then insert small tags into posts and pages that will be replaced with the template. It's template system is based on that of [MediaWiki](http://www.mediawiki.org/wiki/Help:Templates), the software behind [Wikipedia](http://www.wikipedia.org/).

###Some Possible Usages###

* A styled download box that contains information about the latest version of a file and a link to download
* Adding a [Digg](http://digg.com/) button to a post or page
* Easily adding differently styled boxes of text or images
* ... and countless other things!

###See Also###

* [Templatedia Chess](http://wordpress.org/extend/plugins/templatedia-chess/): an addon for this plugin that adds a new template for displaying a chess diagram

== Installation ==

###Updgrading From A Previous Version###

To upgrade from a previous version of this plugin, delete the entire folder and files from the previous version of the plugin and then follow the installation instructions below.

###Uploading The Plugin###

Extract all files from the ZIP file, making sure to keep the file structure intact, and then upload it to `/wp-content/plugins/`.

This should result in the following file structure:

`- wp-content
    - plugins
        - templatedia
            | readme.txt
            | screenshot-1.png
            | screenshot-2.png
            | templatedia.php
            | templatedia-mediawiki-variables.php
            - localization
                | template.po`

**See Also:** ["Installing Plugins" article on the WP Codex](http://codex.wordpress.org/Managing_Plugins#Installing_Plugins)

###Plugin Activation###

Go to the admin area of your WordPress install and click on the "Plugins" menu. Click on "Activate" for the "Templatedia" plugin. The "Templatedia MediaWiki Variables" plugin is entirely optional and **does not have to be activated**.

###Plugin Configuration###

The management page for adding, editing, and deleting templates can be found at Manage -> Templates.

###Example Usage###

Write a new post and paste this in:

`{{diggthis}}

{{wordpresslogo}}

{{bluebox|text=This is some test text.}}

Browse Happy logo with border: {{imgborder|{{WP:WPURL}}/wp-admin/images/browse-happy.gif}}`

That will show off the example templates that come loaded with Templatedia by default. You can safely delete those templates from the template management page.

**More details about how to write and use a template can be found at [Mediawiki](http://www.mediawiki.org/wiki/Help:Templates).**

== Frequently Asked Questions ==

= How in the heck do I use this plugin? =

The [templates help article at Mediawiki](http://www.mediawiki.org/wiki/Help:Templates) pretty much explains it all. It should get you well on your way to being a template expert.

= I still don't get it / something is broken! =

I'm a very busy guy, so please use the [official WordPress support forums](http://wordpress.org/tags/templatedia#postform) for help (post it in the "Plugins and Hacks" section). By posting there, you will easily be able to get support from both me as well other WordPress users.

= Where can I suggest a new feature for this plugin? =

[Contact me.](http://www.viper007bond.com/contact/)

= Does this plugin support other languages? =

Yes, it does. Included in the `localization` folder is the translation template you can use to translate the plugin. See the [WordPress Codex](http://codex.wordpress.org/Translating_WordPress) for details.

= I love your plugin! Can I donate to you? =

Sure! I do this in my free time and I appreciate all donations that I get. It makes me want to continue to update this plugin. You can find more details on [my donate page](http://www.viper007bond.com/donate/).

== Screenshots ==

1. Template management page listing all templates
2. Editing a template

== Plugin API ==

= I'm a plugin developer. How can I have my plugin parse the inside of Templatedia templates for replacing my plugin's placeholder text? =

Simple. Just as you've added a filter to `the_content`, add the same function as a filter for `templatedia_pre_template`. The completed template will be parsed by your function right before it's inserted into the post/page's content.

= What other things can I modify via filters? =

This plugin has some very useful filters. Here are the two important ones:

* `templatedia_templates` - modify the templates array to add new template(s). Note that the stub name needs to be **lowercase**. See the [Templatedia Chess](http://wordpress.org/extend/plugins/templatedia-chess/) plugin for an example.

* `templatedia_variables` - add to the list of variables that can be used in posts and templates. Do a `global $post;` if you need to. See `templatedia-mediawiki-variables.php` for an example.

There are also various other filters available for use, but which are highly technical in manner. You probably won't need to use them, but you can find out about them by reading the plugin's source.

== ChangeLog ==

**Version 1.1.0**

* Various bug fixes including issues relating to dollar signs and such in template input. Thanks to BustToBracelet for the report.

**Version 1.0.0**

* Initial release!