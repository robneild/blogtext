<?php
#########################################################################################
#
# Copyright 2010-2011  Maya Studios (http://www.mayastudios.com)
#
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program.  If not, see <http://www.gnu.org/licenses/>.
#
#########################################################################################


require_once(dirname(__FILE__).'/../api/commons.php');
MSCL_Api::load(MSCL_Api::THUMBNAIL_API);
MSCL_Api::load(MSCL_Api::THUMBNAIL_CACHE);

MSCL_require_once('textmarkup_base.php', __FILE__);
MSCL_require_once('markup_cache.php', __FILE__);
MSCL_require_once('interlinks/MediaMacro.php', __FILE__);
MSCL_require_once('interlinks/WordpressLinkResolver.php', __FILE__);


class MarkupException extends Exception {
  public function  __construct($message, $code='', $previous='') {
    parent::__construct($message, $code, $previous);
  }
}

class BlogTextMarkup extends AbstractTextMarkup implements IThumbnailContainer, IMarkupCacheHandler {
  const CACHE_PREFIX = 'blogtext_';

  /**
   * @var MarkupCache
   */
  private static $CACHE;

  // regular expression rules
  // Syntax:
  // * "\1" : Backreference
  // * "(?:" : non-capturing subpattern (http://www.php.net/manual/en/regexp.reference.subpatterns.php)
  // * "(?<=", "(?<!", "(?=", "(?!" : Assertions (http://www.php.net/manual/en/regexp.reference.assertions.php)
  // * "+?", "*?" : ungreedy versions of "+" and "*" (http://www.php.net/manual/en/regexp.reference.repetition.php)
  // * "(?R)" : recursion
  // * Modifiers: http://php.net/manual/de/reference.pcre.pattern.modifiers.php
  //
  // NOTE: This list is ordered and the order is important.
  //
  // REMARKS: For each entry there must be a callback function called "<key_name>_callback($matches)".
  private static $RULES = array(
    // heading with optional anchor names
    // NOTE: This syntax must also allow for # in the heading (like in "C# overview")
    //   and '=' (like in "a != b"). So we make this syntax more restrictive.
    'headings' =>'/^[ \t]*(={1,6})(.*?)(?:[=]+[ \t]*#([^ \t].*)[ \t]*)?$/m',

    // InterLinks using the [[ ]] syntax
    // NOTE: We don't use just single brackets (ie. [ ]) as this is already use by Wordpress' Shortcode API
    // NOTE: Must run AFTER "headings" and BEFORE the tables, as the tables also use pipes
    // NOTE: Must work with [[...\]]] (resulting in "...\]" being the content
    'interlinks' => '/(?<!\[)\[\[(?!\[)[ \t]*((?:[^\]]|\\\])+)[ \t]*(?<!(?<!\\\\)\\\\)\]\]([[:alpha:]]*(?![[:alpha:]]))/',
    // Interlink without arguments [[[ ]]] (three brackets instead of two)
    // NOTE: For now this must run after "headings" as otherwise the TOC can't be generated (which is done
    //   by this rule.
    'simple_interlinks' => '/\[\[\[([a-zA-Z0-9\-]+)\]\]\]/',

    // External links (plain text urls)
    // NOTE: Plain text urls must also work in list. Lists may surround the links with <li>
    //   tags and then white space could no longer be used as sole delimter for URLs. On the
    //   other hand we can't use < and > as delimeter as this would interfere with URL interlinks.
    //   So plaintext urls need to be parsed before tables and lists.
    'plain_text_urls' => '/(?<=[ \t\n])(([a-zA-Z0-9\+\.\-]+)\:\/\/((?:[^\.,;: \t\n]|[\.,;:](?![ \t\n]))+))([ \t]+[.,;:\?\!)\]}"\'])?/',

    'plain_text_email' => '/(?<=\s)[^\s]+@[^\s.]+(?:\.[^\s.]+)+(?=\s)/U',

    // complex tables (possibly contained in a list) - MediaWiki syntax
    'complex_table' => '/^\{\|(.*?)(?:^\|\+(.*?))?(^(?:((?R))|.)*?)^\|}/msi',
    // simple tables - Creole syntax
    // NOTE: Need to be done AFTER "complex_tables" as they syntaxes otherwise may collide (eg. on the
    //   table caption)
    'simple_table' => '/\n(\|(?!\+)[^\|]+\|.+(?:\n\|(?!\+)[^\|]+\|.+)*)(?:\n\|\+(.+))?/',
    // Ordered (#) and unordered (*) lists; definition list(;)
    // NOTE: The user can't start a list with "**" (list with sublist).
    // NOTE: Indentations in lists must be done with at least two spaces/tabs. Otherwise it's too easy to accidentally
    //   insert a space and thereby add a line to a list. This also "fixes" the problem of having a more-link directly
    //   after a list being placed inside the list.
    'list' => '/\n[ \t]?[\*#;][^\*#;].*?\n(?:(?:(?:[ \t]?[\*#]+[\^!]? |[ \t]?;|[ \t]{2,}).*?)?\n)*/',
    // Block quotes
    'blockquote' => '/\n>(.*?\n)(?!>)/s',
    // Indentation (must be done AFTER lists)
    'indentation' => '/\n((?:[ \t]{2,}.*?\n)+)/',

    // Horizontal lines
    'horizontal' => '/^----[\-]*[ \t]*$/m',

    // Emphasis and bold
    // NOTE: We must check that there's no : before the // in emphasis so that URLs won't be interpreted as
    //   emphasis.
    'bold' => '/(?<!\*)\*\*(.+?)\*\*(?!\*)/',
    'emphasis' => '@(?<![/\:])//(.+?)//(?!/)@',
    // Underline, strike-though, super script, and sub script
    'underline' => '/(?<!_)__(.+?)__(?!_)/',
    'strike_through' => '/(?<!~)~~(.+?)~~(?!~)/',
    'super_script' => '/(?<!\^)\^\^(.+?)\^\^(?!\^)/',
    'sub_script' => '/(?<!,),,(.+?),,(?!,)/',

    # Handle all code sections not yet handled (due to inability of the parser)
    'mask_remaining_no_markup_section' => '/##(.+)##/U',
  );

  // Rules to remove white space at the beginning of line that don't expect this (headings, lists, quotes)
  private static $TRIM_RULE = '/^[ \t]*(?=[=\*#:;>$])(?!\*\*|##)/m';

  private static $interlinks = array();

  /**
   * @var bool Indicates whether the current post is just an excerpt (i.e. containing a more link and is rendered for
   *   multi post view).
   */
  private $is_excerpt;

  private $is_rss;

