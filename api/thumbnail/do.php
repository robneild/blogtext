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


use MSCL\FileInfo\FileInfoException;

require_once(dirname(__FILE__).'/../commons.php');
require_once(dirname(__FILE__).'/api.php');

// set memory limit to be able to have enough space to resize larger images
ini_set('memory_limit', '50M');

try {
  // NOTE: We can't use the ThumbnailAPI here as this requires Wordpress being loaded.
  $thumb = new MSCL_Thumbnail($_GET['id'], null, null, null);
  $thumb->display_thumbnail();
} catch (FileInfoException $e) {
  MSCL_Thumbnail::display_error_msg_image($e->getMessage());
} catch (Exception $e) {
  print MSCL_ErrorHandling::format_exception($e, true);
  // exit here as the exception may come from some static constructor that is only executed once
  exit;
}
?>
