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


abstract class MSCL_Option {
  private $option_name;
  private $default_value;
  private $title;
  private $doc;

  private $custom_validator_func;

  /**
   * Constructor.
   *
   * @param string $option_name name/id for this option; must be unique throughout all wordpress options
   * @param mixed $default_value the option's default value
   * @param <type> $title
   * @param <type> $doc
   */
  public function __construct($option_name, $title, $default_value, $doc='', $custom_validator_func=null) {
    $this->option_name = $option_name;
    $this->default_value = $default_value;
    $this->title = $title;
    $this->doc = $doc;

    $this->custom_validator_func = $custom_validator_func;
  }

  public function get_id() {
    return 'field_'.$this->option_name;
  }

  public function get_name() {
    return $this->option_name;
  }

  public function get_title() {
    return $this->title;
  }

  public function get_doc() {
    return $this->doc;
  }

  public function print_option() {
    $this->print_input_control();
    $doc = $this->get_doc();
    if (!empty($doc)) {
      print '<p class="description">'.$doc.'</p>';
    }
  }

  protected abstract function print_input_control();

  public function get_default_value() {
    return $this->default_value;
  }

  public function get_value() {
    return get_option($this->option_name, $this->get_default_value());
  }

  public function set_value($new_value) {
    update_option($this->option_name, $this->validate($new_value));
  }

  public function validate($input) {
    // IMPORTANT: We need to convert the value here to a string. Otherwise a "false"/0/... value won't be
    //   be stored. This is a problem, if the default value isn't "false"/0/... . In this case, the option
    //   would always be the default value.
    if ($this->check_value($input)) {
      if ($this->custom_validator_func != null) {
        if (call_user_func($this->custom_validator_func, $input)) {
          return $this->convert_to_string($input);
        }
      } else {
        return $this->convert_to_string($input);
      }
    }
    return $this->convert_to_string($this->get_value());
  }

  protected function convert_to_string($input) {
    return (string)$input;
  }

  protected abstract function check_value(&$input);
}


class MSCL_TextfieldOption extends MSCL_Option {
  private $size;

  public function __construct($option_name, $title, $size, $default_value, $doc='', $custom_validator_func=null) {
    parent::__construct($option_name, $title, $default_value, $doc, $custom_validator_func);
    switch ($size) {
      case 'short':
        $this->size = 10;
        break;
      case 'medium':
        $this->size = 20;
        break;
      case 'long':
        $this->size = 40;
        break;
      default:
        $this->size = $size;
        break;
    }
  }

  protected function print_input_control() {
    echo '<input id="'.$this->get_id().'" name="'.$this->get_name().'" size="'.$this->size.'" value="'.$this->get_value().'"/>';
  }

  protected function check_value(&$input) {
    return true;
  }
}


class MSCL_TextareaOption extends MSCL_Option {
  private $cols;
  private $rows;

  public function __construct($option_name, $title, $cols, $rows, $default_value, $doc='', $custom_validator_func=null) {
    parent::__construct($option_name, $title, $default_value, $doc, $custom_validator_func);
    switch ($cols) {
      case 'short':
        $this->cols = 10;
        break;
      case 'medium':
        $this->cols = 20;
        break;
      case 'long':
        $this->cols = 40;
        break;
      default:
        $this->cols = $cols;
        break;
    }
    $this->rows = $rows;
  }

  protected function print_input_control() {
    echo '<textarea id="'.$this->get_id().'" name="'.$this->get_name().'" cols="'.$this->cols.'" rows="'.$this->rows.'">'.$this->get_value().'</textarea>';
  }
  
  protected function check_value(&$input) {
    return true;
  }
}


class MSCL_BoolOption extends MSCL_Option {
  private static $TRUE_VALUES = array('true', 'yes', 'on');

  public function __construct($option_name, $title, $default_value, $doc='', $custom_validator_func=null) {
    if (!is_bool($default_value)) {
      throw new Exception("The default value must be boolean value.");
    }
    parent::__construct($option_name, $title, $default_value, $doc, $custom_validator_func);
  }

