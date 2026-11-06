<?php
/*
 * Filas (Bradial Voz): tela própria de filas sobre a API do IssabelPBX.
 *
 * Particularidades da API de filas (lidas no código dela e confirmadas por
 * tools/pbxapi-teste-filas.php):
 *   - a criação é um PUT no número desejado e grava os nomes INTERNOS das colunas
 *     (music, cwignore, dest, maxwait...), sem conversão;
 *   - a alteração é um PUT com os nomes de apresentação (music_on_hold_class,
 *     skip_busy_agents...) e mistura com os valores atuais, então o que não for enviado
 *     fica como está;
 *   - o DELETE exige um corpo JSON.
 * O Issabel só chama _moduleContent() para quem tem permissão neste item de menu.
 */
function _moduleContent(&$smarty, $module_name)
{
    $action = isset($_REQUEST['action']) ? (string) $_REQUEST['action'] : '';
    if ($action !== '') return bv_fl_action($action);

    if (empty($_SESSION['bv_csrf'])) $_SESSION['bv_csrf'] = bin2hex(random_bytes(16));
    $mod  = 'modules/' . rawurlencode($module_name);
    $ver  = htmlspecialchars(trim((string) @file_get_contents(__DIR__ . '/VERSION')) ?: '1', ENT_QUOTES);
    $base = htmlspecialchars('?menu=' . rawurlencode($module_name) . '&rawmode=yes&action=', ENT_QUOTES);
    $tok  = htmlspecialchars($_SESSION['bv_csrf'], ENT_QUOTES);
    $L    = bv_fl_lang();
    $spr  = 'themes/bradial/assets/icons/sprite.svg?v=' . $ver;

    return <<<HTML
<link rel="stylesheet" href="modules/bv_pbx_ramais/assets/ramais.css?v={$ver}" />
<link rel="stylesheet" href="{$mod}/assets/filas.css?v={$ver}" />
<div class="bvr" id="bvr" data-base="{$base}" data-token="{$tok}" data-lang="{$L}" data-sprite="{$spr}">
  <div class="bvr-loading">…</div>
</div>
<script src="{$mod}/assets/filas.js?v={$ver}"></script>
HTML;
}

class BvFlError extends Exception
{
    public $field;
    public function __construct($msg, $field = null) { parent::__construct($msg); $this->field = $field; }
}

function bv_fl_action($action)
{
    $write = in_array($action, array('save', 'delete', 'apply'), true);
    $user = isset($_SESSION['issabel_user']) ? (string) $_SESSION['issabel_user'] : '?';
    if ($write) {
        $tok = isset($_SERVER['HTTP_X_BV_TOKEN']) ? (string) $_SERVER['HTTP_X_BV_TOKEN'] : '';
        $ok = $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SESSION['bv_csrf']) && hash_equals($_SESSION['bv_csrf'], $tok);
        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
        if (!$ok) return bv_fl_json(array('erro' => bv_fl_t('Sessão expirada. Recarregue a página.', 'Session expired. Reload the page.')), 403);
    } elseif (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    require_once __DIR__ . '/../bv_pbx_common/lib/PbxApi.php';
    try {
        $api = new BvPbxApi();
        switch ($action) {
            case 'list':    return bv_fl_json(bv_fl_list($api));
            case 'options': return bv_fl_json(bv_fl_options($api));
            case 'get':     return bv_fl_json(bv_fl_get($api, isset($_GET['num']) ? $_GET['num'] : ''));
            case 'save':    return bv_fl_json(bv_fl_save($api, $user));
            case 'delete':  return bv_fl_json(bv_fl_delete($api, isset($_POST['num']) ? $_POST['num'] : '', $user));
            case 'apply':
                $r = $api->applyChanges();
                bv_fl_log($user, 'aplicou as alterações do PBX', $r['ok']);
                if (!$r['ok']) return bv_fl_json(array('erro' => bv_fl_t('O PBX não aplicou as alterações: ', 'The PBX did not apply the changes: ') . $r['output']), 500);
                return bv_fl_json(array('ok' => true));
        }
        return bv_fl_json(array('erro' => 'Ação desconhecida.'), 400);
    } catch (BvFlError $e) {
        return bv_fl_json(array('erro' => $e->getMessage(), 'campo' => $e->field), 422);
    } catch (Exception $e) {
        return bv_fl_json(array('erro' => $e->getMessage()), 500);
    }
}

/* ------------------------------------------------------------------ leitura */

