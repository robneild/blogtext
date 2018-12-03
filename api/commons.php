<?php
#########################################################################################
#
# Copyright 2010-2015  Maya Studios (http://www.mayastudios.com)
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
 * Loads commonly used APIs.
 */

require_once(dirname(__FILE__).'/base-type-extensions.php');

require_once(dirname(__FILE__).'/ClassLoader.php');

\MSCL\ClassLoader::register('\\MSCL', dirname(__FILE__));

/**
 * The root directory of this plugin.
 */
define('MSCL_PLUGIN_ROOT_DIR', dirname(dirname(__FILE__)));

class MSCL_Api {
  const THUMBNAIL_API = 'thumbnail/api.php';
  const THUMBNAIL_CACHE = 'thumbnail/cache.php';
  const CACHE_API = 'cache-api.php';
  const OPTIONS_API = 'options-api.php';
  const USER_API = 'user-api.php';
  const GESHI = '../thirdparty/geshi/geshi.php';

  private static $api_dir = null;
  private static $loaded_apis = array();

  /**
   * Loads the specified API.
   * @param string $api_file
   */
  public static function load($api_file) {
    if (self::$api_dir === null) {
      self::$api_dir = dirname(__FILE__).'/';
    }

    if (isset(self::$loaded_apis[$api_file])) {
      // Api already loaded
      return;
    }
    self::$loaded_apis[$api_file] = true;

    if ($api_file == self::GESHI) {
      if (class_exists('GeSHi')) {
        // TODO: Check Geshi version and display a warning to the admin, if the version is too low
        // Geshi has already been loaded by another plugin
        return;
      }
    }

    require_once(self::$api_dir.$api_file);
  }

  public static function get_mod_date() {
    static $mod_date = null;
    if ($mod_date === null) {
      $mod_date = MSCL_AbstractPlugin::check_modfication_date(dirname(__FILE__).'/');
    }
    return $mod_date;
  }
}

MSCL_Api::load('error-api.php');
MSCL_Api::load('logging/logging-api.php');
MSCL_Api::load('plugin-api.php');

function MSCL_is_wordpress_loaded() {
  static $is_loaded = false;
  if (!$is_loaded) {
    // Use two arbitrary function to check whether Wordpress is loaded. Only check, if it has been "false"
    // until now. If Wordpress has been loaded, we don't need to check this anymore.
    $is_loaded = function_exists('wp_load_alloptions') && function_exists('plugin_basename');
  }
  return $is_loaded;
}

function MSCL_check_wordpress_is_loaded() {
  if (!MSCL_is_wordpress_loaded()) {
    throw new MSCL_ExceptionEx("Wordpress is not loaded.", 'wordpress_not_loaded');
  }
}

/**
 * Includes (actually requires) the specified file. Usually invoked like this:
 *
 *   MSCL_require_once('file/to/include.php', __FILE__);
 *
 */
function MSCL_require_once($relative_required_file, $base_file) {
  static $base_files = array();

  $base_dir = @$base_files[$base_file];
  if (!$base_dir) {
    $base_dir = dirname($base_file).'/';
    $base_files[$base_file] = $base_dir;
  }

  require_once($base_dir.$relative_required_file);
}