  protected function print_input_control() {
    echo '<input type="checkbox" id="'.$this->get_id().'" name="'.$this->get_name().'" value="true"'.($this->get_value() ? ' checked="checked"' : '').'/>';
  }

  public static function is_true($value) {
    if ($value === null) {
      return false;
    }
    
    if (is_bool($value)) {
      return $value;
    }

    if (is_numeric($value)) {
      return (int)$value != 0;
    }

    if (!is_string($value)) {
      return false;
    }

    $value = strtolower(trim($value));
    foreach (self::$TRUE_VALUES as $true_val) {
      if ($true_val == $value) {
        return true;
      }
    }

    return false;
  }

  protected function check_value(&$input) {
    $input = self::is_true($input);
    return true;
  }
}

class MSCL_IntOption extends MSCL_TextfieldOption {
  public function __construct($option_name, $title, $default_value, $doc='', $custom_validator_func=null) {
    if (!is_int($default_value)) {
      throw new Exception("The default value must be integer value.");
    }
    parent::__construct($option_name, $title, 'short', $default_value, $doc, $custom_validator_func);
  }

  protected function check_value(&$input) {
    if (is_int($input)) {
      return true;
    }

    $input = trim($input);
    $input = intval($input);
    return true;
  }
}

class MSCL_ChoiceOption extends MSCL_Option {
  private $choices;

  public function __construct($option_name, $title, $choices, $default = 0, $doc='', $custom_validator_func=null) {
    if (!is_array($choices)) {
      throw new Exception("The choices must be an array.");
    }
    
    // Convert choices
    $converted_choices = array();
    foreach ($choices as $key => $value) {
      if (is_int($key)) {
        // regular array - use value as id
        $converted_choices[] = array($value, $value);
      } else {
        $converted_choices[] = array($key, $value);
      }
    }

    parent::__construct($option_name, $title, $converted_choices[$default][0], $doc, $custom_validator_func);
    $this->choices = $converted_choices;
  }

  protected function print_input_control() {
    echo '<select id="'.$this->get_id().'" name="'.$this->get_name().'" size="1">';
    foreach ($this->choices as $choice) {
      echo '  <option value="'.$choice[0].'"'
           .($this->get_value() == $choice[0] ? ' selected="selected"' : '').'>'
           // Add some spaces here so that the text isn't directly at the down arrow
           .$choice[1].'&nbsp;&nbsp;</option>';
    }
    echo '</select>';
  }

  protected function check_value(&$input) {
    // check whether the choice is valid
    $input = trim($input);
    foreach ($this->choices as $choice) {
      if ($input == $choice[0]) {
        return true;
      }
    }

    // invalid value
    return false;
  }
}

/**
 * Represents a collection of options that are represented under a common heading.
 */
class MSCL_OptionsPageSection {
  private $section_name;
  private $title;
  private $doc;

  private $options = array();

  public function __construct($section_name, $title, $doc) {
    $this->section_name = $section_name;
    $this->title = $title;
    $this->doc = $doc;
  }

  public function get_name() {
    return $this->section_name;
  }

  public function get_title() {
    return $this->title;
  }

  public function get_doc() {
    return $this->doc;
  }

  public function print_doc_html() {
    if (empty($this->doc)) {
      return;
    }
    echo '<p>'.htmlspecialchars($this->get_doc()).'</p>';
  }
  
  public function add_option($option) {
    $this->options[$option->get_name()] = $option;
  }
  
  public function get_options() {
    return $this->options;
  }
}

//
// See: http://codex.wordpress.org/Creating_Options_Pages#See_It_All_Together_2
//

abstract class MSCL_AbstractOptionsForm {
  private $form_id;

  private $updated_options = array();
  private $settings_error_changed = false;

