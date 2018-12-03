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


require_once(dirname(__FILE__) . '/../../api/commons.php');


/**
 * This exception is thrown whenever a IInterlinkLinkResolver could not resolve its Interlink or if it could not
 * find the Interlink's target.
 *
 * @see IInterlinkLinkResolver
 */
class LinkTargetNotFoundException extends Exception
{
    const REASON_DONT_EXIST = 'doesn\'t exist';

    private $reason;
    private $link_name;

    /**
     * Constructor.
     *
     * @param string $reason  the more detailed reason why the Interlink couldn't be resolved. Should only be a
     *    few words as they are added to the link's name (ie. the part between <a> and </a>). Defaults to
     *    @see REASON_DONT_EXIST. If possible, you should use one of the constants provided by this class.
     * @param string $link_name  the title of the link that could not be resolved, if known and not explicitly
     *    provided in the interlink. Otherwise "null".
     */
    public function __construct($reason = null, $link_name = '')
    {
        parent::__construct();

        if (empty($reason))
        {
            $this->reason = self::REASON_DONT_EXIST;
        }
        else
        {
            $this->reason = $reason;
        }

        if (empty($link_name))
        {
            $this->link_name = '';
        }
        else
        {
            $this->link_name = $link_name;
        }
    }

    /**
     * Returns the reason why the Interlink could not be resolved. Is never null nor empty.
     * @return string
     */
    public function get_reason()
    {
        return $this->reason;
    }

    /**
     * The link's name (without the reason attached to). Is never null, but can be empty.
     * @return string
     */
    public function get_link_name()
    {
        return $this->link_name;
    }
}
