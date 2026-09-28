// Delete / important buttons par confirmation
document.addEventListener('submit', function (ev) {
  var msg = ev.target.getAttribute('data-confirm');
  if (msg && !confirm(msg)) ev.preventDefault();
});

// Edit modal: button k data-* attributes se form fill karo
document.addEventListener('click', function (ev) {
  var btn = ev.target.closest('[data-fill]');
  if (!btn) return;
  var form = document.querySelector(btn.getAttribute('data-fill'));
  if (!form) return;
  var data = JSON.parse(btn.getAttribute('data-values') || '{}');
  form.reset();
  Object.keys(data).forEach(function (k) {
    var el = form.elements[k];
    if (el) el.value = data[k] === null ? '' : data[k];
  });
});
