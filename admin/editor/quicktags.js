//
// Replace default quicktags buttons
// NOTE: We create our own HTML code here instead of replacing the existing Wordpress functions. This makes
//   it more robust against future Wordpress versions.
//
// See: http://scribu.net/wordpress/right-way-to-add-custom-quicktags.html
// See: wp-includes/js/quicktags.dev.js
//

//
// Adds a BlogText button based on the regular editor button.
//
function blogtext_copy_tag_button(orig_button, prio, start_tag, end_tag, default_title, display) {
  // Use default title only if there is no original title.
  var title = orig_button.title || default_title;
  display = display || orig_button.display;
  
  QTags.addButton('bt-'+orig_button.id, display, start_tag, end_tag, orig_button.access, title, prio);
}

function blogtext_create_tag_button(id, prio, start_tag, end_tag, title, display, access) {
  QTags.addButton('bt-'+id, display, start_tag, end_tag, access, title, prio);
}

function blogtext_create_func_button(id, prio, func, title, display, access) {
  QTags.addButton('bt-'+id, display, func, '', access, title, prio);
}

//
// Creates the BlogText editor buttons.
//
function blogtext_create_buttons() {
  // First, create BlogText versions of the existing buttons.
  for (var i in edButtons) {
    // Convert i to an integer. It's a string by default which makes problems with "i + 1" (otherwise 
    // resulting in 201 for i = "20")
    i = parseInt(i);
    var curButton = edButtons[i];
    if (!curButton) {
      // Not every index has an button. Some are left free for plugins.
      continue;
    }
    
    // For ids, see "qt._buttonsInit" -> "defaults"
    switch (curButton.id) {
      case 'strong':
        blogtext_copy_tag_button(curButton, i - 1, '**', '**', 'Bold Font');
        break;
      case 'em':
        blogtext_copy_tag_button(curButton, i - 1, '//', '//', 'Italics Font');

        // Add additional strike-through button
        blogtext_create_tag_button('strike', i + 1, '~~', '~~', 'Strike-Through', 'strike', '');
        break;
      case 'link':
        blogtext_create_func_button('link', i - 1, blogtext_insert_link, 'Insert Link', 'link', 
                                    curButton.access);  
        break;
      case 'img':
        blogtext_create_func_button('img', i - 1, blogtext_insert_image, 'Insert Image', 'img', 
                                    curButton.access);  
        break;
      case 'block':
        blogtext_copy_tag_button(curButton, i - 1, '\n>', '', 'Block Quote');
        break;
      case 'ul':
        blogtext_copy_tag_button(curButton, i - 1, '*', '', 'Bullet List');
        break;
      case 'ol':
        blogtext_copy_tag_button(curButton, i - 1, '#', '', 'Numbered List');
        break;
      case 'code':
        // Add additional single line code button
        blogtext_create_tag_button('inline-code', i - 2, '##', '##', 'Inline Code', '##code##', '');

        blogtext_copy_tag_button(curButton, i - 1, '{{{', '}}}', 'Code Block', '{{{code}}}');
        
        blogtext_create_tag_button('no-parse', i + 1, '{{!', '!}}', 'Disable BlogText syntax for text section', 'no-parse', '');
        break;
    }
  }
  
  blogtext_create_func_button('geshi-lookup', 0, blogtext_geshi_lookup, 
                              'Opens a window to look up languages available for syntax highlighting.', 
                              'lang lookup', '');  
}

function blogtext_insert_link(e, c, ed, defaultValue) {
  // TODO: Use wpLink (see wplink.dev.js and the original link button) instead.
	var url = prompt(quicktagsL10n.enterURL, 'http://');
	if (url) {
    var alt = prompt('Enter description for the link or leave it empty.', '');
    var content = '[[' + url;
    if (alt) {
      content += '|' + alt;
    }
    content += ']]';
		QTags.insertContent(content);
	}
}

function blogtext_insert_image(e, c, ed, defaultValue) {
	var url = prompt(quicktagsL10n.enterImageURL, 'http://');
	if (url) {
    var alt = prompt(quicktagsL10n.enterImageDescription, '');
    var content = '[[image:' + url;
    if (alt) {
      content += '|' + alt;
    }
    content += ']]';
		QTags.insertContent(content);
	}
}

function blogtext_edInsertMultilineCode(myField) {
	var lang = prompt('Programming Language', '');
  var code = "\n{{{";
  if (lang) {
    code += ' lang="' + lang + '"';
  }
  code += "\n\n}}}\n";

  edInsertContent(myField, code);
}

