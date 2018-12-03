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


abstract class MSCL_AbstractPlugin {
  private $plugin_name;
  private $plugin_dir;
  private $plugin_dir_relative;
  private $plugin_file;
  private $plugin_filename_relative;
  private $plugin_url;

  private $was_wordpress_loaded;

  private $stylesheets = array();
  private $scripts = array();

  protected function  __construct() {
    // Get information about this class, or about the sub class to be more precise since this class is abstract
    $class_info = new ReflectionClass(get_class($this));
    // NOTE: At least on Windows, symlinks are resolved for "$plugin_file". So, if the plugin directory is a symlink,
    //   it is resolved and the real file path is returned.
    $this->plugin_file = $class_info->getFileName();

    $this->plugin_dir = dirname($this->plugin_file);
    // Since the plugin directory may be symlinked, use the plugin file name instead of the directory.
    $this->plugin_name = basename($this->plugin_file, '.php');

    $this->was_wordpress_loaded = MSCL_is_wordpress_loaded();
    if ($this->was_wordpress_loaded) {
      MSCL_Logging::on_wordpress_loaded();

      $in_plugins_dir = WP_PLUGIN_DIR.'/'.$this->plugin_name;
      if (!is_dir($in_plugins_dir) || realpath($in_plugins_dir) != realpath($this->plugin_dir)) {
        log_error('Could not determined plugin directory relative to the Wordpress installation.');
      }

      // Use defaults in any case (i.e. regardless of whether the plugin directory could be found).
      $this->plugin_dir_relative = $this->plugin_name;
      $this->plugin_filename_relative = $this->plugin_name.'/'.$this->plugin_name.'.php';
      $this->plugin_url = plugins_url($this->plugin_dir_relative);

      add_action('init', array($this, 'wordpress_initialize'));
      add_action('wp_print_styles', array($this, 'enqueue_stylesheets_callback'));
      add_action('admin_print_styles', array($this, 'enqueue_stylesheets_callback'));
      add_action('wp_print_scripts', array($this, 'enqueue_scripts_callback'));
      add_filter('plugin_action_links', array($this, 'add_settings_page_link_callback'), 10, 2);
    }
  }

  /**
   * Returns the name of this plugin (which is based on the file name of the implementing class).
   * @return string
   */
  public function get_plugin_name() {
    return $this->plugin_name;
  }

  /**
   * Returns the base directory of this plugin (without trailing slash). Note that this directory is the canonical path
   * of the plugin directory, i.e. if the plugin directory was a symlink it has been resolved.
   * @return string
   */
  public function get_plugin_dir() {
    return $this->plugin_dir;
  }

  /**
   * Returns the file name relative to the parent directory of the plugin.
   *
   * Replacement for Wordpress' "plugin_basename()" as it doesn't work with files that are not inside the "plugins"
   * directory (but are, for example, symlinked there).
   *
   * @param string $file  the file inside the plugin directory
   * @return string
   */
  public static function get_plugin_basename($file) {
    static $root_dir = null;

    if ($root_dir === null) {
      $root_dir = realpath(dirname(MSCL_PLUGIN_ROOT_DIR));
      $root_dir = str_replace('\\', '/', $root_dir);
    }

    $count = 1;
    return str_ireplace($root_dir, '', str_replace('\\', '/', realpath($file)), $count);
  }

  /**
   * Indicates whether Wordpress was loaded when the instance of this class was created.
   * @return bool
   */
  public function was_wordpress_loaded() {
    return $this->was_wordpress_loaded;
  }

  private function check_wordpress_was_loaded() {
    if (!$this->was_wordpress_loaded) {
      throw new MSCL_ExceptionEx("Wordpress wasn't loaded when this plugin instance was created.", 'wordpress_not_loaded');
    }
  }

  /**
   * Returns the directory of this plugin relative to the Wordpress installation's plugin directory.
   * @return string
   */
  public function get_plugin_dir_relative() {
    $this->check_wordpress_was_loaded();
    return $this->plugin_dir_relative;
  }

  public function get_plugin_url() {
    $this->check_wordpress_was_loaded();
    return $this->plugin_url;
  }

  public function get_plugin_version() {
    $this->check_wordpress_was_loaded();
    if (!function_exists('get_plugin_data')) {
      require_once(ABSPATH.'wp-admin/includes/plugin.php');
    }
    $plugin_data = get_plugin_data($this->plugin_file, false, false);
    return $plugin_data['Version'];
  }

  /**
   * Wordpress callback function (action: init). Don't call directly.
   *
   * Called when Wordpress' init action has happen (and won't be called, if Wordpress isn't loaded). Designed
   * to be overloaded by subclasses.
   */
  public function wordpress_initialize() { }

