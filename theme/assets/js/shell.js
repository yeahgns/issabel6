/* Bradial Voz: comportamento da casca (sem dependências). */
(function () {
  'use strict';
  var body = document.body;

  var html = document.documentElement;
  var mobile = window.matchMedia ? window.matchMedia('(max-width: 960px)') : { matches: false };

  // Menu recolhido: só ícones. A escolha fica salva neste navegador.
  function setCollapsed(on) {
    html.classList.toggle('bv-collapsed', on);
    try { localStorage.setItem('bv-nav', on ? 'recolhido' : 'expandido'); } catch (err) {}
    var t = document.querySelector('.bv-menu-toggle');
    if (t) t.setAttribute('aria-expanded', on ? 'false' : 'true');
  }
  if (html.classList.contains('bv-collapsed')) {
    var t0 = document.querySelector('.bv-menu-toggle');
    if (t0) t0.setAttribute('aria-expanded', 'false');
  }

  // Grupos recolhíveis da barra lateral (Usuários, Rede, Segurança, Preferências).
  // Com o menu recolhido, clicar num grupo expande o menu e abre o grupo.
  document.addEventListener('click', function (e) {
    var btn = e.target.closest && e.target.closest('.bv-nav-branch > .bv-nav-item');
    if (!btn) return;
    var branch = btn.parentNode;
    if (html.classList.contains('bv-collapsed') && !mobile.matches) {
      setCollapsed(false);
      branch.classList.add('is-open');
      btn.setAttribute('aria-expanded', 'true');
      return;
    }
    var open = branch.classList.toggle('is-open');
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
  });

  // Menu em telas pequenas
  function setNav(open) {
    body.classList.toggle('bv-nav-open', open);
    var t = document.querySelector('.bv-menu-toggle');
    if (t) t.setAttribute('aria-expanded', open ? 'true' : 'false');
  }
  document.addEventListener('click', function (e) {
    if (e.target.closest && e.target.closest('.bv-menu-toggle')) {
      if (mobile.matches) setNav(!body.classList.contains('bv-nav-open'));
      else setCollapsed(!html.classList.contains('bv-collapsed'));
    }
    else if (e.target.classList && e.target.classList.contains('bv-backdrop')) { setNav(false); }
  });

  // Painel lateral do Issabel (Panels)
  document.addEventListener('click', function (e) {
    var t = e.target.closest && e.target.closest('#header_open_sidebar, #chat .chat-close');
    if (!t) return;
    e.preventDefault();
    body.classList.toggle('bv-panels-open');
  });

  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    setNav(false);
    body.classList.remove('bv-panels-open');
  });

  // Mantém o item ativo visível quando a barra lateral tem rolagem
  var side = document.getElementById('bv-sidebar');
  var active = side && side.querySelector('.is-active');
  if (active) {
    var top = active.getBoundingClientRect().top - side.getBoundingClientRect().top;
    if (top + active.offsetHeight > side.clientHeight - 60) side.scrollTop = top - side.clientHeight / 2;
  }

  // Ajustes de texto em telas nativas, sem alterar os módulos do Issabel
  var mod = document.querySelector('.neo-module-content');
  var modId = mod ? mod.getAttribute('data-bv-module') : '';
  if (modId === 'ping') {
    // Exemplo de teste: o site da Bradial no lugar do Issabel
    Array.prototype.forEach.call(mod.querySelectorAll('input[type=text]'), function (i) {
      if (/^(https?:\/\/)?www\.issabel\.org\/?$/i.test(i.value)) i.value = 'www.bradial.com.br';
      if (/issabel\.org/i.test(i.placeholder || '')) i.placeholder = 'www.bradial.com.br';
    });
  }
  if (modId === 'themes_system') {
    // Nomes dos temas: o nosso é a v6; o tenant (original) é a v5
    var names = { bradial: 'Bradial v6', tenant: 'Bradial v5' };
    Array.prototype.forEach.call(mod.querySelectorAll('option'), function (o) {
      var k = (o.value || '').toLowerCase();
      if (names[k]) o.textContent = names[k];
    });
  }
})();