function blogtext_geshi_lookup() {
  window.open(blogTextPluginDir + '/admin/editor/codeblock-lang/query.php', '_blank', 'width=320,toolbar=no,menubar=no,status=no,location=no,scrollbars=yes');
}

function blogtext_edShowButton(button, i) {
  var func = '';

  switch (button.id) {
    case 'ed_img':
      func = 'blogtext_edInsertImage(edCanvas);';
      break;
    case 'ed_link':
      func = 'blogtext_edInsertLink(edCanvas, ' + i + ');';
      break;
    case 'ed_code':
      func = 'blogtext_edInsertMultilineCode(edCanvas);';
      break;
    case 'geshi_lookup':
      func = 'blogtext_edLangLookup();';
      break;
    default:
      func = 'edInsertTag(edCanvas, ' + i + ');';
      break;
  }

  var tooltip = '';
  if (button.tooltip) {
    tooltip = ' title="' + button.tooltip + '"';
  }
  return '<input type="button" id="' + button.id + '" accesskey="' + button.access + '" class="ed_button" onclick="' + func + '" value="' + button.display + '"' + tooltip + '/>';
}

function blogtext_edToolbar() {
  // replace global edButtons var - necessary for the buttons to work
  edButtons = get_blogtext_editor_buttons();

  var code = '';
	for (var i = 0; i < edButtons.length; i++) {
		code += blogtext_edShowButton(edButtons[i], i);
	}
	code += '<input type="button" id="ed_close" class="ed_button" onclick="edCloseAllTags();" title="' + quicktagsL10n.closeAllOpenTags + '" value="' + quicktagsL10n.closeTags + '" />';
  if (wordpressVersion >= "3.2") {
    code += '<input type="button" id="ed_fullscreen" class="ed_button" onclick="fullscreen.on();" title="' + quicktagsL10n.toggleFullscreen + '" value="' + quicktagsL10n.fullscreen + '" />';
  }
  return code;
}

function blogtext_get_editor_toolbar() {
  var toolbar = QTags.getInstance(0);
  if (!toolbar || !toolbar.settings || !toolbar.settings.buttons) {
    // Check object so that we don't break things.
    return false;
  }
  return toolbar;
}

function blogtext_start_toolbar_editing(toolbar) {
  // For easier matching, sourround the settings string with commas.
  toolbar.settings.buttons = ',' + toolbar.settings.buttons + ',';
}

function blogtext_end_toolbar_editing(toolbar) {
  toolbar.settings.buttons = toolbar.settings.buttons.slice(1, toolbar.settings.buttons.length - 1);
}

function blogtext_remove_toolbar_button(toolbar, button_id) {
  toolbar.settings.buttons = toolbar.settings.buttons.replace(new RegExp(','+button_id+','), ',');
}

function blogtext_replace_toolbar_button(toolbar, button_id) {
  toolbar.settings.buttons = toolbar.settings.buttons.replace(new RegExp(','+button_id+','), ',bt-'+button_id+',');
}

function blogtext_update_toolbar() {
  QTags._buttonsInit();
}

// NOTE: the dollar $ object isn't defined in WordPress jQuery (historical reasons)
jQuery(document).ready(function($) {
  // rename editor tab
  $('#edButtonHTML').html(function(index, oldHtml) {
    return oldHtml + '/BlogText';
  });
  // replace toolbar content
  /*$('#ed_toolbar').html(function(index, oldHtml) {
    return blogtext_edToolbar();
  });*/
  blogtext_create_buttons();
  var toolbar = blogtext_get_editor_toolbar();
  if (toolbar) {
    //console.log(editorToolbar);
    blogtext_start_toolbar_editing(toolbar);
    
    blogtext_remove_toolbar_button(toolbar, 'ins');
    blogtext_remove_toolbar_button(toolbar, 'del');
    blogtext_remove_toolbar_button(toolbar, 'li');
    blogtext_remove_toolbar_button(toolbar, 'spell');
    
    blogtext_replace_toolbar_button(toolbar, 'strong');
    blogtext_replace_toolbar_button(toolbar, 'em');
    blogtext_replace_toolbar_button(toolbar, 'link');
    blogtext_replace_toolbar_button(toolbar, 'block');
    blogtext_replace_toolbar_button(toolbar, 'img');
    blogtext_replace_toolbar_button(toolbar, 'ul');
    blogtext_replace_toolbar_button(toolbar, 'ol');
    blogtext_replace_toolbar_button(toolbar, 'code');
    
    blogtext_end_toolbar_editing(toolbar);
    blogtext_update_toolbar();
  }
});
