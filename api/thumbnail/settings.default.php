<?php
//
// Settings for the thumbnail API.
// IMPLEMENTERS NOTE: Since Wordpress may not be loaded, these settings can't be implemented using "get_option()". For
//   customizing these settings, create a "settings.custom.php" file in this directory and define the
//   constants you like to modify.
//


if (!defined('LOCAL_IMG_CACHE_DIR'))
{
  /**
   * The name of the thumbnail cache directory for local image files. Relative to the Wordpress' upload directory.
   * Can be the same as {@link REMOTE_IMG_CACHE_DIR}.
   */
  define('LOCAL_IMG_CACHE_DIR', 'thumb_cache/local');
}
if (!defined('REMOTE_IMG_CACHE_DIR'))
{
  /**
   * The name of the thumbnail cache directory for remote image files. Relative to the Wordpress' upload directory.
   * Can be the same as {@link LOCAL_IMG_CACHE_DIR}.
   */
  define('REMOTE_IMG_CACHE_DIR', 'thumb_cache/remote');
}

if (!defined('REMOTE_IMAGE_TIMEOUT'))
{
  /**
   * Specifies the number of seconds after which a remote image should be checked for changes again. Before this
   * timeout expires, the remote image is considered unchanged. The following values are possible:
   *
   * * value &gt; 0: works as specified above
   * * value = 0: the remote image is checked every time
   * * value &lt; 0: update checks are triggered manually, for example when publishing/updating a post
   */
  define('REMOTE_IMAGE_TIMEOUT', -1);
}

if (!defined('JPEG_QUALITY')) {
  /**
   * Specifies the JPEG quality (0 - 100) to be used. The higher the value the better the quality.
   */
  define('JPEG_QUALITY', 80);
}