  /**
   * This array contains the amount each id has occurred in this posting. This is used to alter ids (by
   * appending a number) so that the remain unique. Eg. this will result in "my_id", "my_id_2", "my_id_3", ...
   */
  private $id_suffix = array();
  /**
   * Stores all headings in this post/page.
   * @var <type>
   */
  private $headings = array();

  private $headings_title_map = array();

  private $thumbs_used = array();

  /**
   * Used to prevent the static constructor from running multiple times.
   * @var bool
   */
  private static $IS_STATIC_INITIALIZED = false;

  private static function static_constructor() {
    if (self::$IS_STATIC_INITIALIZED) {
      # Static constructor has already run.
      return;
    }
    self::$CACHE = new MarkupCache(self::CACHE_PREFIX);

    //
    // interlinks
    //

    // Handles regular links to post (ie. without prefix), as well as attachment and WordPress links (such
    // as categories, tags, blogroll, and archive).
    self::register_interlink_handler(self::$interlinks, new WordpressLinkProvider());

    // let the custom interlinks overwrite the WordPress link provider, but not the media macro.
    self::register_all_interlink_patterns(self::$interlinks);

    // Media macro (images) - load it as the last one (to overwrite any previously created custom interlinks)
    self::register_interlink_handler(self::$interlinks, new MediaMacro());

    self::$IS_STATIC_INITIALIZED = true;
  }

  public function __construct() {
    self::static_constructor();
    parent::__construct();

    $this->resetBlogTextMarkup(false, false);
  }

  protected function resetBlogTextMarkup($is_rss, $is_excerpt) {
    $this->resetAbstractTextMarkup();
    $this->is_rss = $is_rss;
    $this->is_excerpt = $is_excerpt;
    $this->id_suffix = array();
    $this->headings = array();
    $this->headings_title_map = array();
    $this->thumbs_used = array();
  }

  public function convert_post_to_html($post, $markup_content, $render_type, $is_excerpt) {
    if ($render_type == self::RENDER_KIND_PREVIEW) {
      return $this->convert_markup_to_html_uncached($markup_content, $post, false);
    } else {
      return self::$CACHE->get_html_code($this, $markup_content, $post,
                                         $render_type == self::RENDER_KIND_RSS);
    }
  }

  /**
   * Converts the specified markup into HTML code. This method must explicitly convert the markup code
   * without using cached HTML code for this markup.
   *
   * (Required by IMarkupCacheHandler)
   *
   * @param string $markup_content  the content to be converted. May not be identical to the content in the
   *   $post parameter, in case this is an excerpt or post with more-link.
   * @param object $post  the post to be converted
   * @param bool $is_rss  indicates whether the content is to be displayed in an RSS feed (RSS reader). If
   *   this is false, the content is to be displayed in the browser.
   *
   * @return string  the HTML code
   */
  public function convert_markup_to_html_uncached($markup_content, $post, $is_rss) {
    if (!$this->is_single()) {
      # For the regular expression, see "get_the_content()" in "post-template.php".
      # NOTE: If this posting has a "more" link, $markup_content will already contain the converted link. This makes
      #   it kind of hard to exclude it from being included in the parsing process.
      $is_excerpt = (preg_match('/<!--more(.*?)?-->/', $post->post_content) == 1);
    } else {
      $is_excerpt = false;
    }

    $this->resetBlogTextMarkup($is_rss, $is_excerpt);

    // add blank lines for rules that expect a \n at the beginning of a line (even on the first)
    $markup_content = "\n$markup_content\n";
    // clean up line breaks - convert all to "\n"
    $ret = preg_replace('/\r\n|\r/', "\n", $markup_content);
    $ret = $this->maskNoParseTextSections($ret);
    // remove leading whitespace
    $ret = preg_replace(self::$TRIM_RULE, '', $ret);

    foreach (self::$RULES as $name => $unused) {
      $ret = $this->execute_regex($name, $ret);
    }

    $this->determineTextPositions($ret);
    $ret = $this->unmaskAllTextSections($ret);

    // Remove line breaks from the start and end to prevent Wordpress from adding unnecessary paragraphs
    // and line breaks.
    return trim($ret);
  }

  /**
   * Determines the externals for the specified post. Externals are "links" to things that, if changed, will
   * invalidate the post's cache. Externals are for example thumbnails or links to other posts. Changed means
   * the "link" target has been deleted or created (if it didn't exist before), or for thumbnails that the
   * thumbnail's size has changed.
   *
   * (Required by IMarkupCacheHandler)
   *
   * @param object $post  the post the be checked
   * @param array $thumbnail_ids  an array of the ids of the thumbnails used in the post
   */
  public function determine_externals($post, &$thumbnail_ids) {
    // This method is a trimmed down version of "convert_markup_to_html_uncached()". It finds all interlinks and processes
    // them to find all thumbnails. Note that this method works on the original post content rather than on the
    // content WordPress gives us. This is necessary since the content Wordpress gives us may be only an excerpt
    // which in turn won't contain all image links.
    $this->resetBlogTextMarkup(false, false);

    // clean up line breaks - convert all to "\n"
    $ret = preg_replace('/\r\n|\r/', "\n", $post->post_content);
    $ret = $this->maskNoParseTextSections($ret);

    $this->execute_regex('interlinks', $ret);

    $thumbnail_ids = array_keys($this->thumbs_used);
  }

  /**
   * Clears the page cache completely or only for the specified post.
   * @param int|null $post  if this is "null", the whole cache will be cleared. Otherwise only the cache for
   *   the specified post/page id will be cleared.
   */
  public static function clear_page_cache($post=null) {
    self::static_constructor();
    self::$CACHE->clear_page_cache($post);
  }

  private function is_single() {
    return is_single() || is_page();
  }

  /**
   * @param MSCL_Thumbnail $thumbnail
   */
  public function add_used_thumbnail($thumbnail) {
    $token = $thumbnail->get_token();
    $this->thumbs_used[$token] = $thumbnail;
  }

  private function execute_regex($regex_name, $value) {
    return preg_replace_callback(self::$RULES[$regex_name], array($this, $regex_name.'_callback'), $value);
  }


  ######################################################################################################################
  #
  # region Masking Text Sections
  #

