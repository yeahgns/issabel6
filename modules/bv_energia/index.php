<?php
/*
 * Reiniciar ou desligar (Bradial Voz), no layout do protótipo Bradial Voz v6.
 * Substitui a tela nativa "Shutdown". Usa a mesma permissão de sudo que o Issabel já
 * concede ao usuário asterisk (/etc/sudoers.d/issabel: /sbin/shutdown).
 *
 * O desligamento é agendado para daqui a 1 minuto, o que dá tempo de cancelar.
 */
function _moduleContent(&$smarty, $module_name)
{
    $action = isset($_REQUEST['action']) ? (string) $_REQUEST['action'] : '';

    if ($action === 'power' || $action === 'cancel') {
        $tok = isset($_SERVER['HTTP_X_BV_TOKEN']) ? (string) $_SERVER['HTTP_X_BV_TOKEN'] : '';
        $ok = $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SESSION['bv_csrf']) && hash_equals($_SESSION['bv_csrf'], $tok);
        $user = isset($_SESSION['issabel_user']) ? (string) $_SESSION['issabel_user'] : '?';
        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
        if (!$ok) return bv_en_json(array('erro' => bv_en_t('Sessão expirada. Recarregue a página.', 'Session expired. Reload the page.')), 403);

        if ($action === 'cancel') {
            $cmd = 'sudo -n /sbin/shutdown -c';
        } else {
            $op = isset($_POST['op']) ? (string) $_POST['op'] : '';
            if ($op !== 'reboot' && $op !== 'poweroff') return bv_en_json(array('erro' => bv_en_t('Opção inválida.', 'Invalid option.')), 400);
            $cmd = 'sudo -n /sbin/shutdown ' . ($op === 'reboot' ? '-r' : '-h') . ' +1';
        }
        $out = array(); $rc = 1;
        exec($cmd . ' 2>&1', $out, $rc);
        $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '?';
        @error_log(sprintf('[bv_energia] %s por %s (%s): %s', $action === 'cancel' ? 'cancelamento' : $_POST['op'], $user, $ip, $rc === 0 ? 'ok' : 'falhou'));
        if ($rc !== 0) {
            return bv_en_json(array('erro' => bv_en_t('O servidor recusou o comando: ', 'The server refused the command: ') . trim(implode(' ', $out))), 500);
        }
        return bv_en_json(array('ok' => true));
    }

    if (empty($_SESSION['bv_csrf'])) $_SESSION['bv_csrf'] = bin2hex(random_bytes(16));
    $tok = htmlspecialchars($_SESSION['bv_csrf'], ENT_QUOTES);
    $mod = 'modules/' . rawurlencode($module_name);
    $ver = htmlspecialchars(trim((string) @file_get_contents(__DIR__ . '/VERSION')) ?: '1', ENT_QUOTES);
    $base = htmlspecialchars('?menu=' . rawurlencode($module_name) . '&rawmode=yes&action=', ENT_QUOTES);
    $sprite = 'themes/bradial/assets/icons/sprite.svg?v=' . $ver;

    $calls = bv_en_calls();
    $L = bv_en_lang();
    $base0 = bv_en_t('Ligações em andamento serão encerradas.', 'Calls in progress will be dropped.');
    if ($calls === null) $sub = $base0;
    elseif ($calls === 0) $sub = $base0 . bv_en_t(' Agora não há nenhuma chamada ativa.', ' There are no active calls right now.');
    else $sub = $base0 . bv_en_t(' Agora há ', ' Right now there ') . ($L === 'en' ? ($calls === 1 ? 'is 1 active call.' : 'are ' . $calls . ' active calls.') : $calls . ($calls === 1 ? ' chamada ativa.' : ' chamadas ativas.'));
    $T = array(
        'title' => bv_en_t('Reiniciar ou desligar', 'Restart or shut down'),
        'cancel' => bv_en_t('Cancelar', 'Cancel'),
        'what' => bv_en_t('O que fazer', 'What to do'),
        'r' => bv_en_t('Reiniciar', 'Restart'),
        'rd' => bv_en_t('O servidor desliga e volta sozinho em cerca de 2 minutos.', 'The server shuts down and comes back on its own in about 2 minutes.'),
        'p' => bv_en_t('Desligar', 'Shut down'),
        'pd' => bv_en_t('O servidor fica desligado até alguém ligá-lo novamente.', 'The server stays off until someone turns it back on.'),
        'back' => bv_en_t('Voltar', 'Back'),
        'go' => bv_en_t('Reiniciar servidor', 'Restart server'),
    );
    $sub = htmlspecialchars($sub, ENT_QUOTES);
    $agendado = bv_en_scheduled();
    $agTxt = $agendado ? htmlspecialchars($agendado, ENT_QUOTES) : '';
    $hidden = $agendado ? '' : ' hidden';

    return <<<HTML
