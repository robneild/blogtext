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


use MSCL\FileInfo\FileInfoException;
use MSCL\FileInfo\FileNotFoundException;
use MSCL\FileInfo\AbstractFileInfo;
use MSCL\FileInfo\ImageFileInfo;

require_once(dirname(__FILE__).'/../../api/commons.php');
MSCL_Api::load(MSCL_Api::THUMBNAIL_API);

MSCL_require_once('IInterlinkMacro.php', __FILE__);

class MediaMacro implements IInterlinkMacro {
  public function get_handled_prefixes() {
    return array('img', 'image');
  }

  public function handle_macro($link_resolver, $prefix, $params, $generate_html, $after_text) {
    $post_id = get_the_ID();
    list($is_attachment, $ref) = $this->get_file_info($params[0], $post_id);
    if ($is_attachment && $ref === null) {
      $html = self::generate_error_html($params[0], true);
    } else {
      switch ($prefix) {
        case 'img':
        case 'image':
          $html = $this->generate_img_tag($link_resolver, $is_attachment, $ref, $params, $generate_html);
          break;
        default:
          throw new Exception('Unexpected prefix: '.$prefix);
      }
    }

    return $html.$after_text;
  }

  private static function generate_error_html($message, $not_found=false) {
    if ($not_found) {
      return AbstractTextMarkup::generate_error_html('Attachment "'.$message.'" not found', 'error-attachment-not-found');
    }

    return AbstractTextMarkup::generate_error_html($message);
  }

  private function generate_img_tag($link_resolver, $is_attachment, $ref, $params, $generate_html) {
    $link = '';
    $link_params = array();
    $display_caption = false;
    $no_caption = false;
    $is_thumb = false;
    $alignment = '';
    $title = '';
    $img_size_attr = '';

    if (!$generate_html) {
      if ($is_attachment) {
        return wp_get_attachment_url($ref);
      } else {
        return $ref;
      }
    }

    if (   !$is_attachment
        && AbstractFileInfo::isRemoteFileStatic($ref)
        && AbstractFileInfo::isRemoteFileSupportAvailable()
        && !AbstractFileInfo::isUrlProtocolSupported($ref)) {
      // Remote image whose protocol isn't supported. Create a good looking error message here.
      return self::generate_error_html("The protocol for remote file '".$ref."' isn't supported.");
    }

    // Identify parameters
    foreach ($params as $key => $param) {
      $param = trim($param);
      if (empty($param) || $key == 0) {
        // Skip empty params. Also skip the first element as it always contains the image address and
        // otherwise could be used as title (if it's also the last parameter).
        continue;
      }

      if (substr($param, 0, 5) == 'link=') {
        // NOTE: We don't allow overwriting the link for "thumb". That's what thumb is for.
        if (!$is_thumb) {
          $link = substr($param, 5);
        }
      } // -------------------------------
      else if ($param == 'thumb') {
        // Thumbnails always have a link to their fullsize image.
        $is_thumb = true;
        $link = 'source';
      } else if ($param == 'caption') {
        $display_caption = true;
      } else if ($param == 'nocaption') {
        $no_caption = true;
      } else if ($param == 'left' || $param == 'right' || $param == 'center') {
        $alignment = $param;
      } else if ($param == 'small' || $param == 'medium' || $param == 'large' || substr($param, -2) == 'px') {
        $img_size_attr = $param;
      } else if ($param == 'big') {
        # "big" is just an alias for "large"
        $img_size_attr = 'large';
      } else {
        if ($key == count($params) - 1) {
          // if this is the last parameter and not one of the types above, assume it's the title
          $title = $param;
        } else if (!empty($link) && !$is_thumb) {
          // if not the parameters may belong to the link
          // note that the following code isn't completely correct
          $link_params[] = $param;
        }
      }
    }

    // display caption if the user specified one
    if (!empty($title) && BlogTextSettings::display_caption_if_provided() && !$no_caption) {
      $display_caption = true;
    }

    //
    // resolve link
    //
    if ($link == 'source') {
      // link to source image
      if ($is_attachment) {
        $link = wp_get_attachment_url($ref);
      } else {
        $link = $ref;
      }
    } else if (!empty($link) && !$is_thumb) {
      list($prefix, $link) = $link_resolver->get_prefix($link);
      array_unshift($link_params, $link); // place the link at the beginning of the params
      $link = $link_resolver->resolve_link($prefix, $link_params, false, '', '');
    }

    //
    // title
    //
    $alt_text = '';
    if ($is_attachment) {
      // Get image caption as stored in the database - if the attachment is an image
      if (empty($title)) {
        list($title, $alt_text) = MarkupUtil::get_attachment_image_titles($ref);
      } else {
        $alt_text = MarkupUtil::get_attachment_image_alt_text($ref);
      }
    }
    $title = htmlspecialchars(trim($title));
    if (!empty($alt_text)) {
      $alt_text = htmlspecialchars($alt_text);
    } else {
      $alt_text = $title;
    }

    //
    // size and alignment
    //
    if ($is_thumb && empty($img_size_attr)) {
      // Set default thumb size
      if ($alignment == 'center') {
        // Use "large" when the thumbnail is centered.
        $img_size_attr = 'large';
      } else {
        // NOTE: We assume "small" here as this is what Wordpress calls "thumbnail" size.
        $img_size_attr = 'small';
      }
    }

    if (empty($alignment) && ($is_thumb || $display_caption)) {
      if ($img_size_attr == 'small') {
        $alignment = BlogTextSettings::get_default_small_img_alignment();
      } else if ($img_size_attr == 'medium' || $img_size_attr == 'large') {
        $alignment = 'center';
      }
      // Don't align images without a named size (like "200px" or no size at all).
    }

    // Default values: If width/height is zero, it's omitted from the HTML code.
    $img_width = 0;
    $img_height = 0;

    try {
      if (empty($img_size_attr)) {
        if ($is_attachment) {
          $img_url = wp_get_attachment_url($ref);
        }
        else {
          $img_url = $ref;
        }

        $content_width = MSCL_ThumbnailApi::get_content_width();
        if ($content_width != 0) {
          $img_size = self::getImageSize($is_attachment, $ref);
          if ($img_size !== false) {
            if ($img_size[0] > $content_width) {
              # Image is larger then the content width. Create a "thumbnail" to limit its width.
              list($img_url, $img_width, $img_height) = self::getThumbnailInfo($link_resolver, $is_attachment, $ref,
                                                                               array($content_width, 0));
            }
            else {
              # If we've already determined the image's size, lets use it. Also required if we need to display the
              # image's caption.
              $img_width = $img_size[0];
              $img_height = $img_size[1];
            }
          }
        }
        else if ($display_caption && !empty($title)) {
          # NOTE: If the image's caption is to be display, we need the image's width (see below).
          $img_size = self::getImageSize($is_attachment, $ref);
          if ($img_size !== false) {
            $img_width = $img_size[0];
            $img_height = $img_size[1];
          }
        }
      }
      else {
        // Width is specified.
        if (substr($img_size_attr, -2) == 'px') {
          // Actual size - not a symbolic one.
          $img_size_attr = array((int)substr($img_size_attr, 0, -2), 0);
        }

        list($img_url, $img_width, $img_height) = self::getThumbnailInfo($link_resolver, $is_attachment, $ref, $img_size_attr);
      }
    }
    catch (FileNotFoundException $e) {
      return self::generate_error_html($e->getFilePath(), true);
    }
    catch (FileInfoException $e) {
      return self::generate_error_html($e->getMessage());
    }
    catch (MSCL_ThumbnailException $e) {
      return self::generate_error_html($e->getMessage());
    }

    #
    # Generate HTML code
    #
    $html = '<img class="wp-post-image" src="'.$img_url.'" title="'.$title.'" alt="'.$alt_text.'"';
    // image width and height may be "null" for remote images for performance reasons. We let the browser
    // determine their size.
    if ($img_width > 0) {
      $html .= ' width="'.$img_width.'"';
    }
    if ($img_height > 0) {
      $html .= ' height="'.$img_height.'"';
    }
    $html .= '/>';

    // Add link
    if (!empty($link)) {
      $html = '<a href="'.$link.'"'
            . ($is_attachment ? ' rel="attachment"' : '')
            . (!empty($title) ? " title=\"$title\"" : '')
            . '>'.$html.'</a>';
    }

    # Display caption
    if ($display_caption && !empty($title)) {
      $align_style = !empty($alignment) ? (' align-'.$alignment.' image-frame-align-'.$alignment) : '';

      # NOTE: We need to specify the width here so that long titles break properly. Note also that the width needs
      #   to be specified on the container (image-frame) to prevent it from expanding to the full page width.
      $html = '<div class="image-frame'.$align_style.'" style="width:'.$img_width.'px;">'
            . '<div class="image">'.$html.'</div>'
            . '<div class="image-caption">'.$title.'</div>'
            . '</div>';
    } else if (!empty($alignment)) {
      $html = '<div class="align-'.$alignment.' image-align-'.$alignment.'">'.$html.'</div>';
    }

    return $html;
  }

