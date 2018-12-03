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
 * Creates and handles the thumbnail cache. Note that this implementation allows multiple thumbnails for the
 * same image and also allows thumbnails for external images (which Wordpress doesn't).
 */

use MSCL\FileInfo\AbstractFileInfo;
use MSCL\FileInfo\ImageFileInfo;
use MSCL\FileInfo\NotModifiedNotification;

require_once(dirname(__FILE__).'/settings.php');
require_once(dirname(__FILE__).'/cache.php');


/**
 * Special exception when trying to create a thumbnail image. These exceptions should be catched and a error
 * message block should be inserted into the HTML code instead of the <img> tag.
 */
class MSCL_ThumbnailException extends Exception {
  public function __construct($message) {
    parent::__construct($message);
  }
}

class MSCL_ThumbnailApi {
  const DEFAULT_THUMB_WIDTH = 128;
  const DEFAULT_THUMB_HEIGHT = 96;

  private static $_instance = null;

  private $thumb_crop;
  private $thumbnails = array();

  private function __construct() {
    $this->thumb_crop = (get_option('thumbnail_crop') == '1');
  }

  public function is_thumb_crop_enabled() {
    return $this->thumb_crop;
  }

  /**
   * Checks whether the graphics library is available. If not, image resizing must be done in the browser.
   */
  public static function is_gd_available() {
    return (   extension_loaded('gd')
            && (imagetypes() & IMG_PNG)
            && (imagetypes() & IMG_GIF)
            && (imagetypes() & IMG_JPG));
  }

  public static function get_image_info($img_path) {
    return ImageFileInfo::get_instance($img_path);
  }

  /**
   * Returns the width available for a post's content in pixels. Returns "0" (zero), if the content width is
   * unknown.
   */
  public static function get_content_width() {
    global $content_width;
    if (is_numeric($content_width)) {
      $width = (int)$content_width;
      if ($width > 0) {
        return $width;
      }
    }
    return 0;
  }

  /**
   * Returns the maximum size (in pixels) for specified size (name; eg. "small", "medium", "large").
   *
   * @param string $size the size as name (eg. "small", "medium", "large")
   * @return array Returns "list($max_width, $max_height)". Either or both can be "0", if they're not
   *   specified, meaning that the width or height isn't restricted for this size.
   */
  public static function get_max_size($size) {
    //
    // NOTE: This method is based on "image_constrain_size_for_editor()" defined in "media.php" in Wordpress.
    //
    global $_wp_additional_image_sizes;

    if ($size == 'small') {
      $max_width = intval(get_option('thumbnail_size_w'));
      $max_height = intval(get_option('thumbnail_size_h'));
      // last chance thumbnail size defaults
      if (!$max_width) {
        $max_width = self::DEFAULT_THUMB_WIDTH;
      }
      if (!$max_height) {
        $max_height = self::DEFAULT_THUMB_HEIGHT;
      }
      // Fix the size name for "apply_filters()" below.
      $size = 'thumb';
    } elseif ( $size == 'medium' ) {
      $max_width = intval(get_option('medium_size_w'));
      $max_height = intval(get_option('medium_size_h'));
    }
    elseif ( $size == 'large' ) {
      $max_width = intval(get_option('large_size_w'));
      $max_height = intval(get_option('large_size_h'));
    } elseif ( isset( $_wp_additional_image_sizes ) && count( $_wp_additional_image_sizes ) && in_array( $size, array_keys( $_wp_additional_image_sizes ) ) ) {
      $max_width = intval( $_wp_additional_image_sizes[$size]['width'] );
      $max_height = intval( $_wp_additional_image_sizes[$size]['height'] );
    } else {
      throw new MSCL_ThumbnailException("Invalid image size: ".$size);
    }

    $content_width = self::get_content_width();
    if ($content_width != 0 && $max_width > $content_width) {
      $max_width = $content_width;
    }

    list($max_width, $max_height) = apply_filters('editor_max_image_size', array($max_width, $max_height), $size);
    if ($max_width < 1) {
      $max_width = 0;
    }
    if ($max_height < 1) {
      $max_height = 0;
    }

    return array($max_width, $max_height);
  }

  public static function get_instance() {
    if (is_null(self::$_instance)) {
      self::$_instance = new MSCL_ThumbnailApi();
    }
    return self::$_instance;
  }

  private static function check_mode($size, $mode) {
    if ($mode === null) {
      // use default
      $crop = (!is_array($size) && $size == 'small' && self::get_instance()->thumb_crop);
      return $crop ? MSCL_Thumbnail::MODE_CROP : MSCL_Thumbnail::MODE_RESIZE_IF_LARGER;
    }

    return $mode;
  }

  public static function get_thumbnail_info_from_attachment($thumbnail_container, $attachment_id, $size, $mode=null) {
    if (!wp_attachment_is_image($attachment_id)) {
      // not a image - let wordpress give us a media icon
      return wp_get_attachment_image_src($attachment_id, $size, true);
    }

    return self::get_thumbnail_info($thumbnail_container, get_attached_file($attachment_id), $size, $mode);
  }

  public static function get_thumbnail_info($thumbnail_container, $img_src, $size, $mode=null) {
    if (is_array($size)) {
      $requested_width = max(0, intval($size[0]));
      $requested_height = max(0, intval($size[1]));
    } else {
      list($requested_width, $requested_height) = self::get_max_size($size);
    }

    $token = MSCL_Thumbnail::createThumbnailCacheId($img_src, $requested_width, $requested_height, $mode);
    $thumb = self::getThumbnailFromCacheId($token, false);
    if ($thumb === null) {
      $thumb = new MSCL_Thumbnail($img_src, $requested_width, $requested_height,
                                  self::check_mode($size, $mode), true);
      $instance = self::get_instance();
      $instance->thumbnails[$token] = $thumb;
    }
    // NOTE: Always call this method (not only when the thumbnail instance hadn't been created yet), as we
    //   can't assume that the same container was used when the thumbnail was first created. This is
    //   especially true, when a post's content is checked for thumbnails (in a second "iteration", see
    //   BlogTextMarkup::check_thumbnails()).
    $thumbnail_container->add_used_thumbnail($thumb);

    return array($thumb->get_thumb_image_url(), $thumb->get_thumb_width(), $thumb->get_thumb_height());
  }

  public static function doesThumbnailInfoFileExist($cacheId, $isRemote=null)
  {
    return MSCL_Thumbnail::doesThumbnailInfoFileExist($cacheId, $isRemote);
  }

  public static function getThumbnailFromCacheId($token, $create_if_necessary=true) {
    $instance = self::get_instance();
    $thumb = @$instance->thumbnails[$token];
    if ($thumb === null && $create_if_necessary) {
      $thumb = new MSCL_Thumbnail($token, null, null, null);
    }
    return $thumb;
  }

  /**
   * Removes the thumbnail to which the token belongs from the cache directory. Do this, if the thumbnail
   * is not longer needed.
   *
   * @param string $token the token; you may use "create_token()" to create the token.
   */
  public static function delete_thumbnail($token) {
    MSCL_Thumbnail::delete_thumbnail($token);
  }
}

interface IThumbnailContainer {
  /**
   * Is called when a thumbnail is created for this "container" (usually this is a text that uses thumbnails).
   */
  public function add_used_thumbnail($thumbnail);
}


/**
 * Represents a single thumbnail image.
 */
class MSCL_Thumbnail {
  // TODO: Move these constants to its own class (enum)
  /**
   * Resizes the image so that it matches the specified size, even if the original image is smaller than the
   * specified size. Aspect ratio is kept so one dimension (width or height) may not match the specified
   * size (but the other will).
   */
  const MODE_ALWAYS_RESIZE = 'always_resize';
  /**
   * Same as "MODE_ALWAYS_RESIZE" but only applies for source images that are larger than the specified size.
   * If the source image is smaller that thumbnail will have the size of the source image.
   */
  const MODE_RESIZE_IF_LARGER = 'resize_if_larger';
  /**
   * Always resizes the source image so that it matches the specified size. Doesn't keep the aspect ratio
   * meaning that the image may look stretched in one dimension.
   */
  const MODE_FILL_RESIZE = 'fill_resize';
  /**
   * Crops the image to the specified size. The center of the image will be used, using the largest possible
   * region of the image. If the source image is smaller than the specified size, the image won't be resized.
   */
  const MODE_CROP = 'crop';
  /**
   * Same as "MODE_CROP" but resizes the image so that the specified size is always matched. Aspect ratio is
   * kept.
   */
  const MODE_CROP_AND_RESIZE = 'crop_resize';

  /**
   * The thumbnail cache id of this thumbnail.
   * @var string
   */
  private $m_cacheId;

  /**
   * The absolute path to the source image of this thumbnail. Can either be a local or a remote image.
   * @var string
   */
  private $m_srcImgFullPath;

  /**
   * Whether this thumbnail is based on a remote image (true) or local image (false).
   * @var bool
   */
  private $m_isSrcImgRemote;

  /**
   * Width of the source image.
   * @var int
   */
  private $m_srcImgWidth;

  /**
   * Height of the source image.
   * @var int
   */
  private $m_srcImgHeight;

  // TODO: What about gif?
  /**
   * Image type of the source image; will only be either "jpg" or "png". May change, if the
   * source image has changed its image type.
   * @var string
   */
  private $m_srcImgType;

  /**
   * The timestamp when the remote image was last time check for modifications. Only available when
   * {@link REMOTE_IMAGE_TIMEOUT} > 0.
   */
  private $m_lastRemoteUpdateCheckTimestamp = null;

  /**
   * The requested thumbnail width in pixels; or 0 if auto determined. In the latter case
   * {@link m_requestedThumbHeight} must be non-zero.
   * @var int
   */
  private $m_requestedThumbWidth;
  /**
   * The requested thumbnail height in pixels; or 0 if auto determined. In the latter case
   * {@link m_requestedThumbWidth} must be non-zero.
   * @var int
   */
  private $m_requestedThumbHeight;
  private $m_resizeMode;

  private $thumb_width = 0;
  private $thumb_height = 0;
  private $src_crop_bounds;

  /**
   * This field describes the "version" of the source image. It's required for two tasks:
   *  1. This date is sent to the server to check whether the file has been modified (ie. if the
   *     modification date of the source image is later than this cache date, the file has been modified).
   *  2. This date is also used to check whether the thumbnail (on the local file system) is up-to-date.
   *     This check is done by comparing the thumbnail's last modification date with the cache date.
   *
   * It's basically a timestamp (seconds since Linux epoch).
   *
   * @var int
   */
  private $cache_date = null;
  private $m_isUpToDate = null;

  public function  __construct($img_src, $requested_thumb_width, $requested_thumb_height,
                               $mode, $do_remote_check=false) {
    $img_src = trim($img_src);

    if (is_null($requested_thumb_width))
    {
      $this->m_cacheId = $img_src;

      // We need to figure out whether the image is a remote image. Just check whether the file exists.
      if (!file_exists(self::createThumbnailInfoFilePath($img_src, false)))
      {
        $this->m_isSrcImgRemote = true;
      }
      else
      {
        $this->m_isSrcImgRemote = false;
      }

      $this->loadDataFromThumbnailInfoFile();

      // We need to redo the check here in case local and remote file reside in the same directory
      $this->m_isSrcImgRemote = AbstractFileInfo::isRemoteFileStatic($this->m_srcImgFullPath);
    }
    else
    {
      $this->m_cacheId = self::createThumbnailCacheId($img_src, $requested_thumb_width, $requested_thumb_height, $mode);
      $this->m_isSrcImgRemote = AbstractFileInfo::isRemoteFileStatic($img_src); // required for getting the file path

      if (file_exists($this->getThumbnailInfoFilePath())) {
        // reuse already existing data
        $this->loadDataFromThumbnailInfoFile();
      }
      else
      {
        $this->m_srcImgFullPath = $img_src;
        $this->m_requestedThumbWidth = max(0, intval($requested_thumb_width));
        $this->m_requestedThumbHeight = max(0, intval($requested_thumb_height));

        switch ($mode)
        {
          case self::MODE_ALWAYS_RESIZE:
          case self::MODE_RESIZE_IF_LARGER:
          case self::MODE_FILL_RESIZE:
          case self::MODE_CROP:
          case self::MODE_CROP_AND_RESIZE:
            $this->m_resizeMode = $mode;
            break;
          default:
            throw new Exception("Invalid mode: ".$mode);
        }

        if ($this->m_requestedThumbWidth == 0 && $this->m_requestedThumbHeight == 0)
        {
          throw new Exception("At least one dimension must be specified.");
        }

        // Since this seems to be a newly created thumbnail, we need to store its token file.
        $this->storeDataInThumbnailInfoFile();
      }
    }

    if (!$this->m_isSrcImgRemote || $do_remote_check)
    {
      $this->checkForModificationsAndUpdate();
    }
  }

  /**
   * Constructs a thumbnail cache id for the specified information.
   *
   * @param string $srcImagePath  the absolute path to the source image (local or remote)
   * @param int $thumbWidth  the width of the thumbnail (or 0, if the original width is to be used)
   * @param int $thumbHeight  the height of the thumbnail (or 0, if the original height is to be used)
   * @param string $resizeMode  how to resize the image if it's too large
   *
   * @return string
   */
  public static function createThumbnailCacheId($srcImagePath, $thumbWidth, $thumbHeight, $resizeMode)
  {
    // NOTE: The token must only contain a-z,0-9, "_", "-", and ".". This way it can be used directly as
    //  an URL parameter and doesn't produce an invalid file name on the local file system.
    return sha1($srcImagePath)
         . '_'.intval($thumbWidth).'x'.intval($thumbHeight)
         . '_'.$resizeMode;
  }

  public static function doesThumbnailInfoFileExist($cacheId, $isRemote=null)
  {
    if ($isRemote === null)
    {
      if (file_exists(self::createThumbnailInfoFilePath($cacheId, false)))
      {
        return true;
      }
      $isRemote = true;
    }

    return file_exists(self::createThumbnailInfoFilePath($cacheId, $isRemote));
  }

  private static function getThumbnailCacheDir($is_remote)
  {
    return ($is_remote ? MSCL_ThumbnailCache::get_remote_file_cache_dir()
                       : MSCL_ThumbnailCache::get_local_file_cache_dir());
  }

  private static function createThumbnailInfoFilePath($cacheId, $isRemote)
  {
    return self::getThumbnailCacheDir($isRemote).'/'.$cacheId.'.info.txt';
  }

  public function getThumbnailInfoFilePath()
  {
    return self::createThumbnailInfoFilePath($this->m_cacheId, $this->m_isSrcImgRemote);
  }

  public function get_thumb_image_path()
  {
    // NOTE: Don't use "get_thumb_image_type()" as extension as this tends to produce endless recursions.
    return self::getThumbnailCacheDir($this->m_isSrcImgRemote).'/'.$this->m_cacheId.'.img';
  }

  public function get_thumb_image_url() {
    static $script_do_php_url = null;

    if ($script_do_php_url === null) {
      if (!defined('WP_PLUGIN_URL')) {
        throw new Exception("The thumbnail image url isn't available because WordPress isn't loaded.");
      }
      $script_do_php_url = WP_PLUGIN_URL.MSCL_AbstractPlugin::get_plugin_basename(dirname(__FILE__)).'/do.php?';
    }
    // NOTE: We don't return the direct path to the thumbnail here for several reasons:
    //  1. The browser had to load the same image twice (one time to create the thumbnail using "do.php" and
    //     one time to load the thumbnail directly). This is not very bandwidth friendly.
    //  2. A direct link won't update the thumbnail, if necessary. The user might copy the direct link when
    //     he/she instead wanted to have a link to an thumbnail that's always up-to-date.
    //  3. The load on "do.php" is not that big since the browser's cache is used.
    // NOTE: "$img_token" doesn't need to run through "urlencode()". See definition above.
    return $script_do_php_url.'id='.$this->m_cacheId;
  }

  private static function create_token_glob($cache_dir, $token) {
    // NOTE: This function basically does the same as "glob()" (with a few differences). However, we don't
    //   use "glob()" here, as its not available on every system and may(!) return "false" in some situations
    //   (see for example: http://bugs.php.net/bug.php?id=47358)
    $all_files = scandir($cache_dir);
    if ($all_files === false) {
      return false;
    } else {
      $filtered_files = array();
      $token = $token.'.'; // add "."
      $token_len = strlen($token);
      foreach ($all_files as $filename) {
        if (strlen($filename) < $token_len || substr($filename, 0, $token_len) != $token) {
          // name doesn't match
          continue;
        }

        $filename = $cache_dir.DIRECTORY_SEPARATOR.$filename;
        if (!is_file($filename)) {
          // only allow for files - gives errors otherwise (and should never happen anyway).
          continue;
        }

        $filtered_files[] = $filename;
      }
      return $filtered_files;
    }
  }

  /**
   * Removes the thumbnail to which the token belongs from the cache directory. Do this, if the thumbnail
   * is not longer needed.
   *
   * @param string $token the token; you may use "create_token()" to create the token.
   */
  public static function delete_thumbnail($token) {
    // NOTE: We use a glob to find all related files.
    //   This also handles the case where, for example, the token file has already been deleted but the
    //   thumbnail hasn't (shouldn't happen, however).

    // local files
    $files = self::create_token_glob(self::getThumbnailCacheDir(false), $token);
    if ($files === false) {
      log_error("Listing files for '".self::getThumbnailCacheDir(false)."' (local files cache) failed.");
    } else {
      foreach ($files as $filename) {
        unlink($filename);
      }
    }

    // remote  files
    $files = self::create_token_glob(self::getThumbnailCacheDir(true), $token);
    if ($files === false) {
      log_error("Listing files for '".self::getThumbnailCacheDir(true)."' (remote files cache) failed.");
    } else {
      foreach ($files as $filename) {
        unlink($filename);
      }
    }

    log_info("Thumbnail for id '$token' has been deleted.");
  }

  private function loadDataFromThumbnailInfoFile()
  {
    $token_file = $this->getThumbnailInfoFilePath();
    $contents = @file_get_contents($token_file);
    if ($contents === false)
    {
      throw new Exception("Could not read specified token file.");
    }

    $data = unserialize($contents);

    $this->m_srcImgFullPath = $data['img_src'];
    $this->m_srcImgWidth = $data['src_img_width'];
    $this->m_srcImgHeight = $data['src_img_height'];
    $this->m_srcImgType = $data['src_img_type'];

    $this->m_requestedThumbWidth = $data['requested_thumb_width'];
    $this->m_requestedThumbHeight = $data['requested_thumb_height'];
    $this->m_resizeMode = $data['mode'];

    // NOTE: This field describes the "version" of the source image. It's required for two tasks:
    //  1. This date is sent to the server to check whether the file has been modified (ie. if the
    //     modification date of the source image is later than this cache date, the file has been modified).
    //  2. This date is also used to check whether the thumbnail (on the local file system) is up-to-date.
    //     This check is done by comparing the thumbnail's last modification date with the cache date.
    // So, we need to store the cache-date in the info file for several reasons:
    //  * We can't use the token file's modification date as this file may change even if the source image
    //    hasn't.
    //  * We can't use the thumbnail's modification date as it needs to be checked against the cache date
    //    (otherwise they would always be the same).
    $this->cache_date = $data['cache_date'];

    $this->m_lastRemoteUpdateCheckTimestamp = $data['last_remote_update_check'];
  }

  private function storeDataInThumbnailInfoFile() {
    $filename = $this->getThumbnailInfoFilePath();
    // Store data
    $data = array
    (
      'img_src' => $this->m_srcImgFullPath,
      'src_img_width' => $this->m_srcImgWidth,
      'src_img_height' => $this->m_srcImgHeight,
      'src_img_type' => $this->m_srcImgType,

      'requested_thumb_width' => $this->m_requestedThumbWidth,
      'requested_thumb_height' => $this->m_requestedThumbHeight,
      'mode' => $this->m_resizeMode,

      'cache_date' => $this->cache_date,
      'last_remote_update_check' => $this->m_lastRemoteUpdateCheckTimestamp,
    );
    file_put_contents($filename, serialize($data), LOCK_EX);
  }

  public function is_remote_image() {
    return $this->m_isSrcImgRemote;
  }

  public function get_token() {
    return $this->m_cacheId;
  }

  /**
   * Returns the image format of the thumbnail, that is either "jpg", "png", or "gif". Only available after
   *   calling "check_for_modifications()" or "is_uptodate()". Note that "gif" will only be returned if the
   *   source image is a .gif image and if it's neither being cropped nor resized. Gif files that are cropped
   *   or resized will be converted into .png files.
   * @return string
   */
  public function get_thumb_image_type() {
    if ($this->m_srcImgType === null) {
      throw new Exception("Image type not yet available.");
    }
    if ($this->is_use_original_image() && $this->m_srcImgType == ImageFileInfo::TYPE_GIF) {
      return 'gif';
    }

    return $this->m_srcImgType == ImageFileInfo::TYPE_JPEG ? 'jpg' : 'png';
  }

  public function get_thumb_image_mimetype() {
    switch ($this->get_thumb_image_type()) {
      case 'jpg':
        return 'image/jpeg';
      case 'png':
        return 'image/png';
      case 'gif':
        return 'image/gif';
    }
    throw new Exception('Unexpected image type: '.$this->get_thumb_image_type());
  }

  /**
   * Returns the width of the thumbnail.
   * @return int
   */
  public function get_thumb_width() {
    if ($this->thumb_width == 0) {
      $this->checkForModificationsAndUpdate();
    }
    return $this->thumb_width;
  }

  /**
   * Returns the height of the thumbnail.
   * @return int
   */
  public function get_thumb_height() {
    if ($this->thumb_height == 0) {
      $this->checkForModificationsAndUpdate();
    }
    return $this->thumb_height;
  }

  /**
   * Indicates whether the thumbnail exists in the cache. If so, the cached image may be used, if the source
   * image hasn't changed.
   * @return bool
   */
  public function is_in_cache() {
    return file_exists($this->get_thumb_image_path());
  }

  /**
   * Indicates whether the cached thumbnail image is up-to-date. Also updates the thumbnail info file, if necessary.
   * @return bool
   */
  public function isUpToDate()
  {
    if ($this->m_isUpToDate === null)
    {
      // We've already check whether the file is up to date. Let's assume that the file hasn't changed in the
      // meantime.
      $this->checkForModificationsAndUpdate();
    }

    return $this->m_isUpToDate;
  }

  /**
   * Returns whether an is-up-to-date-check should be performed or not.
   * @return bool
   */
  private function shouldCheckForModifications()
  {
    if (!$this->m_isSrcImgRemote)
    {
      // Local images can always be checked; it's cheap.
      return true;
    }

    if ($this->m_srcImgWidth == null)
    {
      // The source image has never been "analyzed". We need to do this at least once.
      return true;
    }

    if (REMOTE_IMAGE_TIMEOUT < 0)
    {
      // Manual update only.
      return false;
    }
    else if (REMOTE_IMAGE_TIMEOUT == 0)
    {
      // Always check for modifications
      return true;
    }

    if ($this->m_lastRemoteUpdateCheckTimestamp === null)
    {
      // Remote image was never checked.
      return true;
    }

    return (time() > $this->m_lastRemoteUpdateCheckTimestamp + REMOTE_IMAGE_TIMEOUT);
  }

  /**
   * Checks whether this thumbnail is up-to-date (i.e. still matches the source
   * image) and updated {@link isUpToDate()} accordingly.
   * @param bool $forceUpdate
   */
  public function checkForModificationsAndUpdate($forceUpdate = false)
  {
    if ($this->m_isUpToDate !== null && $forceUpdate == false)
    {
      // We're already up-to-date.
      return;
    }

    $isUpToDate = null;

    if ($forceUpdate || $this->shouldCheckForModifications())
    {
      try
      {
        //
        // Check for source file modifications
        //
        if (!file_exists($this->m_srcImgFullPath))
        {
          // May happen if the information of this thumbnail were loaded from the thumbnail info file
          // but the source image has moved.
          // TODO: What to do in this case????
        }

        $info = ImageFileInfo::get_instance($this->m_srcImgFullPath, $this->cache_date);
        $this->m_srcImgWidth = $info->get_width();
        $this->m_srcImgHeight = $info->get_height();
        $this->m_srcImgType = $info->get_type();
        $this->cache_date = $info->getLastModifiedDate();

        if ($this->cache_date == null)
        {
          // if the last modified date isn't available.
          $this->cache_date = time();
        }

        $this->m_lastRemoteUpdateCheckTimestamp = time();
        $this->storeDataInThumbnailInfoFile();
        $isUpToDate = false;
      }
      catch (NotModifiedNotification $e)
      {
        // Source image hasn't been changed.
        if (REMOTE_IMAGE_TIMEOUT > 0)
        {
          // Only update the last remote check if this is really necessary (i.e.
          // when auto-checking is enabled).
          $this->m_lastRemoteUpdateCheckTimestamp = time();
          $this->storeDataInThumbnailInfoFile();
        }
      }
    }

    if ($isUpToDate === null)
    {
      // Check whether thumbnail file needs to be updated. This happens if the source image was modified.
      $isUpToDate = ($this->is_in_cache() && filemtime($this->get_thumb_image_path()) >= $this->cache_date);
    }

    if ($isUpToDate !== $this->m_isUpToDate)
    {
      $this->m_isUpToDate = $isUpToDate;
      $this->updateThumbnailSizeFromSrcImg();
    }
  }

  /**
   * Updates the thumbnail, if necessary, and displays it (ie. output its data).
   */
  public function display_thumbnail() {
    try {
      if (!$this->isUpToDate()) {
        // NOTE: We don't reuse the image object created in this method for the output but rather read the
        //   file this method has written. We do this under the assumption that compressing the file (jpg/png)
        //   will take longer than reading an already compressed file from the harddrive.
        $this->update_thumbnail();
      }
    } catch (MSCL_ThumbnailException $e) {
      $msg = $e->getMessage();
      if (empty($msg)) {
        $msg = "An error has occured";
      }
      self::display_error_msg_image($msg);
    }

    $thumb_file = $this->get_thumb_image_path();
    $last_modified_date = filemtime($thumb_file);
    if ($last_modified_date === false) {
      throw new Exception("Could not determine last modified date");
    }

    $etag = sha1((string)$last_modified_date);

    // use browser cache if available to speed up page load
    if ($this->m_isUpToDate && isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
      $moddate_cache = @strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']);
      if ($moddate_cache !== false && $moddate_cache >= $last_modified_date) {
        // Browser cache date is newer than the source images modification date. So the image in the browser
        // cache is still valid. Send "Not Modified" response.
        header('HTTP/1.1 304 Not Modified');
        header('Etag: "'.$etag.'"');
        exit;
      }
    }

    // send content headers then display image
    header('Content-Type: '.$this->get_thumb_image_mimetype());
    header('Last-Modified: '.gmdate("D, d M Y H:i:s", $last_modified_date)." GMT");
    // NOTE: It seems Firefox needs the etag to make use of its cache. Maybe other browsers too.
    //   See: http://en.wikipedia.org/wiki/HTTP_ETag
    header('Etag: "'.$etag.'"');
    header('Content-Length: '.filesize($thumb_file));
    // IMPORTANT: Don't use "max-age" here as this results in that the browser doesn't send the
    //   "if-modified-since" header when the time has expired.
    header('Cache-Control: must-revalidate, public');

    if (!@readfile($thumb_file)) {
      // Should never happen
      throw new Exception("Could not read cache filed");
    }

    exit;
  }

  public static function display_error_msg_image($error_msg) {
    $font_size = 3;
    $text_width = imagefontwidth($font_size)*strlen($error_msg);
    $center = ceil($text_width / 2);
    $x = $center - (ceil($text_width/2));

    $im = imagecreate($text_width, 30);

    $bg = imagecolorallocate($im, 255, 96, 96);
    $textcolor = imagecolorallocate($im, 0, 0, 0);

    // Write the string at the top left
    imagestring($im, $font_size, $x, 0, $error_msg, $textcolor);

    // Output the image
    header('Content-type: image/png');

    imagepng($im);
    imagedestroy($im);
    exit;
  }

  /**
   * Updates the thumbnails dimensions from the image source. After calling this method, the size of the
   * thumbnail can be retrieved
   */
  private function updateThumbnailSizeFromSrcImg()
  {
    $orig_w = $this->m_srcImgWidth;
    $orig_h = $this->m_srcImgHeight;
    $dest_w = $this->m_requestedThumbWidth;
    $dest_h = $this->m_requestedThumbHeight;

    if ($orig_w <= 0 || $orig_h <= 0)
    {
      throw new Exception("Image has invalid size: ".$this->m_srcImgFullPath);
    }

    $aspect_ratio = $orig_w / $orig_h;

    if ($this->m_resizeMode == self::MODE_CROP || $this->m_resizeMode == self::MODE_CROP_AND_RESIZE)
    {
      //
      // crop the largest possible portion of the original image that we can size to $dest_w x $dest_h
      //
      if ($this->m_resizeMode == self::MODE_CROP_AND_RESIZE && ($orig_w < $dest_w || $orig_h < $dest_h))
      {
        if ($dest_w == 0)
        {
          $dest_w = intval($dest_h * $aspect_ratio);
        }
        else if ($dest_h == 0)
        {
          $dest_h = intval($dest_w / $aspect_ratio);
        }

        $factor = max($dest_w / $orig_w, $dest_h / $orig_h);
        $crop_w = $dest_w / $factor;
        $crop_h = $dest_h / $factor;

        $new_w = $dest_w;
        $new_h = $dest_h;
      }
      else
      {
        $new_w = min($dest_w, $orig_w);
        $new_h = min($dest_h, $orig_h);

        if ($new_w == 0)
        {
          $new_w = floor($new_h * $aspect_ratio);
        }
        else if ($new_h == 0)
        {
          $new_h = floor($new_w / $aspect_ratio);
        }

        $size_ratio = max($new_w / $orig_w, $new_h / $orig_h);

        $crop_w = round($new_w / $size_ratio);
        $crop_h = round($new_h / $size_ratio);
      }

      $s_x = floor( ($orig_w - $crop_w) / 2 );
      $s_y = floor( ($orig_h - $crop_h) / 2 );
    }
    else
    {
      // don't crop, just resize using $dest_w x $dest_h as a maximum bounding box
      $crop_w = $orig_w;
      $crop_h = $orig_h;
      $s_x = 0;
      $s_y = 0;

      $new_w = $dest_w;
      $new_h = $dest_h;
      if ($this->m_resizeMode != self::MODE_FILL_RESIZE)
      {
        if ($dest_w == 0)
        {
          $new_w = floor($dest_h * $aspect_ratio);
        }
        else
        {
          $new_h = floor($dest_w / $aspect_ratio);
        }
      }
    }

    if ($orig_w <= $new_w && $orig_h <= $new_h && $this->m_resizeMode == self::MODE_RESIZE_IF_LARGER)
    {
      // the source image is smaller than the thumbnail; we don't want to resize it
      $this->thumb_width = $orig_w;
      $this->thumb_height = $orig_h;
      $this->src_crop_bounds = null;
    }
    else
    {
      $this->thumb_width = (int)$new_w;
      $this->thumb_height = (int)$new_h;
      $this->src_crop_bounds = array((int)$s_x, (int)$s_y, (int)$crop_w, (int)$crop_h);
    }
  }

  /**
   * Indicates whether the original image is being used as thumbnail. It's not being used if needs to be
   * resized or cropped.
   * @return bool
   */
  public function is_use_original_image() {
    $this->checkForModificationsAndUpdate();
    return (   $this->src_crop_bounds === null
            && $this->thumb_width == $this->m_srcImgWidth
            && $this->thumb_height = $this->m_srcImgHeight);
  }

  private function update_thumbnail() {
    if ($this->is_use_original_image()) {
      // same size; don't resize - use original image so that we don't lose image quality or gif animations
      // NOTE: this situation always happens when the src image is smaller than the requested thumbnail
      file_put_contents($this->get_thumb_image_path(), AbstractFileInfo::getFileContents($this->m_srcImgFullPath));
      return;
    }

    switch ($this->m_srcImgType) {
      case ImageFileInfo::TYPE_JPEG:
        $src_image = imagecreatefromjpeg($this->m_srcImgFullPath);
        break;
      case ImageFileInfo::TYPE_PNG:
        $src_image = imagecreatefrompng($this->m_srcImgFullPath);
        break;
      case ImageFileInfo::TYPE_GIF:
        $src_image = imagecreatefromgif($this->m_srcImgFullPath);
        break;
      default:
        throw new MSCL_ThumbnailException("Unsupported mimetype: ".ImageFileInfo::convert_to_mime_type($this->m_srcImgType));
    }

    // create a new true color image
    // NOTE: This snippet was copied from "wp_imagecreatetruecolor()" in "media.php".
    $canvas = imagecreatetruecolor($this->thumb_width, $this->thumb_height);
    if (is_resource($canvas) && function_exists('imagealphablending') && function_exists('imagesavealpha')) {
      imagealphablending($canvas, false);
      imagesavealpha($canvas, true);
    }

    // copy and resize part of an image with resampling
    // bool imagecopyresampled ( resource $dst_image , resource $src_image , int $dst_x , int $dst_y ,
    //                           int $src_x , int $src_y , int $dst_w , int $dst_h , int $src_w , int $src_h )
    imagecopyresampled($canvas, $src_image,
                       0, 0,
                       $this->src_crop_bounds[0], $this->src_crop_bounds[1],
                       $this->thumb_width, $this->thumb_height,
                       $this->src_crop_bounds[2], $this->src_crop_bounds[3]);

    imagedestroy($src_image);

    // Store the thumbnail in its thumbnail file.
    // NOTE: Unfortunately PHP doesn't provide us with the means of locking the thumbnail image. So use a
    //   temporary file as work-around (assuming that the rename command is atomic - sort of). Using a
    //   temporary file also prevents problems when the file could not be written (in which case the original
    //   thumbnail won't be overwritten).
    $tmp_name = $this->get_thumb_image_path().'_'.mt_rand(0, 0xFFFF);
    if ($this->get_thumb_image_type() == 'jpg') {
      $ret = imagejpeg($canvas, $tmp_name, JPEG_QUALITY);
    } else {
      $ret = imagepng($canvas, $tmp_name, 9);
    }

    imagedestroy($canvas);

    if ($ret == false) {
      // Does this even happen?
      throw new Exception("Could not create thumbnail file: ".$tmp_name);
    }

    // Lock the token file to simulate a lock on the thumbnail file.
    $lock_file = fopen($this->getThumbnailInfoFilePath(), 'r+');
    if ($lock_file == false) {
      throw new Exception("Token file doesn't exist although it should.");
    }

    // Get exclusive lock
    if (flock($lock_file, LOCK_EX) == false) {
      fclose($lock_file);
      throw new Exception("Token file couldn't be locked.");
    }

    rename($tmp_name, $this->get_thumb_image_path());

    flock($lock_file, LOCK_UN); // release the lock
    fclose($lock_file);
  }
}
?>
