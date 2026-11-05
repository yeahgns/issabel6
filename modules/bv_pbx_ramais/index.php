<?php
/*
 * Ramais (Bradial Voz): tela própria de ramais sobre a API do IssabelPBX.
 *
 * O Issabel só chama _moduleContent() para usuários logados com permissão neste item de
 * menu, inclusive nas requisições rawmode=yes. A API do PBX é chamada daqui, do servidor,
 * pelo BvPbxApi; o navegador nunca recebe o token. Gravações exigem o token de sessão
 * (X-BV-Token) e não aplicam na hora: o botão "Aplicar alterações" faz o Apply Config.
 */
function _moduleContent(&$smarty, $module_name)
{
    $action = isset($_REQUEST['action']) ? (string) $_REQUEST['action'] : '';
    if ($action !== '') return bv_rm_action($action);

    if (empty($_SESSION['bv_csrf'])) $_SESSION['bv_csrf'] = bin2hex(random_bytes(16));
    $mod  = 'modules/' . rawurlencode($module_name);
    $ver  = htmlspecialchars(trim((string) @file_get_contents(__DIR__ . '/VERSION')) ?: '1', ENT_QUOTES);
    $base = htmlspecialchars('?menu=' . rawurlencode($module_name) . '&rawmode=yes&action=', ENT_QUOTES);
    $tok  = htmlspecialchars($_SESSION['bv_csrf'], ENT_QUOTES);
    $L    = bv_rm_lang();
    $spr  = 'themes/bradial/assets/icons/sprite.svg?v=' . $ver;

    return <<<HTML
<link rel="stylesheet" href="{$mod}/assets/ramais.css?v={$ver}" />
<div class="bvr" id="bvr" data-base="{$base}" data-token="{$tok}" data-lang="{$L}" data-sprite="{$spr}">
  <div class="bvr-loading">…</div>
</div>
<script src="{$mod}/assets/ramais.js?v={$ver}"></script>
HTML;
}

/* ------------------------------------------------------------------ ações */

function bv_rm_action($action)
{
    $write = in_array($action, array('save', 'delete', 'apply', 'secret'), true);
    if ($write) {
        $tok = isset($_SERVER['HTTP_X_BV_TOKEN']) ? (string) $_SERVER['HTTP_X_BV_TOKEN'] : '';
        $ok = $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SESSION['bv_csrf']) && hash_equals($_SESSION['bv_csrf'], $tok);
        $user = isset($_SESSION['issabel_user']) ? (string) $_SESSION['issabel_user'] : '?';
        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
        if (!$ok) return bv_rm_json(array('erro' => bv_rm_t('Sessão expirada. Recarregue a página.', 'Session expired. Reload the page.')), 403);
    } else {
        $user = isset($_SESSION['issabel_user']) ? (string) $_SESSION['issabel_user'] : '?';
        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    }

    require_once __DIR__ . '/../bv_pbx_common/lib/PbxApi.php';
    try {
        $api = new BvPbxApi();
        switch ($action) {
            case 'list':   return bv_rm_json(bv_rm_list($api));
            case 'get':    return bv_rm_json(bv_rm_get($api, isset($_GET['ext']) ? $_GET['ext'] : ''));
            case 'secret': return bv_rm_json(bv_rm_secret($api, isset($_POST['ext']) ? $_POST['ext'] : ''));
            case 'save':   return bv_rm_json(bv_rm_save($api, $user));
            case 'delete': return bv_rm_json(bv_rm_delete($api, isset($_POST['ext']) ? $_POST['ext'] : '', $user));
            case 'apply':
                $r = $api->applyChanges();
                bv_rm_log($user, 'aplicou as alterações do PBX', $r['ok']);
                if (!$r['ok']) return bv_rm_json(array('erro' => bv_rm_t('O PBX não aplicou as alterações: ', 'The PBX did not apply the changes: ') . $r['output']), 500);
                return bv_rm_json(array('ok' => true));
        }
        return bv_rm_json(array('erro' => 'Ação desconhecida.'), 400);
    } catch (BvRmError $e) {
        return bv_rm_json(array('erro' => $e->getMessage(), 'campo' => $e->field), 422);
    } catch (Exception $e) {
        return bv_rm_json(array('erro' => $e->getMessage()), 500);
    }
}

