/* Reiniciar ou desligar (Bradial Voz). Clicar numa opção abre a confirmação. */
(function () {
  'use strict';
  var root = document.getElementById('bve');
  if (!root) return;
  var BASE = root.getAttribute('data-base'), TOKEN = root.getAttribute('data-token');
  var EN = root.getAttribute('data-lang') === 'en';
  var TXT = EN ? {
    reboot:   { title: 'Restart the server?', text: 'Calls in progress will be dropped. The server comes back on its own in about 2 minutes.', ok: 'Restart', done: 'The server will restart in 1 minute.' },
    poweroff: { title: 'Shut down the server?', text: 'Calls in progress will be dropped. The server only comes back when someone turns it on.', ok: 'Shut down', done: 'The server will shut down in 1 minute.' }
  } : {
    reboot:   { title: 'Reiniciar o servidor?', text: 'As ligações em andamento serão encerradas. O servidor volta sozinho em cerca de 2 minutos.', ok: 'Reiniciar', done: 'O servidor vai reiniciar em 1 minuto.' },
    poweroff: { title: 'Desligar o servidor?', text: 'As ligações em andamento serão encerradas. O servidor só volta quando alguém ligá-lo.', ok: 'Desligar', done: 'O servidor vai desligar em 1 minuto.' }
  };
  var modal = document.getElementById('bve-modal'), ok = document.getElementById('bve-m-ok');
  var cancel = document.getElementById('bve-m-cancel'), err = document.getElementById('bve-error');
  var pend = document.getElementById('bve-pending'), pendText = document.getElementById('bve-pending-text');
  var op = null, opener = null;

  function post(action, data) {
    var body = new URLSearchParams(data || {});
    return fetch(BASE + action, { method: 'POST', credentials: 'same-origin', headers: { 'X-BV-Token': TOKEN }, body: body })
      .then(function (r) { return r.json().then(function (j) { if (!r.ok || j.erro) throw new Error(j.erro || (EN ? 'Failed' : 'Falha')); return j; }); });
  }
  function open(which, btn) {
    op = which; opener = btn;
    document.getElementById('bve-m-title').textContent = TXT[op].title;
    document.getElementById('bve-m-text').textContent = TXT[op].text;
    ok.textContent = TXT[op].ok; ok.disabled = false;
    err.hidden = true;
    modal.hidden = false;
    cancel.focus();
  }
  function close() {
    modal.hidden = true;
    if (opener) opener.focus();
  }

  Array.prototype.forEach.call(root.querySelectorAll('.bve-opt'), function (b) {
    b.addEventListener('click', function () { open(b.getAttribute('data-op'), b); });
  });
  cancel.addEventListener('click', close);
  modal.addEventListener('click', function (e) { if (e.target === modal) close(); });
  document.addEventListener('keydown', function (e) {
    if (modal.hidden) return;
    if (e.key === 'Escape') close();
    if (e.key === 'Tab') { // mantém o foco dentro do diálogo
      if (e.shiftKey && document.activeElement === cancel) { e.preventDefault(); ok.focus(); }
      else if (!e.shiftKey && document.activeElement === ok) { e.preventDefault(); cancel.focus(); }
    }
  });
  ok.addEventListener('click', function () {
    ok.disabled = true; err.hidden = true;
    post('power', { op: op }).then(function () {
      pendText.textContent = TXT[op].done; pend.hidden = false; close();
    }).catch(function (e) { err.textContent = e.message; err.hidden = false; ok.disabled = false; });
  });
  document.getElementById('bve-cancel').addEventListener('click', function () {
    post('cancel').then(function () { pend.hidden = true; })
      .catch(function (e) { pendText.textContent = e.message; });
  });
})();