  /**
   * Masks text sections that are to be excluded from markup parsing. This includes code blocks (<code>, <pre> and
   * {{{ ... }}}, `...`), and no-markup blocks ({{! ... !}}). Also masks HTML attributes containing URLs (such as <a>
   * or <img>), and removes end-of-line comments (%%).
   *
   * @param string $markup_code  the BlogText code
   *
   * @return string the masked BlogText code
   */
  private function maskNoParseTextSections($markup_code) {
    # end-of-line comments (%%)
    $pattern = '/(?<!%)%%(.*)$/m';
    $markup_code = preg_replace($pattern, '', $markup_code);

    # IMPORTANT: The implementation of "encode_no_markup_blocks_callback()" depends on the order of the
    #   alternative in this regexp! So don't change the order unless you know what you're doing!
    $pattern = '/<(pre|code)([ \t]+[^>]*)?>(.*?)<\/\1>' # <pre> and <code>
             . '|\{\{\{(.*?)\}\}\}'  # {{{ ... }}} - multi-line or single line code
             . '|((?<!\n)[ \t]+|(?<![\*;:#\n \t]))##([^\n]*?)##(?!#)'  # ## ... ## single line code - a little bit more complicated
             . '|(?<!\`)\`([^\n\`]*?)\`(?!\`)'  # ` ... ` single line code
             . '|\{\{!(!)?(.*?)!\}\}/si';  # {{! ... !}} and {{!! ... !}} - no markup
    $markup_code = preg_replace_callback($pattern, array($this, 'mask_no_markup_section_callback'), $markup_code);

    # Fix for single line code blocks (##) starting at the beginning of a line. If we still find another "##" in the
    # same line (now that all other existing ## blocks have already been masked), assume it's a code block and not a
    # two-level ordered list. We additionally don't allow a space after the "##" here to make it safer. If there is
    # a space, it's going to be replaced as regular rule.
    $pattern = '/##([^ ].*)##/U';
    $markup_code = preg_replace_callback($pattern, array($this, 'mask_remaining_no_markup_section_callback'), $markup_code);

    #
    # URLs in HTML attributes
    #
    $pattern = '/<[a-zA-Z]+[ \t]+[^>]*[a-zA-Z0-9\+\.\-]+\:\/\/[^>]*>/Us';
    $markup_code = preg_replace_callback($pattern, array($this, 'encode_inner_tag_urls_callback'), $markup_code);

    return $markup_code;
  }

  /**
   * The {@link preg_replace_callback()} callback function for encode_no_markup_blocks
   *
   * @param string[] $matches  array of matched elements in the complete markup text; this only includes the text to be
   *   masked
   *
   * @return string  the masked text
   */
  private function mask_no_markup_section_callback($matches) {
    $preceding_text = '';

    // Depending on the last array key we can find out which type of block was escaped.
    switch (count($matches)) {
      case 4: // capture groups: 3
        // HTML tag
        $value = $this->format_no_markup_block($matches[1], $matches[3], $matches[2]);
        break;

      case 5: // capture groups: 1
        // {{{ ... }}}
        $parts = explode("\n", $matches[4], 2);
        if (count($parts) == 2) {
          $value = $this->format_no_markup_block('{{{', $parts[1], $parts[0]);
        } else {
          $value = $this->format_no_markup_block('{{{', $parts[0], '');
        }
        break;

      case 7: // capture groups: 2
        // ##...##
        $preceding_text = $matches[5];
        $value = $this->format_no_markup_block('##', $matches[6], '');
        break;

      case 8: // capture groups: 1
        // `...`
        $value = $this->format_no_markup_block('##', $matches[7], '');
        break;

      case 10: // capture groups: 2
        // {{! ... !}}} and {{!! ... !}} - ignore syntax
        if ($matches[8] != '!') {
          // Simply return contents - also escape tag brackets (< and >); this way the user can use this
          // syntax to prevent a < to open an HTML tag.
          $value = htmlspecialchars($matches[9]);
        } else {
          // Allow HTML
          $value = $matches[9];
        }
        break;

      default:
        throw new Exception('Plugin error: unexpected match count in "encode_callback()": '.count($matches)
                            ."\n".print_r($matches, true));

    }

    return $preceding_text.$this->registerMaskedText($value);
  }

  private function mask_remaining_no_markup_section_callback($matches) {
    $value = $this->format_no_markup_block('##', $matches[1], '');
    return $this->registerMaskedText($value);
  }

  /**
   * @param $block_type
   * @param $contents
   * @param $attributes
   * @return string
   * @throws Exception
   */
  private function format_no_markup_block($block_type, $contents, $attributes) {
    switch ($block_type) {
      case 'pre':
        // No syntax highlighting for <pre>, just for <code>.
        return '<pre'.$attributes.'>'.htmlspecialchars($contents).'</pre>';

      case '##':
      case 'code':
      case '{{{':
        $language = '';
        $start_line = false;

        // Special ltrim b/c leading whitespace matters on 1st line of content.
        $code = preg_replace('/^\s*\n/U', '', $contents);
        $code = rtrim($code);
        // NOTE: Use $contents here (instead of $code) to differentiate
        //   "{{{ my code }}}"
        //   from
        //   {{{
        //   my only code line
        //   }}}
        $is_multiline = (strpos($contents, "\n") !== false);
        $additional_html_attribs = '';
        $highlighted_lines = array();

        if ($block_type == '{{{' && !$is_multiline) {
          // special case: {{{ lang=php my code goes here }}}   (single line)
          if (preg_match('/^[ \t]*(?:lang=(?:"([^"]+)"|([^ \t]+))[ \t]+)(.*)$/U', $code, $matches)) {
            // found lang attribute
            if (!empty($matches[1])) {
              // with quotes
              $language = $matches[1];
            } else {
              // without quotes
              $language = $matches[2];
            }
            $code = $matches[3];
          }
        } else {
          // default case
          preg_match_all('/(?:^|[ \t]+)(\w+)[ \t]*=[ \t]*(?:"([^"]*)"|(?<!")([^ \t]*)(?=[ \t]|$))/U', $attributes, $matches, PREG_SET_ORDER);
          foreach ($matches as $match) {
            $value = count($match) == 3 ? $match[2] : $match[3];
            switch ($match[1]) {
              case 'lang':
                $language = strtolower(trim($value));
                break;
              case 'line':
                $start_line = (int)$value;
                break;
              case 'highlight':
                foreach (explode(',', $value) as $line) {
                  $highlighted_lines[] = (int)$line;
                }
                break;
              default:
                $additional_html_attribs .= ' '.$match[1].'="'.$value.'"';
            }
          }
        }

        return $this->create_code_block($code, $is_multiline, $language, $start_line, $highlighted_lines,
                                        $this->is_rss, $additional_html_attribs);
        break;
    }

    throw new Exception('Plugin error: invalid block type '.$block_type.' encountered in "format_no_markup_block()"');
  }

  private function encode_inner_tag_urls_callback($matches) {
    return $this->registerMaskedText($matches[0]);
  }

  #
  # endregion Masking Text Sections
  #
  ######################################################################################################################


  //
  // RegExp callbacks
  //

  private function bold_callback($matches) {
    return '<strong>'.$matches[1].'</strong>';
  }

  private function emphasis_callback($matches) {
    return '<em>'.$matches[1].'</em>';
  }

