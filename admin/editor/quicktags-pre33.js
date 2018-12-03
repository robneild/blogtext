//
// Replace default quicktags buttons
// NOTE: We create our own HTML code here instead of replacing the existing Wordpress functions. This makes
//   it more robust against future Wordpress versions.
//
// See: http://scribu.net/wordpress/right-way-to-add-custom-quicktags.html
// See: wp-includes/js/quicktags.dev.js
//

function get_blogtext_editor_buttons() {
  var newEdButtons = new Array();
  for (var i = 0; i < edButtons.length; i++) {
    var curButton = edButtons[i];
    switch (curButton.id) {
      case 'ed_strong':
        curButton.tagStart = "**";
        curButton.tagEnd = "**";
        curButton.tooltip = "Bold Font";
        break;
      case 'ed_em':
        curButton.tagStart = "//";
        curButton.tagEnd = "//";
        curButton.tooltip = "Italics Font";
        newEdButtons[newEdButtons.length] = curButton;

        // Add additional strike-through button
        curButton = new edButton('ed_strike', 'strike', '~~', '~~');
        curButton.tooltip = "Strike-Through";
        break;
      case 'ed_link':
        curButton.tagEnd = "]]";
        curButton.tooltip = "Insert Link";
        break;
      case 'ed_img':
        curButton.tooltip = "Insert Image";
        break;
      case 'ed_block':
        curButton.tagStart = "\n>";
        curButton.tagEnd = "";
        curButton.open = -1;
        curButton.tooltip = "Block Quote";
        break;
      case 'ed_ul':
        curButton.tagStart = "*";
        curButton.tagEnd = "";
        curButton.open = -1;
        curButton.tooltip = "Bullet List";
        break;
      case 'ed_ol':
        curButton.tagStart = "#";
        curButton.tagEnd = "";
        curButton.open = -1;
        curButton.tooltip = "Numbered List";
        break;
      case 'ed_code':
        // Add additional single line code button
        newEdButtons[newEdButtons.length] = new edButton('ed_code_single', '##code##', '##', '##');
        newEdButtons[newEdButtons.length-1].tooltip = "Inline Code";

        curButton.display = '{{{code}}}';
        curButton.tagStart = "{{{";
        curButton.tagEnd = "}}}";
        curButton.tooltip = "Code Block";
        break;
      case 'ed_more':
        curButton.tooltip = "Insert More Tag";
        break;

      case 'ed_ins':
      case 'ed_del':
      case 'ed_li':
        // Ignore useless tags (does anyone really use "<ins>" or "<del>"?)
        continue;
    }
    newEdButtons[newEdButtons.length] = curButton;
  }
  var newEdButton = new edButton('ed_no_parse', 'no-parse', '{{!', '!}}');
  newEdButton.tooltip = "Disable BlogText syntax for text section";
  newEdButtons[newEdButtons.length] = newEdButton;

  newEdButton = new edButton('geshi_lookup', 'lang lookup', '', '');
  newEdButton.open = -1;
  newEdButton.tooltip = "Opens a window to look up languages available for syntax highlighting.";
  newEdButtons[newEdButtons.length] = newEdButton;

  return newEdButtons;
}

//
// redefine methods for special buttons
// Names are the same as the original function name but with "blogtext_" prefix
//
function blogtext_edInsertLink(myField, i, defaultValue) {
	if (!defaultValue) {
		defaultValue = 'http://';
	}
	if (!edCheckOpenTags(i)) {
		var URL = prompt(quicktagsL10n.enterURL, defaultValue);
		if (URL) {
			edButtons[i].tagStart = '[[' + URL + '|';
			edInsertTag(myField, i);
		}
	}
	else {
		edInsertTag(myField, i);
	}
}

function blogtext_edInsertImage(myField) {
	var myValue = prompt(quicktagsL10n.enterImageURL, 'http://');
	if (myValue) {
		myValue = '[[image:'
				+ myValue
				+ '|' + prompt(quicktagsL10n.enterImageDescription, '')
				+ ']]';
		edInsertContent(myField, myValue);
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

function blogtext_edLangLookup() {
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

// NOTE: the dollar $ object isn't defined in WordPress jQuery (historical reasons)
jQuery(document).ready(function($) {
  $('#edButtonHTML').html(function(index, oldHtml) {
    // rename editor tab
    return oldHtml + '/BlogText';
  });
  $('#ed_toolbar').html(function(index, oldHtml) {
    // replace toolbar content
    return blogtext_edToolbar();
  });
});
