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


/*
 * This file contains the plugin's options page.
 */
require_once(dirname(__FILE__) . '/../api/commons.php');
MSCL_Api::load(MSCL_Api::OPTIONS_API);
MSCL_require_once('settings.php', __FILE__);

class BlogTextSettingsMainForm extends MSCL_OptionsForm {
  const FORM_ID = 'blogtext_settings';

  public function __construct() {
    parent::__construct(self::FORM_ID);

    $section = new MSCL_OptionsPageSection('blogtext_behavior', 'Behavior Settings', '');
    $section->add_option(BlogTextSettings::get_toc_title(true));
    $section->add_option(BlogTextSettings::get_top_level_heading_level(true));
    $section->add_option(BlogTextSettings::new_window_for_external_links(true));
    $section->add_option(BlogTextSettings::remove_common_protocol_prefixes(true));
    $section->add_option(BlogTextSettings::get_default_small_img_alignment(true));
    $section->add_option(BlogTextSettings::display_caption_if_provided(true));
    $this->add_section($section);

    $section = new MSCL_OptionsPageSection('blogtext_theme', 'Theme Settings', '');
    $section->add_option(BlogTextSettings::get_content_width(true));
    $section->add_option(BlogTextSettings::use_default_css(true));
    $section->add_option(BlogTextSettings::use_default_external_link_icon(true));
    $section->add_option(BlogTextSettings::use_default_https_link_icon(true));
    $section->add_option(BlogTextSettings::use_default_attachment_link_icon(true));
    $section->add_option(BlogTextSettings::use_default_updown_link_icon(true));
    $section->add_option(BlogTextSettings::use_default_broken_link_icon(true));
    $section->add_option(BlogTextSettings::get_geshi_theme(true));
    $section->add_option(BlogTextSettings::get_custom_css(true));
    $this->add_section($section);

    $section = new MSCL_OptionsPageSection('blogtext_warnings', 'Backend Settings', '');
    $section->add_option(BlogTextSettings::disable_fix_invalid_xhtml_warning(true));
    $section->add_option(BlogTextSettings::enable_monospace_editor_font(true));
    $this->add_section($section);

    $section = new MSCL_OptionsPageSection('blogtext_interlinks', 'Interlinks', '');
    $section->add_option(BlogTextSettings::get_interlinks(true));
    $this->add_section($section);
  }

  protected function on_options_updated($updated_options) {
    if ($this->need_to_clear_cache()) {
      BlogTextActionButtonsForm::clear_page_cache();
      $this->add_settings_error('cache_cleared', __("BlogText's Page cache has been cleared."), 'updated');
    }
  }

  private function need_to_clear_cache() {
    if (   $this->is_option_updated(BlogTextSettings::get_top_level_heading_level(true))
        || $this->is_option_updated(BlogTextSettings::get_toc_title(true))
        || $this->is_option_updated(BlogTextSettings::remove_common_protocol_prefixes(true))
        || $this->is_option_updated(BlogTextSettings::new_window_for_external_links(true))
        || $this->is_option_updated(BlogTextSettings::get_default_small_img_alignment(true))
        || $this->is_option_updated(BlogTextSettings::display_caption_if_provided(true))
        || $this->is_option_updated(BlogTextSettings::get_content_width(true))
        || $this->is_option_updated(BlogTextSettings::get_interlinks(true))) {
      return true;
    }

    return false;
  }
}

class BlogTextActionButtonsForm extends MSCL_ButtonsForm {
  const CLEAR_CACHE_BUTTON_NAME = 'clear_cache';

  public function __construct() {
    parent::__construct('blogtext_action_buttons');

    $this->add_button(self::CLEAR_CACHE_BUTTON_NAME, 'Clear Page Cache');
  }

  /**
   * Is being called, if the specified button has been clicked in the buttons form.
   */
  protected function on_button_clicked($button_id) {
    if ($button_id == self::CLEAR_CACHE_BUTTON_NAME) {
      // explicit cache clearing
      self::clear_page_cache();
      $this->add_settings_error('cache_cleared', __("BlogText's Page cache has been cleared."), 'updated');
      return;
    }
  }

  public static function clear_page_cache() {
    MSCL_require_once('../markup/blogtext_markup.php', __FILE__);
    BlogTextMarkup::clear_page_cache();
  }
}

class BlogTextSettingsPage extends MSCL_OptionsPage {
  const PAGE_ID = 'blogtext_settings';
  const PAGE_NAME = 'BlogText';
  const PAGE_TITLE = 'BlogText Plugin Settings';

  public function __construct() {
    parent::__construct(self::PAGE_ID, self::PAGE_NAME, self::PAGE_TITLE);

    $this->add_form(new BlogTextSettingsMainForm());
    $this->add_form(new BlogTextActionButtonsForm());
  }

  protected function print_forms() {
?>
<p class="description">Note: You can find more settings in the
<a href="<?php echo admin_url('options-media.php'); ?>">Media Settings</a>.</p>
<?php
    MSCL_OptionsPage::print_forms();
  }
}
?>
