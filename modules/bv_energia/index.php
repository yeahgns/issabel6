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
        if (!$ok) return bv_en_json(array('erro' => 'Sessão expirada. Recarregue a página.'), 403);

        if ($action === 'cancel') {
            $cmd = 'sudo -n /sbin/shutdown -c';
        } else {
            $op = isset($_POST['op']) ? (string) $_POST['op'] : '';
            if ($op !== 'reboot' && $op !== 'poweroff') return bv_en_json(array('erro' => 'Opção inválida.'), 400);
            $cmd = 'sudo -n /sbin/shutdown ' . ($op === 'reboot' ? '-r' : '-h') . ' +1';
        }
        $out = array(); $rc = 1;
        exec($cmd . ' 2>&1', $out, $rc);
        $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '?';
        @error_log(sprintf('[bv_energia] %s por %s (%s): %s', $action === 'cancel' ? 'cancelamento' : $_POST['op'], $user, $ip, $rc === 0 ? 'ok' : 'falhou'));
        if ($rc !== 0) {
            return bv_en_json(array('erro' => 'O servidor recusou o comando: ' . trim(implode(' ', $out))), 500);
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
    if ($calls === null) $sub = 'Ligações em andamento serão encerradas.';
    elseif ($calls === 0) $sub = 'Ligações em andamento serão encerradas. Agora não há nenhuma chamada ativa.';
    else $sub = 'Ligações em andamento serão encerradas. Agora há ' . $calls . ($calls === 1 ? ' chamada ativa.' : ' chamadas ativas.');
    $sub = htmlspecialchars($sub, ENT_QUOTES);
    $agendado = bv_en_scheduled();
    $agTxt = $agendado ? htmlspecialchars($agendado, ENT_QUOTES) : '';
    $hidden = $agendado ? '' : ' hidden';

    return <<<HTML
<link rel="stylesheet" href="{$mod}/assets/energia.css?v={$ver}" />
<div class="bve" id="bve" data-base="{$base}" data-token="{$tok}">
  <div>
    <h1>Reiniciar ou desligar</h1>
    <p class="bve-sub">{$sub}</p>
  </div>

  <div class="bve-pending"{$hidden} id="bve-pending" role="status">
    <svg class="bve-i" aria-hidden="true"><use href="{$sprite}#triangle-alert"></use></svg>
    <span id="bve-pending-text">{$agTxt}</span>
    <button type="button" class="bl-btn bl-btn--outline bl-btn--sm" id="bve-cancel">Cancelar</button>
  </div>

  <div class="bve-opts" role="radiogroup" aria-label="O que fazer">
    <button type="button" class="bve-opt is-selected" role="radio" aria-checked="true" data-op="reboot">
      <span class="bve-opt-ic"><svg class="bve-i" aria-hidden="true"><use href="{$sprite}#rotate-ccw"></use></svg></span>
      <span class="bve-opt-title">Reiniciar</span>
      <span class="bve-opt-desc">O servidor desliga e volta sozinho em cerca de 2 minutos.</span>
    </button>
    <button type="button" class="bve-opt" role="radio" aria-checked="false" data-op="poweroff">
      <span class="bve-opt-ic"><svg class="bve-i" aria-hidden="true"><use href="{$sprite}#power"></use></svg></span>
      <span class="bve-opt-title">Desligar</span>
      <span class="bve-opt-desc">O servidor fica desligado até alguém ligá-lo novamente.</span>
    </button>
  </div>

  <div class="bve-actions">
    <p class="bve-confirm" id="bve-confirm" hidden></p>
    <button type="button" class="bl-btn bl-btn--ghost" id="bve-back" hidden>Voltar</button>
    <button type="button" class="bl-btn bl-btn--destructive" id="bve-go">Reiniciar servidor</button>
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
    $what = $mode === 'reboot' ? 'reiniciar' : 'desligar';
    return 'O servidor vai ' . $what . ' às ' . date('H:i', $ts) . '.';
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
