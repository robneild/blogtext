<?php

class MSCL_WPUploadDirDeterminator {
  public static function determine_upload_dir() {
    $upload_dir = self::get_from_wordpress();
    if ($upload_dir) {
      return $upload_dir;
    }

    $upload_dir = self::get_via_script_path_guessing();
    if ($upload_dir) {
      return $upload_dir;
    }

    $upload_dir = self::get_via_file_name_guessing();
    if ($upload_dir) {
      return $upload_dir;
    }

    return null;
  }

  private static function get_from_wordpress() {
    if (!function_exists('wp_upload_dir')) {
      // Wordpress not available. We're most likely in "do.php".
      return null;
    }

    // NOTE: "wp_upload_dir()" will create the upload directory, if it didn't exist.
    $upload_infos = wp_upload_dir();
    if ($upload_infos['error'] != '') {
      // An error occurred while determining the uploads dir; we can't recover from this.
      throw new MSCL_AdminException('The uploads directory is not accessible.', 'Error: '.$upload_infos['error']);
    }

    // uploads dir could be determined
    $uploads_dir = $upload_infos['basedir'];
    if (!$uploads_dir) {
      return null;
    }

    return $uploads_dir;
  }

  private static function get_via_script_path_guessing() {
    // Assuming "do.php" in this directory is executed.
    $script_file = $_SERVER['SCRIPT_FILENAME'];
    $plugin_dir = dirname(dirname(dirname($script_file)));
    return self::get_via_plugin_dir_guessing($plugin_dir);
  }

  private static function get_via_file_name_guessing() {
    // NOTE: __FILE__ will always be resolved when this file is actually a symlink; so it may be unusable
    $plugin_dir = dirname(dirname(dirname(__FILE__)));
    return self::get_via_plugin_dir_guessing($plugin_dir);
  }

  /**
   * Searches for the uploads directory at the default location based on the location of the plugin directory.
   * @param string $plugin_dir  the absolute path to the plugin directory
   * @return null|string
   */
  private static function get_via_plugin_dir_guessing($plugin_dir) {
    $default_upload_dir = dirname(dirname($plugin_dir)).'/uploads';
    if (is_dir($default_upload_dir)) {
      // upload directory exists at the default position
      return $default_upload_dir;
    }

    return null;
  }
}

?>