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


abstract class MSCL_AbstractCache {
  private $prefix;

  public function  __construct($prefix) {
    $this->prefix = '_cache_'.$prefix;
  }

  public function get_prefix() {
    return $this->prefix;
  }

  protected function create_key($key) {
    return $this->prefix.$key;
  }
}

/**
 * Stores strings transient (ie. they expire after some time). See also "MSCL_PersistentObjectCache".
 *
 * NOTE: This class is a logical abstraction layer for a transient cache. Don't make any assumption on where
 *   the string will be stored.
 */
class MSCL_TransientObjectCache extends MSCL_AbstractCache {
  public function  __construct($prefix) {
    parent::__construct($prefix);
  }

  public function get_value($key) {
    return get_transient($this->create_key($key));
  }

  public function set_value($key, $value, $expiration) {
    set_transient($this->create_key($key), $value, $expiration);
  }

  public function delete_value($key) {
    delete_transient($this->create_key($key));
  }
}

/**
 * Stores strings persistently (ie. they don't expire). See also "MSCL_TransientObjectCache".
 *
 * NOTE: This class is a logical abstraction layer for a persistent cache. Don't make any assumption on where
 *   the string will be stored.
 */
class MSCL_PersistentObjectCache extends MSCL_AbstractCache {
  public function  __construct($prefix) {
    parent::__construct($prefix);
  }

  /**
   * Returns the value for the specified key. Returns an empty string, if there's no value for the specified
   * key.
   */
  public function get_value($key) {
    return get_option($this->create_key($key), '');
  }

  public function set_value($key, $value) {
    update_option($this->create_key($key), $value);
  }

  public function delete_value($key) {
    delete_option($this->create_key($key));
  }

  /**
   * Deletes all entries belonging to this cache.
   */
  public function clear_cache() {
    global $wpdb;

    @mysql_query("BEGIN", $wpdb->dbh); // begin transaction

    // NOTE: The % token must be added to the parameter and not directly in the SQL statement as this doesn't
    //   work.
    $col = $wpdb->get_col($wpdb->prepare("SELECT option_name FROM $wpdb->options WHERE option_name like %s", $this->get_prefix().'%'));
    foreach ($col as $entry) {
      delete_option($entry);
    }
    
    @mysql_query("COMMIT", $wpdb->dbh); // commit transaction
  }
}
?>
