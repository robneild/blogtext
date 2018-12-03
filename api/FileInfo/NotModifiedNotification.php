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

use Exception;

// TODO: Remove this class to make code flow easier to understand
/**
 * This "notification" is thrown by any media info class, if the specified file hasn't been modified and
 * its cached counterpart is still valid.
 */
class NotModifiedNotification extends Exception
{
    // This class is a "hack" but it's the easiest way to handle this situation.
}
