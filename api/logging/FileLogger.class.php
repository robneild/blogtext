<?php
#########################################################################################
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

require_once(dirname(__FILE__).'/logging-api.php');

class MSCL_FileLogger {
  private $file;

  public function __construct($file) {
    $this->file = $file;
    $this->write_line("\n\n----------------------------------------------------\n");
  }

  private function write_line($text) {
    $fh = fopen($this->file, 'a');
    if (!$fh) {
      return;
    }
    fwrite($fh, $text."\n");
    fclose($fh);
  }

  public function error($obj, $label) {
    $this->log($obj, '[ERR] '.$label);
    MSCL_Logging::get_instance(false)->error($obj, $label);
  }

  public function warn($obj, $label) {
    $this->log($obj, '[WARN] '.$label);
    MSCL_Logging::get_instance(false)->warn($obj, $label);
  }

  public function info($obj, $label) {
    $this->log($obj, '[INFO] '.$label);
    MSCL_Logging::get_instance(false)->info($obj, $label);
  }

  public function log($obj, $label) {
    $this->write_line($label.': '.print_r($obj, true));
    MSCL_Logging::get_instance(false)->log($obj, $label);
  }
}
