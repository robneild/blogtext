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

namespace MSCL\FileInfo;

/**
 * Indicates that the specified file doesn't exist.
 */
class FileNotFoundException extends FileInfoIOException
{
    /**
     * Constructor.
     *
     * @param string $filePath     the affected file
     * @param bool   $isRemoteFile whether the affected file is a remote file
     */
    public function  __construct($filePath, $isRemoteFile)
    {
        parent::__construct('File could not be found', $filePath, $isRemoteFile);
    }
}
