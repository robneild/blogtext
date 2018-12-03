<?php
// load custom settings
@include_once(dirname(__FILE__).'/settings.custom.php');
// load default settings; must happen after loading the custom settings
require_once(dirname(__FILE__).'/settings.default.php');
?>