  private function underline_callback($matches) {
    // NOTE: <u> is no longer a valid tag in HTML5. So we use
    //   a <span> together with CSS instead.
    return '<span class="underline">'.$matches[1].'</span>';
  }

  private function strike_through_callback($matches) {
    // NOTE: <strike> is no longer a valid tag in HTML5. So we use
    //   a <span> together with CSS instead.
    return '<span class="strike">'.$matches[1].'</span>';
  }

  private function super_script_callback($matches) {
    return '<sup>'.$matches[1].'</sup>';
  }

  private function sub_script_callback($matches) {
    return '<sub>'.$matches[1].'</sub>';
  }

  /**
   * The callback function for horizontal line
   */
  private function horizontal_callback($matches) {
    return '<hr/>';
  }

  private function blockquote_callback($matches) {
    return '<blockquote>'.str_replace("\n>", "\n", $matches[1]).'</blockquote>';
  }

  private function indentation_callback($matches) {
    return '<p class="indented">'.trim($matches[1]).'</p>';
  }

  //
  // Links
  //
  private function plain_text_urls_callback($matches) {
    $protocol = $matches[2];
    $url = $matches[1];
    if (count($matches) == 5) {
      # There is some punctuation following the link. Both are separated by one or more spaces. For some selected
      # punctuation (full stop, question mark, closing brackets, ...) the space is removed automatically, like in
      # "(my link: http://en.wikipedia.org/wiki/Portal_(Game) )". If, however, the punctuation is separated from the
      # link by more than one space, the punctuation isn't changed.
      $punctuation = ltrim($matches[4]);
      if (strlen($punctuation) != strlen($matches[4]) - 1) {
        # More than one space. Revert change.
        $punctuation = $matches[4];
      }
    }
    else {
      $punctuation = '';
    }

    # Check for trailing // in the URL which should be interpreted as emphasis rather than part of the URL
    if (substr($url, -2) == '//') {
      $url = substr($url, 0, -2);
      $punctuation = '//'.$punctuation;
    }

    $title = $this->get_plain_url_name($url);
    # Make sure the title isn't parsed (especially if it still contains '//')!
    $title = $this->registerMaskedText($title);

    # Replace "+" and "." for the css name as they have special meaning in CSS.
    $protocol_css_name = str_replace(array('+', '.'), '-', $protocol);
    return $this->generate_link_tag($url, $title,
                                    array('external', "external-$protocol_css_name", $protocol_css_name))
           .$punctuation;
  }

  private function get_plain_url_name($url) {
    if (!BlogTextSettings::remove_common_protocol_prefixes()) {
      return $url;
    }

    $url_info = parse_url($url);
    if ($url_info['scheme'] != 'http' && $url_info['scheme'] != 'https') {
      // we only handle http and https
      return $url;
    }

    if (   isset($url_info['path'])
        || isset($url_info['query']) || isset($url_info['fragment'])
        || isset($url_info['user']) || isset($url_info['pass'])) {
      // If any of the above mentioned "advanced" URL parts is in the URL, don't shorten the URL.
      return $url;
    }

    // Only shorten URLs that don't have a path (or any other URL parts)
    return $url_info['host'];
  }

  private function plain_text_email_callback($matches) {
    return $this->generate_link_tag('mailto:'.$matches[0], $matches[0], array('mailto'), false);
  }

  private function generate_link_tag($url, $name, $css_classes, $new_window=true, $is_attachment=false) {
    if ($this->is_rss) {
      // no css classes in RSS feeds
      $css_classes = '';
    } else {
      $css_classes = trim(implode(' ', $css_classes));
    }
    $target_attr = $new_window ? ' target="_blank"' : '';
    $target_attr .= $is_attachment ? ' rel="attachment"' : '';
    $css_classes = !empty($css_classes) ? ' class="'.$css_classes.'"' : '';

    if (strpos($url, '//') !== false || strpos($url, '@') !== false) {
      # Mask URL if it contains a protocol as this will otherwise interfere with the emphasis markup
      # (which is '//' too). Also mask "@" so that it wont be recognized as plain-text email address.
      $url = $this->registerMaskedText($url);
    }

    if (strpos($name, '@') !== false) {
      # Mask name if it contains an @, so that it wont be recognized as plain-text email address.
      $name = $this->registerMaskedText($name);
    }

    return '<a'.$css_classes.' href="'.$url.'"'.$target_attr.'>'.$name.'</a>';
  }

  private function interlinks_callback($matches) {
    // split at | (but not at \| but at \\|)
    $params = preg_split('/(?<!(?<!\\\\)\\\\)\|/', $matches[1]);
    // unescape \|, \[, and \] - don't escape \\ just yet, as it may still be used in \:
    $params = str_replace(array('\\[', '\\]', '\\|'), array('[', ']', '|'), $params);
    // find prefix (allow \: as escape for :)
    $prefix_parts = preg_split('/(?<!(?<!\\\\)\\\\):/', $params[0], 2);
    if (count($prefix_parts) == 2) {
      $prefix = $prefix_parts[0];
      $params[0] = $prefix_parts[1];
    } else {
      $prefix = '';
      $params[0] = $prefix_parts[0];
    }
    $params = str_replace('\\\\', '\\', $params);

    $text_after = $matches[2]; // like in [[syntax]]es
    return $this->resolve_link($prefix, $params, true, $text_after);
  }

  private function interlink_params_callback($matches) {
    $key = $matches[1] - 1;
    if (array_key_exists($key, $this->cur_interlink_params)) {
      return $this->cur_interlink_params[$key];
    } else {
      return '';
    }
  }

  public static function get_prefix($link) {
    // determine prefix
    $parts = explode(':', $link, 2);
    if (count($parts) == 2) {
      return $parts;
    } else {
      return array('', $link);
    }
  }

