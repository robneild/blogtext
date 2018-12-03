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


MSCL_Api::load(MSCL_Api::USER_API);

/**
 * Just like any regular exception but the code (now name "error_id") can be any type and not just integers.
 * Retrieve it with "get_error_id()".
 */
class MSCL_ExceptionEx extends Exception {
  private $error_id;
  private $previous;

  /**
   * Constructor.
   *
   * @param string $message the message; must not contain the confidential information
   * @param mixed $error_id some id to identify the exception (rather than parsing its message); usually a
   *   string or an integer.
   * @param Exception $previous inner exception
   */
  public function  __construct($message, $error_id='', $previous=null) {
    $this->error_id = $error_id;
    $this->previous = $previous;
    // NOTE: We can't use the "$code" parameter as it only allows integers.
    // NOTE 2: We can't use "$previous" either as this parameter isn't always available.
    parent::__construct($message);
  }

  public function get_error_id() {
    return $this->error_id;
  }

  public function get_previous() {
    return $this->previous;
  }
}

/**
 * An exception the may contain confidential information that will only be displayed to the admin.
 */
class MSCL_AdminException extends MSCL_ExceptionEx {
  /**
   * Constructor.
   *
   * @param string $message the message; must not contain the confidential information
   * @param string $add_admin_msg the confidential information; will only displayed to the admin and will be
   *   appended to the message
   * @param mixed $error_id some id to identify the exception (rather than parsing its message); usually a
   *   string or an integer.
   * @param Exception $previous inner exception
   */
  public function  __construct($message, $add_admin_msg='', $error_id='', $previous=null) {
    if (MSCL_UserApi::can_manage_options(false)) {
      $message .= ' '.$add_admin_msg;
    }
    parent::__construct($message, $error_id, $previous);
  }
}

class MSCL_ErrorHandling {
  public static function format_warning($warn_msg, $additional_code='') {
    // Abuse the "update" style for warnings.
    return '<div class="updated"><p>'.$warn_msg.'</p>'.$additional_code.'</div>';
  }
  
  public static function format_error($error_msg, $additional_code='') {
    return '<div class="error"><p>'.$error_msg.'</p>'.$additional_code.'</div>';
  }

  public static function format_exception($excpt, $show_stacktrace=null) {
    if (!is_object($excpt) || !($excpt instanceof Exception)) {
      throw new Exception("Not an exception.", '', $excpt);
    }

    $admin_code = '';
    if ($show_stacktrace === null) {
      $show_stacktrace = MSCL_UserApi::can_manage_options();
    }
    if ($show_stacktrace) {
      // display additional information to the admin
      $admin_code = '<p><b>Exception Type: </b>'.get_class($excpt)."</p>\n";
      $admin_code .= self::format_stacktrace($excpt->getTrace());
      $admin_code = "\n$admin_code\n";
    }
    return self::format_error('<b>Fatal error:</b> '.str_replace("\n", "<br/>\n", $excpt->getMessage()), 
                              $admin_code);
  }

  public static function print_stacktrace() {
    echo self::format_stacktrace(debug_backtrace(), 1);
  }

  public static function format_stacktrace($stack_trace, $skip_frames = 0, $text_only = false) {
    $admin_code = '';

    if (count($stack_trace) > $skip_frames) {
      // TODO: Make this stack trace as fancy as in Trac
      if (!$text_only) {
        $admin_code .= '<p><b>Stack Trace: </b></p>';
        $admin_code .= '<pre class="stack-trace">'."\n";
      }
      $frame_counter = 0;

      foreach ($stack_trace as $frame) {
        if ($frame_counter < $skip_frames) {
          $frame_counter++;
          continue;
        }

        // Format function name
        if (array_key_exists('class', $frame)) {
          // class method; "type" is "::" or "->"
          $func_name = $frame['class'].$frame['type'].$frame['function'];
        } else {
          // global function
          $func_name = $frame['function'];
        }

        // Format function args
        $args = array();
        foreach ($frame['args'] as $arg) {
          $shortend = false;
          if ($arg === null) {
            $arg_str = 'null';
          } else if (is_bool($arg)) {
            $arg_str = $arg ? 'true' : 'false';
          } else if (is_string($arg)) {
            // get only the first line
            $first_lines = explode("\n", preg_replace('/\r|\r\n/', "\n", $arg), 2);
            $arg_str = trim($first_lines[0], "\n");
            if (strlen($arg) > 50) {
              $arg_str = substr($arg_str, 0, 50).'...';
              $shortend = true;
            } else if (count($first_lines) > 1) {
              // there are more lines
              $arg_str .= '...';
              $shortend = true;
            }
            $arg_str = '"'.htmlspecialchars($arg_str).'"';
          } else if (is_object($arg)) {
            $arg_str = get_class($arg).' object';
            $shortend = true;
          } else if (is_array($arg)) {
            $arg_str = 'array('.count($arg).')';
            $shortend = true;
          } else {
            // int, float
            $arg_str = htmlspecialchars(print_r($arg, true));
          }

          if ($shortend && !$text_only) {
            $arg_str = '<span title="'.htmlspecialchars(print_r($arg, true)).'">'.$arg_str.'</span>';
          }
          $args[] = $arg_str;
        }

        // Format file name
        if (array_key_exists('file', $frame)) {
          $filename = $frame['file'];
          // Is this a file in a plugin?
          $filename = MSCL_AbstractPlugin::get_plugin_basename($filename);
          // Otherwise (even for themes) check whether the file is even in the wordpress dir
          $filename = str_replace(ABSPATH, '', $filename);

          $linenr = $frame['line'];
          $pos = "at $filename:$linenr";
        } else {
          // not file information available; is be possible for callback functions
          $pos = 'at unknown position';
        }

        if ($text_only) {
          $admin_code .= $func_name.'('.join(', ', $args).") $pos\n";
        }
        else {
          $admin_code .= '<b>'.$func_name.'</b>('.  join(', ', $args).")\n"
                      .  "  <span style=\"color:gray;\">$pos</span>\n";
        }
      }

      if (!$text_only) {
        $admin_code .= '</pre>';
      }
    }

    return $admin_code;
  }
}

