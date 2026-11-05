<?php
/*
 * Bradial Voz: teste da API do PBX antes de construir as telas.
 *
 *   php /opt/issabel6/tools/pbxapi-teste.php            usa o ramal 9999
 *   php /opt/issabel6/tools/pbxapi-teste.php 8990       usa outro número livre
 *
 * Cria um ramal PJSIP de teste, lê, altera, lê de novo e apaga. Não mexe em nenhum outro
 * ramal. Recusa rodar se o número já existir. A exclusão no fim faz um "Apply Config"
 * (recarrega o dialplan, sem derrubar ligações).
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../modules/bv_pbx_common/lib/PbxApi.php';

$num = isset($argv[1]) ? preg_replace('/\D/', '', $argv[1]) : '9999';
if ($num === '') { fwrite(STDERR, "Número inválido.\n"); exit(1); }

function ok($m) { echo "  [ok]   $m\n"; }
function bad($m) { echo "  [erro] $m\n"; }
function show($label, $v) { echo '         ' . str_pad($label, 34) . var_export($v, true) . "\n"; }
function pick($a, $path) {
    foreach (explode('.', $path) as $k) { if (!is_array($a) || !array_key_exists($k, $a)) return null; $a = $a[$k]; }
    return $a;
}
function one(BvPbxApi $api, $num) {
    $r = $api->get('extensions', $num);
    $res = isset($r['body']['results']) ? $r['body']['results'] : null;
    if (is_array($res) && isset($res[0])) return $res[0];
    if (is_array($res) && isset($res['extension'])) return $res;
    return null;
}

echo "Teste da API do PBX com o ramal $num\n\n";
try { $api = new BvPbxApi(); ok('chave da API lida de /etc/issabel.conf'); }
catch (Exception $e) { bad($e->getMessage()); exit(1); }

// 1. Listagem
$r = $api->get('extensions');
if ($r['code'] !== 200 || !isset($r['body']['results'])) { bad('listar ramais: ' . BvPbxApi::errorText($r)); exit(1); }
ok('listar ramais: ' . count($r['body']['results']) . ' encontrados');
foreach ($r['body']['results'] as $x) {
    if ((string) (isset($x['extension']) ? $x['extension'] : '') === $num) { bad("o ramal $num já existe; escolha outro número livre"); exit(1); }
}

// 2. Criação (chaves "achatadas", como a API documenta)
$r = $api->post('extensions', array(
    'extension' => $num, 'name' => 'Teste Bradial', 'tech' => 'pjsip',
    'extension_options.call_waiting' => 'yes', 'voicemail.enabled' => 'no',
    // valores incomuns, para conferir que a alteração seguinte não os reseta
    'extension_options.internal_auto_answer' => 'yes', 'device_options.nat' => 'route',
));
if ($r['code'] < 200 || $r['code'] >= 300) { bad('criar ramal: ' . BvPbxApi::errorText($r)); exit(1); }
ok("criar ramal $num (HTTP {$r['code']})");

// 3. Leitura
$x = one($api, $num);
if (!$x) { bad('ler o ramal criado'); exit(1); }
ok('ler o ramal criado');
show('name', pick($x, 'name'));
show('tech', pick($x, 'tech'));
show('extension_options.call_waiting', pick($x, 'extension_options.call_waiting'));
show('voicemail.enabled', pick($x, 'voicemail.enabled'));
show('secret gerado (tamanho)', strlen((string) pick($x, 'secret')));
show('device_options.dtmf_mode', pick($x, 'device_options.dtmf_mode'));
show('extension_options.internal_auto_answer', pick($x, 'extension_options.internal_auto_answer'));
show('device_options.nat', pick($x, 'device_options.nat'));
show('extension_options.ring_time', pick($x, 'extension_options.ring_time'));
echo '         chaves de 1º nível: ' . implode(', ', array_keys($x)) . "\n";

// 4. Alteração (o cliente envia aninhado): nome, opções do ramal e do aparelho
$r = $api->put('extensions', $num, array(
    'name' => 'Teste Bradial 2',
    'extension_options.call_waiting' => 'no',
    'extension_options.ring_time' => '30',
    'device_options.dtmf_mode' => 'info',
));
if ($r['code'] < 200 || $r['code'] >= 300) bad('alterar ramal: ' . BvPbxApi::errorText($r));
else ok("alterar ramal (HTTP {$r['code']})");

// 5. Leitura depois da alteração
$x = one($api, $num);
show('name depois', pick($x, 'name'));
show('call_waiting depois', pick($x, 'extension_options.call_waiting'));
show('ring_time depois', pick($x, 'extension_options.ring_time'));
show('dtmf_mode depois', pick($x, 'device_options.dtmf_mode'));
show('secret mantido (tamanho)', strlen((string) pick($x, 'secret')));
if (pick($x, 'name') === 'Teste Bradial 2') ok('nome alterado'); else bad('nome NÃO mudou');
if (pick($x, 'extension_options.call_waiting') === 'no') ok('chamada em espera desligada'); else bad('chamada em espera não mudou');
if ((string) pick($x, 'extension_options.ring_time') === '30') ok('tempo de toque alterado'); else bad('tempo de toque não mudou');
if (pick($x, 'device_options.dtmf_mode') === 'info') ok('DTMF alterado'); else bad('DTMF não mudou');
if (strlen((string) pick($x, 'secret')) === 32) ok('senha do aparelho preservada'); else bad('senha do aparelho mudou ou sumiu');
// Campos que NÃO foram enviados na alteração devem continuar como estavam
if (pick($x, 'extension_options.internal_auto_answer') === 'yes') ok('atendimento automático preservado'); else bad('atendimento automático foi resetado: ' . var_export(pick($x, 'extension_options.internal_auto_answer'), true));
if (pick($x, 'device_options.nat') === 'route') ok('NAT preservado'); else bad('NAT foi resetado: ' . var_export(pick($x, 'device_options.nat'), true));

// 6. Exclusão (aplica na hora)
$r = $api->delete('extensions', $num);
if ($r['code'] < 200 || $r['code'] >= 300) bad('apagar ramal: ' . BvPbxApi::errorText($r));
else ok("apagar ramal $num (HTTP {$r['code']})");
$x = one($api, $num);
if ($x) bad("o ramal $num continua existindo"); else ok('ramal de teste removido');

echo "\nPronto. Mande esta saída inteira.\n";
