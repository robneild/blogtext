<?php

require_once(dirname(__FILE__).'/../api/commons.php');
MSCL_Api::load(MSCL_Api::CACHE_API);

interface IMarkupCacheHandler {
  /**
   * Converts the specified markup into HTML code. This method must explicitely convert the markup code
   * without using cached HTML code for this markup.
   *
   * @param string $markup_content  the content to be converted. May not be identical to the content in the
   *   $post parameter, in case this is an excerpt or post with more-link.
   * @param object $post  the post to be converted
   * @param bool $is_rss  indicates whether the content is to be displayed in an RSS feed (RSS reader). If
   *   this is false, the content is to be displayed in the browser.
   *
   * @return string  the HTML code
   */
  function convert_markup_to_html_uncached($markup_content, $post, $is_rss);

  /**
   * Determines the externals for the specified post. Externals are "links" to things that, if changed, will
   * invalidate the post's cache. Externals are for example thumbnails or links to other posts. Changed means
   * the "link" target has been deleted or created (if it didn't exist before), or for thumbnails that the
   * thumbnail's size has changed.
   *
   * @param object $post  the post the be checked
   * @param array $thumbnail_ids  an array of the ids of the thumbnails used in the post
   */
  function determine_externals($post, &$thumbnail_ids);
}



/**
 * Caches the content for markup plugins. Automatically detects when the content must be recreated. You can
 * store an instance of this class as static variable in your plugin class.
 */
class MarkupCache {
  const CONTENT_CACHE_PREFIX = 'content_';
  const CONTENT_CACHE_KEY = 'cache_';
  const CONTENT_CACHE_DATE_KEY = 'cache_date_';

  const EXTERNALS_CACHE_PREFIX = 'externals_determine_date';

  const TYPE_SINGLE_POST_VIEW = 'single_';
  const TYPE_LOOP_POST_VIEW = 'loop_';
  const TYPE_RSS_VIEW = 'rss_';

  private $markup_modification_date;

  private $cache_prefix;

  /**
   * Constructor.
   *
   * @param string $cache_prefix  the prefix to be used for the cached contents of this markup plugin.
   */
  public function __construct($cache_prefix) {
    $this->cache_prefix = $cache_prefix;

    // modification date
    $this->markup_modification_date = max(
        BlogTextPlugin::get_instance()->get_plugin_modification_date(array('markup/', 'util.php')),
        MSCL_Api::get_mod_date()
        );
    $this->markup_modification_date = MarkupUtil::create_mysql_date($this->markup_modification_date);
  }

  /**
   * Returns the HTML code for the specified markup. If the code is available in the cache and the post's code
   * hasn't been changed, the cached HTML code is returned.
   *
   * @param IMarkupCacheHandler $cache_handler  the cache handler to be used
   * @param string $markup_content  the content to be converted. May not be identical to the content in the
   *   $post parameter, in case this is an excerpt or post with more-link.
   * @param object $post  the post to be converted
   * @param bool $is_rss  indicates whether the content is to be displayed in an RSS feed (RSS reader). If
   *   this is false, the content is to be displayed in the browser.
   *
   * @return string  the HTML code
   */
    public function get_html_code($cache_handler, $markup_content, $post, $is_rss)
    {
        // NOTE: Always check this (even if the cached content can't be used), so that externals can be
        //   registered.
        $are_externals_uptodate = $this->check_and_register_externals($cache_handler, $post);

        // We need to have two different cached: one for when a post is displayed alone and one when it's
        // displayed together with other posts (in the loop). HTML IDs may vary and if there's a more link the
        // contents differ dramatically. (The same applies for RSS feed item which can be dramatically trimmed
        // down.)
        if ($is_rss)
        {
            $content_cache = $this->get_post_content_cache(self::TYPE_RSS_VIEW, $post->ID);
            $cache_name    = 'rss-item';
        }
        else if (is_singular())
        {
            $content_cache = $this->get_post_content_cache(self::TYPE_SINGLE_POST_VIEW, $post->ID);
            $cache_name    = 'single-page';
        }
        else
        {
            $content_cache = $this->get_post_content_cache(self::TYPE_LOOP_POST_VIEW, $post->ID);
            $cache_name    = 'loop-view';
        }

        // reuse cached content; significantly speeds up the whole process
        $cached_content      = $content_cache->get_value(self::CONTENT_CACHE_KEY);
        $cached_content_date = $content_cache->get_value(self::CONTENT_CACHE_DATE_KEY);
        if (   !empty($cached_content)
            && $cached_content_date >= $post->post_modified_gmt
            && $cached_content_date >= $this->markup_modification_date
            && $are_externals_uptodate)
        {
            return $cached_content;
        }

        $html_code = $cache_handler->convert_markup_to_html_uncached($markup_content, $post, $is_rss);

        // update cache
        $mod_date = MarkupUtil::create_mysql_date();
        $content_cache->set_value(self::CONTENT_CACHE_DATE_KEY, $mod_date);
        $content_cache->set_value(self::CONTENT_CACHE_KEY, $html_code);

        log_info("Cache for post $post->ID ($cache_name) has been updated.");

        return $html_code;
    }