function bv_fl_list(BvPbxApi $api)
{
    $r = $api->get('queues');
    if ($r['code'] !== 200 || !isset($r['body']['results'])) throw new Exception(bv_fl_t('Não foi possível listar as filas: ', 'Could not list queues: ') . BvPbxApi::errorText($r));
    $live = bv_fl_live();
    $names = bv_fl_ext_names($api);
    $all = bv_fl_all_members();
    $items = array();
    foreach ((array) $r['body']['results'] as $q) {
        $num = (string) (isset($q['extension']) ? $q['extension'] : '');
        if ($num === '') continue;
        $members = array();
        $list = $all !== null ? (isset($all[$num]) ? $all[$num] : array()) : bv_fl_members($api, $num);
        foreach ($list as $m) $members[] = array('ext' => $m, 'name' => isset($names[$m]) ? $names[$m] : '');
        $l = isset($live[$num]) ? $live[$num] : null;
        $items[] = array(
            'num' => $num, 'name' => (string) @$q['name'],
            'strategy' => (string) @$q['ring_strategy'], 'timeout' => (string) @$q['timeout'],
            'members' => $members,
            'waiting' => $l ? $l['waiting'] : null, 'online' => $l ? $l['online'] : null,
        );
    }
    usort($items, function ($a, $b) { return strnatcmp($a['num'], $b['num']); });
    return array('itens' => $items, 'aoVivo' => (bool) $live, 'pendente' => BvPbxApi::pending());
}

/* Ramais fixos de todas as filas numa consulta só (leitura no banco do PBX).
 * Devolve null se o banco não estiver acessível; aí a lista pergunta fila a fila à API. */
function bv_fl_all_members()
{
    if ($f = getenv('BV_TEST_QMEMBERS')) return (array) json_decode((string) @file_get_contents($f), true); // só testes
    try {
        $st = BvPbxApi::pbxDb()->query("SELECT id, data FROM queues_details WHERE keyword = 'member' ORDER BY id");
        $out = array();
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            // Local/201@from-queue/n,0  |  SIP/201,0  |  PJSIP/201,0
            if (preg_match('#^(?:Local|SIP|PJSIP|IAX2|DAHDI)/(\d+)#i', (string) $r['data'], $m)) $out[(string) $r['id']][] = $m[1];
        }
        return $out;
    } catch (Exception $e) {
        return null;
    }
}

/* Ramais fixos de uma fila (a listagem da API não traz os membros). */
function bv_fl_members(BvPbxApi $api, $num)
{
    static $cache = array();
    if (isset($cache[$num])) return $cache[$num];
    $r = $api->get('queues', $num);
    $q = isset($r['body']['results'][0]) ? $r['body']['results'][0] : array();
    $out = array();
    foreach ((array) @$q['static_members'] as $m) {
        $ext = preg_replace('/\D/', '', strtok((string) $m, ','));
        if ($ext !== '') $out[] = $ext;
    }
    return $cache[$num] = $out;
}

function bv_fl_ext_names(BvPbxApi $api)
{
    $r = $api->get('extensions');
    $out = array();
    foreach ((array) @$r['body']['results'] as $x) if (isset($x['extension'])) $out[(string) $x['extension']] = (string) @$x['name'];
    return $out;
}

function bv_fl_live()
{
    $dir = '/var/www/html/modules/painel_operador';
    if (!is_file($dir . '/lib/bootstrap.php') || !is_file($dir . '/lib/Snapshot.php')) return array();
    $out = array();
    try {
        require_once $dir . '/lib/bootstrap.php';
        require_once $dir . '/lib/Snapshot.php';
        $cfg = pop_config();
        if (!empty($cfg['demo'])) return array();
        $d = PopSnapshot::cached($cfg);
        foreach ((array) @$d['queues'] as $q) {
            $on = 0;
            foreach ((array) @$q['members'] as $m) if (!empty($m['online']) || (isset($m['state']) && $m['state'] !== 'unavailable' && $m['state'] !== 'offline')) $on++;
            $out[(string) $q['num']] = array('waiting' => count((array) @$q['waiting']), 'online' => $on);
        }
    } catch (Exception $e) {
    } catch (Error $e) {
    }
    return $out;
}