/**
 * Displays configuration or plugin error messages (like missing write permissions in directories). These
 * errors can be displayed in the backend as well in the front end. An subclass instance of this class must be
 * created directly when the plugin is loaded (ie. in "<pluginname>.php").
 */
abstract class MSCL_ErrorNotifier {
  public function __construct($backend_only=false) {
    add_action('admin_notices', array($this, 'display_notifications_callback'));
    if (!$backend_only) {
      add_action('loop_start', array($this, 'display_notifications_callback'));
    }
  }

  protected static function is_in_backend() {
    return is_admin();
  }

  /**
   * Callback function for Wordpress. Don't call it.
   */
  public function display_notifications_callback() {
    if (is_feed()) {
      // don't display problems in feeds
      // NOTE: We can't check this in the constructor as the loop query isn't available there.
      return;
    }

    $warnings = array();
    $errors = array();
    
    try {
      // common warnings and errors
      $this->merge_errors($this->check_for_warnings(), $warnings);
      $this->merge_errors($this->check_for_errors(), $errors);

      // editor warnings and errors
      if (MSCL_UserApi::is_editor()) {
        $this->merge_errors($this->check_for_editor_warnings(), $warnings);
        $this->merge_errors($this->check_for_editor_errors(), $errors);
      }

      // admin warnings and errors
      if (MSCL_UserApi::can_manage_options()) {
        $this->merge_errors($this->check_for_admin_warnings(), $warnings);
        $this->merge_errors($this->check_for_admin_errors(), $errors);
      }

      if (count($errors) == 0 && count($warnings) == 0) {
        // no warnings or errors found
        return;
      }
      // echo this here so that we still can catch exceptions happening in this method without creating
      // wrong HTML output
      echo $this->format_messages($warnings, $errors);
    } catch (Exception $e) {
      echo MSCL_ErrorHandling::format_exception($e);
    }
  }

  private function merge_errors($new_errors, &$existing_errors) {
    if ($new_errors === null) {
      // no errors found
      return;
    }
    if (!is_array($new_errors)) {
      $existing_errors[] = $new_errors;
    } else if (count($new_errors) != 0) {
      $existing_errors = array_merge($existing_errors, $new_errors);
    }
  }

  /**
   * Checks for warnings that concern all users regardless of their capabilities/role.
   *
   * @return array|string|null Returns the warning message(s) for the errors that have been found. If there's
   *   only one, its returned as string. Multiple warning messages will be returned as array of strings. If no
   *   warnings are found, returns "null" or an empty array.
   */
  protected function check_for_warnings() {
    return null;
  }

  /**
   * Checks for errors that concern all users regardless of their capabilities/role.
   *
   * @return array|string|null Returns the error message(s) for the errors that have been found. If there's
   *   only one, its returned as string. Multiple error messages will be returned as array of strings. If no
   *   errors are found, returns "null" or an empty array.
   */
  protected function check_for_errors() {
    return null;
  }

  /**
   * Checks for warnings that concern only editors (ie. users that can publish and edit posts).
   *
   * @return array|string|null Returns the warning message(s) for the errors that have been found. If there's
   *   only one, its returned as string. Multiple warning messages will be returned as array of strings. If no
   *   warnings are found, returns "null" or an empty array.
   */
  protected function check_for_editor_warnings() {
    return null;
  }

  /**
   * Checks for errors that concern only editors (ie. users that can publish and edit posts).
   *
   * @return array|string|null Returns the error message(s) for the errors that have been found. If there's
   *   only one, its returned as string. Multiple error messages will be returned as array of strings. If no
   *   errors are found, returns "null" or an empty array.
   */
  protected function check_for_editor_errors() {
    return null;
  }

  /**
   * Checks for warnings that concern only admin (ie. users that can manage the blog's options).
   *
   * @return array|string|null Returns the warning message(s) for the errors that have been found. If there's
   *   only one, its returned as string. Multiple warning messages will be returned as array of strings. If no
   *   warnings are found, returns "null" or an empty array.
   */
  protected function check_for_admin_warnings() {
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
    return null;
  }

  /**
   * Formats and returns the errror messages previously found by "check_for_errors()". Will only be called,
   * if errors have been found.
   *
   * @param array $errors the error messages as array. Will even be an array, if there's only one message.
   *
   * @return string the formatted error messages as HTML code
   */
  protected function format_messages($warnings, $errors) {
    $code = '';
    // TODO: This can be done as list instead of individual error "sections"
    foreach($errors as $error_msg) {
      $code .= MSCL_ErrorHandling::format_error($error_msg);
    }
    foreach($warnings as $warn_msg) {
      $code .= MSCL_ErrorHandling::format_warning($warn_msg);
    }
    return $code;
  }
}
?>
