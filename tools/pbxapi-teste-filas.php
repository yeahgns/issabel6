<?php
/*
 * Bradial Voz: teste da API de filas, com uma fila temporária.
 *
 *   php /opt/issabel6/tools/pbxapi-teste-filas.php          usa a fila 6999
 *   php /opt/issabel6/tools/pbxapi-teste-filas.php 6990     usa outro número livre
 *
 * Cria (como a tela faz: PUT com nomes internos), lê, altera (PUT com nomes de
 * apresentação), confere o que mudou e o que deveria ficar igual, e apaga. Não aplica a
 * configuração no PBX: nada chega ao Asterisk.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../modules/bv_pbx_common/lib/PbxApi.php';

$num = isset($argv[1]) ? preg_replace('/\D/', '', $argv[1]) : '6999';
function ok($m) { echo "  [ok]   $m\n"; }
function bad($m) { echo "  [erro] $m\n"; }
function show($l, $v) { echo '         ' . str_pad($l, 30) . var_export($v, true) . "\n"; }
function q(BvPbxApi $api, $num) { $r = $api->get('queues', $num); return isset($r['body']['results'][0]) ? $r['body']['results'][0] : null; }

echo "Teste da API de filas com a fila $num\n\n";
$api = new BvPbxApi();
if (q($api, $num)) { bad("a fila $num já existe; escolha outro número"); exit(1); }
$ext = $api->get('extensions');
$e1 = isset($ext['body']['results'][0]['extension']) ? (string) $ext['body']['results'][0]['extension'] : null;
$e2 = isset($ext['body']['results'][1]['extension']) ? (string) $ext['body']['results'][1]['extension'] : null;
if (!$e1 || !$e2) { bad('são precisos pelo menos dois ramais para o teste'); exit(1); }
ok("ramais usados como membros: $e1 e $e2");

$moh = $api->get('musiconhold');
$classes = array();
foreach ((array) @$moh['body']['results'] as $m) if (isset($m['category'])) $classes[] = $m['category'];
ok('classes de música de espera: ' . implode(', ', $classes));
$dst = $api->get('alldestinations');
ok('destinos disponíveis: ' . implode(', ', array_map(function ($k, $v) { return "$k (" . count($v) . ')'; }, array_keys((array) @$dst['body']['results']), (array) @$dst['body']['results'])));

// 1. Criação (PUT com nomes internos)
$r = $api->putRaw('queues', $num, array(
    'name' => 'Teste Bradial', 'strategy' => 'rrmemory', 'timeout' => '20', 'maxwait' => '120',
    'music' => 'default', 'cwignore' => '1', 'dest' => 'app-blackhole,hangup,1', 'destcontinue' => '',
    'monitor-format' => 'wav', 'monitor_type' => 'b', 'static_members' => array("$e1,0", "$e2,0"),
));
if ($r['code'] < 200 || $r['code'] >= 300) { bad('criar fila: ' . BvPbxApi::errorText($r)); exit(1); }
ok("criar fila (HTTP {$r['code']})");
$x = q($api, $num);
foreach (array('name', 'ring_strategy', 'timeout', 'max_wait', 'music_on_hold_class', 'skip_busy_agents', 'failover_destination', 'continue_destination', 'recording_format', 'recording_mode', 'agent_retry') as $k) show($k, @$x[$k]);
show('static_members', @$x['static_members']);
$chk = array('name' => 'Teste Bradial', 'ring_strategy' => 'rrmemory', 'timeout' => '20', 'max_wait' => '120', 'skip_busy_agents' => 'yes',
    'failover_destination' => 'app-blackhole,hangup,1', 'recording_format' => 'wav', 'recording_mode' => 'after_answered');
foreach ($chk as $k => $v) { if ((string) @$x[$k] === $v) ok("criação: $k"); else bad("criação: $k veio " . var_export(@$x[$k], true) . ", esperado '$v'"); }
if (count((array) @$x['static_members']) === 2) ok('criação: 2 membros'); else bad('criação: membros não conferem');
$retry = @$x['agent_retry'];

// 2. Alteração (PUT com nomes de apresentação), sem mandar membros
$r = $api->put('queues', $num, array(
    'name' => 'Teste Bradial 2', 'ring_strategy' => 'ringall', 'timeout' => '15', 'max_wait' => '',
    'skip_busy_agents' => 'queue_calls_only', 'recording_format' => '', 'recording_mode' => 'include_hold_time',
    'failover_destination' => 'app-blackhole,busy,1',
));
if ($r['code'] < 200 || $r['code'] >= 300) bad('alterar fila: ' . BvPbxApi::errorText($r)); else ok("alterar fila (HTTP {$r['code']})");
$x = q($api, $num);
$chk = array('name' => 'Teste Bradial 2', 'ring_strategy' => 'ringall', 'timeout' => '15', 'max_wait' => '', 'skip_busy_agents' => 'queue_calls_only',
    'recording_format' => '', 'failover_destination' => 'app-blackhole,busy,1');
foreach ($chk as $k => $v) { if ((string) @$x[$k] === $v) ok("alteração: $k"); else bad("alteração: $k veio " . var_export(@$x[$k], true) . ", esperado '$v'"); }
if (count((array) @$x['static_members']) === 2) ok('membros preservados (não enviados na alteração)'); else bad('membros sumiram: ' . var_export(@$x['static_members'], true));
if ((string) @$x['agent_retry'] === (string) $retry) ok('campo não enviado preservado (agent_retry)'); else bad('agent_retry mudou: ' . var_export(@$x['agent_retry'], true));

// 3. Alteração só dos membros
$r = $api->put('queues', $num, array('static_members' => array("$e1,0")));
$x = q($api, $num);
if (count((array) @$x['static_members']) === 1) ok('membros alterados (1)'); else bad('membros não mudaram: ' . var_export(@$x['static_members'], true));
if ((string) @$x['name'] === 'Teste Bradial 2') ok('nome preservado'); else bad('nome mudou: ' . var_export(@$x['name'], true));

// 4. Exclusão (com corpo JSON)
$r = $api->delete('queues', $num, array());
if ($r['code'] < 200 || $r['code'] >= 300) bad('apagar fila: ' . BvPbxApi::errorText($r)); else ok("apagar fila (HTTP {$r['code']})");
if (q($api, $num)) bad("a fila $num continua existindo"); else ok('fila de teste removida');
echo "\nPronto. Mande esta saída inteira.\n";
