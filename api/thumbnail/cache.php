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


require_once(dirname(__FILE__).'/settings.php');

class MSCL_ThumbnailCache {
  const DATABASE_SCHEMA_VERSION = '1.0';
  /**
   * Used to determine whether a post has been registered or not. This is necessary to distinguish a post that
   * hasn't been registered with a post that doesn't use any thumbnails.
   */
  const REGISTERED_MARKER = 'post_registered';

  const THUMBNAIL_TYPE = 'thumb';

  private $local_file_cache_dir_path = null;
  private $remote_file_cache_dir_path = null;
  private $table_name;

  private function  __construct() {
    require_once(dirname(__FILE__).'/wp-upload-dir.php');

    $base_dir = MSCL_WPUploadDirDeterminator::determine_upload_dir();
    if (!$base_dir) {
      throw new MSCL_AdminException('The uploads directory could not be determined.');
    }

    $this->local_file_cache_dir_path = str_replace('//', '/', $base_dir.'/'.LOCAL_IMG_CACHE_DIR);
    $this->remote_file_cache_dir_path = str_replace('//', '/', $base_dir.'/'.REMOTE_IMG_CACHE_DIR);

    if (MSCL_is_wordpress_loaded()) {
      // Only available when running from inside of Wordpress
      // Note that this isn't a problem though, as the directories already have been created when creating
      // the token files.
      if (!file_exists($this->local_file_cache_dir_path)) {
        if (!wp_mkdir_p($this->local_file_cache_dir_path)) {
          throw new MSCL_AdminException("Could not created thumbnail cache directory.",
                                   "Location: ".$this->local_file_cache_dir_path." (maybe not writable?)",
                                   'could_not_create_cache_local_dir');
        }
      }

      if (!file_exists($this->remote_file_cache_dir_path)) {
        if (!wp_mkdir_p($this->remote_file_cache_dir_path)) {
          throw new MSCL_AdminException("Could not created thumbnail cache directory.",
                                   "Location: ".$this->remote_file_cache_dir_path." (maybe not writable?)",
                                   'could_not_create_cache_remote_dir');
        }
      }

      $this->check_cache_database();
    }
  }

  private function check_cache_database() {
    // see: http://codex.wordpress.org/Creating_Tables_with_Plugins
    global $wpdb;

    $this->table_name = $wpdb->prefix.'media_cache_registry';

    if($wpdb->get_var("SHOW TABLES LIKE '$this->table_name'") != $this->table_name) {
      // create table
      // IMPORTANT: There must be 2 spaces after "PRIMARY KEY"!!!
      $sql = "CREATE TABLE $this->table_name (
              post_id bigint(20) unsigned NOT NULL,
              media_type varchar(20) NOT NULL,
              media_id varchar(200) NOT NULL,
              PRIMARY KEY  (post_id, media_type, media_id)
            );";

      require_once(ABSPATH.'wp-admin/includes/upgrade.php');
      dbDelta($sql);

      add_option('media_cache_registry_version', self::DATABASE_SCHEMA_VERSION);
    }
  }

  private static function get_instance() {
    static $instance = null;
    if ($instance === null) {
      $instance = new MSCL_ThumbnailCache();
    }

    return $instance;
  }

  public static function get_local_file_cache_dir() {
    return self::get_instance()->local_file_cache_dir_path;
  }

  public static function get_remote_file_cache_dir() {
    return self::get_instance()->remote_file_cache_dir_path;
  }

  private static function get_table_name() {
    return self::get_instance()->table_name;
  }

  public static function register_post($post_id, $thumbnail_ids) {
    global $wpdb;
    MSCL_check_wordpress_is_loaded();

    @mysql_query("BEGIN", $wpdb->dbh); // begin transaction

    $table_name = self::get_table_name();

    $old_thumb_ids = array_flip(self::get_post_thumbnail_ids($post_id, false));
    if (!isset($old_thumb_ids[self::REGISTERED_MARKER])) {
      // post has never been registered. Register it.
      $wpdb->insert($table_name, array('post_id' => $post_id, 'media_id' => self::REGISTERED_MARKER,
                                       'media_type' => self::THUMBNAIL_TYPE));
    }

    // FIXME: Some times all thumbnails are deleted
    if (count($old_thumb_ids) > 1 && count($thumbnail_ids) == 0) {
      log_exception("Thumbnail deletion problem occured.");
    }

    // insert new thumb ids
    foreach ($thumbnail_ids as $thumb_id) {
      if (isset($old_thumb_ids[$thumb_id])) {
        // thumb already exists
        unset($old_thumb_ids[$thumb_id]);
      } else {
        // thumb hasn't been used before - insert
        $wpdb->insert($table_name, array('post_id' => $post_id, 'media_id' => $thumb_id,
                                         'media_type' => self::THUMBNAIL_TYPE));
      }
    }

    // delete no longer used thumb id
    foreach ($old_thumb_ids as $thumb_id => $unused) {
      if ($thumb_id == self::REGISTERED_MARKER) {
        continue;
      }
      $wpdb->query($wpdb->prepare("DELETE FROM $table_name WHERE media_id = %s AND media_type = %s",
                                  $thumb_id, self::THUMBNAIL_TYPE));
      // check whether it's still being used
      if (!self::is_thumbnail_used($thumb_id)) {
        // NOTE: If the transaction fails for some reason, it doesn't matter as the thumbnail can be
        //   recreated.
        MSCL_ThumbnailApi::delete_thumbnail($thumb_id);
      }
    }

    @mysql_query("COMMIT", $wpdb->dbh); // commit transaction
  }

  public static function is_thumbnail_used($thumbnail_id) {
    global $wpdb;
    MSCL_check_wordpress_is_loaded();
    $table_name = self::get_table_name();
    $num = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE media_id = %s AND media_type = %s",
                                         $thumbnail_id, self::THUMBNAIL_TYPE));
    return $num > 0;
  }

  public static function get_post_thumbnail_ids($post_id, $filter_registered_marker=true) {
    global $wpdb;
    MSCL_check_wordpress_is_loaded();
    $table_name = self::get_table_name();
    $sql = "SELECT media_id FROM $table_name WHERE post_id = %d AND media_type = %s";
    if ($filter_registered_marker) {
      $sql .= 'AND media_id != \''.self::REGISTERED_MARKER.'\'';
    }

    return $wpdb->get_col($wpdb->prepare($sql, $post_id, self::THUMBNAIL_TYPE));
  }

  public static function are_post_thumbnails_uptodate($post_id, $do_remote_check=false)
  {
    $thumb_ids = self::get_post_thumbnail_ids($post_id, false);

    if (count($thumb_ids) == 0)
    {
      // this post has never been registered in the cache; return false
      return false;
    }

    foreach ($thumb_ids as $thumb_id)
    {
      if ($thumb_id == self::REGISTERED_MARKER)
      {
        // Marker that this post has been registered. Ignore it.
        continue;
      }

      if (!MSCL_ThumbnailApi::doesThumbnailInfoFileExist($thumb_id))
      {
        // Happens if the thumbnail cache has been cleared.
        return false;
      }

      $thumb = MSCL_ThumbnailApi::getThumbnailFromCacheId($thumb_id);
      if ($thumb->is_remote_image() && !$do_remote_check)
      {
        // Ignore remote images, as they have not put their size into the HTML code.
        continue;
      }

      if (!$thumb->isUpToDate())
      {
        return false;
      }
    }

    return true;
  }
}
?>
