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


namespace MSCL;

/**
 * Whether $str starts with $with.
 *
 * @param string $str  the string to check
 * @param string $with  the start string
 * @param int $offset  offset within the string to start the comparison at
 *
 * @return bool
 */
function string_starts_with($str, $with, $offset = 0)
{
    $withLen = strlen($with);
    if ($withLen === 1)
    {
        return $str[$offset] === $with[0];
    }
    else
    {
        return substr($str, $offset, strlen($with)) === $with;
    }
}

/**
 * Whether $str ends with $with.
 *
 * @param string $str  the string to check
 * @param string $with  the start string
 *
 * @return bool
 */
function string_ends_with($str, $with)
{
    $withLen = strlen($with);
    if ($withLen === 1)
    {
        return $str[strlen($str) - 1] === $with[0];
    }
    else
    {
        return substr($str, -strlen($with)) === $with;
    }
}
