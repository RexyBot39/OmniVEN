// Applies the person's light/dark choice before the page paints. No choice: follow the device.
(function () {
  try {
    var t = localStorage.getItem('omniven:theme');
    if (t === 'light' || t === 'dark') document.documentElement.setAttribute('data-theme', t);
    if (localStorage.getItem('omniven:sidebar') === 'collapsed') document.documentElement.classList.add('sb-collapsed');
  } catch (e) {}
})();
