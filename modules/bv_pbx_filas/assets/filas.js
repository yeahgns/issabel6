/* Filas (Bradial Voz): lista e formulário, no layout do protótipo. Reaproveita o CSS de Ramais. */
(function () {
  'use strict';
  var root = document.getElementById('bvr');
  if (!root) return;
  var BASE = root.getAttribute('data-base'), TOKEN = root.getAttribute('data-token');
  var SPRITE = root.getAttribute('data-sprite');
  var EN = root.getAttribute('data-lang') === 'en';
  function tr(pt, en) { return EN ? en : pt; }

  var state = { itens: [], aoVivo: false, pendente: null, busca: '', view: 'list', atual: null, opts: null };
  var refreshTimer = null;

  var STRAT = {
    ringall: tr('Tocar todos juntos', 'Ring all'), rrmemory: tr('Revezamento (lembra o último)', 'Round robin with memory'),
    leastrecent: tr('Quem atendeu há mais tempo', 'Least recent'), fewestcalls: tr('Quem atendeu menos', 'Fewest calls'),
    linear: tr('Em ordem (lista fixa)', 'Linear'), rrordered: tr('Revezamento em ordem', 'Round robin ordered'),
    random: tr('Aleatório', 'Random'), wrandom: tr('Aleatório com peso', 'Weighted random')
  };
  var SKIP = [
    ['no', tr('Não: toca mesmo se o ramal estiver em ligação', 'No: ring even if the agent is on a call')],
    ['yes', tr('Sim: pula ramais em qualquer ligação', 'Yes: skip agents on any call')],
    ['queue_calls_only', tr('Só se estiver em ligação de fila', 'Only if on a queue call')],
    ['yes_ringinuse_no', tr('Sim, e não toca quem está em uso', 'Yes, and do not ring in-use agents')]
  ];

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function icon(n) { return '<svg class="bvr-i" aria-hidden="true"><use href="' + SPRITE + '#' + n + '"></use></svg>'; }
  function call(action, opts) {
    opts = opts || {};
    var init = { credentials: 'same-origin', cache: 'no-store', headers: {} };
    if (opts.post) {
      init.method = 'POST'; init.headers['X-BV-Token'] = TOKEN;
      if (opts.json) { init.headers['Content-Type'] = 'application/json'; init.body = JSON.stringify(opts.json); }
      else init.body = new URLSearchParams(opts.form || {});
    }
    return fetch(BASE + action + (opts.qs || ''), init).then(function (r) {
      return r.json().catch(function () { return { erro: tr('Resposta inválida do servidor.', 'Invalid server response.') }; })
        .then(function (j) { if (!r.ok || j.erro) { var e = new Error(j.erro || 'Falha'); e.campo = j.campo; throw e; } return j; });
    });
  }
  function secs(v) {
    v = parseInt(v, 10);
    if (!v) return tr('Ilimitada', 'Unlimited');
    if (v % 60 === 0) return (v / 60) + ' min';
    return v + ' s';
  }

  /* ---------------- pendências ---------------- */
  function pendingBar() {
    var p = state.pendente;
    if (!p || !p.itens || !p.itens.length) return '';
    var n = p.itens.length;
    return '<div class="bvr-pending" role="status">' + icon('info') +
      '<span>' + (EN ? n + (n === 1 ? ' change not applied yet.' : ' changes not applied yet.') :
        n + (n === 1 ? ' alteração ainda não aplicada no PBX.' : ' alterações ainda não aplicadas no PBX.')) + '</span>' +
      '<button type="button" class="bl-btn bl-btn--primary bl-btn--sm" data-act="apply">' + tr('Aplicar alterações', 'Apply changes') + '</button></div>';
  }

  /* ---------------- lista ---------------- */
  function renderList() {
    var q = state.busca.trim().toLowerCase();
    var waiting = 0;
    state.itens.forEach(function (x) { waiting += x.waiting || 0; });
    var sub = state.itens.length + (EN ? ' queues' : ' filas') + (state.aoVivo ? ' · ' + waiting + tr(' esperando agora', ' waiting now') : '');
    var rows = state.itens.filter(function (x) {
      return !q || (x.num + ' ' + x.name + ' ' + x.members.map(function (m) { return m.ext + ' ' + m.name; }).join(' ')).toLowerCase().indexOf(q) !== -1;
    }).map(function (x) {
      var shown = x.members.slice(0, 5).map(function (m) { return '<span class="bvr-chip" title="' + esc(m.name) + '">' + esc(m.ext) + '</span>'; }).join('');
      if (x.members.length > 5) shown += '<span class="bvr-chip">+' + (x.members.length - 5) + '</span>';
      var live = x.waiting === null ? '<span class="bvr-muted">—</span>' :
        '<span class="bvf-wait' + (x.waiting ? ' is-on' : '') + '">' + x.waiting + '</span>';
      return '<tr data-num="' + esc(x.num) + '" tabindex="0">' +
        '<td><strong class="bvr-num">' + esc(x.num) + '</strong>' + esc(x.name) + '</td>' +
        '<td class="bvr-muted">' + esc(STRAT[x.strategy] || x.strategy) + '</td>' +
        '<td><div class="bvr-chips">' + (shown || '<span class="bvr-muted">' + tr('Nenhum', 'None') + '</span>') + '</div></td>' +
        '<td>' + live + '</td><td class="bvr-go">' + icon('chevron-right') + '</td></tr>';
    }).join('');
    if (!rows) rows = '<tr class="bvr-empty"><td colspan="5">' + tr('Nenhuma fila encontrada.', 'No queues found.') + '</td></tr>';
    root.innerHTML = pendingBar() +
      '<header class="bvr-head"><div><h1>' + tr('Filas', 'Queues') + '</h1><p>' + esc(sub) + '</p></div>' +
      '<div class="bvr-actions"><button type="button" class="bl-btn bl-btn--primary" data-act="novo">' + icon('list-ordered') + tr('Nova fila', 'New queue') + '</button></div></header>' +
      '<div class="bvr-toolbar"><div></div><label class="bvr-search">' + icon('search') +
        '<input type="search" id="bvr-q" placeholder="' + tr('Número, nome ou ramal', 'Number, name or extension') + '" value="' + esc(state.busca) + '" /></label></div>' +
      '<div class="bvr-card"><table class="bvr-table"><thead><tr><th>' + tr('Fila', 'Queue') + '</th><th>' + tr('Distribuição', 'Strategy') +
        '</th><th>' + tr('Ramais', 'Extensions') + '</th><th>' + tr('Esperando', 'Waiting') + '</th><th></th></tr></thead><tbody>' + rows + '</tbody></table></div>';
    var qi = document.getElementById('bvr-q');
    qi.addEventListener('input', function () { state.busca = qi.value; var pos = qi.selectionStart; renderList(); var n = document.getElementById('bvr-q'); n.focus(); n.setSelectionRange(pos, pos); });
  }
  function loadList(silent) {
    clearTimeout(refreshTimer);
    return call('list').then(function (d) {
      state.itens = d.itens || []; state.aoVivo = !!d.aoVivo; state.pendente = d.pendente;
      if (state.view === 'list') renderList();
    }).catch(function (e) {
      if (!silent) root.innerHTML = '<div class="bvr-error">' + esc(e.message) + '</div>';
    }).then(function () {
      if (state.view === 'list') refreshTimer = setTimeout(function () { loadList(true); }, 20000);
    });
  }

  /* ---------------- formulário ---------------- */
  function field(name, label, input, hint) {
    return '<label class="bvr-field" data-field="' + name + '"><span>' + label + '</span>' + input +
      (hint ? '<small>' + hint + '</small>' : '') + '<em class="bvr-ferr"></em></label>';
  }
  function sel(name, value, opts) {
    var has = opts.some(function (o) { return String(o[0]) === String(value); });
    if (!has && value !== undefined && value !== null) opts = opts.concat([[value, String(value)]]);
    return '<select class="bl-input" name="' + name + '">' + opts.map(function (o) {
      return '<option value="' + esc(o[0]) + '"' + (String(value) === String(o[0]) ? ' selected' : '') + '>' + esc(o[1]) + '</option>';
    }).join('') + '</select>';
  }
  function destSel(name, value, emptyLabel) {
    var found = false, html = '<option value="">' + esc(emptyLabel) + '</option>';
    (state.opts.destinos || []).forEach(function (g) {
      html += '<optgroup label="' + esc(g.grupo) + '">' + g.itens.map(function (d) {
        var on = d.valor === value; if (on) found = true;
        return '<option value="' + esc(d.valor) + '"' + (on ? ' selected' : '') + '>' + esc(d.nome) + '</option>';
      }).join('') + '</optgroup>';
    });
    if (value && !found) html += '<optgroup label="' + tr('Atual', 'Current') + '"><option value="' + esc(value) + '" selected>' + esc(value) + '</option></optgroup>';
    return '<select class="bl-input" name="' + name + '">' + html + '</select>';
  }
  function sw(name, on, label, hint) {
    return '<label class="bvr-switch"><input type="checkbox" name="' + name + '"' + (on ? ' checked' : '') + ' />' +
      '<span class="bvr-switch-ui" aria-hidden="true"></span><span class="bvr-switch-text"><strong>' + label + '</strong>' +
      (hint ? '<small>' + hint + '</small>' : '') + '</span></label>';
  }
  function membersBox(sel) {
    var chosen = {}; sel.forEach(function (m) { chosen[m.ext] = m.penalty || '0'; });
    return '<div class="bvf-members"><div class="bvf-mtools"><label class="bvr-search bvf-msearch">' + icon('search') +
      '<input type="search" id="bvf-mq" placeholder="' + tr('Filtrar ramais', 'Filter extensions') + '" /></label>' +
      '<span class="bvr-muted" id="bvf-mcount"></span></div><div class="bvf-mgrid" id="bvf-mgrid">' +
      state.opts.ramais.map(function (r) {
        var on = chosen.hasOwnProperty(r.ext);
        return '<label class="bvf-m' + (on ? ' is-on' : '') + '" data-q="' + esc((r.ext + ' ' + r.name).toLowerCase()) + '">' +
          '<input type="checkbox" value="' + esc(r.ext) + '" data-penalty="' + esc(on ? chosen[r.ext] : '0') + '"' + (on ? ' checked' : '') + ' />' +
          '<strong>' + esc(r.ext) + '</strong><span>' + esc(r.name) + '</span></label>';
      }).join('') + '</div></div>';
  }
  function renderForm(f, novo) {
    state.view = 'form'; clearTimeout(refreshTimer);
    var o = state.opts;
    var timeouts = [['10', '10 s'], ['15', '15 s'], ['20', '20 s'], ['30', '30 s'], ['45', '45 s'], ['60', '60 s']];
    var waits = [['', tr('Ilimitada', 'Unlimited')], ['30', '30 s'], ['60', '1 min'], ['120', '2 min'], ['180', '3 min'], ['300', '5 min'], ['600', '10 min']];
    if (f.max_wait && !waits.some(function (w) { return w[0] === f.max_wait; })) waits.push([f.max_wait, secs(f.max_wait)]);

    root.innerHTML = pendingBar() +
      '<header class="bvr-head"><div><button type="button" class="bvr-back" data-act="voltar">' + icon('chevron-right') + tr('Filas', 'Queues') + '</button>' +
      '<h1>' + (novo ? tr('Nova fila', 'New queue') : tr('Fila ', 'Queue ') + esc(f.num)) + '</h1><p>' +
      (novo ? tr('Distribui as ligações entre os ramais participantes.', 'Distributes calls among the member extensions.') : esc(f.name)) + '</p></div>' +
      '<div class="bvr-actions">' + (novo ? '' : '<button type="button" class="bl-btn bl-btn--ghost" data-act="excluir">' + tr('Excluir', 'Delete') + '</button>') +
      '<button type="button" class="bl-btn bl-btn--outline" data-act="voltar">' + tr('Cancelar', 'Cancel') + '</button>' +
      '<button type="submit" form="bvr-form" class="bl-btn bl-btn--primary">' + tr('Salvar fila', 'Save queue') + '</button></div></header>' +
      '<p class="bvr-error" id="bvr-ferror" role="alert" hidden></p>' +
      '<form id="bvr-form" class="bvr-form" novalidate>' +
      '<section class="bvr-card bvr-sec"><div class="bvr-sec-head"><h2>' + tr('Identificação', 'Identification') + '</h2></div><div class="bvr-grid">' +
        field('num', tr('Número da fila', 'Queue number'), '<input class="bl-input" name="num" inputmode="numeric" value="' + esc(f.num) + '"' + (novo ? '' : ' disabled') + ' />', tr('Discado para entrar na fila.', 'Dialed to enter the queue.')) +
        field('name', tr('Nome', 'Name'), '<input class="bl-input" name="name" maxlength="35" value="' + esc(f.name) + '" />') +
      '</div></section>' +
      '<section class="bvr-card bvr-sec"><div class="bvr-sec-head"><h2>' + tr('Ramais participantes', 'Member extensions') + '</h2><p>' +
        tr('Ramais fixos da fila (static agents). Agentes do call center entram pelo login no console.', 'Static members. Call center agents join by logging in to the console.') + '</p></div>' +
        membersBox(f.members) + '<em class="bvr-ferr" data-for="members"></em></section>' +
      '<section class="bvr-card bvr-sec"><div class="bvr-sec-head"><h2>' + tr('Distribuição e tempos', 'Strategy and timing') + '</h2></div><div class="bvr-grid">' +
        field('strategy', tr('Como distribuir', 'Ring strategy'), sel('strategy', f.strategy, Object.keys(STRAT).map(function (k) { return [k, STRAT[k]]; }))) +
        field('timeout', tr('Toque por ramal', 'Agent timeout'), sel('timeout', f.timeout || '15', timeouts), tr('Quanto tempo cada ramal toca antes de tentar o próximo.', 'How long each extension rings before trying the next.')) +
        field('max_wait', tr('Espera máxima', 'Max wait time'), sel('max_wait', f.max_wait || '', waits), tr('Depois disso, a ligação vai para o destino de falha.', 'After this, the call goes to the failover destination.')) +
      '</div></section>' +
      '<section class="bvr-card bvr-sec"><div class="bvr-sec-head"><h2>' + tr('Comportamento', 'Behaviour') + '</h2></div><div class="bvr-grid">' +
        field('skip_busy', tr('Pular ramais ocupados', 'Skip busy agents'), sel('skip_busy', f.skip_busy, SKIP)) +
        field('music', tr('Música de espera', 'Music on hold class'), sel('music', f.music, o.musicas.map(function (m) { return [m, m === 'default' ? tr('Padrão (default)', 'Default') : m]; })),
          tr('Classes cadastradas no PBX. O cadastro de áudios vai para PBX > Áudios.', 'Classes registered in the PBX. Audio management goes to PBX > Audio.')) +
      '</div><div class="bvr-switches">' + sw('recording', f.recording === 'yes', tr('Gravar ligações da fila', 'Record queue calls'), tr('Grava em WAV as ligações atendidas pela fila.', 'Records calls answered by the queue as WAV.')) + '</div>' +
      '<div class="bvr-grid bvf-recmode"' + (f.recording === 'yes' ? '' : ' hidden') + '>' +
        field('recording_mode', tr('O que gravar', 'What to record'), sel('recording_mode', f.recording_mode, [['include_hold_time', tr('Desde a entrada na fila (inclui a espera)', 'From joining (includes hold time)')], ['after_answered', tr('Só depois que o ramal atende', 'Only after answered')]])) +
      '</div></section>' +
      '<section class="bvr-card bvr-sec"><div class="bvr-sec-head"><h2>' + tr('Destinos', 'Destinations') + '</h2></div><div class="bvr-grid">' +
        field('failover', tr('Se ninguém atender', 'Failover destination'), destSel('failover', f.failover, tr('Nenhum (desliga)', 'None (hang up)')), tr('Quando a espera máxima acaba ou não há ramais disponíveis.', 'When the max wait ends or no agents are available.')) +
        field('continue', tr('Depois que o ramal desligar', 'Queue continue destination'), destSel('continue', f.continue, tr('Nenhum (a ligação termina)', 'None (call ends)')), tr('Para onde o cliente segue quando o atendente encerra, ex.: pesquisa de satisfação.', 'Where the caller goes when the agent hangs up, e.g. a survey.')) +
      '</div>' + (novo ? '' : '<button type="button" class="bvr-link" data-act="pbx">' + icon('sliders-horizontal') + tr('Todas as opções no PBX', 'All options in the PBX') + '</button>') + '</section>' +
      '</form>';

    var form = document.getElementById('bvr-form');
    form.elements['recording'].addEventListener('change', function () { root.querySelector('.bvf-recmode').hidden = !form.elements['recording'].checked; });
    form.addEventListener('submit', function (e) { e.preventDefault(); save(form, novo); });
    var grid = document.getElementById('bvf-mgrid'), mq = document.getElementById('bvf-mq');
    function count() {
      var n = grid.querySelectorAll('input:checked').length;
      document.getElementById('bvf-mcount').textContent = n + (EN ? ' selected' : (n === 1 ? ' selecionado' : ' selecionados'));
    }
    grid.addEventListener('change', function (e) { var l = e.target.closest('.bvf-m'); if (l) l.classList.toggle('is-on', e.target.checked); count(); });
    mq.addEventListener('input', function () {
      var v = mq.value.trim().toLowerCase();
      Array.prototype.forEach.call(grid.querySelectorAll('.bvf-m'), function (l) { l.hidden = v && l.getAttribute('data-q').indexOf(v) === -1; });
    });
    count();
    var first = novo ? form.elements['num'] : form.elements['name']; if (first) first.focus();
  }

  function formError(msg, campo) {
    Array.prototype.forEach.call(root.querySelectorAll('.bvr-field.is-err'), function (el) { el.classList.remove('is-err'); el.querySelector('.bvr-ferr').textContent = ''; });
    var box = document.getElementById('bvr-ferror');
    var fieldEl = campo ? root.querySelector('.bvr-field[data-field="' + campo + '"]') : null;
    if (fieldEl) {
      fieldEl.classList.add('is-err'); fieldEl.querySelector('.bvr-ferr').textContent = msg; box.hidden = true;
      var inp = fieldEl.querySelector('input,select'); if (inp) inp.focus();
    } else { box.textContent = msg; box.hidden = false; box.scrollIntoView({ block: 'nearest' }); }
  }
  function save(form, novo) {
    var btn = root.querySelector('button[type=submit]'); btn.disabled = true;
    var members = Array.prototype.map.call(document.querySelectorAll('#bvf-mgrid input:checked'), function (i) {
      return { ext: i.value, penalty: i.getAttribute('data-penalty') || '0' };
    });
    var e = form.elements;
    var data = {
      novo: novo, num: novo ? e['num'].value : state.atual.num, name: e['name'].value, members: members,
      strategy: e['strategy'].value, timeout: e['timeout'].value, max_wait: e['max_wait'].value,
      skip_busy: e['skip_busy'].value, music: e['music'].value, recording: e['recording'].checked,
      recording_mode: e['recording_mode'].value, failover: e['failover'].value, continue: e['continue'].value
    };
    call('save', { post: true, json: data }).then(function (d) {
      state.pendente = d.pendente; state.view = 'list';
      toast(novo ? tr('Fila criada. Aplique as alterações para valer no PBX.', 'Queue created. Apply the changes for the PBX to use it.') :
        tr('Fila salva. Aplique as alterações para valer no PBX.', 'Queue saved. Apply the changes for the PBX to use it.'));
      loadList();
    }).catch(function (er) { btn.disabled = false; formError(er.message, er.campo); });
  }
  function withOptions() {
    if (state.opts) return Promise.resolve(state.opts);
    return call('options').then(function (o) { state.opts = o; return o; });
  }
  function openForm(num) {
    root.innerHTML = '<div class="bvr-loading">' + tr('Carregando…', 'Loading…') + '</div>';
    withOptions().then(function () {
      if (!num) {
        state.atual = null;
        var nums = state.itens.map(function (x) { return parseInt(x.num, 10) || 0; });
        var next = nums.length ? Math.max.apply(null, nums) + 1 : 600;
        renderForm({ num: String(next), name: '', members: [], strategy: 'ringall', timeout: '15', max_wait: '', skip_busy: 'no',
          music: 'default', recording: 'no', recording_mode: 'include_hold_time', failover: '', continue: '' }, true);
        return;
      }
      return call('get', { qs: '&num=' + encodeURIComponent(num) }).then(function (d) { state.atual = d.fila; renderForm(d.fila, false); });
    }).catch(function (e) { toast(e.message, true); state.view = 'list'; renderList(); });
  }

  /* ---------------- diálogos ---------------- */
  function dialog(title, text, okLabel, onOk, danger) {
    var m = document.createElement('div');
    m.className = 'bvr-modal';
    m.innerHTML = '<div class="bvr-dialog" role="alertdialog" aria-modal="true" aria-labelledby="bvr-dt"><h2 id="bvr-dt">' + esc(title) + '</h2><p>' + text + '</p>' +
      '<p class="bvr-error" hidden></p><div class="bvr-dialog-actions"><button type="button" class="bl-btn bl-btn--outline" data-x>' + tr('Cancelar', 'Cancel') + '</button>' +
      '<button type="button" class="bl-btn ' + (danger ? 'bl-btn--destructive' : 'bl-btn--primary') + '" data-ok>' + esc(okLabel) + '</button></div></div>';
    document.body.appendChild(m);
    var prev = document.activeElement, ok = m.querySelector('[data-ok]'), x = m.querySelector('[data-x]'), err = m.querySelector('.bvr-error');
    function close() { m.remove(); document.removeEventListener('keydown', key); if (prev && prev.focus) prev.focus(); }
    function key(e) { if (e.key === 'Escape') close(); }
    document.addEventListener('keydown', key);
    x.addEventListener('click', close);
    m.addEventListener('click', function (e) { if (e.target === m) close(); });
    ok.addEventListener('click', function () {
      ok.disabled = true; err.hidden = true;
      Promise.resolve(onOk()).then(close).catch(function (e) { err.textContent = e.message; err.hidden = false; ok.disabled = false; });
    });
    x.focus();
  }
  function pbxDialog(num) {
    var m = document.createElement('div');
    m.className = 'bvr-modal bvr-modal--wide';
    m.innerHTML = '<div class="bvr-dialog bvr-dialog--frame" role="dialog" aria-modal="true" aria-label="PBX"><div class="bvr-frame-head"><strong>' +
      tr('Fila ', 'Queue ') + esc(num) + ' · ' + tr('todas as opções', 'all options') + '</strong><span class="bvr-muted">' +
      tr('Depois de salvar aqui, clique em "Apply Config" na barra vermelha do PBX.', 'After saving here, click "Apply Config" on the PBX red bar.') +
      '</span><button type="button" class="bl-btn bl-btn--outline bl-btn--sm" data-x>' + tr('Fechar', 'Close') + '</button></div>' +
      '<iframe src="/admin/config.php?display=queues&extdisplay=' + encodeURIComponent(num) + '" title="PBX"></iframe></div>';
    document.body.appendChild(m);
    function close() { m.remove(); document.removeEventListener('keydown', key); openForm(num); }
    function key(e) { if (e.key === 'Escape') close(); }
    document.addEventListener('keydown', key);
    m.querySelector('[data-x]').addEventListener('click', close);
  }
  var toastTimer = null;
  function toast(msg, bad) {
    var t = document.getElementById('bvr-toast');
    if (!t) { t = document.createElement('div'); t.id = 'bvr-toast'; t.setAttribute('role', 'status'); document.body.appendChild(t); }
    t.className = 'bvr-toast' + (bad ? ' is-bad' : ''); t.textContent = msg; t.hidden = false;
    clearTimeout(toastTimer); toastTimer = setTimeout(function () { t.hidden = true; }, 5000);
  }

  /* ---------------- eventos ---------------- */
  root.addEventListener('click', function (e) {
    var row = e.target.closest('tr[data-num]');
    if (row && state.view === 'list') { openForm(row.getAttribute('data-num')); return; }
    var b = e.target.closest('[data-act]');
    if (!b) return;
    var act = b.getAttribute('data-act');
    if (act === 'novo') openForm('');
    else if (act === 'voltar') { state.view = 'list'; renderList(); loadList(true); }
    else if (act === 'excluir') {
      var x = state.atual;
      dialog(tr('Excluir a fila ', 'Delete queue ') + x.num + '?',
        tr('Ligações que chegam para essa fila deixam de ser atendidas. Confira rotas de entrada e URAs que apontam para ela.',
          'Calls sent to this queue will no longer be answered. Check inbound routes and IVRs pointing to it.'),
        tr('Excluir fila', 'Delete queue'), function () {
          return call('delete', { post: true, form: { num: x.num } }).then(function (d) {
            state.pendente = d.pendente; state.view = 'list'; toast(tr('Fila excluída. Aplique as alterações.', 'Queue deleted. Apply the changes.')); loadList();
          });
        }, true);
    } else if (act === 'apply') {
      dialog(tr('Aplicar as alterações?', 'Apply the changes?'),
        tr('O PBX recarrega a configuração. Ligações em andamento não caem.', 'The PBX reloads its configuration. Calls in progress are not dropped.'),
        tr('Aplicar', 'Apply'), function () {
          return call('apply', { post: true }).then(function () {
            state.pendente = null; toast(tr('Alterações aplicadas no PBX.', 'Changes applied to the PBX.'));
            if (state.view === 'list') loadList(); else { var pb = root.querySelector('.bvr-pending'); if (pb) pb.remove(); }
          });
        });
    } else if (act === 'pbx') pbxDialog(state.atual.num);
  });
  root.addEventListener('keydown', function (e) {
    var row = e.target.closest && e.target.closest('tr[data-num]');
    if (row && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); openForm(row.getAttribute('data-num')); }
  });

  root.innerHTML = '<div class="bvr-loading">' + tr('Carregando as filas…', 'Loading queues…') + '</div>';
  loadList();
})();