/* Opções do formulário: ramais, classes de música de espera e destinos. */
function bv_fl_options(BvPbxApi $api)
{
    $exts = array();
    foreach (bv_fl_ext_names($api) as $e => $n) $exts[] = array('ext' => (string) $e, 'name' => $n);
    usort($exts, function ($a, $b) { return strnatcmp($a['ext'], $b['ext']); });

    $moh = array();
    $r = $api->get('musiconhold');
    foreach ((array) @$r['body']['results'] as $m) if (isset($m['category'])) $moh[] = (string) $m['category'];
    if (!in_array('default', $moh, true)) array_unshift($moh, 'default');

    $dest = array(array('grupo' => bv_fl_t('Encerrar', 'End'), 'itens' => array(
        array('nome' => bv_fl_t('Desligar', 'Hang up'), 'valor' => 'app-blackhole,hangup,1'),
        array('nome' => bv_fl_t('Sinal de ocupado', 'Busy signal'), 'valor' => 'app-blackhole,busy,1'),
    )));
    $r = $api->get('alldestinations');
    $labels = array('Extensions' => bv_fl_t('Ramais', 'Extensions'), 'Queues' => bv_fl_t('Filas', 'Queues'),
        'IVR' => bv_fl_t('URAs', 'IVRs'), 'Ring Groups' => bv_fl_t('Grupos de toque', 'Ring groups'),
        'Time Conditions' => bv_fl_t('Condições de tempo', 'Time conditions'), 'Announcements' => bv_fl_t('Anúncios', 'Announcements'),
        'Voicemail' => bv_fl_t('Correio de voz', 'Voicemail'), 'Conferences' => bv_fl_t('Conferências', 'Conferences'));
    foreach ((array) @$r['body']['results'] as $cat => $list) {
        $items = array();
        foreach ((array) $list as $d) if (isset($d['destination'])) $items[] = array('nome' => (string) @$d['name'], 'valor' => (string) $d['destination']);
        if ($items) $dest[] = array('grupo' => isset($labels[$cat]) ? $labels[$cat] : (string) $cat, 'itens' => $items);
    }
    return array('ramais' => $exts, 'musicas' => $moh, 'destinos' => $dest);
}

function bv_fl_get(BvPbxApi $api, $num)
{
    $num = preg_replace('/\D/', '', (string) $num);
    if ($num === '') throw new BvFlError(bv_fl_t('Fila inválida.', 'Invalid queue.'));
    $r = $api->get('queues', $num);
    $q = isset($r['body']['results'][0]) ? $r['body']['results'][0] : null;
    if (!$q) throw new BvFlError(bv_fl_t('Fila não encontrada.', 'Queue not found.'));
    $members = array();
    foreach ((array) @$q['static_members'] as $m) {
        $parts = explode(',', (string) $m);
        $ext = preg_replace('/\D/', '', $parts[0]);
        if ($ext !== '') $members[] = array('ext' => $ext, 'penalty' => isset($parts[1]) ? (string) (int) $parts[1] : '0');
    }
    return array('fila' => array(
        'num' => (string) $q['extension'], 'name' => (string) @$q['name'],
        'strategy' => (string) (@$q['ring_strategy'] ?: 'ringall'),
        'timeout' => (string) @$q['timeout'], 'max_wait' => (string) @$q['max_wait'],
        'music' => (string) (@$q['music_on_hold_class'] ?: 'default'),
        'skip_busy' => (string) (@$q['skip_busy_agents'] ?: 'no'),
        'recording' => (string) @$q['recording_format'] !== '' ? 'yes' : 'no',
        'recording_mode' => (string) (@$q['recording_mode'] ?: 'include_hold_time'),
        'failover' => (string) @$q['failover_destination'], 'continue' => (string) @$q['continue_destination'],
        'members' => $members,
    ));
}

/* ------------------------------------------------------------------ gravação */

