/* Visão geral (Bradial Voz): busca os dados a cada 5 s e desenha a tela no layout do
   protótipo Bradial Voz v6. Sem dependências. */
(function () {
  'use strict';
  var root = document.getElementById('bvg');
  if (!root) return;
  var API = root.getAttribute('data-api');
  var DISMISS = root.getAttribute('data-dismiss');
  var TOKEN = root.getAttribute('data-token');
  var OP = root.getAttribute('data-op');
  var SPRITE = root.getAttribute('data-sprite') || 'themes/bradial/assets/icons/sprite.svg';
  var POLL = 5000;
  var EN = root.getAttribute('data-lang') === 'en';
  function tr(pt, en) { return EN ? en : pt; }
  var LOC = EN ? 'en-US' : 'pt-BR';
  var lastOk = 0, timer = null, busy = false, hidden = {};

  var nf = new Intl.NumberFormat(LOC, { maximumFractionDigits: 0 });
  var nf1 = new Intl.NumberFormat(LOC, { maximumFractionDigits: 1 });
  function $(id) { return document.getElementById(id); }
  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function icon(n) { return '<svg class="bvg-i" aria-hidden="true"><use href="' + SPRITE + '#' + n + '"></use></svg>'; }
  function bytes(b, unit, fmt) {
    if (b == null) return '—';
    var u = ['B', 'kB', 'MB', 'GB', 'TB'], i = 0;
    if (unit) { i = u.indexOf(unit); b = b / Math.pow(1024, i); }
    else while (b >= 1024 && i < u.length - 1) { b /= 1024; i++; }
    return (fmt || (i >= 3 ? nf1 : nf)).format(b) + ' ' + u[i];
  }
  function rate(b) { return b == null ? '—' : bytes(b, null, nf1) + '/s'; }
  function dur(s) {
    if (s == null) return '—';
    var d = Math.floor(s / 86400), h = Math.floor(s % 86400 / 3600), m = Math.floor(s % 3600 / 60);
    if (d) return d + 'd ' + h + 'h';
    if (h) return h + 'h ' + m + 'min';
    return Math.max(m, 0) + 'min';
  }
  function when(ts) {
    if (!ts) return '';
    var d = new Date(ts * 1000), p = function (n) { return (n < 10 ? '0' : '') + n; };
    return p(d.getDate()) + '/' + p(d.getMonth() + 1) + ' ' + p(d.getHours()) + ':' + p(d.getMinutes());
  }

  /* ---------- Faixa de telefonia ---------- */
  function kpi(label, k, total, href) {
    var v = k && k.valor != null ? nf.format(k.valor) : '—';
    var of = k && total && k.total != null ? '<span class="bvg-kpi-of">/' + nf.format(k.total) + '</span>' : '';
    var det = k ? k.detalhe : tr('com o Painel do Operador', 'with the Operator panel');
    var tag = href ? 'a href="' + href + '"' : 'div';
    return '<' + tag + ' class="bvg-kpi' + (k && k.alerta ? ' is-alert' : '') + '">' +
      '<span class="bvg-kpi-label">' + esc(label) + '</span>' +
      '<span class="bvg-kpi-num"><span class="bvg-kpi-value">' + v + '</span>' + of + '</span>' +
      '<span class="bvg-kpi-sub">' + esc(det) + '</span></' + (href ? 'a' : 'div') + '>';
  }
  function renderKpis(t) {
    $('bvg-kpis').innerHTML =
      kpi(tr('Chamadas ativas', 'Active calls'), t.chamadas, false, OP) + kpi(tr('Canais', 'Channels'), t.canais, false, OP) +
      kpi(tr('Na fila', 'In queue'), t.fila, false, OP) + kpi(tr('Ramais online', 'Extensions online'), t.ramais, true, OP) +
      kpi(tr('Troncos', 'Trunks'), t.troncos, true, OP);
  }

  /* ---------- Recursos do servidor ---------- */
  function bar(label, pct, detail) {
    var p = pct == null ? 0 : Math.max(0, Math.min(100, pct));
    var lvl = p >= 90 ? ' is-crit' : (p >= 75 ? ' is-warn' : '');
    return '<div class="bvg-bar' + lvl + '"><div class="bvg-bar-top">' +
      '<span class="bvg-bar-label">' + esc(label) + '</span>' +
      '<span class="bvg-muted">' + esc(detail || '') + '</span>' +
      '<span class="bvg-bar-pct">' + (pct == null ? '—' : nf.format(pct) + '%') + '</span></div>' +
      '<div class="bvg-track" role="progressbar" aria-label="' + esc(label) + '" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' + p + '">' +
      '<span style="width:' + p + '%"></span></div></div>';
  }
  function useText(u, unit) { return u ? bytes(u.usado, unit) + tr(' de ', ' of ') + bytes(u.total, unit) : tr('sem leitura', 'no data'); }
  function renderServidor(s, a) {
    var c = s.cpu || {};
    var info = [];
    if (c.modelo) info.push(c.modelo.replace(/\s+version\s+/i, ' '));
    if (c.mhz) info.push(nf.format(c.mhz) + ' MHz');
    if (s.ligado != null) info.push(tr('ligado há ', 'up ') + dur(s.ligado));
    $('bvg-cpuinfo').textContent = info.join(' · ');
    var memUnit = s.memoria && s.memoria.total < 64 * Math.pow(1024, 3) ? 'MB' : null;
    var swUnit = s.swap && s.swap.total < 64 * Math.pow(1024, 3) ? 'MB' : null;
    $('bvg-bars').innerHTML =
      bar('CPU', c.pct, c.nucleos ? c.nucleos + (c.nucleos === 1 ? tr(' núcleo', ' core') : tr(' núcleos', ' cores')) : '') +
      bar(tr('Memória', 'Memory'), s.memoria && s.memoria.pct, useText(s.memoria, memUnit)) +
      bar('Swap', s.swap && s.swap.pct, s.swap ? useText(s.swap, swUnit) : tr('sem swap', 'no swap')) +
      bar(tr('Disco /', 'Disk /'), s.disco && s.disco.pct, useText(s.disco));
    var r = s.rede;
    $('bvg-srvfoot').innerHTML =
      '<div><span class="bvg-muted">' + tr('Rede', 'Network') + (r ? ' ' + esc(r.iface) : '') + '</span><strong>' +
      (r ? rate(r.rx) + ' in · ' + rate(r.tx) + ' out' : '—') + '</strong></div>' +
      '<div><span class="bvg-muted">' + tr('Último reload', 'Last reload') + '</span><strong>' +
      (a && a.reload != null ? (EN ? dur(a.reload) + ' ago' : 'há ' + dur(a.reload)) : '—') + '</strong></div>';
  }

  /* ---------- Avisos do PBX ---------- */
  var TIPO = {
    erro: ['triangle-alert', 'is-error'], aviso: ['triangle-alert', 'is-warn'],
    atualizacao: ['download', ''], info: ['mail', '']
  };
  function renderAvisos(av) {
    var box = $('bvg-notices');
    if (av.erro) { box.innerHTML = '<p class="bvg-empty">' + esc(av.erro) + '</p>'; $('bvg-av-count').textContent = ''; return; }
    var it = (av.itens || []).filter(function (n) { return !hidden[n.modulo + '|' + n.id]; });
    $('bvg-av-count').textContent = it.length ? it.length + (it.length === 1 ? tr(' pendente', ' pending') : tr(' pendentes', ' pending')) : '';
    if (!it.length) { box.innerHTML = '<p class="bvg-empty">' + tr('Nenhum aviso pendente.', 'No pending notices.') + '</p>'; return; }
    box.innerHTML = it.map(function (n) {
      var t = TIPO[n.tipo] || TIPO.info;
      if (n.tipo === 'info' && !/e-mail|email/i.test(n.texto)) t = ['info', ''];
      return '<div class="bvg-notice ' + t[1] + '">' + '<span class="bvg-notice-ic">' + icon(t[0]) + '</span>' +
        '<div class="bvg-notice-text"><span>' + esc(n.texto) + '</span><small>' + esc(when(n.quando)) + '</small></div>' +
        (n.removivel ? '<button type="button" class="bvg-x" data-mod="' + esc(n.modulo) + '" data-id="' + esc(n.id) + '" title="' + tr('Dispensar aviso', 'Dismiss notice') + '" aria-label="' + tr('Dispensar aviso', 'Dismiss notice') + '">' + icon('x') + '</button>' : '') +
        '</div>';
    }).join('');
  }
  $('bvg-notices').addEventListener('click', function (e) {
    var b = e.target.closest && e.target.closest('.bvg-x');
    if (!b) return;
    var key = b.getAttribute('data-mod') + '|' + b.getAttribute('data-id');
    hidden[key] = true;
    b.closest('.bvg-notice').remove();
    var body = new URLSearchParams();
    body.set('modulo', b.getAttribute('data-mod'));
    body.set('id', b.getAttribute('data-id'));
    fetch(DISMISS, { method: 'POST', credentials: 'same-origin', headers: { 'X-BV-Token': TOKEN }, body: body })
      .then(function (r) { if (!r.ok) delete hidden[key]; })
      .catch(function () { delete hidden[key]; })
      .then(poll);
  });

  /* ---------- Serviços ---------- */
  var ESTADO = { rodando: [tr('Rodando', 'Running'), 'bl-badge--secondary'], iniciando: [tr('Iniciando', 'Starting'), 'bl-badge--outline'], parado: [tr('Parado', 'Stopped'), 'bl-badge--destructive'] };
  function renderServicos(list) {
    $('bvg-services').innerHTML = (list || []).map(function (s) {
      var st = ESTADO[s.estado] || ESTADO.parado;
      return '<div class="bvg-svc-row"><span class="bvg-svc-ic">' + icon(s.icone) + '</span>' +
        '<span class="bvg-svc-name">' + esc(s.nome) + '</span>' +
        '<span class="bl-badge ' + st[1] + '">' + st[0] + '</span></div>';
    }).join('');
  }

  /* ---------- Cabeçalho ---------- */
  function renderHead(d) {
    var sub = $('bvg-sub');
    if (lastOk) {
      var ago = Math.round((Date.now() - lastOk) / 1000);
      sub.textContent = ago < 8 ? tr('Atualizado agora', 'Updated just now') : (EN ? 'Updated ' + ago + 's ago' : 'Atualizado há ' + ago + 's');
      if (ago > 30) sub.textContent += tr(' · sem resposta do servidor, tentando de novo', ' · no response from the server, retrying');
    }
    if (!d) return;
    var a = d.asterisk || {}, t = d.telefonia || {}, live = $('bvg-live');
    live.hidden = false;
    live.classList.toggle('is-off', !a.ativo);
    var calls = t.chamadas && t.chamadas.valor != null ? t.chamadas.valor : null;
    $('bvg-live-text').textContent = a.ativo
      ? tr('Asterisk ativo', 'Asterisk running') + (calls != null ? ' · ' + calls + (calls === 1 ? tr(' chamada agora', ' call now') : tr(' chamadas agora', ' calls now')) : '')
      : tr('Asterisk sem resposta', 'Asterisk not responding');
  }

  function render(d) {
    renderKpis(d.telefonia || {});
    renderServidor(d.servidor || {}, d.asterisk);
    renderAvisos(d.avisos || { itens: [] });
    renderServicos(d.servicos);
    renderHead(d);
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
        lastOk = Date.now(); render(d);
      })
      .catch(function () {
        if (!lastOk) $('bvg-sub').textContent = tr('Não foi possível ler o servidor. Tentando de novo…', 'Could not read the server. Retrying…');
        renderHead(null);
      })
      .then(function () {
        if (to) clearTimeout(to);
        busy = false;
        if (!document.hidden) timer = setTimeout(poll, POLL);
      });
  }
  document.addEventListener('visibilitychange', function () { if (!document.hidden) poll(); });
  setInterval(function () { renderHead(null); }, 5000);
  poll();
})();
