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


MSCL_Api::load(MSCL_Api::OPTIONS_API);

class BlogTextErrorNotifier extends MSCL_ErrorNotifier {
  private static function in_editor_interface($including_overview=true) {
    if (   strstr($_SERVER['REQUEST_URI'], 'wp-admin/post-new.php')
        || strstr($_SERVER['REQUEST_URI'], 'wp-admin/post.php')) {
      return true;
    }

    if ($including_overview && strstr($_SERVER['REQUEST_URI'], 'wp-admin/edit.php')) {
      return true;
    }

    return false;
  }

  /**
   * Checks for warnings that concern only editors (ie. users that can publish and edit posts).
   *
   * @return array|string|null Returns the warning message(s) for the errors that have been found. If there's
   *   only one, its returned as string. Multiple warning messages will be returned as array of strings. If no
   *   warnings are found, returns "null" or an empty array.
   */
  protected function check_for_editor_warnings() {
    // check for problematic editor settings; only display in the backend.
    $warnings = array();
    if (self::is_in_backend()) {
      if (MSCL_BoolOption::is_true(get_user_option('rich_editing')) && self::in_editor_interface()) {
        // we're in the editing interface for posts or pages; display the HTML editor warning
        $warnings[] = "<b>Warning:</b> The <em>visual editor</em> is enabled. Don't use it to edit your "
                    . "BlogText postings as this may result in problems. You can disable it in your "
                    . "<a href=\"".get_admin_url(null, 'profile.php')."\">user settings</a>.";
      }
    }

    return $warnings;
  }

  /**
   * Checks for warnings that concern only admin (ie. users that can manage the blog's options).
   *
   * @return array|string|null Returns the warning message(s) for the errors that have been found. If there's
   *   only one, its returned as string. Multiple warning messages will be returned as array of strings. If no
   *   warnings are found, returns "null" or an empty array.
   */
  protected function check_for_admin_warnings() {
    if (   self::is_in_backend()
        && !BlogTextSettings::disable_fix_invalid_xhtml_warning()
        && MSCL_BoolOption::is_true(get_option('use_balanceTags'))) {
      return "<b>Warning:</b> You should disable the option which tells Wordpress to correct <em>invalidly "
           . "nested XHTML automatically</em>. You can do this in "
           . "<a href=\"".get_admin_url(null, 'options-writing.php')."\">Writing Settings</a>.";
    }
    return null;
  }

  /**
   * Checks for errors that concern only admin (ie. users that can manage the blog's options).
   *
   * @return array|string|null Returns the error message(s) for the errors that have been found. If there's
   *   only one, its returned as string. Multiple error messages will be returned as array of strings. If no
   *   errors are found, returns "null" or an empty array.
   */
  protected function check_for_admin_errors() {
    //
    // check for thumbnail cache directory errors
    //
    MSCL_Api::load(MSCL_Api::THUMBNAIL_API);

    $error_msg = array();

    try {
      // check whether the cache directories could be created
      if (   !is_writable(MSCL_ThumbnailCache::get_local_file_cache_dir())
          || !is_writable(MSCL_ThumbnailCache::get_remote_file_cache_dir())) {
        $error_msg[] = "Thumbnail cache directory is not writable.";
      }
    } catch (MSCL_AdminException $e) {
      $error_msg[] = $e->getMessage();
    }
    return $error_msg;
  }
}

?>