  /**
   * Resolves and returns the specified link.
   *
   * @param string $prefix the links prefix; use "get_prefix()" to obtain the prefix.
   * @param array $params the params of this link; note that the first element must not contain the prefix
   * @param bool $generate_html if this is "true", HTML code will be generated for this link. This is usually
   *   a <a> tag, but may be any other tag (such as "<div>", "<img>", "<span>", ...). If this is "false", only
   *   the link to the specified element will be returned. May be "null", if the link target could not be
   *   found or the prefix doesn't allow direct linking.
   * @param string $text_after text that comes directly after the link; ie. the text isn't separated from
   *   the link by a space (like "[wiki:URL]s"). Not used when "$generate_html = false".
   *
   * @return string HTML code or the link (which may be "null")
   */
  public function resolve_link($prefix, $params, $generate_html, $text_after) {
    $post_id = MarkupUtil::get_post(null, true);

    $link = null;
    $title = null;
    $is_external = false;
    $is_attachment = false;
    $link_type = null;

    $not_found_reason = '';

    $prefix_lowercase = strtolower($prefix);

    if (isset(self::$interlinks[$prefix_lowercase])) {
      // NOTE: The prefix may even be empty.
      $prefix_handler = self::$interlinks[$prefix_lowercase];

      if ($prefix_handler instanceof IInterlinkMacro) {
        // Let the macro create the HTML code and return it directly.
        return $prefix_handler->handle_macro($this, $prefix_lowercase, $params, $generate_html, $text_after);
      }

      if ($prefix_handler instanceof IInterlinkLinkResolver) {
        try {
          list($link, $title, $is_external, $link_type) = $prefix_handler->resolve_link($post_id, $prefix_lowercase, $params);
          $is_attachment = ($link_type == IInterlinkLinkResolver::TYPE_ATTACHMENT);
        }
        catch (LinkTargetNotFoundException $e) {
          $not_found_reason = $e->get_reason();
          $title = $e->get_link_name();
        }
      }
      else if (is_array($prefix_handler)) {
        // Simple text replacement
        // Unfortunately as a hack we need to store the current params in a member variable. This is necessary
        // because we can't pass them directly to the callback method, nested functions can't be used as
        // callback functions and anonymous function are only available in PHP 5.3 and higher.
        $this->cur_interlink_params = $params;
        $link = preg_replace_callback('/\$(\d+)/', array($this, 'interlink_params_callback'),
                                      self::$interlinks[$prefix_lowercase]['pattern']);
        $is_external = self::$interlinks[$prefix_lowercase]['external'];
      }
      else {
        throw new Exception("Invalid prefix handler: ".gettype($prefix_handler));
      }
    }
    else {
      // Unknown prefix; in most cases this is a url like "http://www.lordb.de" where "http" is the prefix
      // and "//www.lordb.de" is the first parameter.
      if (empty($prefix)) {
        // Special case: if the user (for some reasons) has removed the interlink handler for the empty
        // prefix.
        $not_found_reason = LinkTargetNotFoundException::REASON_DONT_EXIST;
      }
      else {
        if (substr($params[0], 0, 2) == '//') {
          // URL
          $link = $prefix.':'.$params[0];
          $is_external = true;
          if (count($params) == 1 && substr($params[0], 0, 2) == '//') {
            $title = $this->get_plain_url_name($link);
          }
        }
        else {
          // not an url - assume wrong prefix
          $not_found_reason = 'unknown prefix: ' . $prefix;
          if (count($params) == 1) {
            $title = "$prefix:$params[0]";
          }
        }
      }
    }

    if (!$generate_html) {
      return $link;
    }

    // new window for external links - if enabled in the settings
    $new_window = ($is_external && BlogTextSettings::new_window_for_external_links());

    //
    // CSS classes
    // NOTE: We store them as associative array to prevent inserting the same CSS class twice.
    //
    if ($is_attachment) {
      // Attachments are a special case.
      $css_classes = array('attachment' => true);
    }
    else if ($link_type == IInterlinkLinkResolver::TYPE_EMAIL_ADDRESS) {
      $css_classes = array('mailto' => true);
    }
    else if ($link_type == IInterlinkLinkResolver::TYPE_SAME_PAGE_ANCHOR) {
      // Link on the same page - add text position requests to determine whether the heading is above or
      // below the link's position.
      // NOTE: We can't check whether the heading already exists in our headings array to determine whether
      //   it's above; this would only be possible, if we parsed character after character. We, however,
      //   execute rule after rule; so at this point all headings are already known.
      $anchor_name = substr($link, 1);
      if ($this->heading_name_exists($anchor_name)) {
        if ($this->needsHmlIdEscaping()) {
          # Ids and anchor names are prefixed with the post's id
          $escaped_anchor_name = $this->escapeHtmlId($anchor_name);
          $link = '#'.$escaped_anchor_name;
        }

        # NOTE: Each anchor must be unique. Otherwise all links to the same anchor will get the same position calculated.
        $placeholderText = $this->registerMaskedText($anchor_name, true, array($this, '_resolveHeadingRelativePos'));
        $this->add_text_position_request($placeholderText);
        $css_classes = array('section-link-'.$placeholderText => true);
      }
      else {
        if ($this->is_excerpt) {
          # This is just an excerpt. Assume that the link target is in the full text.
          global $post;
          $link = get_permalink($post->ID).'#'.$anchor_name;
          $css_classes = array('section-link-below' => true);
        }
        else {
          $not_found_reason = 'not existing';
        }
      }
    }
    else {
      if ($is_external) {
        $css_classes = array('external' => true);
      }
      else {
        $css_classes = array('internal' => true);
      }

      if (!empty($prefix)) {
        // Replace "+" and "." for the css name as they have special meaning in CSS.
        // NOTE: When this is just an URL the prefix will be the protocol (eg. "http", "ftp", ...)
        $css_name = ($is_external ? 'external-' : 'internal-')
                  . str_replace(array('+', '.'), '-', $prefix);
        $css_classes[$css_name] = true;
      }

      if (!empty($link_type)) {
        // Replace "+" and "." for the css name as they have special meaning in CSS.
        $css_name = ($is_external ? 'external-' : 'internal-')
                  . str_replace(array('+', '.'), '-', $link_type);
        $css_classes[$css_name] = true;
      }
    }


    if (!empty($not_found_reason)) {
      // Page or anchor not found
      if ($link == '' || substr($link, 0, 1) != '#') {
        // Replace link only for non anchors (i.e. full links).
        $link = '#';
      }
      // NOTE: Create title as otherwise "#" (the link) will be used as title
      if (empty($title) && count($params) == 1) {
        $title = $params[0];
      }
    }

    //
    // Determine link name
    //
    if (empty($title)) {
      if (count($params) > 1) {
        // if there's more than one parameter, the last parameter is the link's name
        // NOTE: For "[[wiki:Portal|en]]" this would create a link to the Wikipedia article "Portal" and at the
        // same time name the link "Portal"; this is quite clever. If this interlink had only one parameter,
        // one would use "[[wiki:Portal|]]" (note the empty last param).
        $title = $params[count($params) - 1];
        if (empty($title)) {
          // an empty name is a shortcut for using the first param as name
          $title = $params[0];
        }
      }

      // No "else if(empty($title))" here as (although unlikely) the last parameter may have been empty
      if (empty($title)) {
        if ($link_type == IInterlinkLinkResolver::TYPE_SAME_PAGE_ANCHOR) {
          $anchor_name = substr($link, 1); // remove leading #
          if ($this->needsHmlIdEscaping()) {
            $anchor_name = $this->unescapeHtmlId($anchor_name);
          }
          $title = $this->resolve_heading_name($anchor_name, true);
        } else {
          // If no name has been specified explicitly, we use the link instead.
          $title = $link;
        }
      }
    }

    if (!empty($not_found_reason)) {
      // Page not found
      $title .= '['.$not_found_reason.']';
      if ($link_type != IInterlinkLinkResolver::TYPE_SAME_PAGE_ANCHOR) {
        $css_classes = array('not-found' => true);
      } else {
        $css_classes = array('section-link-not-existing' => true);
      }
    } else if ($is_attachment || $is_external) {
      // Check for file extension
      if ($is_attachment) {
        $filename = basename($link);
      } else {
        // we need to extract the path here, so that query (everything after ?) or the domain name doesn't
        // "confuse" basename.
        $filename = basename(parse_url($link, PHP_URL_PATH));
      }
      $dotpos = strrpos($filename, '.');
      if ($dotpos !== false) {
        $suffix = strtolower(substr($filename, $dotpos + 1));
        if ($suffix == 'jpeg') {
          $suffix = 'jpg';
        }

        switch ($suffix) {
          case 'htm':
          case 'html':
          case 'php':
          case 'jsp':
          case 'asp':
          case 'aspx':
            // ignore common html extensions
            break;

          default:
            if (!$is_attachment) {
              $css_classes = array('external-file' => true);
            }
            if ($suffix == 'txt') {
              // certain file types can't be uploaded by default (eg. .php). A common fix would be to add the
              // ".txt" extension (eg. "phpinfo.php.txt"). Wordpress converts this file name to
              // "phpinfo.php_.txt").
              $olddotpos = $dotpos;
              $dotpos = strrpos($filename, '.', -5);
              if ($dotpos !== false) {
                $real_suffix = strtolower(substr($filename, $dotpos + 1, $olddotpos - $dotpos - 1));
                if (strlen($real_suffix) > 2) {
                  if ($real_suffix[strlen($real_suffix) - 1] == '_') {
                    $real_suffix = substr($real_suffix, 0, -1);
                  }

                  switch ($real_suffix) {
                    case 'htm':
                    case 'html':
                    case 'php':
                    case 'jsp':
                    case 'asp':
                    case 'aspx':
                      $suffix = $real_suffix;
                      break;
                  }
                }
              }
            }
            $css_classes['file-'.$suffix] = true;
            break;
        }

        // Force new window for certain suffixes. Note most suffix will trigger a download, so for those
        // there's no need to open them in a new window. Only open files in a new window that the browser
        // usually displays "in-browser".
        switch ($suffix) {
            // images
          case 'png':
          case 'jpg':
          case 'gif':
            // video files
          case 'ram':
          case 'rb':
          case 'rm':
          case 'mov':
          case 'mpg':
          case 'wmv':
          case 'avi':
          case 'divx':
          case 'xvid':
            $new_window = true;
            break;

            // special case for MacOSX, where PDFs are usually not displayed in the browser
          case 'pdf':
            if (strpos($_SERVER['HTTP_USER_AGENT'], 'Mac OS X') === false) {
              $new_window = true;
            }
            break;
        }
      }
    }

    $title = $title.$text_after;

    return $this->generate_link_tag($link, $title, array_keys($css_classes), $new_window, $is_attachment);
  }