class BvRmError extends Exception
{
    public $field;
    public function __construct($msg, $field = null) { parent::__construct($msg); $this->field = $field; }
}

/* Lista: dados da API + estado ao vivo do Painel do operador (quando instalado). */
function bv_rm_list(BvPbxApi $api)
{
    $r = $api->get('extensions');
    if ($r['code'] !== 200 || !isset($r['body']['results'])) throw new Exception(bv_rm_t('Não foi possível listar os ramais: ', 'Could not list extensions: ') . BvPbxApi::errorText($r));

    $live = bv_rm_live();
    $items = array();
    foreach ((array) $r['body']['results'] as $x) {
        $ext = (string) (isset($x['extension']) ? $x['extension'] : '');
        if ($ext === '') continue;
        $l = isset($live['ext'][$ext]) ? $live['ext'][$ext] : null;
        $items[] = array(
            'ext' => $ext,
            'name' => (string) (isset($x['name']) ? $x['name'] : ''),
            'tech' => strtolower((string) (isset($x['tech']) ? $x['tech'] : '')),
            'online' => $l ? (bool) $l['online'] : null,
            'ip' => $l && !empty($l['ip']) ? (string) $l['ip'] : '',
            'filas' => isset($live['filas'][$ext]) ? $live['filas'][$ext] : array(),
        );
    }
    usort($items, function ($a, $b) { return strnatcmp($a['ext'], $b['ext']); });
    return array('itens' => $items, 'aoVivo' => $live['ok'], 'pendente' => BvPbxApi::pending());
}

function bv_rm_live()
{
    $out = array('ok' => false, 'ext' => array(), 'filas' => array());
    $dir = '/var/www/html/modules/painel_operador';
    if (!is_file($dir . '/lib/bootstrap.php') || !is_file($dir . '/lib/Snapshot.php')) return $out;
    try {
        require_once $dir . '/lib/bootstrap.php';
        require_once $dir . '/lib/Snapshot.php';
        $cfg = pop_config();
        if (!empty($cfg['demo'])) return $out;
        $d = PopSnapshot::cached($cfg);
        foreach ((array) @$d['extensions'] as $e) {
            if (!isset($e['ext'])) continue;
            $out['ext'][(string) $e['ext']] = array('online' => !empty($e['online']), 'ip' => isset($e['ip']) ? $e['ip'] : '');
        }
        foreach ((array) @$d['queues'] as $q) {
            foreach ((array) @$q['members'] as $m) {
                if (!isset($m['ext'])) continue;
                $out['filas'][(string) $m['ext']][] = (string) $q['num'];
            }
        }
        $out['ok'] = true;
    } catch (Exception $e) {
    } catch (Error $e) {
    }
    return $out;
}

/* Um ramal no formato do formulário (sem a senha). */
function bv_rm_get(BvPbxApi $api, $ext)
{
    $x = bv_rm_fetch($api, $ext);
    $g = function ($path, $def = '') use ($x) {
        $a = $x;
        foreach (explode('.', $path) as $k) { if (!is_array($a) || !array_key_exists($k, $a)) return $def; $a = $a[$k]; }
        return is_array($a) ? $def : (string) $a;
    };
    $rec = array($g('recording.inbound_external', 'dontcare'), $g('recording.inbound_internal', 'dontcare'),
                 $g('recording.outbound_external', 'dontcare'), $g('recording.outbound_internal', 'dontcare'));
    $rec = count(array_unique($rec)) === 1 ? $rec[0] : 'misto';
    return array('ramal' => array(
        'ext' => $g('extension'), 'name' => $g('name'), 'tech' => strtolower($g('tech')),
        'call_waiting' => $g('extension_options.call_waiting', 'no') === 'yes',
        'auto_answer' => $g('extension_options.internal_auto_answer', 'no') === 'yes',
        'voicemail' => $g('voicemail.enabled', 'no') === 'yes',
        'vm_pin' => $g('voicemail.pin'), 'vm_email' => $g('voicemail.email'),
        'recording' => $rec,
        'dtmf' => $g('device_options.dtmf_mode', 'rfc2833'), 'nat' => $g('device_options.nat', 'yes'),
        'ring_time' => $g('extension_options.ring_time', '0'),
        'concurrency' => $g('extension_options.outbound_concurrency_limit', '0'),
        'dial_options' => $g('extension_options.dial_options'),
        'outbound_cid' => $g('callerid_override.outbound'),
    ));
}

