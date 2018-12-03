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

class ATM_TableCell {
  const TYPE_TD = 'td';
  const TYPE_TH = 'th';

  public $cell_type;
  public $cell_content;
  public $tag_attributes = '';

  public function __construct($cell_type, $cell_content) {
    $this->cell_type = $cell_type;
    $this->cell_content = $cell_content;
  }
}

class ATM_TableRow {
  public $cells = array();
  public $tag_attributes = '';
}

class ATM_Table {
  public $rows = array();
  public $tag_attributes = '';
  public $caption = '';
}
?>