  //
  // Headings
  //

  /**
   * This is a callback function. Don't call it directly.
   *
   * @param string $linkTargetId  the HTML id (from the "id" attribute) this link targets
   * @param string $linkPlaceholderText  the placeholder text used to mask the link
   *
   * @return string the replacement text
   */
  public function _resolveHeadingRelativePos($linkTargetId, $linkPlaceholderText) {
    # Retrieve the text position of the heading
    $headingPos = $this->get_text_position($linkTargetId);
    if ($headingPos == -1) {
      return 'not-existing';
    }

    $linkPos = $this->get_text_position($linkPlaceholderText);

    # Compare the link's position with the heading's position
    return ($headingPos < $linkPos ? 'above' : 'below');
  }

  /**
   * The callback function for headings (=====)
   */
  private function headings_callback($matches) {
    $level = strlen($matches[1]);
    $text = trim($matches[2]);
    // Remove trailing equal signs
    $text = trim(rtrim($text, '='));

    if (count($matches) == 4) {
      // Replace spaces and tabs in the anchor name. IMO this is the best way to deal with whitespace in
      // the anchor name (although it's not recommended).
      $id = str_replace(array(' ', "\t"), '-', trim($matches[3]));
    } else {
      $id = '';
    }
    return $this->generate_heading($level, $text, $id);
  }

  private function needsHmlIdEscaping() {
    // NOTE: Although RSS is generated in a multi post view, each post is a separated entity.
    // TODO: We need to check whether this is true.
    return !is_single() && !is_page() && !$this->is_rss;
  }

  private function escapeHtmlId($anchor_id) {
    global $post;
    # NOTE: In HTML 4, ids must not start with a digit but a character. So prepend the id with "post_".
    #   See: http://www.w3.org/TR/html4/types.html#type-id
    return 'post-'.$post->ID.'-'.$anchor_id;
  }

  private function unescapeHtmlId($escapedHtmlId) {
    global $post;
    $prefix = 'post-'.$post->ID.'-';
    $prefix_len = strlen($prefix);
    if (!substr($escapedHtmlId, 0, $prefix_len)) {
      warn("Tried to unescape id '$escapedHtmlId' with prefix '$prefix'.");
      return $escapedHtmlId;
    }

    return substr($escapedHtmlId, $prefix_len);
  }

  private function generate_heading($level, $text, $id='') {
    // Check whether a heading with the exact same text already exists. If so, then add a counter.
    // NOTE: Actually we don't check the text but the ID generated from it, because headings with slightly
    //   different special chars (like white space or punctuation) may generate the same id.
    if ($id == '') {
      $id = $this->sanitize_html_id($text);
    }
    $this->register_html_id($id);

    $pure_id = $id;

    global $post;
    if ($this->needsHmlIdEscaping()) {
      // Post listing, ie. not a single posting/page
      // Therefor we need to append the post's ID to the heading ID, because there may be multiple headings
      // with the same text of multiple posts in the listing.
      $permalink = get_permalink($post->ID).'#'.$id;
      $id = $this->escapeHtmlId($id);
    }
    else {
      // Single post view or RSS feed
      $permalink = '';
    }

    // adjust level; this helps to be able to use the top level heading (= heading =) without having to worry
    // about which is the correct level (which is usually <h2> instead of <h1>).
    // But only, if not in the RSS feed. In a RSS feed we can use <h1> without any problems.
    if (!$this->is_rss) {
      $level += (BlogTextSettings::get_top_level_heading_level() - 1);
    }
    if ($level > 6) {
      $level = 6;
    }

    $this->headings[] = array(
      'level' => $level,
      'id' => $id,
      'text' => $text
    );
    # NOTE: We need to store the original id here, as this is what's referenced in links. Otherwise in multi
    #   post view the anchors won't be found.
    $this->headings_title_map[$pure_id] = $text;

    // Don't add anchor links (¶) to headings in the RSS feed. Usually doesn't look good.
    list($code, $idPlaceholder) = $this->format_heading($level, $text, $id, $permalink, !$this->is_rss);

    # Request text position for every heading. This way they can be referenced more easily by "resolve_link()".
    $this->add_text_position_request($idPlaceholder, $pure_id);

    return $code;
  }

