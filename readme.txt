=== BlogText ===
Contributors: manski
Tags: formatting, markup, post
Requires at least: 3.0.0
Tested up to: 4.3.1
Stable tag: 0.9.7
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

BlogText is a plugin for WordPress that adds a simple wiki-like syntax to WordPress and enriches it with a
good Wordpress editor integration.

== Description ==
BlogText (http://blogtext.mayastudios.com) is a plugin for WordPress that allows you to use a simple wiki-like
syntax (based on the Creole wiki syntax) to write your posts and pages. This syntax is easy-to-learn and
fast-to-type. The goal behind BlogText is that you don’t need to write HTML anymore to achieve the desired
text layout.

The following list lists some of the markups supported by BlogText. For a more complete list, see [BlogText's
syntax description page](http://blogtext.mayastudios.com/syntax/) (which is written entirely in BlogText
syntax and demonstrates BlogText's capabilities).

Supported markup:

* Basic text formatting such as bold, italics, underlining, and strike-through
* Lists
* Tables
* Internal and external links
* Headings
* Table of contents
* Preformatted text and code blocks with syntax highlighting

BlogText also integrates into Wordpress' HTML editor by providing its own buttons (to create BlogText syntax),
media browser integration, and help links. This make writing posts with BlogText even easier.

For more information, see [BlogText's feature list](http://blogtext.mayastudios.com/features/)

== Installation ==
Installing BlogText is pretty much straight forward.

You need **Wordpress 3.0 or higher** to install BlogText. You also need **PHP 5.3 or higher** installed on
your webserver.

Simple way: **Install via WordPress' plugin page.**

Manual way:

1. Simply download the BlogText .zip file.
1. Extract it.
1. Upload the folder "blogtext" (containing the file `blogtext.php` among others) into your blog's plugin
   directory (usually `wp-content/plugins`).
1. Activate it from the "Plugins" panel in your blog's admin interface
1. Start writing your posts

== Changelog ==

= 0.9.7 =
* Breaking change: BlogText now requires at least PHP 5.3!
* Feature: Added support for email addresses (plain text as well as interlink ones).
* Change: Exceptions thrown while converting a post from BlogText to HTML no longer stops the whole PHP script (i.e.
  content coming after the affected post is now rendered).
* Change: When a link to a page/post couldn't be created because the post status is not 'published', the post status
  will now be put in the link text. Before it would always say "[unpublished]" even if the post was trashed. (issue #24)
* Change/Fix: Removed BlogText comments from the beginning of every post/page as Wordpress was creating an extra
  paragraph for them (although it shouldn't).
* Change: Updated to GeSHi 1.0.8.12 (from 1.0.8.10). This new version adds some more languages for code blocks. See
  [full changelog](https://github.com/GeSHi/geshi-1.0/blob/d9cfd3e0cc9b24e6bd3045a4d222e651e68accd8/src/docs/CHANGES).
* Change: The default BlogText CSS file (now called `blogtext-default.css`) is now minified.
* Fix: Wordpress instances using BlogText can now be moved to different folders (issue #15).
* Fix: Fixed code blocks with line numbers in Twenty Fifteen theme (issue #27).

= 0.9.6 =
* Change: link anchors in TOC links in list view are now prepended with "post-" to meet the (arguably ancient) HTML 4
  requirement for ids (must not start with a digit).
* Change: Auto-generated anchor links now use dash (`-`) instead of underscores (`_`).
* Change: The icon for broken internal links can now be enabled or disabled as well.
* Change: Changed the style of how image captions are displayed. They no longer use a frame. Also added the CSS class
  "wp-post-image" to `<img>` elements.
* Change: Changed the headings permalink icon from the paragraph to infinity.
* Fix: Nested lists (e.g. `**` in `*`) didn't work when they were indented by a space.
* Fix: Link icons are now displayed correctly even if BlogText's default style sheet isn't enable.
* Fix: BlogText's settings link is displayed again in the plugin list.
* Fix: Linking from a section above a more tag to a heading below the more tag no longer results in a "not found" link.
* Fix: Captions for images now display correctly when the blog's theme has `$content_width` defined.
* Fix: TOCs now display correctly again with Wordpress 3.5's default theme.
* Fix: Highlighting code blocks now works for all built-in themes. Also the text coloring isn't lost for the highlighted
  line.

= 0.9.5.1 =
* Fixed PHP parser error present only in PHP < 5.4

= 0.9.5 =
* BlogText now works with PHP 5.4.0 (did not work due to an error in BlogText's option API).
* Removed all file type icons. Icons for links to external sites, attachments, and subsections in the same page remain.
  The icons have been replaced by a web-font though (making them scale with the font size). Additionally each icon type
  can now be disabled in BlogText's settings (issue #13).
* Fix: Emphasis (`//`) can now surround an external link (issue #12)
* BlogText no longer creates thumbnails when the original image would work just fine.
* A double space in a heading no longer breaks the parser (issue #10).
* Fix/Change: If punctuation is written after a plain-text URL separated by one or more spaces, now the space will be
  removed only for certain punctuations. Especially, it won't be removed anymore for opening brackets. To force BlogText
  to keep the spaces, simply use more than one.

= 0.9.4 =
* [Syntax Change] To add indented text to an open list, its items now need to be indented by at least two spaces;
  previously only one space was necessary. This change was made to avoid accidental indention of content generated by
  other plug-ins (such as the more-link generated by Wordpress itself).
* [Syntax Change] Ordered and unordered list item (e.g. `*`, `#`, `*#`, ...) now need a space after them to be
  recognized. This change was introduced to work around a problem where the BlogText parser didn't recognize the bold
  syntax at the beginning of a line.
* [Style Change] The TOC no longer uses `float: right`.
* Double hash code sections (`##`) now work, when they're at the beginning of a line.
* Fixed code blocks with syntax highlighting: No more additional empty lines between code lines (only happen when
  syntax highlighting was used but line numbering wasn't).
* Fixed thumbnails that weren't displayed when PHP strict warning were enabled.
* Fix: Bold text can now be at the beginning of a line, even if indented.
* Updated to newest GeSHi revision (svn: r2522, hg: b4dcf778dccf)

= 0.9.3 =
* "cat:" is now allowed as alternative to the "category:" Interlink prefix
* Now runs with Wordpress 3.3
* Fixed anchors to headings not working in multi post view and RSS feed (issue #1)
* Anchor links (¶) are no longer added to headings in the RSS feed

= 0.9.2.1 =
* Fixed line highlighting, if the code snippet doesn't start off line one.
* Added BlogText settings to the admin bar

= 0.9.2 =
* Checked compatibility against Wordpress 3.2
* Fixed problem where the id of an attachment could not be determined in some cases. In these cases the
  attachment would not display correctly.
* Fix image info: Now also recognizes Exif JPEG images
* Fixed error when updating a page that doesn't use BlogText
* Added special (more natural) code block languages: C++, C++/Qt, C++/CLI, C#, Java (maps to java5)
* Added two new GeSHi themes with more complete coloring; the bright one is now the default one
* Added ability to highlight certain lines in code blocks
* The language lookup window has been improved: it can now be closed by pressing Escape or Return, has
  scrollbars, displays the language's full name, and displays the most popular languages at the top.
* Updated GeSHI to the most recent SVN revision
* Added some regression tests to avoid/reduce conversion errors when modifying BlogText's sources. (only in
  developer version obtained from BitBucket)

= 0.9.1.2 =
* Fixed RSS rendering

= 0.9.1.1 =
* The cache can now be cleared again from the admin bar
* Custom heading ids now work again (they were broken in 0.9.1)

= 0.9.1 =
* Absolute links (like `[[/feed|my feed]]`) can now be used.
* Fixed image captions for external images; they no longer have a width of 0px.
* Added "big" for image size which is just an alias for "large".
* Certain JPEG images (encoded with Progressive DCT) can now be used
* If an alt text is specified for an image, it's now used as title as well (if no title has been specified
  separately). Previously the file name was chosen instead.
* Image titles will now be added as title attribute to the surround link, if there is any.
* Plain text URLs in lists are now correctly recognized
* Plain text URLs can now have a trailing fullstop, comma, semicolon, or colon without this being
  interpreted as part of the URL. Brackets still need to be "escaped" by a space.
* Added backticks for inline code snippets (as alternative to the `##` syntax).
* Slightly changed heading syntax. Now, an anchor id must be separated by a least one equal sign (`=`) from
  the heading text. This allows for hash signs in the headings text, eg. in "C# overview".
* The quote characters (`"`) around attributes for `{{{ ... }}}` code blocks are now optional.
* Added button to the editor (called "lang lookup") to lookup languages supported for syntax highlighting.
* The programming language can now also be specified by using the language's file extension. You can do this
  with ".c" for example.
* Rewrote output cache

= 0.9.0d =
* This version was just released to fix the buggy readme parser in the Wordpress Plugin Directory. It's
  identical to 0.9.0c.

= 0.9.0c =
* First official release.