  /**
   * Constructor
   * @param string $form_id the id of this form. Can be the same as the id of the options page this form is
   *   contained in.
   */
  public function __construct($form_id) {
    $this->form_id = $form_id;

    // Check whether options have been updated.
    if (   isset($_REQUEST['action'])
        && $_REQUEST['action'] == 'update' 
        && @$_REQUEST['option_page'] == $this->get_form_id()) {
      add_action('updated_option', array($this, 'updated_option_wrapper'), 10, 3);
      add_filter('wp_redirect', array($this, 'option_update_finished_wrapper'));
    }
  }

  public function get_form_id() {
    return $this->form_id;
  }

  /**
   * Registers the settings in this form. Usually you should not call this methods. It's only used by
   * "MSCL_OptionsPage".
   */
  public abstract function register_settings();

  /**
   * Outputs the HTML code for this form. You should not call this method directly. It's only used by
   * "MSCL_OptionsPage". You can, however, overload it to adjust the HTML code.
   */
  public abstract function print_form();

  /**
   * Don't call this. This method needs to be public so that it can be used by Wordpress.
   */
  public function updated_option_wrapper($option_name, $old_value, $new_value) {
    $this->updated_options[$option_name] = array($old_value, $new_value);
    $this->on_option_updated($option_name, $old_value, $new_value);
  }

  /**
   * Don't call this. This method needs to be public so that it can be used by Wordpress.
   */
  public function option_update_finished_wrapper($location) {
    // NOTE: We abuse the "wp_redirect" filter here. If we had not sufficient rights to edit this option page,
    //   Wordpress would have "died" before getting here.
    //   Still we need to check whether the passed URL is really the one we're looking for. This location
    //   address is constructed in "wordpress/wp-admin/options.php".
    $query_vars = parse_url($location, PHP_URL_QUERY);
    parse_str($query_vars, $query_vars_arr);
    if (isset($query_vars_arr['settings-updated']) && $query_vars_arr['settings-updated'] == true) {
      $this->cleanup_settings_errors();
      $this->on_options_updated($this->updated_options);
      if ($this->settings_error_changed) {
        // Store the errors again as this already happend when we get here.
        // copied from "wordpress/wp-admin/options.php"
        set_transient('settings_errors', get_settings_errors(), 30);
      }
    }
    return $location;
  }

  /**
   * Adds the specified update or error message.
   * 
   * @param string $code Slug-name to identify the error. Used as part of 'id' attribute in HTML output.
   * @param string $message The formatted message text to display to the user (will be shown inside styled <div> and <p>)
   * @param string $type The type of message it is, controls HTML class. Use 'error' or 'updated'.
   */
  protected function add_settings_error($code, $message, $type = 'error') {
    add_settings_error('general', $code, $message, $type);
    $this->settings_error_changed = true;
  }

  private function cleanup_settings_errors() {
    global $wp_settings_errors;

    if (!isset($wp_settings_errors)) {
      return;
    }

    if (   count($this->updated_options) == 0
        && count($wp_settings_errors) == 1
        && $wp_settings_errors[0]['code'] == 'settings_updated') {
      // Nothing updated - remove "update successful" message
      $wp_settings_errors = array();
      $this->settings_error_changed = true;
    }
  }

  /**
   * Checks whether the specified option has been updated.
   *
   * @param string|object $option
   * @return bool
   */
  protected function is_option_updated($option) {
    if (is_object($option)) {
      $option = $option->get_name();
    }
    return array_key_exists($option, $this->updated_options);
  }

  /**
   * This method is called when an option of this form has been updated.
   *
   * @param string $option_name the name of this option
   * @param mixed $old_value
   * @param mixed $new_value
   */
  protected function on_option_updated($option_name, $old_value, $new_value) { }

  /**
   * This method is called when options have sucessfully saved. Overload it, if you need to do something in
   * this case. Does nothing by default.
   *
   * @param array $updated_options contains all options that were updated. May be empty when no options have
   *   been updated. The keys are the option names where the value is an array "(old_value, new_value)".
   */
  protected function on_options_updated($updated_options) { }
}

