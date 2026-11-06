/* Ramais (Bradial Voz): lista e formulário, no layout do protótipo Bradial Voz v6. */
(function () {
  'use strict';
  var root = document.getElementById('bvr');
  if (!root) return;
  var BASE = root.getAttribute('data-base'), TOKEN = root.getAttribute('data-token');
  var SPRITE = root.getAttribute('data-sprite');
  var EN = root.getAttribute('data-lang') === 'en';
  function tr(pt, en) { return EN ? en : pt; }

  var state = { itens: [], aoVivo: false, pendente: null, filtro: 'todos', grupo: '', busca: '', view: 'list', atual: null };
  var refreshTimer = null;

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
      init.method = 'POST';
      init.headers['X-BV-Token'] = TOKEN;
      if (opts.json) { init.headers['Content-Type'] = 'application/json'; init.body = JSON.stringify(opts.json); }
      else init.body = new URLSearchParams(opts.form || {});
    }
    return fetch(BASE + action + (opts.qs || ''), init).then(function (r) {
      return r.json().catch(function () { return { erro: tr('Resposta inválida do servidor.', 'Invalid server response.') }; })
        .then(function (j) { if (!r.ok || j.erro) { var e = new Error(j.erro || 'Falha'); e.campo = j.campo; throw e; } return j; });
    });
  }

  /* ---------------- barra de alterações pendentes ---------------- */
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
  function counts() {
    var on = 0, off = 0;
    state.itens.forEach(function (x) { if (x.online === true) on++; else if (x.online === false) off++; });
    return { todos: state.itens.length, online: on, offline: off };
  }
  /* "1,3-4" -> [1,3,4] */
  function groupNums(s) {
    var out = [];
    String(s || '').split(',').forEach(function (p) {
      var m = p.trim().match(/^(\d+)(?:-(\d+))?$/);
      if (!m) return;
      var a = +m[1], b = m[2] ? +m[2] : a;
      for (var i = a; i <= b && i - a < 64; i++) if (out.indexOf(i) === -1) out.push(i);
    });
    return out;
  }
  function allGroups() {
    var g = [];
    state.itens.forEach(function (x) { groupNums(x.call_group).concat(groupNums(x.pickup_group)).forEach(function (n) { if (g.indexOf(n) === -1) g.push(n); }); });
    return g.sort(function (a, b) { return a - b; });
  }
  function filtered() {
    var q = state.busca.trim().toLowerCase();
    return state.itens.filter(function (x) {
      if (state.filtro === 'online' && x.online !== true) return false;
      if (state.filtro === 'offline' && x.online !== false) return false;
      if (q && (x.ext + ' ' + x.name).toLowerCase().indexOf(q) === -1) return false;
      if (state.grupo !== '') {
        var gn = +state.grupo;
        if (groupNums(x.call_group).indexOf(gn) === -1 && groupNums(x.pickup_group).indexOf(gn) === -1) return false;
      }
      return true;
    });
  }
  function groupsCell(x) {
    var parts = [];
    if (x.call_group) parts.push('<span class="bvr-grp" title="' + tr('Grupo de chamada', 'Call group') + '"><em>' + tr('Chamada', 'Call') + '</em> ' + esc(x.call_group) + '</span>');
    if (x.pickup_group) parts.push('<span class="bvr-grp" title="' + tr('Grupo de captura', 'Pickup group') + '"><em>' + tr('Captura', 'Pickup') + '</em> ' + esc(x.pickup_group) + '</span>');
    return parts.length ? '<div class="bvr-grps">' + parts.join('') + '</div>' : '<span class="bvr-muted">—</span>';
  }
  function renderList() {
    var c = counts();
    var sub = c.todos + (EN ? ' extensions' : ' ramais') + (state.aoVivo ? ' · ' + c.online + ' online' : '');
    var tab = function (k, label) {
      return '<button type="button" class="bvr-seg' + (state.filtro === k ? ' is-on' : '') + '" data-filtro="' + k + '">' +
        label + ' <span>' + c[k] + '</span></button>';
    };
    var rows = filtered().map(function (x) {
      var st = x.online === null ? '<span class="bvr-muted">—</span>' :
        '<span class="bvr-st' + (x.online ? ' is-on' : '') + '"><i></i>' + (x.online ? 'Online' : 'Offline') + '</span>';
      var filas = (x.filas || []).map(function (f) { return '<span class="bvr-chip">' + esc(f) + '</span>'; }).join('');
      return '<tr data-ext="' + esc(x.ext) + '" tabindex="0">' +
        '<td><strong class="bvr-num">' + esc(x.ext) + '</strong>' + esc(x.name) + '</td>' +
        '<td><span class="bvr-chip">' + esc((x.tech || '').toUpperCase()) + '</span></td>' +
        '<td>' + st + '</td><td>' + groupsCell(x) + '</td><td><div class="bvr-chips">' + filas + '</div></td>' +
        '<td class="bvr-muted">' + (x.ip ? esc(x.ip) : '—') + '</td>' +
        '<td class="bvr-go">' + icon('chevron-right') + '</td></tr>';
    }).join('');
    if (!rows) rows = '<tr class="bvr-empty"><td colspan="7">' + tr('Nenhum ramal encontrado.', 'No extensions found.') + '</td></tr>';

    root.innerHTML = pendingBar() +
      '<header class="bvr-head"><div><h1>' + tr('Ramais', 'Extensions') + '</h1><p>' + esc(sub) + '</p></div>' +
      '<div class="bvr-actions">' +
        '<button type="button" class="bl-btn bl-btn--outline" data-act="lote">' + icon('list-plus') + tr('Criar ramais em lote', 'Batch extensions') + '</button>' +
        '<button type="button" class="bl-btn bl-btn--primary" data-act="novo">' + icon('phone') + tr('Novo ramal', 'New extension') + '</button></div></header>' +
      '<div class="bvr-toolbar"><div class="bvr-segs" role="tablist">' +
        (state.aoVivo ? tab('todos', tr('Todos', 'All')) + tab('online', 'Online') + tab('offline', 'Offline') : '') +
      '</div>' + (function () {
        var g = allGroups(); if (!g.length) return '';
        return '<select class="bvr-gsel" id="bvr-g" aria-label="' + tr('Filtrar por grupo', 'Filter by group') + '"><option value="">' + tr('Todos os grupos', 'All groups') + '</option>' +
          g.map(function (n) { return '<option value="' + n + '"' + (String(n) === state.grupo ? ' selected' : '') + '>' + tr('Grupo ', 'Group ') + n + '</option>'; }).join('') + '</select>';
      })() + '<label class="bvr-search">' + icon('search') +
        '<input type="search" id="bvr-q" placeholder="' + tr('Número ou nome', 'Number or name') + '" value="' + esc(state.busca) + '" /></label></div>' +
      '<div class="bvr-card"><table class="bvr-table"><thead><tr><th>' + tr('Ramal', 'Extension') + '</th><th>' +
        tr('Tecnologia', 'Technology') + '</th><th>Status</th><th>' + tr('Grupos', 'Groups') + '</th><th>' + tr('Filas', 'Queues') + '</th><th>IP</th><th></th></tr></thead><tbody>' +
        rows + '</tbody></table></div>';
    var gs = document.getElementById('bvr-g');
    if (gs) gs.addEventListener('change', function () { state.grupo = gs.value; renderList(); });
    var q = document.getElementById('bvr-q');
    q.addEventListener('input', function () { state.busca = q.value; var pos = q.selectionStart; renderList(); var n = document.getElementById('bvr-q'); n.focus(); n.setSelectionRange(pos, pos); });
  }
  function loadList(silent) {
    clearTimeout(refreshTimer);
    return call('list').then(function (d) {
      state.itens = d.itens || []; state.aoVivo = !!d.aoVivo; state.pendente = d.pendente;
      if (state.view === 'list') renderList();
    }).catch(function (e) {
      if (!silent) root.innerHTML = '<div class="bvr-error">' + esc(e.message) + '</div>';
    }).then(function () {
      if (state.view === 'list') refreshTimer = setTimeout(function () { loadList(true); }, 15000);
    });
  }

  /* ---------------- formulário ---------------- */
  function sw(name, on, label, hint) {
    return '<label class="bvr-switch"><input type="checkbox" name="' + name + '"' + (on ? ' checked' : '') + ' />' +
      '<span class="bvr-switch-ui" aria-hidden="true"></span><span class="bvr-switch-text"><strong>' + label + '</strong>' +
      (hint ? '<small>' + hint + '</small>' : '') + '</span></label>';
  }
  function field(name, label, input, hint) {
    return '<label class="bvr-field" data-field="' + name + '"><span>' + label + '</span>' + input +
      (hint ? '<small>' + hint + '</small>' : '') + '<em class="bvr-ferr"></em></label>';
  }
  function sel(name, value, opts) {
    return '<select class="bl-input" name="' + name + '">' + opts.map(function (o) {
      return '<option value="' + esc(o[0]) + '"' + (String(value) === String(o[0]) ? ' selected' : '') + '>' + esc(o[1]) + '</option>';
    }).join('') + '</select>';
  }
  function renderForm(r, novo) {
    state.view = 'form'; clearTimeout(refreshTimer);
    var title = novo ? tr('Novo ramal', 'New extension') : tr('Ramal ', 'Extension ') + esc(r.ext);
    var recOpts = [['dontcare', tr('Seguir a regra da rota ou da fila', 'Follow the route or queue rule')], ['always', tr('Sempre gravar', 'Always record')], ['never', tr('Nunca gravar', 'Never record')]];
    if (r.recording === 'misto') recOpts.unshift(['misto', tr('Personalizado no PBX (manter)', 'Custom in the PBX (keep)')]);

    root.innerHTML = pendingBar() +
      '<header class="bvr-head"><div><button type="button" class="bvr-back" data-act="voltar">' + icon('chevron-right') + tr('Ramais', 'Extensions') + '</button>' +
      '<h1>' + title + '</h1><p>' + (novo ? tr('Os campos não alterados usam os padrões do PBX.', 'Fields left unchanged use the PBX defaults.') : esc(r.name)) + '</p></div>' +
      '<div class="bvr-actions">' + (novo ? '' : '<button type="button" class="bl-btn bl-btn--ghost" data-act="excluir">' + tr('Excluir', 'Delete') + '</button>') +
      '<button type="button" class="bl-btn bl-btn--outline" data-act="voltar">' + tr('Cancelar', 'Cancel') + '</button>' +
      '<button type="submit" form="bvr-form" class="bl-btn bl-btn--primary">' + tr('Salvar ramal', 'Save extension') + '</button></div></header>' +
      '<p class="bvr-error" id="bvr-ferror" role="alert" hidden></p>' +
      '<form id="bvr-form" class="bvr-form" novalidate>' +
      // Identificação
      '<section class="bvr-card bvr-sec"><div class="bvr-sec-head"><h2>' + tr('Identificação', 'Identification') + '</h2><p>' +
        tr('Como o ramal aparece para quem liga e no painel.', 'How the extension shows to callers and on the panel.') + '</p></div><div class="bvr-grid">' +
        field('ext', tr('Número do ramal', 'Extension number'), '<input class="bl-input" name="ext" inputmode="numeric" value="' + esc(r.ext) + '"' + (novo ? '' : ' disabled') + ' />') +
        field('name', tr('Nome de exibição', 'Display name'), '<input class="bl-input" name="name" maxlength="50" value="' + esc(r.name) + '" />', tr('Exibido como CallerID interno.', 'Shown as internal CallerID.')) +
        (novo ? field('tech', tr('Tecnologia', 'Technology'), sel('tech', 'pjsip', [['pjsip', 'PJSIP'], ['sip', 'SIP (chan_sip)']])) : '') +
        field('secret', tr('Senha do aparelho', 'Device password'),
          '<div class="bvr-pass"><input class="bl-input" name="secret" autocomplete="new-password" placeholder="' +
          (novo ? tr('Gerada automaticamente', 'Generated automatically') : tr('Manter a atual', 'Keep the current one')) + '" />' +
          '<button type="button" class="bl-btn bl-btn--outline bl-btn--sm" data-act="gerar">' + tr('Gerar', 'Generate') + '</button>' +
          (novo ? '' : '<button type="button" class="bl-btn bl-btn--ghost bl-btn--sm" data-act="ver">' + tr('Ver atual', 'Show current') + '</button>') + '</div>',
          tr('Usada pelo telefone ou softphone para registrar.', 'Used by the phone or softphone to register.')) +
      '</div></section>' +
      // Chamadas
      '<section class="bvr-card bvr-sec"><div class="bvr-sec-head"><h2>' + tr('Chamadas', 'Calls') + '</h2><p>' +
        tr('Comportamento ao receber e originar ligações.', 'Behaviour when receiving and making calls.') + '</p></div><div class="bvr-switches">' +
        sw('call_waiting', novo ? true : r.call_waiting, tr('Chamada em espera', 'Call waiting'), tr('Permite receber uma segunda ligação durante outra.', 'Allows a second call during another.')) +
        sw('auto_answer', r.auto_answer, tr('Atendimento automático interno', 'Internal auto answer'), tr('Atende sozinho ligações de outros ramais.', 'Answers calls from other extensions by itself.')) +
        sw('voicemail', r.voicemail, tr('Correio de voz', 'Voicemail'), tr('Recebe recados quando ninguém atende.', 'Takes messages when nobody answers.')) +
        '</div><div class="bvr-grid bvr-vm"' + (r.voicemail ? '' : ' hidden') + '>' +
        field('vm_pin', tr('Senha do correio de voz', 'Voicemail PIN'), '<input class="bl-input" name="vm_pin" inputmode="numeric" value="' + esc(r.vm_pin) + '" />', tr('Se ficar vazia, usa o número do ramal.', 'If empty, the extension number is used.')) +
        field('vm_email', tr('Enviar recados para', 'Send messages to'), '<input class="bl-input" name="vm_email" type="email" value="' + esc(r.vm_email) + '" />', tr('Opcional.', 'Optional.')) +
        '</div><div class="bvr-grid">' +
        field('call_group', tr('Grupo de chamada', 'Call group'), '<input class="bl-input" name="call_group" inputmode="numeric" value="' + esc(r.call_group || '') + '" placeholder="' + tr('Nenhum', 'None') + '" />',
          tr('Grupo(s) a que o ramal pertence. Ex.: 1 ou 1,2.', 'Group(s) the extension belongs to. E.g. 1 or 1,2.')) +
        field('pickup_group', tr('Grupo de captura', 'Pickup group'), '<input class="bl-input" name="pickup_group" inputmode="numeric" value="' + esc(r.pickup_group || '') + '" placeholder="' + tr('Nenhum', 'None') + '" />',
          tr('Pode puxar (*8) chamadas que tocam nesses grupos de chamada.', 'Can pick up (*8) calls ringing in these call groups.')) +
        field('recording', tr('Gravação', 'Recording'), sel('recording', r.recording, recOpts)) + '</div></section>' +
      // Avançado
      '<details class="bvr-card bvr-sec bvr-adv"><summary><span><strong>' + tr('Avançado', 'Advanced') + '</strong><small>' +
        tr('DTMF, NAT, toque, chamadas simultâneas, dial options, CallerID externo', 'DTMF, NAT, ring time, concurrent calls, dial options, outbound CallerID') + '</small></span>' + icon('chevron-down') + '</summary><div class="bvr-grid">' +
        field('dtmf', 'DTMF', sel('dtmf', r.dtmf, [['rfc2833', 'RFC 2833'], ['info', 'SIP INFO'], ['inband', 'Inband'], ['auto', tr('Automático', 'Automatic')]])) +
        field('nat', 'NAT', sel('nat', r.nat, [['yes', tr('Sim', 'Yes')], ['no', tr('Não', 'No')], ['never', tr('Nunca', 'Never')], ['route', 'Route']])) +
        field('ring_time', tr('Tempo de toque', 'Ring time'), sel('ring_time', r.ring_time, [['0', tr('Padrão do sistema', 'System default')], ['15', '15 s'], ['20', '20 s'], ['30', '30 s'], ['45', '45 s'], ['60', '60 s'], ['90', '90 s'], ['120', '120 s']].concat(['0', '15', '20', '30', '45', '60', '90', '120'].indexOf(String(r.ring_time)) === -1 ? [[r.ring_time, r.ring_time + ' s']] : []))) +
        field('concurrency', tr('Chamadas simultâneas de saída', 'Concurrent outbound calls'), sel('concurrency', r.concurrency, [['0', tr('Sem limite', 'No limit')], ['1', '1'], ['2', '2'], ['3', '3'], ['4', '4'], ['5', '5']].concat(['0', '1', '2', '3', '4', '5'].indexOf(String(r.concurrency)) === -1 ? [[r.concurrency, r.concurrency]] : []))) +
        field('dial_options', 'Dial options', '<input class="bl-input" name="dial_options" value="' + esc(r.dial_options) + '" />', tr('Ex.: tTr. Vazio usa o padrão do PBX.', 'E.g. tTr. Empty uses the PBX default.')) +
        field('outbound_cid', tr('CallerID externo', 'Outbound CallerID'), '<input class="bl-input" name="outbound_cid" inputmode="numeric" value="' + esc(r.outbound_cid) + '" />', tr('Só números. Vazio segue a rota de saída.', 'Digits only. Empty follows the outbound route.')) +
      '</div>' + (novo ? '' : '<button type="button" class="bvr-link" data-act="pbx">' + icon('sliders-horizontal') + tr('Todas as opções no PBX', 'All options in the PBX') + '</button>') + '</details>' +
      '</form>';

    var f = document.getElementById('bvr-form');
    f.elements['voicemail'].addEventListener('change', function () { root.querySelector('.bvr-vm').hidden = !f.elements['voicemail'].checked; });
    f.addEventListener('submit', function (e) { e.preventDefault(); save(f, novo); });
    var first = novo ? f.elements['ext'] : f.elements['name']; if (first) first.focus();
  }

  function formError(msg, campo) {
    Array.prototype.forEach.call(root.querySelectorAll('.bvr-field.is-err'), function (el) { el.classList.remove('is-err'); el.querySelector('.bvr-ferr').textContent = ''; });
    var box = document.getElementById('bvr-ferror');
    var fieldEl = campo ? root.querySelector('.bvr-field[data-field="' + campo + '"]') : null;
    if (fieldEl) {
      fieldEl.classList.add('is-err'); fieldEl.querySelector('.bvr-ferr').textContent = msg;
      var adv = fieldEl.closest('details'); if (adv) adv.open = true;
      var inp = fieldEl.querySelector('input,select'); if (inp) inp.focus();
      box.hidden = true;
    } else { box.textContent = msg; box.hidden = false; box.scrollIntoView({ block: 'nearest' }); }
  }

  function save(f, novo) {
    var btn = root.querySelector('button[type=submit]'); btn.disabled = true;
    var data = {
      novo: novo, ext: novo ? f.elements['ext'].value : state.atual.ext, name: f.elements['name'].value, secret: f.elements['secret'].value,
      tech: f.elements['tech'] ? f.elements['tech'].value : '', call_waiting: f.elements['call_waiting'].checked, auto_answer: f.elements['auto_answer'].checked,
      voicemail: f.elements['voicemail'].checked, vm_pin: f.elements['vm_pin'].value, vm_email: f.elements['vm_email'].value,
      recording: f.elements['recording'].value, dtmf: f.elements['dtmf'].value, nat: f.elements['nat'].value, ring_time: f.elements['ring_time'].value,
      concurrency: f.elements['concurrency'].value, dial_options: f.elements['dial_options'].value, outbound_cid: f.elements['outbound_cid'].value,
      call_group: f.elements['call_group'].value, pickup_group: f.elements['pickup_group'].value
    };
    call('save', { post: true, json: data }).then(function (d) {
      state.pendente = d.pendente; state.view = 'list';
      toast(novo ? tr('Ramal criado. Aplique as alterações para valer no PBX.', 'Extension created. Apply the changes for the PBX to use it.') :
        tr('Ramal salvo. Aplique as alterações para valer no PBX.', 'Extension saved. Apply the changes for the PBX to use it.'));
      loadList();
    }).catch(function (e) { btn.disabled = false; formError(e.message, e.campo); });
  }

  function openForm(ext) {
    if (!ext) { state.atual = null; renderForm({ ext: '', name: '', call_waiting: true, auto_answer: false, voicemail: false, vm_pin: '', vm_email: '', recording: 'dontcare', dtmf: 'rfc2833', nat: 'yes', ring_time: '0', concurrency: '0', dial_options: '', outbound_cid: '', call_group: '', pickup_group: '' }, true); return; }
    root.innerHTML = '<div class="bvr-loading">' + tr('Carregando o ramal…', 'Loading extension…') + '</div>';
    call('get', { qs: '&ext=' + encodeURIComponent(ext) }).then(function (d) { state.atual = d.ramal; renderForm(d.ramal, false); })
      .catch(function (e) { toast(e.message, true); state.view = 'list'; renderList(); });
  }

  /* ---------------- criar ramais em lote (planilha) ---------------- */
  function renderLote() {
    state.view = 'lote'; clearTimeout(refreshTimer);
    root.innerHTML = pendingBar() +
      '<header class="bvr-head"><div><button type="button" class="bvr-back" data-act="voltar">' + icon('chevron-right') + tr('Ramais', 'Extensions') + '</button>' +
      '<h1>' + tr('Criar ramais em lote', 'Batch extensions') + '</h1><p>' +
      tr('Crie e edite vários ramais de uma vez com uma planilha no formato do Issabel.', 'Create and edit many extensions at once with an Issabel-format spreadsheet.') + '</p></div></header>' +
      '<section class="bvr-card bvr-sec"><div class="bvr-sec-head"><h2>1. ' + tr('Baixe a planilha', 'Download the spreadsheet') + '</h2><p>' +
        tr('Comece pelos ramais atuais para editar e acrescentar linhas, ou pelo modelo vazio.', 'Start from the current extensions to edit and add rows, or from the blank template.') + '</p></div>' +
        '<div class="bvr-actions"><a class="bl-btn bl-btn--outline" href="' + BASE + 'export" download>' + icon('download') + tr('Ramais atuais', 'Current extensions') + '</a>' +
        '<a class="bl-btn bl-btn--ghost" href="' + BASE + 'template" download>' + tr('Modelo vazio', 'Blank template') + '</a></div>' +
        '<ul class="bvr-rules"><li>' + tr('Ramal que ainda não existe é <strong>criado</strong>.', 'An extension that does not exist is <strong>created</strong>.') + '</li>' +
        '<li>' + tr('Ramal que já existe é <strong>atualizado</strong>, só nas colunas que mudaram.', 'An existing extension is <strong>updated</strong>, only in the columns that changed.') + '</li>' +
        '<li>' + tr('Célula vazia mantém o valor atual. A planilha <strong>nunca exclui</strong> ramais.', 'Empty cells keep the current value. The spreadsheet <strong>never deletes</strong> extensions.') + '</li></ul>' +
      '</section>' +
      '<section class="bvr-card bvr-sec"><div class="bvr-sec-head"><h2>2. ' + tr('Envie a planilha', 'Upload the spreadsheet') + '</h2><p>' +
        tr('Arquivo .csv do Issabel ou salvo pelo Excel. Você confere tudo antes de gravar.', 'Issabel .csv or saved from Excel. You review everything before saving.') + '</p></div>' +
        '<label class="bvr-drop" id="bvr-drop"><input type="file" id="bvr-file" accept=".csv,text/csv" />' + icon('list-plus') +
        '<span><strong>' + tr('Escolha o arquivo', 'Choose the file') + '</strong> ' + tr('ou arraste para cá', 'or drag it here') + '</span></label>' +
      '</section><div id="bvr-preview"></div>';
    var fi = document.getElementById('bvr-file'), drop = document.getElementById('bvr-drop');
    fi.addEventListener('change', function () { if (fi.files && fi.files[0]) upload(fi.files[0]); });
    ['dragover', 'dragenter'].forEach(function (ev) { drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.add('is-over'); }); });
    ['dragleave', 'drop'].forEach(function (ev) { drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.remove('is-over'); }); });
    drop.addEventListener('drop', function (e) { var f = e.dataTransfer.files[0]; if (f) upload(f); });
  }
  function upload(file) {
    var box = document.getElementById('bvr-preview');
    box.innerHTML = '<div class="bvr-loading">' + tr('Lendo a planilha e comparando com o PBX…', 'Reading the spreadsheet and comparing with the PBX…') + '</div>';
    var fd = new FormData(); fd.append('planilha', file);
    fetch(BASE + 'import_preview', { method: 'POST', credentials: 'same-origin', headers: { 'X-BV-Token': TOKEN }, body: fd })
      .then(function (r) { return r.json().then(function (j) { if (!r.ok || j.erro) throw new Error(j.erro || 'Falha'); return j; }); })
      .then(function (p) { preview(p, file.name); })
      .catch(function (e) { box.innerHTML = '<div class="bvr-error">' + esc(e.message) + '</div>'; });
  }
  function preview(p, fname) {
    var box = document.getElementById('bvr-preview');
    var n = p.novos.length, a = p.alterados.length;
    var body = '';
    if (n) body += '<h3>' + tr('Ramais novos', 'New extensions') + '</h3><div class="bvr-chips">' +
      p.novos.map(function (x) { return '<span class="bvr-chip">' + esc(x.ext) + ' · ' + esc(x.name) + '</span>'; }).join('') + '</div>';
    if (a) body += '<h3>' + tr('Ramais alterados', 'Changed extensions') + '</h3>' + p.alterados.map(function (x) {
      return '<details class="bvr-lote-item"><summary><strong>' + esc(x.ext) + '</strong> ' + esc(x.name) + ' <span class="bvr-muted">· ' +
        x.mudancas.length + (x.mudancas.length === 1 ? tr(' campo', ' field') : tr(' campos', ' fields')) + '</span></summary><table>' +
        x.mudancas.map(function (m) {
          return '<tr><td>' + esc(m.campo) + '</td><td class="bvr-muted">' + (m.senha ? tr('alterada', 'changed') : esc(m.de || '—')) + '</td><td>' + (m.senha ? '' : '→ ' + esc(m.para)) + '</td></tr>';
        }).join('') + '</table></details>';
    }).join('');
    if (p.erros.length) body += '<h3>' + tr('Linhas com erro (serão ignoradas)', 'Rows with errors (will be skipped)') + '</h3><ul class="bvr-lote-err">' +
      p.erros.map(function (e) { return '<li>' + tr('Linha ', 'Row ') + e.linha + ': ' + esc(e.msg) + '</li>'; }).join('') + '</ul>';
    if (p.ignoradas.length) body += '<p class="bvr-muted bvr-lote-ign">' + tr('Colunas que esta tela não grava (o PBX mantém o valor atual): ', 'Columns this screen does not write (the PBX keeps the current value): ') + esc(p.ignoradas.join(', ')) + '.</p>';
    if (!n && !a) body += '<p class="bvr-muted">' + tr('Nada a fazer: a planilha é igual ao que já está no PBX.', 'Nothing to do: the spreadsheet matches the PBX.') + '</p>';

    box.innerHTML = '<section class="bvr-card bvr-sec"><div class="bvr-sec-head"><h2>3. ' + tr('Confira e aplique', 'Review and apply') + '</h2><p>' + esc(fname) + '</p></div>' +
      '<div class="bvr-lote-sum"><div><strong>' + n + '</strong><span>' + tr('para criar', 'to create') + '</span></div>' +
      '<div><strong>' + a + '</strong><span>' + tr('para alterar', 'to change') + '</span></div>' +
      '<div><strong>' + p.iguais + '</strong><span>' + tr('sem mudança', 'unchanged') + '</span></div>' +
      '<div class="' + (p.erros.length ? 'is-bad' : '') + '"><strong>' + p.erros.length + '</strong><span>' + tr('com erro', 'with errors') + '</span></div></div>' +
      '<div class="bvr-lote-body">' + body + '</div><p class="bvr-error" id="bvr-lerr" hidden></p>' +
      (n || a ? '<div class="bvr-actions bvr-actions--end"><button type="button" class="bl-btn bl-btn--primary" id="bvr-lapply">' +
        (EN ? 'Save ' + (n + a) + ' extensions' : 'Gravar ' + (n + a) + (n + a === 1 ? ' ramal' : ' ramais')) + '</button></div>' : '') + '</section>';
    var ok = document.getElementById('bvr-lapply'), err = document.getElementById('bvr-lerr');
    if (ok) ok.addEventListener('click', function () {
      ok.disabled = true; ok.textContent = tr('Gravando…', 'Saving…'); err.hidden = true;
      call('import_apply', { post: true, form: { id: p.id } }).then(function (r) {
        state.pendente = r.pendente;
        var msg = (EN ? r.criados + ' created, ' + r.alterados + ' changed.' : r.criados + ' criados, ' + r.alterados + ' alterados.') +
          ' ' + tr('Aplique as alterações para valer no PBX.', 'Apply the changes for the PBX to use them.');
        if (r.falhas && r.falhas.length) {
          dialog(tr('Alguns ramais não foram gravados', 'Some extensions were not saved'), esc(msg) + '<br><br>' +
            r.falhas.map(function (f) { return '<strong>' + esc(f.ext) + '</strong>: ' + esc(f.msg); }).join('<br>'), 'OK', function () {});
        } else toast(msg);
        state.view = 'list'; loadList();
      }).catch(function (e) { err.textContent = e.message; err.hidden = false; ok.disabled = false; ok.textContent = tr('Tentar de novo', 'Try again'); });
    });
    box.scrollIntoView({ behavior: 'smooth', block: 'start' });
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
    return m;
  }
  function pbxDialog(ext) {
    var m = document.createElement('div');
    m.className = 'bvr-modal bvr-modal--wide';
    m.innerHTML = '<div class="bvr-dialog bvr-dialog--frame" role="dialog" aria-modal="true" aria-label="PBX"><div class="bvr-frame-head"><strong>' +
      tr('Ramal ', 'Extension ') + esc(ext) + ' · ' + tr('todas as opções', 'all options') + '</strong><span class="bvr-muted">' +
      tr('Depois de salvar aqui, clique em "Apply Config" na barra vermelha do PBX.', 'After saving here, click "Apply Config" on the PBX red bar.') +
      '</span><button type="button" class="bl-btn bl-btn--outline bl-btn--sm" data-x>' + tr('Fechar', 'Close') + '</button></div>' +
      '<iframe src="/admin/config.php?display=extensions&extdisplay=' + encodeURIComponent(ext) + '" title="PBX"></iframe></div>';
    document.body.appendChild(m);
    function close() { m.remove(); document.removeEventListener('keydown', key); openForm(ext); }
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
    var seg = e.target.closest('[data-filtro]');
    if (seg) { state.filtro = seg.getAttribute('data-filtro'); renderList(); return; }
    var row = e.target.closest('tr[data-ext]');
    if (row && state.view === 'list') { openForm(row.getAttribute('data-ext')); return; }
    var b = e.target.closest('[data-act]');
    if (!b) return;
    var act = b.getAttribute('data-act');
    if (act === 'novo') openForm('');
    else if (act === 'lote') renderLote();
    else if (act === 'voltar') { state.view = 'list'; renderList(); loadList(true); }
    else if (act === 'gerar') {
      var cs = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789', a = new Uint32Array(16), s = '';
      (window.crypto || window.msCrypto).getRandomValues(a);
      for (var i = 0; i < a.length; i++) s += cs[a[i] % cs.length];
      var inp = root.querySelector('input[name=secret]'); inp.value = s; inp.type = 'text'; inp.focus(); inp.select();
    } else if (act === 'ver') {
      call('secret', { post: true, form: { ext: state.atual.ext } }).then(function (d) {
        var inp = root.querySelector('input[name=secret]'); inp.placeholder = d.senha || tr('(sem senha)', '(no password)');
        b.hidden = true;
      }).catch(function (er) { toast(er.message, true); });
    } else if (act === 'excluir') {
      var x = state.atual, item = state.itens.filter(function (i) { return i.ext === x.ext; })[0];
      var filas = item && item.filas && item.filas.length ? '<br><br>' + tr('Ele participa das filas ', 'It is a member of queues ') + esc(item.filas.join(', ')) + '.' : '';
      dialog(tr('Excluir o ramal ', 'Delete extension ') + x.ext + '?',
        tr('O aparelho deixa de registrar e o correio de voz do ramal é apagado. A exclusão é aplicada no PBX na hora, junto com outras alterações pendentes.',
          'The device stops registering and its voicemail is deleted. The deletion is applied to the PBX right away, together with other pending changes.') + filas,
        tr('Excluir ramal', 'Delete extension'), function () {
          return call('delete', { post: true, form: { ext: x.ext } }).then(function (d) {
            state.pendente = d.pendente; state.view = 'list'; toast(tr('Ramal excluído.', 'Extension deleted.')); loadList();
          });
        }, true);
    } else if (act === 'apply') {
      dialog(tr('Aplicar as alterações?', 'Apply the changes?'),
        tr('O PBX recarrega a configuração. Ligações em andamento não caem.', 'The PBX reloads its configuration. Calls in progress are not dropped.'),
        tr('Aplicar', 'Apply'), function () {
          return call('apply', { post: true }).then(function () { state.pendente = null; toast(tr('Alterações aplicadas no PBX.', 'Changes applied to the PBX.')); if (state.view === 'list') loadList(); else root.querySelector('.bvr-pending') && root.querySelector('.bvr-pending').remove(); });
        });
    } else if (act === 'pbx') pbxDialog(state.atual.ext);
  });
  root.addEventListener('keydown', function (e) {
    var row = e.target.closest && e.target.closest('tr[data-ext]');
    if (row && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); openForm(row.getAttribute('data-ext')); }
  });

  root.innerHTML = '<div class="bvr-loading">' + tr('Carregando os ramais…', 'Loading extensions…') + '</div>';
  loadList();
})();