function bv_fl_save(BvPbxApi $api, $user)
{
    $in = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($in)) throw new BvFlError(bv_fl_t('Dados inválidos.', 'Invalid data.'));
    $novo = !empty($in['novo']);
    $num = trim((string) @$in['num']);
    $name = trim((string) @$in['name']);

    if (!preg_match('/^\d{2,8}$/', $num)) throw new BvFlError(bv_fl_t('O número da fila deve ter de 2 a 8 dígitos.', 'The queue number must have 2 to 8 digits.'), 'num');
    if ($name === '') throw new BvFlError(bv_fl_t('Informe o nome da fila.', 'Enter the queue name.'), 'name');

    $strategies = array('ringall', 'leastrecent', 'fewestcalls', 'random', 'rrmemory', 'rrordered', 'linear', 'wrandom');
    $strategy = in_array(@$in['strategy'], $strategies, true) ? $in['strategy'] : 'ringall';
    $timeout = (string) @$in['timeout'];
    if (!ctype_digit($timeout) || (int) $timeout > 600) throw new BvFlError(bv_fl_t('Tempo de toque inválido.', 'Invalid ring time.'), 'timeout');
    $maxwait = trim((string) @$in['max_wait']);
    if ($maxwait !== '' && !ctype_digit($maxwait)) throw new BvFlError(bv_fl_t('Espera máxima inválida.', 'Invalid max wait.'), 'max_wait');
    $music = trim((string) @$in['music']);
    if ($music === '' || !preg_match('/^[\w\-. ]+$/u', $music)) $music = 'default';
    $skipMap = array('no' => '0', 'yes' => '1', 'yes_ringinuse_no' => '2', 'queue_calls_only' => '3');
    $skip = isset($skipMap[@$in['skip_busy']]) ? $in['skip_busy'] : 'no';
    $recording = !empty($in['recording']);
    $recMode = @$in['recording_mode'] === 'after_answered' ? 'after_answered' : 'include_hold_time';
    $failover = bv_fl_dest(@$in['failover'], 'failover');
    $continue = bv_fl_dest(@$in['continue'], 'continue');

    $members = array(); $seen = array();
    foreach ((array) @$in['members'] as $m) {
        $ext = preg_replace('/\D/', '', (string) (is_array($m) ? @$m['ext'] : $m));
        $pen = is_array($m) && isset($m['penalty']) && ctype_digit((string) $m['penalty']) ? (int) $m['penalty'] : 0;
        if ($ext === '' || isset($seen[$ext])) continue;
        $seen[$ext] = 1;
        $members[] = $ext . ',' . $pen;
    }

    if ($novo) {
        // Criação: PUT no número, com os nomes internos das colunas.
        $raw = array(
            'name' => $name, 'strategy' => $strategy, 'timeout' => $timeout, 'maxwait' => $maxwait,
            'music' => $music, 'cwignore' => $skipMap[$skip], 'dest' => $failover, 'destcontinue' => $continue,
            'monitor-format' => $recording ? 'wav' : '', 'monitor_type' => $recMode === 'after_answered' ? 'b' : '',
            'static_members' => $members,
        );
        $exists = $api->get('queues', $num);
        if (!empty($exists['body']['results'])) throw new BvFlError(bv_fl_t('Já existe uma fila com esse número.', 'A queue with this number already exists.'), 'num');
        $r = $api->putRaw('queues', $num, $raw);
        if ($r['code'] < 200 || $r['code'] >= 300) {
            $msg = BvPbxApi::errorText($r);
            if (stripos($msg, 'use') !== false || stripos($msg, 'exist') !== false || stripos($msg, 'duplicat') !== false)
                throw new BvFlError(bv_fl_t('Esse número já está em uso no PBX (ramal, fila ou outro destino).', 'This number is already in use on the PBX.'), 'num');
            throw new Exception(bv_fl_t('O PBX recusou a fila: ', 'The PBX refused the queue: ') . $msg);
        }
        BvPbxApi::markPending("fila $num criada");
        bv_fl_log($user, "criou a fila $num", true);
    } else {
        // Alteração: nomes de apresentação; o que não for enviado fica como está.
        $p = array(
            'name' => $name, 'ring_strategy' => $strategy, 'timeout' => $timeout, 'max_wait' => $maxwait,
            'music_on_hold_class' => $music, 'skip_busy_agents' => $skip,
            'failover_destination' => $failover, 'continue_destination' => $continue,
            'recording_format' => $recording ? 'wav' : '', 'recording_mode' => $recMode,
            'static_members' => $members,
        );
        $r = $api->put('queues', $num, $p);
        if ($r['code'] < 200 || $r['code'] >= 300) throw new Exception(bv_fl_t('O PBX recusou a alteração: ', 'The PBX refused the change: ') . BvPbxApi::errorText($r));
        BvPbxApi::markPending("fila $num alterada");
        bv_fl_log($user, "alterou a fila $num", true);
    }
    return array('ok' => true, 'num' => $num, 'pendente' => BvPbxApi::pending());
}

function bv_fl_dest($v, $field)
{
    $v = trim((string) $v);
    if ($v === '') return '';
    if (!preg_match('/^[\w\-]+,[\w\-*#+]+,\d+$/', $v)) throw new BvFlError(bv_fl_t('Destino inválido.', 'Invalid destination.'), $field);
    return $v;
}

function bv_fl_delete(BvPbxApi $api, $num, $user)
{
    $num = preg_replace('/\D/', '', (string) $num);
    if ($num === '') throw new BvFlError(bv_fl_t('Fila inválida.', 'Invalid queue.'));
    $r = $api->delete('queues', $num, array());
    if ($r['code'] < 200 || $r['code'] >= 300) throw new Exception(bv_fl_t('O PBX não excluiu a fila: ', 'The PBX did not delete the queue: ') . BvPbxApi::errorText($r));
    BvPbxApi::markPending("fila $num excluída");
    bv_fl_log($user, "excluiu a fila $num", true);
    return array('ok' => true, 'pendente' => BvPbxApi::pending());
}

/* ------------------------------------------------------------------ util */

function bv_fl_log($user, $what, $ok)
{
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '?';
    @error_log(sprintf('[bv_pbx_filas] %s %s (%s)%s', $user, $what, $ip, $ok ? '' : ' - falhou'));
}

function bv_fl_lang()
{
    $l = function_exists('get_language') ? strtolower((string) get_language()) : 'pt';
    return (strpos($l, 'br') === 0 || strpos($l, 'pt') === 0) ? 'pt' : 'en';
}

function bv_fl_t($pt, $en) { return bv_fl_lang() === 'en' ? $en : $pt; }

function bv_fl_json($data, $code = 200)
{
    if (!headers_sent()) {
        http_response_code($code);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');
    }
    return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