/**
 * Represents a options form that can be placed on a "MSCL_OptionsPage". Each options form can have multiple
 * "OptionSection"s which in turn contain the actual options.
 *
 * NOTE: The difference between an options section and an options form is that the form contains the "Save"
 *   button to apply the option changes the user made. A section is just a form of grouping several options
 *   in a form. Usually you have just one form per options page (which in turn contains all available
 *   options). There may, however, be situations in which multiple, separate forms are required on one options
 *   page. A special case is the "MSCL_ButtonsForm".
 *
 * NOTE 2: A form needs to have at least one section.
 */
class MSCL_OptionsForm extends MSCL_AbstractOptionsForm {
  private $sections = array();

  /**
   * Constructor
   * @param string $form_id the id of this form. Can be the same as the id of the options page this form is
   *   contained in.
   */
  public function __construct($form_id) {
    parent::__construct($form_id);
  }

  public function add_section($section) {
    $this->sections[$section->get_name()] = $section;
  }

  /**
   * Registers the settings in this form. Usually you should not call this methods. It's only used by
   * "MSCL_OptionsPage".
   */
  public function register_settings() {
    foreach ($this->sections as $section) {
      // add section to page
      add_settings_section($section->get_name(), $section->get_title(), array($section, 'print_doc_html'), 
                           $this->get_form_id());

      foreach ($section->get_options() as $option) {
        // register option
        register_setting($this->get_form_id(), $option->get_name(), array($option, 'validate'));

        // add option to section
        $title = '<label for="'.$option->get_id().'">'.$option->get_title().'</label>';
        add_settings_field($option->get_name(), $title, array($option, 'print_option'),
                           $this->get_form_id(), $section->get_name());
      }
    }
  }

  /**
   * Outputs the HTML code for this form. You should not call this method directly. It's only used by
   * "MSCL_OptionsPage". You can, however, overload it to adjust the HTML code.
   */
  public function print_form() {
?>
<form action="options.php" method="post">
  <?php settings_fields($this->get_form_id()); ?>
  <?php do_settings_sections($this->get_form_id()); ?>

  <p class="submit">
    <input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
  </p>

</form>
<?php
  }
}


abstract class MSCL_ButtonsForm extends MSCL_AbstractOptionsForm {
  private $buttons = array();

  public function __construct($form_id) {
    parent::__construct($form_id);
  }

  public function add_button($button_id, $button_caption, $doc='') {
    $this->buttons[] = array($button_id, $button_caption, $doc);
  }

  /**
   * Registers the settings in this form. Usually you should not call this methods. It's only used by
   * "MSCL_OptionsPage".
   */
  public function register_settings() {
    // We need to register a fake setting here so that the form is accepted.
    register_setting($this->get_form_id(), 'buttons_form_fake_settings');
  }

  /**
   * Outputs the HTML code for this form. You should not call this method directly. It's only used by
   * "MSCL_OptionsPage". You can, however, overload it to adjust the HTML code.
   */
  public function print_form() {
?>
<form action="options.php" method="post">
  <?php settings_fields($this->get_form_id()); ?>

  <?php $this->print_form_items(); ?>
  
  <?php foreach ($this->buttons as $button): ?>
  <p class="submit">
    <input type="submit" class="button" name="<?php echo $button[0]; ?>" value="<?php echo $button[1]; ?>" />
  </p>
  <?php endforeach; ?>

</form>
<?php
  }
  
  protected function print_form_items() { }

  protected function on_options_updated($updated_options) {
    foreach ($this->buttons as $button) {
      if (array_key_exists($button[0], $_REQUEST)) {
        $this->on_button_clicked($button[0]);
        return;
      }
    }
  }

  /**
   * Is being called, if the specified button has been clicked in the buttons form.
   */
  protected abstract function on_button_clicked($button_id);
}

/**
 * Represents a option page containing a options form.
 */
class MSCL_OptionsPage {
  // See: http://codex.wordpress.org/Creating_Options_Pages#See_It_All_Together_2

  const DEFAULT_CAPABILITY = 'manage_options';
  
