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


class MSCL_UserApi {
  public static function is_logged_in() {
    if (!function_exists('is_user_logged_in')) {
      // Wordpress isn't loaded.
      return false;
    }

    return is_user_logged_in();
  }

  /**
   * Returns whether the user has the specified capability. Returns "false", if Wordpress isn't loaded, or if
   * the user isn't logged in. Throws an exception if run before the "init" action has "executed".
   *
   * @param string $capability the capability to check for; see
   *   http://codex.wordpress.org/Roles_and_Capabilities
   *
   * @return bool
   */
  public static function current_user_can($capability, $check_for_loaded_too_sone=true) {
    if (!function_exists('current_user_can')) {
      // Wordpress isn't loaded.
      return false;
    }

    if (!function_exists('wp_get_current_user')) {
      if ($check_for_loaded_too_sone) {
        throw new Exception("The user API has not yet been loaded. Use this method only after the 'init' action.");
      } else {
        return false;
      }
    }

    return current_user_can($capability);
  }

  public static function can_manage_options($check_for_loaded_too_sone=true) {
    return self::current_user_can('manage_options', $check_for_loaded_too_sone);
  }

  public static function is_editor() {
    return    self::current_user_can('edit_posts') || self::current_user_can('publish_posts')
           || self::current_user_can('edit_pages') || self::current_user_can('publish_pages');
  }
}
?>
