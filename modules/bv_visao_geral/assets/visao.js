/* Visão geral (Bradial Voz): busca os dados a cada 5 s e desenha a tela. Sem dependências. */
(function () {
  'use strict';
  var root = document.getElementById('bvg');
  if (!root) return;
  var API = root.getAttribute('data-api');
  var OP = root.getAttribute('data-op');
  var SPRITE = 'themes/bradial/assets/icons/sprite.svg';
  var POLL = 5000;
  var last = null, lastOk = 0, timer = null, busy = false;

  var nf = new Intl.NumberFormat('pt-BR');
  var nf1 = new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 1 });
  function $(id) { return document.getElementById(id); }
  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function icon(n) { return '<svg class="bvg-i" aria-hidden="true"><use href="' + SPRITE + '#' + n + '"></use></svg>'; }
  function bytes(b) {
    if (b == null) return '—';
    var u = ['B', 'KB', 'MB', 'GB', 'TB'], i = 0;
    while (b >= 1024 && i < u.length - 1) { b /= 1024; i++; }
    return nf1.format(b) + ' ' + u[i];
  }
  function rate(b) { return b == null ? '—' : bytes(b) + '/s'; }
  function dur(s) {
    if (s == null) return '—';
    var d = Math.floor(s / 86400), h = Math.floor(s % 86400 / 3600), m = Math.floor(s % 3600 / 60);
    if (d) return d + 'd ' + h + 'h';
    if (h) return h + 'h ' + m + 'min';
    return Math.max(m, 0) + 'min';
  }
  function when(ts) {
    if (!ts) return '';
    var d = new Date(ts * 1000);
    var p = function (n) { return (n < 10 ? '0' : '') + n; };
    return p(d.getDate()) + '/' + p(d.getMonth() + 1) + ' ' + p(d.getHours()) + ':' + p(d.getMinutes());
  }

  /* ---------- Telefonia ---------- */
  function metric(label, k, total) {
    var v = k && k.valor != null ? nf.format(k.valor) : '—';
    var t = k && total && k.total != null ? '<small>/' + nf.format(k.total) + '</small>' : '';
    var det = k ? k.detalhe : 'disponível com o Painel do Operador';
    return '<div class="bvg-metric' + (k && k.alerta ? ' is-alert' : '') + '">' +
      '<span class="bvg-metric-label">' + esc(label) + '</span>' +
      '<strong class="bvg-metric-value">' + v + t + '</strong>' +
      '<span class="bvg-metric-detail">' + esc(det) + '</span></div>';
  }
  function renderKpis(t) {
    var html = metric('Em ligação', t.ligacao) + metric('Tocando', t.tocando) + metric('Na fila', t.fila) +
      metric('Ramais online', t.ramais, true) + metric('Troncos', t.troncos, true);
    if (t.fonte === 'painel' && OP) html += '<a class="bvg-kpi-link" href="' + OP + '">Painel do operador ' + icon('chevron-right') + '</a>';
    $('bvg-kpis').innerHTML = html;
  }

  /* ---------- Servidor ---------- */
  function bar(label, pct, detail) {
    var p = pct == null ? 0 : Math.max(0, Math.min(100, pct));
    var lvl = p >= 90 ? ' is-crit' : (p >= 75 ? ' is-warn' : '');
    return '<div class="bvg-bar' + lvl + '">' +
      '<div class="bvg-bar-top"><span>' + esc(label) + '</span>' +
      '<span class="bvg-muted">' + esc(detail || '') + '</span>' +
      '<strong>' + (pct == null ? '—' : nf.format(pct) + '%') + '</strong></div>' +
      '<div class="bvg-track" role="progressbar" aria-label="' + esc(label) + '" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' + p + '">' +
      '<span style="width:' + p + '%"></span></div></div>';
  }
  function useText(u) { return u ? bytes(u.usado) + ' de ' + bytes(u.total) : 'sem leitura'; }
  function renderServidor(s, a) {
    var c = s.cpu || {};
    var info = [];
    if (c.modelo) info.push(c.modelo);
    if (c.nucleos) info.push(c.nucleos + (c.nucleos === 1 ? ' núcleo' : ' núcleos'));
    if (c.mhz) info.push(nf.format(c.mhz) + ' MHz');
    $('bvg-cpuinfo').textContent = info.join(' · ');
    $('bvg-bars').innerHTML =
      bar('CPU', c.pct, c.carga != null ? 'carga ' + nf1.format(c.carga) : '') +
      bar('Memória', s.memoria && s.memoria.pct, useText(s.memoria)) +
      bar('Swap', s.swap && s.swap.pct, s.swap ? useText(s.swap) : 'sem swap') +
      bar('Disco /', s.disco && s.disco.pct, useText(s.disco));
    var r = s.rede;
    $('bvg-srvfoot').innerHTML =
      '<div><dt>Rede' + (r ? ' ' + esc(r.iface) : '') + '</dt><dd>' + (r ? '↓ ' + rate(r.rx) + ' · ↑ ' + rate(r.tx) : '—') + '</dd></div>' +
      '<div><dt>Servidor ligado há</dt><dd>' + dur(s.ligado) + '</dd></div>' +
      '<div><dt>Último reload do PBX</dt><dd>' + (a && a.reload != null ? 'há ' + dur(a.reload) : '—') + '</dd></div>';
  }

  /* ---------- Avisos ---------- */
  var TIPO = {
    erro: ['circle-alert', 'is-error'], aviso: ['triangle-alert', 'is-warn'],
    atualizacao: ['package', ''], info: ['info', '']
  };
  function renderAvisos(av) {
    var list = $('bvg-notices');
    if (av.erro) {
      list.innerHTML = '<li class="bvg-empty">' + esc(av.erro) + '</li>';
      $('bvg-av-count').textContent = '';
      return;
    }
    var it = av.itens || [];
    $('bvg-av-count').textContent = it.length ? it.length + (it.length === 1 ? ' pendente' : ' pendentes') : '';
    if (!it.length) { list.innerHTML = '<li class="bvg-empty">' + icon('info') + 'Nenhum aviso no momento.</li>'; return; }
    list.innerHTML = it.map(function (n) {
      var t = TIPO[n.tipo] || TIPO.info;
      return '<li class="bvg-notice ' + t[1] + '">' + icon(t[0]) +
        '<div><span>' + esc(n.texto) + '</span><small>' + esc(when(n.quando)) + '</small></div></li>';
    }).join('');
  }

  /* ---------- Cabeçalho ---------- */
  function renderHead() {
    var sub = $('bvg-sub');
    if (!lastOk) return;
    var ago = Math.round((Date.now() - lastOk) / 1000);
    sub.textContent = ago < 8 ? 'Atualizado agora' : 'Atualizado há ' + ago + 's';
    if (ago > 30) sub.textContent += ' · sem resposta do servidor, tentando de novo';
  }
  function renderStatus(a) {
    var el = $('bvg-status');
    el.hidden = false;
    el.classList.toggle('is-off', !a.ativo);
    $('bvg-status-text').textContent = a.ativo ? 'Asterisk ativo' : 'Asterisk sem resposta';
  }

  function render(d) {
    renderStatus(d.asterisk || {});
    renderKpis(d.telefonia || {});
    renderServidor(d.servidor || {}, d.asterisk);
    renderAvisos(d.avisos || { itens: [] });
    renderHead();
  }

  function poll() {
    clearTimeout(timer);
    if (busy) return;
    busy = true;
    var ctl = window.AbortController ? new AbortController() : null;
    var to = ctl ? setTimeout(function () { ctl.abort(); }, 15000) : null;
    fetch(API, { credentials: 'same-origin', cache: 'no-store', signal: ctl ? ctl.signal : undefined })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (d && d.erro) throw new Error(d.erro);
        last = d; lastOk = Date.now(); render(d);
      })
      .catch(function () {
        if (!lastOk) $('bvg-sub').textContent = 'Não foi possível ler o servidor. Tentando de novo…';
        renderHead();
      })
      .then(function () {
        if (to) clearTimeout(to);
        busy = false;
        if (!document.hidden) timer = setTimeout(poll, POLL);
      });
  }
  document.addEventListener('visibilitychange', function () { if (!document.hidden) poll(); });
  setInterval(renderHead, 5000);
  poll();
})();