  private function format_heading($level, $text, $id, $id_link='', $add_anchor=true) {
    if (empty($id_link)) {
      $id_link = '#'.$id;
    }

    if ($add_anchor) {
      $anchor = " <a class=\"heading-link\" href=\"$id_link\" title=\"Link to this section\">&#8734;</a>";
      # For escaping the anchor, see next comment.
      $anchor = $this->registerMaskedText($anchor);
    } else {
      $anchor = '';
    }

    # Escape $id and $anchor. If the text of the heading contained a double space, it may have been
    # converted into a double underscore in the id. Escaping the id (and $anchor, which references the id) prevents
    # this double underscore from being recognized as underline token.
    $id = $this->registerMaskedText($id);

    return array("<h$level id=\"$id\">$text$anchor</h$level>", $id);
  }

  /**
   * Registers and possibly adjusts the specified id (id attribute in a HTML tag). The id will be adjusted
   * when there is another id with the same name.
   *
   * @param string $id
   */
  private function register_html_id(&$id) {
    if (array_key_exists($id, $this->id_suffix)) {
      $this->id_suffix[$id]++;
      $id .= '_'.$this->id_suffix[$id];
    } else {
      // just register the id here. Don't add the counter.
      $this->id_suffix[$id] = 1;
    }
  }

  /**
   * Convert illegal chars in an id (id attribute in a HTML tag).
   */
  private function sanitize_html_id($id) {
    $ret = str_replace(array(' ', '\t'), '-', strtolower($id));
    return str_replace('%', '.', rawurlencode($ret));
  }

  private function simple_interlinks_callback($matches) {
    // TODO: Make this "real" plugins - not just hardcoded TOC
    switch (strtolower($matches[1])) {
      case 'toc':
        return $this->generate_toc();
      default:
        return self::generate_error_html('Plugin "'.$matches[1].'" not found.');
    }
  }

  private function heading_name_exists($anchor_name) {
    return isset($this->headings_title_map[$anchor_name]);
  }

  private function resolve_heading_name($anchor_name) {
    if (isset($this->headings_title_map[$anchor_name])) {
      return $this->headings_title_map[$anchor_name];
    } else {
      // Section doesn't exist.
      return '#'.$anchor_name;
    }
  }

  /**
   * Generates and returns the TOC (table of contents) for this post/page.
   */
  private function generate_toc() {
    global $post;

    if (empty($this->headings)) {
      return '';
    }

    // Don't display the TOC if this is just an excerpt (either "real" excerpt or more link).
    if ($this->is_excerpt) {
      return '';
    }

    $min = $this->headings[0]['level'];
    $level = array();
    $prev = 0;
    $toc = '';
    foreach ($this->headings as $k => $h) {
      $depth = $h['level'] - $min + 1;
      $depth = $depth < 1 ? 1 : $depth;

      if ($depth > $prev) { // add one level
        $toclevel = count($level) + 1;
        $toc .= "<ul>\n<li class=\"toclevel-$toclevel\">";
        $open = true;
        array_push($level, 1);
      } else if ($depth == $prev || $depth >= count($level)) { // no change
        $toclevel = count($level);
        $toc .= "</li>\n<li class=\"toclevel-$toclevel\">";
        $level[count($level) - 1] = ++$level[count($level) - 1];
      } else {
        $toclevel = $depth;
        while(count($level) > $depth) {
          $toc .= "</li>\n</ul>";
          array_pop($level);
        }
        $level[count($level) - 1] = ++$level[count($level) - 1];
        $toc .= "</li>\n<li class=\"toclevel-$toclevel\">";
      }
      $prev = $depth;

      $toc .= "<a href=\"#".$h['id']."\"><span class=\"tocnumber\">".implode('.', $level)."</span> "
           .  "<span class=\"toctext\">".$h['text']."</span></a>";
    }

    // close left
    while(count($level) > 0) {
      $toc .= "</li>\n</ul>\n";
      array_pop($level);
    }

    return "<div class=\"toc\">\n<div class=\"toc-title\">".BlogTextSettings::get_toc_title()
           .' <span class="toc-toggle">[<a id="_toctoggle_'.$post->ID.'" href="javascript:toggle_toc('.$post->ID.');">hide</a>]</span>'
           ."</div>\n<div id=\"_toclist_$post->ID\">$toc\n</div></div>";
  }

  //
  // Lists
  //

  private static function convert_to_unique_list_types($list_stack_str) {
    // replace the two-character symbols (ie. ";!" and ";:") with single characters, so that this can be
    // be used more easily
    $list_stack_str = str_replace(array(';!', ';:'), array('t', 'd'), $list_stack_str);
    $unique_list_types = array();
    for ($i = 0; $i < strlen($list_stack_str); $i++) {
      switch ($list_stack_str[$i]) {
        case '*':
          $unique_list_types[] = ATM_ListStack::UNIQUE_ITEM_TYPE_UL;
          break;
        case '#':
          $unique_list_types[] = ATM_ListStack::UNIQUE_ITEM_TYPE_OL;
          break;
        case 't':
          $unique_list_types[] = ATM_ListStack::UNIQUE_ITEM_TYPE_DT;
          break;
        case 'd':
          $unique_list_types[] = ATM_ListStack::UNIQUE_ITEM_TYPE_DD;
          break;
        default:
          throw new Exception();
      }
    }
    return $unique_list_types;
  }

