<?php
/*
 * Visão geral (Bradial Voz): tela inicial do Issabel com o tema Bradial.
 *
 * O Issabel só chama _moduleContent() para usuários logados com permissão neste
 * menu, inclusive nas requisições rawmode=yes do JSON. Não há endpoint sem login.
 */
function _moduleContent(&$smarty, $module_name)
{
    $action = isset($_REQUEST['action']) ? (string) $_REQUEST['action'] : '';

    if ($action === 'snapshot') {
        // Só leitura: libera a sessão para não travar as outras abas do Issabel.
        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
        require_once __DIR__ . '/lib/VisaoGeral.php';
        try {
            $v = new BvVisaoGeral();
            return bv_vg_json($v->snapshot());
        } catch (Exception $e) {
            return bv_vg_json(array('erro' => 'Não foi possível ler o servidor: ' . $e->getMessage()), 500);
        }
    }

    $mod = 'modules/' . rawurlencode($module_name);
    $ver = trim((string) @file_get_contents(__DIR__ . '/VERSION')) ?: '1';
    $api = htmlspecialchars('?menu=' . rawurlencode($module_name) . '&action=snapshot&rawmode=yes', ENT_QUOTES);
    $pbx = htmlspecialchars('index.php?menu=pbxadmin', ENT_QUOTES);
    $op  = is_dir('modules/painel_operador') ? htmlspecialchars('index.php?menu=painel_operador', ENT_QUOTES) : '';

    return <<<HTML
<link rel="stylesheet" href="{$mod}/assets/visao.css?v={$ver}" />
<div class="bvg" id="bvg" data-api="{$api}" data-pbx="{$pbx}" data-op="{$op}">
  <header class="bvg-head">
    <div>
      <h1>Visão geral</h1>
      <p class="bvg-sub" id="bvg-sub">Lendo o servidor…</p>
    </div>
    <span class="bvg-status" id="bvg-status" hidden><span class="bvg-dot"></span><span id="bvg-status-text"></span></span>
  </header>

  <section class="bvg-card bvg-kpis" id="bvg-kpis" aria-label="Telefonia agora"></section>

  <div class="bvg-grid">
    <section class="bvg-card" aria-labelledby="bvg-h-srv">
      <div class="bvg-card-head">
        <h2 id="bvg-h-srv">Recursos do servidor</h2>
        <p class="bvg-muted" id="bvg-cpuinfo"></p>
      </div>
      <div class="bvg-bars" id="bvg-bars"></div>
      <dl class="bvg-foot" id="bvg-srvfoot"></dl>
    </section>

    <section class="bvg-card" aria-labelledby="bvg-h-av">
      <div class="bvg-card-head bvg-row">
        <h2 id="bvg-h-av">Avisos do PBX</h2>
        <span class="bvg-muted" id="bvg-av-count"></span>
      </div>
      <ul class="bvg-notices" id="bvg-notices"></ul>
      <a class="bvg-link" href="{$pbx}">Abrir as Configurações do PABX</a>
    </section>
  </div>
</div>
<script src="{$mod}/assets/visao.js?v={$ver}"></script>
HTML;
}

function bv_vg_json($data, $code = 200)
{
    if (!headers_sent()) {
        http_response_code($code);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');
    }
    return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
}
