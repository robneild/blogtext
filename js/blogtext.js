function toggle_toc(toc_nr) {
  var toclist = document.getElementById('_toclist_' + toc_nr);
  var toctoggle = document.getElementById('_toctoggle_' + toc_nr);

  if (toclist.style.display == 'none') {
    toclist.style.display = 'block';
    toctoggle.innerHTML = 'hide';
  } else {
    toclist.style.display = 'none';
    toctoggle.innerHTML = 'show';
  }
  toctoggle.blur();
}
