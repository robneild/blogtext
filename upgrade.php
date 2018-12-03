<?php
#########################################################################################
#
# Copyright 2010-2012  Maya Studios (http://www.mayastudios.com)
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

class BlogTextUpgrader {
  const OPTION_NAME = 'blogtext_version';

  private static $m_checked = false;

  /**
   * @param MSCL_AbstractPlugin $plugin
   */
  public static function run($plugin) {
    if (self::$m_checked) {
      // Already checked
      return;
    }

    $oldVersion = get_option(self::OPTION_NAME, '');
    $curVersion = $plugin->get_plugin_version();
    if ($oldVersion == $curVersion) {
      self::$m_checked = true;
      return;
    }

    #version_compare()
    /*if ($oldVersion == '') {
      self::upgradeFromPre0_9_5();
    }*/

    add_option(self::OPTION_NAME, $curVersion);
  }

  private static function loadSettings() {
    require_once(dirname(__FILE__).'/admin/settings.php');
  }

  /*private static function upgradeFromPre0_9_5() {
    self::loadSettings();

    // Move CSS for link icons into the "Custom CSS" setting
    $customCSSSetting = BlogTextSettings::get_custom_css(true);
    $customCSS = $customCSSSetting->get_value();
    $customCSS = <<<DOC
a.external {
  background: url(common-icons/link-external.png) no-repeat left center transparent;
  padding-left: 19px;
}

a.external-https {
  background-image: url(common-icons/link-https.gif) !important;
}

a.external-wiki {
  background-image: url(common-icons/wikipedia.png) !important;
}

a.external-search {
  background-image: url(common-icons/search.png) !important;
}

a.attachment {
  background: url(common-icons/attachment.gif) no-repeat left center transparent;
  padding-left: 19px;
}

a.section-link-above {
  background: url(common-icons/section-above.png) no-repeat left center transparent !important;
  padding-left: 11px;
}

a.section-link-below {
  background: url(common-icons/section-below.png) no-repeat left center transparent !important;
  padding-left: 11px;
}

$customCSS
DOC;

    $customCSSSetting->set_value($customCSS);
  }*/
}