<link rel="stylesheet" href="{$mod}/assets/energia.css?v={$ver}" />
<div class="bve" id="bve" data-base="{$base}" data-token="{$tok}" data-lang="{$L}">
  <div>
    <h1>{$T['title']}</h1>
    <p class="bve-sub">{$sub}</p>
  </div>

  <div class="bve-pending"{$hidden} id="bve-pending" role="status">
    <svg class="bve-i" aria-hidden="true"><use href="{$sprite}#triangle-alert"></use></svg>
    <span id="bve-pending-text">{$agTxt}</span>
    <button type="button" class="bl-btn bl-btn--outline bl-btn--sm" id="bve-cancel">{$T['cancel']}</button>
  </div>

  <div class="bve-opts" role="radiogroup" aria-label="{$T['what']}">
    <button type="button" class="bve-opt is-selected" role="radio" aria-checked="true" data-op="reboot">
      <span class="bve-opt-ic"><svg class="bve-i" aria-hidden="true"><use href="{$sprite}#rotate-ccw"></use></svg></span>
      <span class="bve-opt-title">{$T['r']}</span>
      <span class="bve-opt-desc">{$T['rd']}</span>
    </button>
    <button type="button" class="bve-opt" role="radio" aria-checked="false" data-op="poweroff">
      <span class="bve-opt-ic"><svg class="bve-i" aria-hidden="true"><use href="{$sprite}#power"></use></svg></span>
      <span class="bve-opt-title">{$T['p']}</span>
      <span class="bve-opt-desc">{$T['pd']}</span>
    </button>
  </div>

  <div class="bve-actions">
    <p class="bve-confirm" id="bve-confirm" hidden></p>
    <button type="button" class="bl-btn bl-btn--ghost" id="bve-back" hidden>{$T['back']}</button>
    <button type="button" class="bl-btn bl-btn--destructive" id="bve-go">{$T['go']}</button>
  </div>
  <p class="bve-error" id="bve-error" role="alert" hidden></p>
</div>
<script src="{$mod}/assets/energia.js?v={$ver}"></script>
HTML;
}

function bv_en_calls()
{
    if (!is_executable('/usr/sbin/asterisk')) return null;
    $out = array(); $rc = 1;
    @exec('/usr/sbin/asterisk -rx ' . escapeshellarg('core show channels count') . ' 2>/dev/null', $out, $rc);
    if ($rc !== 0) return null;
    foreach ($out as $l) if (preg_match('/(\d+)\s+active call/i', $l, $m)) return (int) $m[1];
    return null;
}

/* Desligamento já agendado (systemd guarda em /run/systemd/shutdown/scheduled). */
function bv_en_scheduled()
{
    $f = '/run/systemd/shutdown/scheduled';
    if (!@is_readable($f)) return '';
    $kv = array();
    foreach ((array) @file($f) as $l) {
        if (strpos($l, '=') !== false) { list($k, $v) = explode('=', trim($l), 2); $kv[$k] = $v; }
    }
    if (empty($kv['USEC'])) return '';
    $ts = (int) floor(((float) $kv['USEC']) / 1000000);
    $mode = isset($kv['MODE']) ? $kv['MODE'] : '';
    if (bv_en_lang() === 'en') return 'The server will ' . ($mode === 'reboot' ? 'restart' : 'shut down') . ' at ' . date('H:i', $ts) . '.';
    return 'O servidor vai ' . ($mode === 'reboot' ? 'reiniciar' : 'desligar') . ' às ' . date('H:i', $ts) . '.';
}

function bv_en_json($data, $code = 200)
{
    if (!headers_sent()) {
        http_response_code($code);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');
    }
    return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function bv_en_lang()
{
    $l = function_exists('get_language') ? strtolower((string) get_language()) : 'pt';
    return (strpos($l, 'br') === 0 || strpos($l, 'pt') === 0) ? 'pt' : 'en';
}

function bv_en_t($pt, $en) { return bv_en_lang() === 'en' ? $en : $pt; }
