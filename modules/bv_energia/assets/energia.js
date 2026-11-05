/* Reiniciar ou desligar (Bradial Voz). Pede confirmação antes de agendar. */
(function () {
  'use strict';
  var root = document.getElementById('bve');
  if (!root) return;
  var BASE = root.getAttribute('data-base'), TOKEN = root.getAttribute('data-token');
  var op = 'reboot', confirming = false;
  var go = document.getElementById('bve-go'), back = document.getElementById('bve-back');
  var conf = document.getElementById('bve-confirm'), err = document.getElementById('bve-error');
  var pend = document.getElementById('bve-pending'), pendText = document.getElementById('bve-pending-text');
  var EN = root.getAttribute('data-lang') === 'en';
  var TXT = EN ? {
    reboot:   { cta: 'Restart server', sure: 'Yes, restart', ask: 'Restart the server? Calls in progress will be dropped.', done: 'The server will restart in 1 minute.' },
    poweroff: { cta: 'Shut down server', sure: 'Yes, shut down', ask: 'Shut down the server? It only comes back when someone turns it on.', done: 'The server will shut down in 1 minute.' }
  } : {
    reboot:   { cta: 'Reiniciar servidor', sure: 'Sim, reiniciar', ask: 'Reiniciar o servidor? As ligações em andamento serão encerradas.', done: 'O servidor vai reiniciar em 1 minuto.' },
    poweroff: { cta: 'Desligar servidor', sure: 'Sim, desligar', ask: 'Desligar o servidor? Ele só volta quando alguém ligá-lo.', done: 'O servidor vai desligar em 1 minuto.' }
  };

  function reset() {
    confirming = false;
    conf.hidden = true; back.hidden = true;
    go.textContent = TXT[op].cta; go.disabled = false;
  }
  function post(action, data) {
    var body = new URLSearchParams(data || {});
    return fetch(BASE + action, { method: 'POST', credentials: 'same-origin', headers: { 'X-BV-Token': TOKEN }, body: body })
      .then(function (r) { return r.json().then(function (j) { if (!r.ok || j.erro) throw new Error(j.erro || (EN ? 'Failed' : 'Falha')); return j; }); });
  }
  function showErr(m) { err.textContent = m; err.hidden = false; }

  Array.prototype.forEach.call(root.querySelectorAll('.bve-opt'), function (b) {
    b.addEventListener('click', function () {
      Array.prototype.forEach.call(root.querySelectorAll('.bve-opt'), function (x) {
        x.classList.remove('is-selected'); x.setAttribute('aria-checked', 'false');
      });
      b.classList.add('is-selected'); b.setAttribute('aria-checked', 'true');
      op = b.getAttribute('data-op'); reset();
    });
  });
  back.addEventListener('click', reset);
  go.addEventListener('click', function () {
    err.hidden = true;
    if (!confirming) {
      confirming = true;
      conf.textContent = TXT[op].ask; conf.hidden = false; back.hidden = false;
      go.textContent = TXT[op].sure;
      return;
    }
    go.disabled = true;
    post('power', { op: op }).then(function () {
      pendText.textContent = TXT[op].done; pend.hidden = false; reset();
    }).catch(function (e) { showErr(e.message); reset(); });
  });
  document.getElementById('bve-cancel').addEventListener('click', function () {
    err.hidden = true;
    post('cancel').then(function () { pend.hidden = true; })
      .catch(function (e) { showErr(e.message); });
  });
})();