function bv_rm_secret(BvPbxApi $api, $ext)
{
    $x = bv_rm_fetch($api, $ext);
    return array('senha' => isset($x['secret']) ? (string) $x['secret'] : '');
}

function bv_rm_fetch(BvPbxApi $api, $ext)
{
    $ext = preg_replace('/[^0-9A-Za-z]/', '', (string) $ext);
    if ($ext === '') throw new BvRmError(bv_rm_t('Ramal inválido.', 'Invalid extension.'));
    $r = $api->get('extensions', $ext);
    $res = isset($r['body']['results']) ? $r['body']['results'] : null;
    if (is_array($res) && isset($res[0])) return $res[0];
    throw new BvRmError(bv_rm_t('Ramal não encontrado.', 'Extension not found.'));
}

/* Criação e alteração. Só envia os campos do formulário; o resto fica como está. */
function bv_rm_save(BvPbxApi $api, $user)
{
    $in = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($in)) throw new BvRmError(bv_rm_t('Dados inválidos.', 'Invalid data.'));
    $novo = !empty($in['novo']);
    $ext = trim((string) @$in['ext']);
    $name = trim((string) @$in['name']);

    if (!preg_match('/^\d{2,8}$/', $ext)) throw new BvRmError(bv_rm_t('O número do ramal deve ter de 2 a 8 dígitos.', 'The extension must have 2 to 8 digits.'), 'ext');
    if ($name === '') throw new BvRmError(bv_rm_t('Informe o nome de exibição.', 'Enter the display name.'), 'name');
    if ((function_exists('mb_strlen') ? mb_strlen($name) : strlen($name)) > 50) throw new BvRmError(bv_rm_t('O nome pode ter até 50 caracteres.', 'The name can have up to 50 characters.'), 'name');

    $d = array(
        'name' => $name,
        'extension_options.call_waiting' => !empty($in['call_waiting']) ? 'yes' : 'no',
        'extension_options.internal_auto_answer' => !empty($in['auto_answer']) ? 'yes' : 'no',
        'voicemail.enabled' => !empty($in['voicemail']) ? 'yes' : 'no',
    );
    if (!empty($in['voicemail'])) {
        $pin = trim((string) @$in['vm_pin']);
        $email = trim((string) @$in['vm_email']);
        if ($pin !== '' && !preg_match('/^\d{4,10}$/', $pin)) throw new BvRmError(bv_rm_t('A senha do correio de voz deve ter de 4 a 10 dígitos.', 'The voicemail PIN must have 4 to 10 digits.'), 'vm_pin');
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new BvRmError(bv_rm_t('E-mail inválido.', 'Invalid e-mail.'), 'vm_email');
        $d['voicemail.pin'] = $pin !== '' ? $pin : $ext;
        $d['voicemail.email'] = $email;
    }
    $rec = (string) @$in['recording'];
    if (in_array($rec, array('dontcare', 'always', 'never'), true)) {
        foreach (array('inbound_external', 'inbound_internal', 'outbound_external', 'outbound_internal') as $k) $d['recording.' . $k] = $rec;
    }
    $dtmf = (string) @$in['dtmf'];
    if (in_array($dtmf, array('rfc2833', 'info', 'inband', 'auto'), true)) $d['device_options.dtmf_mode'] = $dtmf;
    $nat = (string) @$in['nat'];
    if (in_array($nat, array('yes', 'no', 'never', 'route'), true)) $d['device_options.nat'] = $nat;
    foreach (array('ring_time' => 'extension_options.ring_time', 'concurrency' => 'extension_options.outbound_concurrency_limit') as $f => $k) {
        $v = trim((string) @$in[$f]);
        if ($v !== '' && !ctype_digit($v)) throw new BvRmError(bv_rm_t('Use só números.', 'Digits only.'), $f);
        if ($v !== '') $d[$k] = $v;
    }
    $d['extension_options.dial_options'] = trim((string) @$in['dial_options']);
    $cid = trim((string) @$in['outbound_cid']);
    if ($cid !== '' && !ctype_digit($cid)) throw new BvRmError(bv_rm_t('O CallerID externo aceita só números.', 'The outbound CallerID accepts digits only.'), 'outbound_cid');
    $d['callerid_override.outbound'] = $cid;

    $secret = (string) @$in['secret'];
    if ($secret !== '') {
        if (strlen($secret) < 8) throw new BvRmError(bv_rm_t('A senha do aparelho precisa de pelo menos 8 caracteres.', 'The device password needs at least 8 characters.'), 'secret');
        if (!preg_match('/^[\x21-\x7e]+$/', $secret)) throw new BvRmError(bv_rm_t('A senha do aparelho não pode ter espaços nem acentos.', 'The device password cannot have spaces or accents.'), 'secret');
        $d['secret'] = $secret;
    }

    if ($novo) {
        $tech = (string) @$in['tech'] === 'sip' ? 'sip' : 'pjsip';
        $d['extension'] = $ext;
        $d['tech'] = $tech;
        $r = $api->post('extensions', $d);
        if ($r['code'] < 200 || $r['code'] >= 300) {
            $msg = BvPbxApi::errorText($r);
            if (stripos($msg, 'use') !== false || stripos($msg, 'exist') !== false || stripos($msg, 'duplicat') !== false)
                throw new BvRmError(bv_rm_t('Esse número já está em uso no PBX.', 'This number is already in use on the PBX.'), 'ext');
            throw new Exception(bv_rm_t('O PBX recusou o ramal: ', 'The PBX refused the extension: ') . $msg);
        }
        BvPbxApi::markPending("ramal $ext criado");
        bv_rm_log($user, "criou o ramal $ext", true);
    } else {
        $r = $api->put('extensions', $ext, $d);
        if ($r['code'] < 200 || $r['code'] >= 300) throw new Exception(bv_rm_t('O PBX recusou a alteração: ', 'The PBX refused the change: ') . BvPbxApi::errorText($r));
        BvPbxApi::markPending("ramal $ext alterado");
        bv_rm_log($user, "alterou o ramal $ext", true);
    }
    return array('ok' => true, 'ext' => $ext, 'pendente' => BvPbxApi::pending());
}