  /**
   * Checks whether the specified reference is a url or an attachment.
   * @param string|int $ref
   * @param int $post_id
   *
   * @return array returns array(is_attachment, id/url)
   */
  private function get_file_info($ref, $post_id) {
    if (MarkupUtil::is_url($ref)) {
      return array(false, $ref);
    } else {
      return array(true, $this->get_attachment_id($ref, $post_id));
    }
  }

  private function get_attachment_id($ref, $post_id) {
    // NOTE: "is_numeric" also checks for numeric strings (which "is_int()" doesn't). So don't use "is_int()"
    //  here.
    if(is_numeric($ref)) {
      // ID
      return MarkupUtil::is_attachment($ref) ? $ref : null;
    } else {
      return MarkupUtil::get_attachment_id($ref, $post_id);
    }
  }

  private static function getImageSize($isAttachment, $ref) {
    try {
      $img_path = $isAttachment ? get_attached_file($ref, true) : $ref;

      $info = ImageFileInfo::get_instance($img_path);
      return array($info->get_width(), $info->get_height());
    }
    catch (FileInfoException $e) {
      // Media information not available; don't specify size
      log_error($e->getMessage(), 'media info not available');
      return false;
    }
  }

  /**
   * @param $linkResolver
   * @param bool $isAttachment  whether the ref is an attachment or URL
   * @param string|int $ref  the ref to the image
   * @param array|string  $requestedSize  the maximum size for the image as array or one of the symbolic sizes ("large",
   *   "small", ...) as string.
   * @return array Returns the thumbnail info as array with ($img_url, $img_width, $img_height)
   */
  private static function getThumbnailInfo($linkResolver, $isAttachment, $ref, $requestedSize) {
    if ($isAttachment) {
      return MSCL_ThumbnailApi::get_thumbnail_info_from_attachment($linkResolver, $ref, $requestedSize);
    } else {
      return MSCL_ThumbnailApi::get_thumbnail_info($linkResolver, $ref, $requestedSize);
    }
  }
}

?>