  const PARENT_CAT_SETTINGS = 'options-general.php';
  const PARENT_CAT_TOOLS = 'tools.php';
  const PARENT_CAT_USERS = 'users.php';
  const PARENT_CAT_PLUGINS = 'plugins.php';
  const PARENT_CAT_APPEARENCE = 'themes.php';
  
  const PARENT_CAT_DASHBOARD = 'index.php';
  const PARENT_CAT_POSTS = 'edit.php';
  const PARENT_CAT_MEDIA = 'upload.php';
  const PARENT_CAT_LINKS = 'link-manager.php';
  const PARENT_CAT_COMMENTS = 'edit-comments.php';
  
  private $page_id;
  private $menu_title;
  private $page_title;
  private $capability;
  private $forms = array();
  private $parent_menu;

  /**
   * Constructor
   * @param string $page_id  the page's slug, ie. an id used as URL for the page
   * @param string $menu_title  the name of the menu item
   * @param string $page_title  the page's title (placed in the title-tag)
   * @param string $capability user capability required to change settings on this page; see
   *   http://codex.wordpress.org/Roles_and_Capabilities
   * @param string $parent_menu  the name of the parent menu's php file (eg. "options-general.php") for
   *   the "Settings" menu. You can use the "PARENT_CAT_..." constants for this.
   */
  public function __construct($page_id, $menu_title, $page_title, $capability=self::DEFAULT_CAPABILITY,
                              $parent_menu = self::PARENT_CAT_SETTINGS) {
    $this->page_id = $page_id;
    $this->menu_title = $menu_title;
    $this->page_title = $page_title;
    $this->capability = $capability;
    $this->parent_menu = $parent_menu;

    add_action('admin_menu', array($this, 'add_menu_entry_wrapper'));
    add_action('admin_init', array($this, 'register_settings'));
  }

  public function get_page_id() {
    return $this->page_id;
  }

  public function get_menu_title() {
    return $this->menu_title;
  }

  public function get_page_title() {
    return $this->page_title;
  }

  public function get_required_capability() {
    return $this->capability;
  }

  public function get_parent_menu() {
    return $this->parent_menu;
  }

  public function get_page_link() {
    return get_admin_url(null, $this->get_parent_menu()).'?page='.$this->get_page_id();
  }

  public function add_form($form) {
    $this->forms[] = $form;
  }

  /**
   * Don't call this. This method needs to be public so that it can be used by Wordpress.
   */
  public function add_menu_entry_wrapper() {
    $this->add_menu_entry(array($this, 'print_page_wrapper'));
  }

  /**
   * Adds the menu entry for this options page. Adds the page to the options menu. If you want another menu
   * you need to override this method.
   *
   * @param mixed $page_gen_function the function to be used as page_gen_function.
   */
  protected function add_menu_entry($page_gen_function) {
    add_submenu_page($this->get_parent_menu(), $this->get_page_title(), $this->get_menu_title(),
                     $this->get_required_capability(), $this->get_page_id(), $page_gen_function);
  }

  /**
   * Don't call this. This method needs to be public so that it can be used by Wordpress.
   */
  public function register_settings() {
    foreach ($this->forms as $form) {
      $form->register_settings();
    }
  }

  /**
   * Don't call this. This method needs to be public so that it can be used by Wordpress.
   */
  public function print_page_wrapper() {
    $this->print_page();
  }

  protected function print_page() {
?>
<div class="wrap">
<h2><?php echo $this->get_page_title(); ?></h2>
<?php 
global $parent_file;
if ($parent_file != 'options-general.php') {
  // NOTE: Settings errors will only be displayed automatically on pages within the "Settings" section. For 
  //   all other sections, we need to display it manually.
  //   See: wp-admin/admin-header.php (at the end)
  settings_errors(); 
}
?>
<?php
    $this->print_forms();
?>
</div>
<?php
  }

  protected function print_forms() { 
    foreach ($this->forms as $form) {
      $form->print_form();
    }
  }
}
?>