/* A API aplica a configuração na hora ao excluir (inclusive o que estava pendente). */
function bv_rm_delete(BvPbxApi $api, $ext, $user)
{
    $ext = preg_replace('/\D/', '', (string) $ext);
    if ($ext === '') throw new BvRmError(bv_rm_t('Ramal inválido.', 'Invalid extension.'));
    $r = $api->delete('extensions', $ext);
    if ($r['code'] < 200 || $r['code'] >= 300) throw new Exception(bv_rm_t('O PBX não excluiu o ramal: ', 'The PBX did not delete the extension: ') . BvPbxApi::errorText($r));
    BvPbxApi::clearPending();
    bv_rm_log($user, "excluiu o ramal $ext", true);
    return array('ok' => true, 'pendente' => BvPbxApi::pending());
}

/* ------------------------------------------------------------------ util */

function bv_rm_log($user, $what, $ok)
{
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '?';
    @error_log(sprintf('[bv_pbx_ramais] %s %s (%s)%s', $user, $what, $ip, $ok ? '' : ' - falhou'));
}

function bv_rm_lang()
{
    $l = function_exists('get_language') ? strtolower((string) get_language()) : 'pt';
    return (strpos($l, 'br') === 0 || strpos($l, 'pt') === 0) ? 'pt' : 'en';
}

function bv_rm_t($pt, $en) { return bv_rm_lang() === 'en' ? $en : $pt; }

function bv_rm_json($data, $code = 200)
{
    if (!headers_sent()) {
        http_response_code($code);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');
    }
    return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
