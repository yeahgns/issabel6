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

    if ($action === 'dismiss') {
        $tok = isset($_SERVER['HTTP_X_BV_TOKEN']) ? (string) $_SERVER['HTTP_X_BV_TOKEN'] : '';
        $ok = $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SESSION['bv_csrf']) && hash_equals($_SESSION['bv_csrf'], $tok);
        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
        if (!$ok) return bv_vg_json(array('erro' => 'Sessão expirada. Recarregue a página.'), 403);
        require_once __DIR__ . '/lib/VisaoGeral.php';
        try {
            $v = new BvVisaoGeral(bv_vg_lang());
            $v->removeAviso(isset($_POST['modulo']) ? $_POST['modulo'] : '', isset($_POST['id']) ? $_POST['id'] : '');
            return bv_vg_json(array('ok' => true));
        } catch (Exception $e) {
            return bv_vg_json(array('erro' => 'Não foi possível remover o aviso.'), 500);
        }
    }

    if ($action === 'snapshot') {
        // Só leitura: libera a sessão para não travar as outras abas do Issabel.
        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
        require_once __DIR__ . '/lib/VisaoGeral.php';
        try {
            $v = new BvVisaoGeral(bv_vg_lang());
            return bv_vg_json($v->snapshot());
        } catch (Exception $e) {
            return bv_vg_json(array('erro' => 'Não foi possível ler o servidor: ' . $e->getMessage()), 500);
        }
    }

    if (empty($_SESSION['bv_csrf'])) $_SESSION['bv_csrf'] = bin2hex(random_bytes(16));
    $mod = 'modules/' . rawurlencode($module_name);
    $ver = htmlspecialchars(trim((string) @file_get_contents(__DIR__ . '/VERSION')) ?: '1', ENT_QUOTES);
    $base = '?menu=' . rawurlencode($module_name) . '&rawmode=yes&action=';
    $api = htmlspecialchars($base . 'snapshot', ENT_QUOTES);
    $dis = htmlspecialchars($base . 'dismiss', ENT_QUOTES);
    $tok = htmlspecialchars($_SESSION['bv_csrf'], ENT_QUOTES);
    $op  = is_dir('modules/painel_operador') ? 'index.php?menu=painel_operador' : '';

    $L = bv_vg_lang();
    $T = $L === 'en'
        ? array('title' => 'Overview', 'reading' => 'Reading the server…', 'now' => 'Telephony now', 'res' => 'Server resources', 'notices' => 'PBX notices', 'services' => 'Services')
        : array('title' => 'Visão geral', 'reading' => 'Lendo o servidor…', 'now' => 'Telefonia agora', 'res' => 'Recursos do servidor', 'notices' => 'Avisos do PBX', 'services' => 'Serviços');
    return <<<HTML
<link rel="stylesheet" href="{$mod}/assets/visao.css?v={$ver}" />
<div class="bvg" id="bvg" data-api="{$api}" data-dismiss="{$dis}" data-token="{$tok}" data-op="{$op}" data-lang="{$L}" data-sprite="themes/bradial/assets/icons/sprite.svg?v={$ver}">
  <header class="bvg-head">
    <div class="bvg-head-text">
      <h1>{$T['title']}</h1>
      <p id="bvg-sub">{$T['reading']}</p>
    </div>
    <span class="bvg-live" id="bvg-live" hidden><span class="bvg-dot"></span><span id="bvg-live-text"></span></span>
  </header>

  <section class="bvg-card bvg-kpis" id="bvg-kpis" aria-label="{$T['now']}"></section>

  <div class="bvg-grid">
    <section class="bvg-card bvg-res" aria-labelledby="bvg-h-srv">
      <div>
        <h2 id="bvg-h-srv">{$T['res']}</h2>
        <p class="bvg-muted" id="bvg-cpuinfo"></p>
      </div>
      <div class="bvg-bars" id="bvg-bars"></div>
      <div class="bvg-sep"></div>
      <div class="bvg-foot" id="bvg-srvfoot"></div>
    </section>

    <div class="bvg-col">
      <section class="bvg-card bvg-av" aria-labelledby="bvg-h-av">
        <div class="bvg-av-head"><h2 id="bvg-h-av">{$T['notices']}</h2><span class="bvg-muted" id="bvg-av-count"></span></div>
        <div class="bvg-notices" id="bvg-notices"></div>
      </section>
      <section class="bvg-card bvg-svc" aria-labelledby="bvg-h-svc">
        <h2 id="bvg-h-svc">{$T['services']}</h2>
        <div id="bvg-services"></div>
      </section>
    </div>
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

/* Idioma da interface (Sistema > Preferências > Idioma): 'pt' ou 'en' */
function bv_vg_lang()
{
    $l = function_exists('get_language') ? strtolower((string) get_language()) : 'pt';
    return (strpos($l, 'br') === 0 || strpos($l, 'pt') === 0) ? 'pt' : 'en';
}