    /**
     * The callback function for lists
     */
    private function list_callback($matches)
    {
        $list_stack = new ATM_ListStack();

        preg_match_all('/^(?:[ ]*((?:\*|#|;[\:\!])+)(\^|;|\!)?[ ]+|(;)(?![\:\!])|[ ]*)(.*)$/m',
            $matches[0], $listLineMatches, PREG_SET_ORDER);
        foreach ($listLineMatches as $lineMatch)
        {
            if (strlen(trim($lineMatch[0])) == 0)
            {
                // Add paragraph; useful to make list wider, like:
                //
                //  * item 1
                //
                //  * item 2
                //
                // Though this isn't the best practice, we still let the user decide whether he/she wants a dense or
                // a wide list.
                $list_stack->append_para();
                continue;
            }

            $text = $lineMatch[4];

            // contains either:
            // * for example "**#" for a three level list
            // * is empty, if the line starts with spaces and/or tabs or in case of a inline definition
            $new_list_stack_str = $lineMatch[1];

            // Either "\n; def : exp" or "**; def : exp"
            $inline_dl = ($lineMatch[2] == ';' || $lineMatch[3] == ';');

            if (strlen(trim($new_list_stack_str)) === 0 && !$inline_dl)
            {
                if (strlen($new_list_stack_str) < 2)
                {
                    // unrecognized syntax. close current list.
                    $list_stack->append_text("\n" . $new_list_stack_str . $text);
                }
                else
                {
                    // continue the previous list level
                    if (trim($text) == '')
                    {
                        // empty line - see note above
                        $list_stack->append_para();
                    }
                    else
                    {
                        $list_stack->append_text("\n" . $text);
                    }
                }
                continue;
            }

            $continue_list = ($lineMatch[2] == '^');
            $restart_list  = ($lineMatch[2] == '!');

            if ($restart_list && $list_stack->has_open_lists())
            {
                // restart the list; useful only for ordered list in which the numbering starts again at 1
                // NOTE: You can only restart the deepest nested list (ie. the right most). I doubt that there's a
                //   use case in which one would need to restart a list lower in the list stack.
                // NOTE 2: We close the deepest list here. If the new list doesn't match the closed list, no harm is
                //   done since the old list would have been closed anyway. In any case a new list is opened.
                $list_stack->close_lists(1);
            }

            $new_list_stack_types = self::convert_to_unique_list_types($new_list_stack_str);
            if ($inline_dl)
            {
                // inline definition line: "term : definition"
                $parts = explode(': ', $text, 2);
                if (count($parts) == 2)
                {
                    $term = $parts[0];
                    $text = $parts[1];
                }
                else
                {
                    $term = $text;
                    $text = '';
                }
                $new_list_stack_types[] = ATM_ListStack::UNIQUE_ITEM_TYPE_DT;
                $list_stack->append_new_item($new_list_stack_types, false, $term);
                array_pop($new_list_stack_types);
                $new_list_stack_types[] = ATM_ListStack::UNIQUE_ITEM_TYPE_DD;
                $list_stack->append_new_item($new_list_stack_types, false, $text);
            }
            else
            {
                $list_stack->append_new_item($new_list_stack_types, $continue_list, $text);
            }
        }

        //
        // generate code
        //
        $code = '';
        foreach ($list_stack->root_items as $root_item)
        {
            if ($root_item instanceof ATM_List)
            {
                $code .= $this->generate_list_code($root_item);
            }
            else
            {
                $code .= $root_item;
            }
        }

        return $code;
    }

  //
  // Tables
  //

  /**
   * The callback function for simple tables
   */
  private function simple_table_callback($matches) {
    $table_code = $matches[1];
    $caption = @$matches[2];

    $table = new ATM_Table();
    $table->caption = $caption;

    foreach (explode("\n", $table_code) as $row_code) {
      $row = new ATM_TableRow();

      foreach (explode('|', $row_code) as $cell_code) {
        // NOTE: DON'T trim the cell code here as we need to differentiate between "|= text" and "| =text".
        if (empty($cell_code)) {
          // can only be happening on the last element - still we need to add it so that we can remove the
          // last element below in a secure way
          $row->cells[] = new ATM_TableCell(ATM_TableCell::TYPE_TD, '');
        } else {
          if ($cell_code[0] == '=') {
            $row->cells[] = new ATM_TableCell(ATM_TableCell::TYPE_TH, trim(substr($cell_code, 1)));
          } else {
            $row->cells[] = new ATM_TableCell(ATM_TableCell::TYPE_TD, trim($cell_code));
          }
        }

        $last_cell = $row->cells[count($row->cells) - 1];
        if (empty($last_cell->cell_content) && $last_cell->cell_type == ATM_TableCell::TYPE_TD) {
          // Remove the last cell, if it's empty. This is the result of "| my cell |" (which would otherwise
          // result in two cells).
          array_pop($row->cells);
        }
      }

      $table->rows[] = $row;
    }

    return $this->generate_table_code($table, true);
  }

  /**
   * The callback function for complex tables
   */
  private function complex_table_callback($matches) {
    $attrs = trim($matches[1]);
    $table_caption = trim($matches[2]);
    $rows = $matches[3];

    if (array_key_exists(4, $matches)) {
      // nested tables
      $rows = $this->execute_regex('complex_table', $rows);
    }

    $table = new ATM_Table();
    $table->tag_attributes = $attrs;
    $table->caption = $table_caption;

    $rregex = '/(?:^(\||!)-|\G)(.*?)^(.*?)(?=(?:\|-|!-|\z))/msi';
    preg_match_all($rregex, $rows, $matches, PREG_SET_ORDER);
    foreach ($matches as $match) {
      if (empty($match[0])) {
        continue;
      }
      $table->rows[] = $this->handle_complex_table_row($match);
    }

    // Don't fill up table cells for complex tables. If the table uses colspan or rowspan (especially through
    // CSS classes) we can't determine the number of missing cells per row. So don't try.
    return $this->generate_table_code($table, false);
  }

  /**
   * The callback function for rows in tables
   */
  private function handle_complex_table_row($matches) {
    $attrs = trim($matches[2]);
    $cells = $matches[3];

    $row = new ATM_TableRow();
    $row->tag_attributes = $attrs;

    $cregex = '#((?:\||!|\|\||!!|\G))(?:([^|\n]*?)\|(?!\|))?(.+?)(?=\||!|\|\||!!|\z)#msi';
    preg_match_all($cregex, $cells, $matches, PREG_SET_ORDER);
    foreach ($matches as $match) {
      if (empty($match[0])) {
        continue;
      }
      $row->cells[] = $this->handle_complex_table_cell($match);
    }

    return $row;
  }

  /**
   * The callback function for cols in rows
   */
  private function handle_complex_table_cell($matches) {
    $type = $matches[1];
    $attrs = trim($matches[2]);
    if ($type == '!') {
      // TODO: The regex above seems to be wrong. For "!! text" it matches only the first ! and places the
      //   second here in the content. This is not how it should work as the syntax requires !! for table
      //   headings on the same line.
      // For now we simply trim the !
      $content = trim(ltrim($matches[3], '!'));
    } else {
      $content = trim($matches[3]);
    }

    $cell = new ATM_TableCell($type == '!' ? ATM_TableCell::TYPE_TH : ATM_TableCell::TYPE_TD, $content);
    $cell->tag_attributes = $attrs;

    return $cell;
  }
}
?>
