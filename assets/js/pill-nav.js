(function () {
  function initPillNav(nav) {
    var cursor = nav.querySelector('.pill-nav__cursor');
    var links = nav.querySelectorAll('.pill-nav__link');
    if (!cursor || !links.length) return;

    function place(el) {
      var navBox = nav.getBoundingClientRect();
      var box = el.getBoundingClientRect();
      cursor.style.left = (box.left - navBox.left) + 'px';
      cursor.style.width = box.width + 'px';
      cursor.style.opacity = '1';
    }

    function hide() {
      cursor.style.opacity = '0';
    }

    for (var i = 0; i < links.length; i++) {
      links[i].addEventListener('mouseenter', function (e) { place(e.currentTarget); });
      links[i].addEventListener('focus', function (e) { place(e.currentTarget); });
    }

    nav.addEventListener('mouseleave', hide);
    nav.addEventListener('focusout', function (e) {
      if (!nav.contains(e.relatedTarget)) hide();
    });
  }

  document.querySelectorAll('.pill-nav').forEach(initPillNav);
})();
