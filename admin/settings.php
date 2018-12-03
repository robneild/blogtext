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


/*
 * This file contains all of the plugin's settings.
 */
require_once(dirname(__FILE__) . '/../api/commons.php');
MSCL_Api::load(MSCL_Api::OPTIONS_API);
MSCL_require_once('docu.php', __FILE__);

class BlogTextSettings {
  const OWN_GESHI_STYLE = 'own';

  public static function get_toc_title($get_option=false) {
    static $option = null;
    if ($option == null) {
      $option = new MSCL_TextfieldOption('blogtext_toc_title', 'Title for TOCs',
              'long', 'Table of Contents',
              'Specifies the title for TOCs (Table of Contents) added to a post or page by using '
              .'<code>[[[TOC]]]</code>.');
    }
    return $get_option ? $option : $option->get_value();
  }

  /**
   * Callback function.
   */
  public static function check_top_level_heading_level($input) {
    return ($input >= 1 && $input <= 6);
  }

  public static function get_top_level_heading_level($get_option=false) {
    static $option = null;
    if ($option == null) {
      $option = new MSCL_IntOption('blogtext_top_level_heading_level', 'Top Level Heading Level', 2, 
              'Specifies which heading level (1 - 6) the top-level heading represents. For example, '
              .'specifying "3" here, will result in "= Heading =" be converted into '
              .'"&lt;h3&gt;Heading&lt;/h3&gt;".',
              'BlogTextSettings::check_top_level_heading_level');
    }
    return $get_option ? $option : $option->get_value();
  }

  public static function remove_common_protocol_prefixes($get_option=false) {
    static $option = null;
    if ($option == null) {
      $option = new MSCL_BoolOption('blogtext_remove_common_protocol_prefixes',
              'Render http:// and https:// links shortend',
              true,
              'When this is enabled, a link like "http://www.mayastudios.com" will render as '
              .'"www.mayastudios.com" - when no name is provided for the link. Only the protocols "http://" '
              .'and "https://" will be removed and only in some cases the prefixes will be removed.');
      // TODO: Add link to explanation which urls will be shortend.
    }
    return $get_option ? $option : $option->get_value();
  }

  public static function new_window_for_external_links($get_option=false) {
    static $option = null;
    if ($option == null) {
      $option = new MSCL_BoolOption('blogtext_new_window_for_external_links', 
              'Open external links in a new window/tab', true);
    }
    return $get_option ? $option : $option->get_value();
  }

  public static function get_default_small_img_alignment($get_option=false) {
    static $option = null;
    if ($option == null) {
      $option = new MSCL_ChoiceOption('blogtext_default_small_img_alignment', 'Default alignment of small images',
                                 array('left' => 'left aligned', 'right' => 'right aligned'), 1,
              'Specifies how images with size "small" or "thumb" are to be aligned, when no alignment is '
              .'specified.');
    }
    return $get_option ? $option : $option->get_value();
  }

  public static function display_caption_if_provided($get_option=false) {
    static $option = null;
    if ($option == null) {
      $option = new MSCL_BoolOption('blogtext_display_caption_if_provided', 'Display image captions when provided',
              true,
              'Specifies whether image captions that are specified directly in the Interlink (ie. as '
              .'<code>[[image:myimage.jpg|My caption]]</code>) are displayed by default. If this is enabled, '
              .'a caption can be disabled by adding <code>nocaption</code> as parameter. If this is disabled '
              .'the caption will only be shown when the user moves the mouse cursor over the image - unless'
              .'the parameter <code>caption</code> is added to the Interlink.');
    }
    return $get_option ? $option : $option->get_value();
  }

  ////////////////////////////////////////////////////////////////////////////

  /**
   * Callback function.
   */
  public static function check_content_width($input) {
    return ($input >= 0);
  }

