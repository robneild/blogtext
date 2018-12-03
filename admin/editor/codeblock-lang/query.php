<?php
// See: http://orderedlist.com/blog/articles/live-search-with-quicksilver-style-for-jquery/

require_once(dirname(__FILE__).'/../../../thirdparty/geshi/geshi.php');
$geshi = new GeSHi();

function is_popular_language($lang_name) {
  switch ($lang_name) {
    // Popular languages
    case 'java5':
    case 'cpp':
    case 'cpp-qt':
    case 'c':
    case 'csharp':
    case 'php':
    case 'php5':
    case 'python':
    case 'perl':
    case 'ruby':
    case 'html4strict':
    case 'html5':
    case 'javascript':
    case 'css':
      return true;
  }
  return false;
}


function create_list_item($lang_name, $html_attribs) {
  global $geshi;
  
  // Handle special cases - needs to be in sync with "AbstractTextMarkup::create_code_block()"
  switch (strtolower($lang_name)) {
    case 'cpp':
      $short_name = 'c++, cpp';
      break;
    case 'cpp-qt':
      $short_name = 'c++/qt, cpp-qt';
      break;
    case 'csharp':
      $short_name = 'c#, csharp';
      break;
    case 'java5':
      $short_name = 'java';
      break;
    default:
      $short_name = $lang_name;
  }

  switch ($lang_name) {
    case 'c++/cli':
      $long_name = 'C++/CLI';
      break;
    default:
      $long_name = $geshi->get_language_fullname($lang_name);
  }

  echo "<li $html_attribs>$short_name <span class=\"fullname\">($long_name)</span></li>\n";
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
	<title>Programming Language Lookup</title>
	<link rel="stylesheet" href="codeblock-lang.css" type="text/css" media="screen" />
	<script type="text/javascript" src="jquery.js"></script>
	<script type="text/javascript" src="quicksilver.js"></script>
	<script type="text/javascript" src="codeblock-lang.js"></script>

	<script type="text/javascript" charset="utf-8">
		$(document).ready(function() {
      $("#q").keyup(function(event) {
        if (event.keyCode == 27) {
          // Escape
          window.close();
        } else if (event.keyCode == 13) {
          // Return
          event.preventDefault();
          // NOTE: JavaScript has no support for clipboard. So no luck here.
          window.close();
        }
      });
			$('#q').liveUpdate('languages').focus();
		});
	</script>
</head>
<body>

<div id="wrapper">
	<h1>Search Supported Programming Languages</h1>
	
	<form method="get" autocomplete="off">
		<div>
			<input type="text" value="" name="q" id="q" autocomplete="off"/>
		</div> 
	</form>

	<ul id="languages">
<?php
$supported_languages = $geshi->get_supported_languages();

# C++/CLI - maps to C++ for now
$supported_languages[] = 'c++/cli';

# Find popular languages
$popular_languages = array();
foreach ($supported_languages as $lang_name) {
  if (is_popular_language($lang_name)) {
    $popular_languages[] = $lang_name;
  }
}

sort($popular_languages);
sort($supported_languages);

foreach ($popular_languages as $lang_name) {
  create_list_item($lang_name, 'class="popular"');
}

foreach ($supported_languages as $lang_name) {
  if ($lang_name == 'java') {
    # Don't list java (which is Java < 5) - java is now an alias for java5.    
    continue;
  }
  
  if (is_popular_language($lang_name)) {
    continue;
  }
  create_list_item($lang_name, '');
}

?>
	</ul>

</div>
</body>
</html>