<?php
class BlogTextAdminBarMenu {
  const MENU_ID = 'blogtext-adminmenu';
  const CLEAR_CACHE_REQUEST = 'clear-blogtext-cache';

  public function __construct() {
    // NOTE: We don't need check whether this is Wordpress 3.1. If it's not, the action will simply not be
    //   executed.
    add_action('admin_bar_menu', array($this, 'create_menu'), 100);
    add_action('init', array($this, 'handle_request'));
  }

  public function create_menu($admin_bar) {
    if (!MSCL_UserApi::can_manage_options()) {
      return;
    }
    
    //
    // For the $admin_bar class, see: 
    // 
    //   wp-include/class-wp-admin-bar.php
    //
    
    // Top Menu
    $admin_bar->add_menu(array('id' => self::MENU_ID, 'title' => 'BlogText'));

    // Sub menus
    $admin_bar->add_menu(array('id' => self::MENU_ID.'_clear_all', 'parent' => self::MENU_ID, 
                               'title' => __('Clear page cache for all posts'),
                               'href' => add_query_arg(self::CLEAR_CACHE_REQUEST, 'true')));
    if (is_single() || is_page()) {
      global $post;
      $admin_bar->add_menu(array('id' => self::MENU_ID.'_clear_one', 'parent' => self::MENU_ID,
                                 'title' => 'Clear page cache for this '.(is_page() ? 'page' : 'post')." (ID: $post->ID)",
                                 'href' => add_query_arg(self::CLEAR_CACHE_REQUEST, $post->ID)));
    }
    
    $admin_bar->add_menu(array('id' => self::MENU_ID.'_settings', 'parent' => self::MENU_ID, 
                               'title' => __('BlogText settings'),
                               'href' => admin_url('options-general.php?page=blogtext_settings')));
    if (BlogTextPlugin::are_tests_available()) {
      $admin_bar->add_menu(array('id' => self::MENU_ID.'_tests', 'parent' => self::MENU_ID, 
                                 'title' => __('BlogText Tests'),
                                 'href' => admin_url('tools.php?page=blogtext_test_exec')));
    }
  }

  public function handle_request() {
    if (isset($_REQUEST[self::CLEAR_CACHE_REQUEST])) {
      if (!MSCL_UserApi::can_manage_options()) {
        die("You're cheating!");
      }

      $what = $_REQUEST[self::CLEAR_CACHE_REQUEST];
      if (is_numeric($what)) {
        $what = (int)$what;
      } else {
        $what = null;
      }
      
      BlogTextMarkup::clear_page_cache($what);
      wp_redirect(remove_query_arg(self::CLEAR_CACHE_REQUEST));
      exit;
    }
    // TODO: Add sucess message
  }
}
?>
