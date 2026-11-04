/* Bradial Voz: comportamento da casca (sem dependências). */
(function () {
  'use strict';
  var body = document.body;

  // Grupos recolhíveis da barra lateral (Usuários, Rede, Segurança, Preferências)
  document.addEventListener('click', function (e) {
    var btn = e.target.closest && e.target.closest('.bv-nav-branch > .bv-nav-item');
    if (!btn) return;
    var branch = btn.parentNode;
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
    if (e.target.closest && e.target.closest('.bv-menu-toggle')) { setNav(!body.classList.contains('bv-nav-open')); }
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
})();