  public static function get_content_width($get_option=false) {
    static $option = null;
    if ($option == null) {
      global $content_width;
      if (isset($content_width) && $content_width > 0) {
        $desc_short = $content_width.' pixel';
        $desc_long = 'The theme specifies its content width with '.$content_width.' pixel. Unless you want '
                   . 'another content width, you can set this option to zero (0).';
      } else {
        $desc_short = 'not specified';
        $desc_long = 'The theme does <strong>not</strong> specify a content width.';
      }
      $option = new MSCL_IntOption('blogtext_content_width', 
              'Content Width (in Pixels)<br/>(by theme: '.$desc_short.')',
              0,
              'Specifies the width available for content (ie. a posting\'s text and images) in pixels. This '
              .'is used to limit the width of images used in posts and pages. If this is 0, the theme is '
              .'checked whether it specifies the width in the global variable <code>$content_width</code>. '
              .'If this variable isn\'t available, the image widths won\' be constrained.'
              .'<br/><br/>'
              .$desc_long,
              'BlogTextSettings::check_content_width');
    }
    return $get_option ? $option : $option->get_value();
  }

  public static function use_default_external_link_icon($get_option=false) {
    static $option = null;
    if ($option == null) {
      $option = new MSCL_BoolOption('blogtext_use_default_external_link_icon', 'Use default icon for external links', true);
    }
    return $get_option ? $option : $option->get_value();
  }

  public static function use_default_https_link_icon($get_option=false) {
    static $option = null;
    if ($option == null) {
      $option = new MSCL_BoolOption('blogtext_use_default_https_link_icon', 'Use default icon for external HTTPS links', true);
    }
    return $get_option ? $option : $option->get_value();
  }

  public static function use_default_attachment_link_icon($get_option=false) {
    static $option = null;
    if ($option == null) {
      $option = new MSCL_BoolOption('blogtext_use_default_attachment_link_icon', 'Use default icon for attachment links', true);
    }
    return $get_option ? $option : $option->get_value();
  }

  public static function use_default_updown_link_icon($get_option=false) {
    static $option = null;
    if ($option == null) {
      $option = new MSCL_BoolOption('blogtext_use_default_updown_link_icon', 'Use default up/down icon for links to the same page', true);
    }
    return $get_option ? $option : $option->get_value();
  }

  public static function use_default_broken_link_icon($get_option=false) {
    static $option = null;
    if ($option == null) {
      $option = new MSCL_BoolOption('blogtext_use_default_broken_link_icon', 'Use default icon for broken internal links', true);
    }
    return $get_option ? $option : $option->get_value();
  }

  public static function use_default_css($get_option=false) {
    static $option = null;
    if ($option == null) {
      $option = new MSCL_BoolOption('blogtext_use_default_css', 'Use default CSS file', true,
              'The BlogText plugin comes with a .css file that contains the default style definitions used '
              .'by the BlogText plugin. This way this plugin works out-of-the-box. However, if you (ie. your '
              .'Wordpress theme) want to specify your own styles, you can disable the default styles with '
              .'this option.');
    }
    return $get_option ? $option : $option->get_value();
  }

  public static function get_geshi_theme($get_option=false) {
    static $option = null;
    if ($option == null) {
      $option = new MSCL_ChoiceOption('blogtext_geshi_theme', 'Theme for code colorizing (aka. syntax highlighting)',
                                 array('default-bright' => "Default: Bright",
                                       'default-dark' => "Default: Dark",
                                       'dawn' => "Dawn (bright)",
                                       'mac-classic' => "Mac Classic (bright)",
                                       'twilight' => "Twilight (dark)",
                                       'vibrant-ink' => "Vibrant Ink (dark)",
                                       self::OWN_GESHI_STYLE => "Don't use built-in style"),
                                 0,
              'The BlogText plugin comes with some default styles that are used to style the syntax '
              .'highlighting of code blocks. You can select the style to be used with this option. If you '
              .'want to specify your own style, choose "Don\'t use built-in style" here.');
    }
    return $get_option ? $option : $option->get_value();
  }