  private function check_and_register_externals($cache_handler, $post) {
    $externals_last_determined_cache = $this->get_externals_last_determined_cache();
    $externals_last_determined_date = $externals_last_determined_cache->get_value($post->ID);

    // Check whether the post's code has changed and the externals (thumbnails, internal links) need to be
    // determined again.
    if (   $externals_last_determined_date >= $post->post_modified_gmt
        && $externals_last_determined_date >= $this->markup_modification_date) {
      // Code hasn't changed, so the externals haven't changed. Now check whether the externals are
      // up-to-date.
      if (!MSCL_ThumbnailCache::are_post_thumbnails_uptodate($post->ID)) {
        log_info("Externals for post $post->ID need to be updated.");
        return false;
      } else {
        return true;
      }
    }

    // Code has changed. Redetermine the externals.
    $thumbnail_ids = array();
    $cache_handler->determine_externals($post, $thumbnail_ids);

    // registed used thumbnails
    MSCL_ThumbnailCache::register_post($post->ID, $thumbnail_ids);

    // Store date of last thumbs check
    $externals_last_determined_cache->set_value($post->ID, MarkupUtil::create_mysql_date());

    log_info("Externals for post $post->ID have been determined.");

    return false;
  }

  /**
   * Clears the page cache completely or only for the specified post.
   * @param int|null $post  if this is "null", the whole cache will be cleared. Otherwise only the cache for
   *   the specified post/page id will be cleared.
   */
  public function clear_page_cache($post=null) {
    if ($post === null) {
      $this->get_content_cache()->clear_cache();
      log_info("The complete page cache has been cleared.");
    } else {
      if (is_numeric($post)) {
        $post = (int)$post;
      } else {
        // Check this so that not arbitrary thing are deleted here
        throw new Exception("Post id must be an integer, but got: ".print_r($post, true));
      }

      $this->get_post_content_cache($post, null)->clear_cache();

      log_info("The page cache for post $post has been cleared.");
    }

    // NOTE: Don't clear the externals cache so that we don't loose the information about which thumbs have
    //   already created.
  }


  /**
   * Returns the cache object for the complete content cache (ie. the cache for all posts and all types).
   *
   * @return MSCL_PersistentObjectCache
   */
  protected function get_content_cache() {
    return new MSCL_PersistentObjectCache($this->cache_prefix.self::CONTENT_CACHE_PREFIX);
  }

  /**
   * Returns the cache for the specified post and view type.
   *
   * @param int $post_id  the id of the post to be retrieve
   * @param string $view_type  the type of content for which the cache is to be returned. Should be one of
   *   the "TYPE" constants in this class. Can be empty in which case a cache for all types of the specified
   *   post will be returned.
   *
   * @return MSCL_PersistentObjectCache
   */
  protected function get_post_content_cache($post_id, $view_type) {
    if (empty($view_type)) {
      $view_type = '';
    }
    return new MSCL_PersistentObjectCache($this->cache_prefix.self::CONTENT_CACHE_PREFIX.$post_id.$view_type);
  }

  /**
   * Returns the cache containing the date when the thumbnails were checked the last time.
   *
   * @return MSCL_PersistentObjectCache
   */
  protected function get_externals_last_determined_cache() {
    return new MSCL_PersistentObjectCache($this->cache_prefix.self::EXTERNALS_CACHE_PREFIX);
  }
}
?>