  /**
   * Wordpress callback function (action: plugin_action_links). Don't call directly.
   *
   * Adds the settings link to the plugin list in the admin backend.
   *
   * @param array of strings $links  each item contains HTML code for one link of a certain plugin in the plugins admin
   *   page
   * @param string $file  the relative file name of the plugin the links belong to (eg. "blogtext/blogtext.php")
   *
   * @return array of string  the modified $links array
   */
  public function add_settings_page_link_callback($links, $file) {
    if ($file == $this->plugin_filename_relative) {
      $option_page = $this->get_main_options_page();
      if ($option_page !== null) {
        $settings_link = '<a href="'.$option_page->get_page_link().'">'.__('Settings').'</a>';
        array_unshift($links, $settings_link); // before other links
      }
    }

    return $links;
  }

  /**
   * Returns the main options page. The link to this page is added to Wordpress' plugin list. Note that you
   * need to override this method to return the correct page.
   * 
   * @return MSCL_OptionPage  the option page or "null", if there's no option page
   */
  protected function get_main_options_page() {
    return null;
  }

  /**
   * Resolves the file name of the file to be enqueued and sets the name, if non has been specified.
   * @param string $file  file to be enqueued, relative to the plugin directory
   * @param string $name  name with which the files is going to be enqueued
   * @return bool  Returns true, if the file can be enqueued; false otherwise.
   */
  private function resolve_enqueable_link(&$file, &$name) {
    $file = '/'.$this->get_plugin_dir_relative().'/'.$file;
    if (!file_exists(WP_PLUGIN_DIR.$file)) {
      log_error("Could not resolve enquable file '$file' because it couldn't be found.");
      return false;
    }

    if ($name === null) {
      $name = $this->get_plugin_name().'_'.basename($file);
    }

    return true;
  }

  public function add_stylesheet($file, $name=null) {
    if ($this->resolve_enqueable_link($file, $name)) {
      $this->stylesheets[] = array($name, $file);
    }
  }

  public function add_frontend_stylesheet($file, $name=null) {
    if (!is_admin()) {
      $this->add_stylesheet($file, $name);
    }
  }

  public function add_backend_stylesheet($file, $name=null) {
    if (is_admin()) {
      $this->add_stylesheet($file, $name);
    }
  }

  /**
   * Wordpress callback function. Don't call directly.
   */
  public function enqueue_stylesheets_callback() {
    foreach ($this->stylesheets as $style) {
      list($name, $file) = $style;
      wp_register_style($name, WP_PLUGIN_URL.$file);
      wp_enqueue_style($name);
    }
  }

  public function add_script($file, $name=null) {
    if ($this->resolve_enqueable_link($file, $name)) {
      $this->scripts[] = array($name, $file);
    }
  }

  public function add_frontend_script($file, $name=null) {
    if (!is_admin()) {
      $this->add_script($file, $name);
    }
  }

  public function add_backend_script($file, $name=null) {
    if (is_admin()) {
      $this->add_script($file, $name);
    }
  }

  /**
   * Wordpress callback function. Don't call directly.
   */
  public function enqueue_scripts_callback() {
    foreach ($this->scripts as $script) {
      list($name, $file) = $script;
      wp_register_script($name, WP_PLUGIN_URL.$file);
      wp_enqueue_script($name);
    }
  }

  public function get_plugin_modification_date($files='/', $exception_when_null=true) {
    if (!is_array($files)) {
      $files = array($files);
    }

    $mod_date = null;
    foreach ($files as $file) {
      $file_path = $this->get_plugin_dir().'/'.$file;
      if (!file_exists($file_path)) {
        throw new Exception("Could not find specified file: ".$file_path);
      }

      $sub_mod_date = self::check_modfication_date($file_path);
      if ($sub_mod_date > $mod_date) {
        $mod_date = $sub_mod_date;
      }
    }

    if ($mod_date === null && $exception_when_null) {
      throw new Exception("Could not find any files.");
    }

    return $mod_date;
  }

  public static function check_modfication_date($file) {
    if (is_dir($file)) {
      $mod_date = null;
      foreach (scandir($file) as $subfile) {
        if ($subfile == '.' || $subfile == '..') {
          continue;
        }
        $sub_mod_date = self::check_modfication_date($file.'/'.$subfile);
        if ($sub_mod_date > $mod_date) {
          $mod_date = $sub_mod_date;
        }
      }
      return $mod_date;
    } else {
      return filemtime($file);
    }
  }
}
?>