  public static function get_custom_css($get_option=false) {
    static $option = null;
    if ($option == null) {
      $option = new MSCL_TextareaOption('blogtext_custom_css', 'Custom CSS', 80, 12, '',
              'This allows you to specify <a href="http://www.w3schools.com/css/default.asp" target="_blank">'
              .'custom CSS</a> to override some of your theme\'s CSS styles so that it looks better together '
              .'with BlogText.');
    }
    return $get_option ? $option : $option->get_value();
  }


  ////////////////////////////////////////////////////////////////////////////

  public static function disable_fix_invalid_xhtml_warning($get_option=false) {
    static $option = null;
    if ($option == null) {
      $option = new MSCL_BoolOption('blogtext_disable_fix_unbalanced_tags_warning',
                               'Disable warning about invalid XHTML nesting correction',
                               false);
    }
    return $get_option ? $option : $option->get_value();
  }

  public static function enable_monospace_editor_font($get_option=false) {
    static $option = null;
    if ($option == null) {
      $option = new MSCL_BoolOption('blogtext_enable_monospace_editor_font',
                               'Monospace font in editor',
                               true,
                               'Use <span style="font-family:monospace;">monospaced</span> font in the post '
                               .'editor (instead of the default proportional font).');
    }
    return $get_option ? $option : $option->get_value();
  }

  ////////////////////////////////////////////////////////////////////////////

  public static function get_interlinks($get_option=false, $parse=true) {
    static $option = null;
    if ($option == null) {
      $option = new MSCL_TextareaOption('blogtext_interlinks', 'Interlinks', 80, 8,
                                    "search = http://www.google.com/search?q=$1\n"
                                   ."wiki = http://$1.wikipedia.org/wiki/$2\n",
              'Interlinks are prefixes associated with an (external) URL. Each URL can have any number of '
              .'parameters (represented by "$number"; eg. "$1", "$2", ...). Interlinks are used in BlogText '
              .'like this: <code>[[prefix:param1|param2|...]]</code>.<br/>For example using '
              .'<code>[[wiki:en|Portal]]</code> with <code>wiki = http://$1.wikipedia.org/wiki/$2</code> '
              .'will create this link: http://en.wikipedia.org/wiki/Portal<br/>For more information, see '
              .'<a href="'.BlogTextDocumentation::INTERLINKS_HELP.'" target="_blank">Interlinks Help</a>.');
    }
    if ($get_option) {
      return $option;
    }

    $option = $option->get_value();
    if ($parse) {
      $option = self::parse_interlinks($option);
    }
    return $option;
  }

  public static function parse_interlinks($interlinks_text) {
    $interlinks = array();
    preg_match_all('/^([a-zA-Z0-9\-_]+)[ \t]*=[ \t]*(.+)$/m', $interlinks_text, $matches, PREG_SET_ORDER);
    foreach ($matches as $match) {
      // TODO: Add ability to specify links as internal
      // NOTE: We need to trim away the line break here for match[2].
      $interlinks[$match[1]] = array(trim($match[2]), true);
    }

    return $interlinks;
  }
}

class BlogTextPostSettings {
  public static function get_use_blogtext($post) {
    if (is_object($post)) {
      $post_id = $post->ID;
    } else {
      $post_id = (int)$post;
    }
    $use_blogtext = get_post_meta($post_id, 'use_blogtext', true);
    if (empty($use_blogtext)) {
      // not set - figure out default
      if (!is_object($post)) {
        $post = get_post($post_id);
      }
      
      // Already published posts/pages were written before BlogText was installed (as otherwise
      // the "use_blogtext" settings would have been set to either "true" or "false"). Only return true
      // for pages with "auto-draft" (new pages) and "draft" status.
      // See: http://codex.wordpress.org/Post_Status_Transitions
      return ($post->post_status == 'auto-draft' || $post->post_status == 'draft');
    }
    return MSCL_BoolOption::is_true($use_blogtext);
  }

  public static function set_use_blogtext($post_id, $use) {
    update_post_meta($post_id, 'use_blogtext', $use ? 'true' : 'false');
  }
}
?>
