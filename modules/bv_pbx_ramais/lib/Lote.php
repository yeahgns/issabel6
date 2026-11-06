<?php
/*
 * Ramais em lote (Bradial Voz): planilha no formato do "Batch of Extensions" do Issabel 5.
 *
 * Diferente do lote nativo, a mesma planilha serve para criar e para editar:
 *   - ramal que não existe no PBX  -> é criado;
 *   - ramal que já existe          -> só as colunas que mudaram são gravadas;
 *   - ramal do PBX fora da planilha -> não é tocado (a planilha nunca exclui nada);
 *   - célula vazia num ramal existente -> mantém o valor atual (não apaga).
 * Colunas que a API do PBX não grava com segurança são ignoradas e listadas na prévia.
 */
class BvRamaisLote
{
    /* Ordem e nomes exatamente como no export do Issabel 5. */
    public static $HEADER = array(
        'Display Name', 'User Extension', 'Direct DID', 'Outbound CID', 'Call Waiting', 'Secret', 'Voicemail Status',
        'Voicemail Password', 'VM Email Address', 'VM Pager Email Address', 'VM Options', 'VM Email Attachment',
        'VM Play CID', 'VM Play Envelope', 'VM Delete Vmail', 'Context', 'Tech', 'Nat', 'Port', 'Callgroup',
        'Pickupgroup', 'Disallow', 'Allow', 'Deny', 'Permit', 'Record Incoming External', 'Record Outgoing External',
        'Record Incoming Internal', 'Record Outgoing Internal', 'Record On Demand', 'Record Priority',
        'DTLS Certificate File', 'DTLS Private Key', 'DTLS Certificate Authority File', 'Media Encryption',
        'Message Context', 'Subscribe Context', 'Allow Subscribe', 'Max Contacts', 'Qualify Timeout',
        'Authenticate Qualify', 'Accountcode', 'Transport', 'RTCP Mux', 'Media Use Received Transport', 'Direct Media',
        'Enable Audio Video Profile', 'Force Audio Video Profile', 'ICE Support', 'DTLS Verify', 'Enable DTLS',
        'DTLS Setup', 'STIR Shaken', 'Force RPort', 'RTP Symmetric',
    );

    /* Coluna -> campo da API, rótulo na prévia e conversão de valores (csv => api). */
    public static function map()
    {
        $yn = array('yes' => 'yes', 'no' => 'no');
        return array(
            'Display Name'             => array('name', 'Nome', null),
            'Outbound CID'             => array('callerid_override.outbound', 'CallerID externo', null),
            'Call Waiting'             => array('extension_options.call_waiting', 'Chamada em espera', array('ENABLED' => 'yes', 'DISABLED' => 'no', '' => 'no')),
            'Secret'                   => array('secret', 'Senha do aparelho', null),
            'Voicemail Status'         => array('voicemail.enabled', 'Correio de voz', array('enabled' => 'yes', 'enable' => 'yes', 'disable' => 'no', 'disabled' => 'no')),
            'Voicemail Password'       => array('voicemail.pin', 'Senha do correio de voz', null),
            'VM Email Address'         => array('voicemail.email', 'E-mail do correio de voz', null),
            'VM Pager Email Address'   => array('voicemail.pager_email', 'E-mail de aviso (pager)', null),
            'VM Email Attachment'      => array('voicemail.options.email_attachment', 'Anexar áudio ao e-mail', $yn),
            'VM Play CID'              => array('voicemail.options.play_callerid', 'Falar o CallerID', $yn),
            'VM Play Envelope'         => array('voicemail.options.play_envelope', 'Falar data e hora', $yn),
            'VM Delete Vmail'          => array('voicemail.options.delete', 'Apagar após enviar por e-mail', $yn),
            'Nat'                      => array('device_options.nat', 'NAT', array('yes' => 'yes', 'no' => 'no', 'never' => 'never', 'route' => 'route')),
            'Callgroup'                => array('device_options.call_group', 'Grupo de chamada', null),
            'Pickupgroup'              => array('device_options.pickup_group', 'Grupo de captura', null),
            'Disallow'                 => array('device_options.disallow_codecs', 'Codecs bloqueados', null),
            'Allow'                    => array('device_options.allow_codecs', 'Codecs permitidos', null),
            'Deny'                     => array('device_options.deny_acl', 'Rede bloqueada (deny)', null),
            'Permit'                   => array('device_options.permit_acl', 'Rede permitida (permit)', null),
            'Record Incoming External' => array('recording.inbound_external', 'Gravar recebidas externas', array('always' => 'always', 'never' => 'never', 'dontcare' => 'dontcare')),
            'Record Outgoing External' => array('recording.outbound_external', 'Gravar feitas externas', array('always' => 'always', 'never' => 'never', 'dontcare' => 'dontcare')),
            'Record Incoming Internal' => array('recording.inbound_internal', 'Gravar recebidas internas', array('always' => 'always', 'never' => 'never', 'dontcare' => 'dontcare')),
            'Record Outgoing Internal' => array('recording.outbound_internal', 'Gravar feitas internas', array('always' => 'always', 'never' => 'never', 'dontcare' => 'dontcare')),
            'Record On Demand'         => array('recording.ondemand', 'Gravação sob demanda', array('enabled' => 'yes', 'disabled' => 'no')),
            'Record Priority'          => array('recording.priority', 'Prioridade da gravação', null),
            'Max Contacts'             => array('device_options.max_contacts', 'Máximo de aparelhos', null),
            'Allow Subscribe'          => array('device_options.allow_subscribe', 'Permitir subscribe', $yn),
            'Accountcode'              => array('device_options.account_code', 'Accountcode', null),
        );
    }

    /* ---------------------------------------------------------------- exportar */

    public static function export(BvPbxApi $api)
    {
        $r = $api->get('extensions');
        if ($r['code'] !== 200 || !isset($r['body']['results'])) throw new Exception('Não foi possível listar os ramais: ' . BvPbxApi::errorText($r));
        $exts = array();
        foreach ((array) $r['body']['results'] as $x) if (isset($x['extension'])) $exts[] = (string) $x['extension'];
        natsort($exts);

        $map = self::map();
        $out = fopen('php://temp', 'w+');
        fputcsv($out, self::$HEADER);
        foreach ($exts as $e) {
            $one = $api->get('extensions', $e);
            $x = isset($one['body']['results'][0]) ? $one['body']['results'][0] : null;
            if (!$x) continue;
            $row = array();
            foreach (self::$HEADER as $col) {
                if ($col === 'User Extension') { $row[] = $e; continue; }
                if ($col === 'Tech') { $row[] = strtolower((string) self::pick($x, 'tech')); continue; }
                if ($col === 'Context') { $row[] = (string) self::pick($x, 'context'); continue; }
                if (!isset($map[$col])) { $row[] = ''; continue; }
                $v = self::pick($x, $map[$col][0]);
                $v = is_array($v) ? implode(',', $v) : (string) $v;
                $conv = $map[$col][2];
                if ($conv) { $back = array_search($v, $conv, true); if ($back !== false) $v = (string) $back; }
                $row[] = $v;
            }
            fputcsv($out, $row);
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);
        return $csv;
    }

    /* ---------------------------------------------------------------- prévia */

    /* Lê a planilha e monta o plano: o que será criado, o que muda, o que fica igual. */
    public static function plan(BvPbxApi $api, $content)
    {
        $rows = self::parseCsv($content);
        if (count($rows) < 2) throw new Exception('A planilha está vazia.');
        $orig = array_map('trim', array_shift($rows));
        $head = array_map('strtolower', $orig);
        $idx = array_flip($head);
        if (!isset($idx['user extension'])) throw new Exception('A planilha precisa da coluna "User Extension". Use o modelo exportado por esta tela ou pelo Issabel.');

        $map = self::map();
        $known = array('user extension' => 1, 'tech' => 1);
        foreach ($map as $col => $_) $known[strtolower($col)] = 1;
        $ignoradas = array();
        foreach ($head as $i => $h) if ($h !== '' && !isset($known[$h])) $ignoradas[] = $orig[$i];

        // ramais que já existem no PBX
        $r = $api->get('extensions');
        if ($r['code'] !== 200 || !isset($r['body']['results'])) throw new Exception('Não foi possível listar os ramais: ' . BvPbxApi::errorText($r));
        $existing = array();
        foreach ((array) $r['body']['results'] as $x) if (isset($x['extension'])) $existing[(string) $x['extension']] = $x;

        $plan = array('novos' => array(), 'alterados' => array(), 'iguais' => 0, 'erros' => array(), 'ignoradas' => $ignoradas, 'total' => 0);
        $seen = array();
        foreach ($rows as $n => $row) {
            $line = $n + 2;
            if (!array_filter($row, function ($v) { return trim((string) $v) !== ''; })) continue;
            $plan['total']++;
            $get = function ($col) use ($row, $idx) { $k = strtolower($col); return isset($idx[$k], $row[$idx[$k]]) ? trim((string) $row[$idx[$k]]) : null; };
            $ext = $get('User Extension');
            if ($ext === null || !preg_match('/^\d{2,8}$/', $ext)) { $plan['erros'][] = array('linha' => $line, 'msg' => 'número de ramal inválido: "' . $ext . '"'); continue; }
            if (isset($seen[$ext])) { $plan['erros'][] = array('linha' => $line, 'msg' => "o ramal $ext aparece mais de uma vez (linha {$seen[$ext]})"); continue; }
            $seen[$ext] = $line;

            // valores da planilha convertidos para a API
            $vals = array(); $err = null;
            foreach ($map as $col => $m) {
                $v = $get($col);
                if ($v === null) continue;
                if ($m[2] !== null && $v !== '') {
                    $key = isset($m[2][$v]) ? $v : (isset($m[2][strtolower($v)]) ? strtolower($v) : (isset($m[2][strtoupper($v)]) ? strtoupper($v) : null));
                    if ($key === null) { $err = "valor \"$v\" inválido em \"$col\""; break; }
                    $v = $m[2][$key];
                }
                $vals[$m[0]] = $v;
            }
            if ($err) { $plan['erros'][] = array('linha' => $line, 'msg' => "ramal $ext: $err"); continue; }
            if (isset($vals['name']) && $vals['name'] === '') unset($vals['name']);

            if (!isset($existing[$ext])) {
                // novo: tudo o que veio preenchido; vazio usa o padrão do PBX
                $d = array_filter($vals, function ($v) { return $v !== ''; });
                $tech = strtolower((string) $get('Tech'));
                $d['tech'] = in_array($tech, array('sip', 'pjsip'), true) ? $tech : 'pjsip';
                $d['extension'] = $ext;
                if (!isset($d['name'])) $d['name'] = $ext;
                if (isset($d['voicemail.enabled']) && $d['voicemail.enabled'] === 'yes' && empty($d['voicemail.pin'])) $d['voicemail.pin'] = $ext;
                $plan['novos'][] = array('ext' => $ext, 'name' => $d['name'], 'linha' => $line, 'dados' => $d);
                continue;
            }

            // existente: só o que mudou; célula vazia mantém o valor atual
            $one = $api->get('extensions', $ext);
            $cur = isset($one['body']['results'][0]) ? $one['body']['results'][0] : array();
            $diff = array(); $mud = array();
            foreach ($vals as $k => $v) {
                if ($v === '') continue;
                $c = self::pick($cur, $k);
                $c = is_array($c) ? implode(',', $c) : (string) $c;
                if ($c === (string) $v) continue;
                $diff[$k] = $v;
                $label = self::label($k);
                $mud[] = array('campo' => $label, 'de' => $k === 'secret' ? '•••' : self::human($c), 'para' => $k === 'secret' ? '•••' : self::human((string) $v), 'senha' => $k === 'secret');
            }
            if (!$diff) { $plan['iguais']++; continue; }
            $name = isset($existing[$ext]['name']) ? (string) $existing[$ext]['name'] : $ext;
            $plan['alterados'][] = array('ext' => $ext, 'name' => $name, 'linha' => $line, 'dados' => $diff, 'mudancas' => $mud);
        }
        return $plan;
    }

    /* ---------------------------------------------------------------- aplicar */

    public static function apply(BvPbxApi $api, array $plan)
    {
        $res = array('criados' => 0, 'alterados' => 0, 'falhas' => array());
        foreach ($plan['novos'] as $it) {
            $r = $api->post('extensions', $it['dados']);
            if ($r['code'] >= 200 && $r['code'] < 300) $res['criados']++;
            else $res['falhas'][] = array('ext' => $it['ext'], 'msg' => BvPbxApi::errorText($r));
        }
        foreach ($plan['alterados'] as $it) {
            $r = $api->put('extensions', $it['ext'], $it['dados']);
            if ($r['code'] >= 200 && $r['code'] < 300) $res['alterados']++;
            else $res['falhas'][] = array('ext' => $it['ext'], 'msg' => BvPbxApi::errorText($r));
        }
        return $res;
    }

    /* ---------------------------------------------------------------- util */

    /* CSV do Issabel (vírgula) ou do Excel em português (ponto e vírgula), UTF-8 ou Windows-1252. */
    public static function parseCsv($content)
    {
        $content = (string) $content;
        if (strncmp($content, "\xEF\xBB\xBF", 3) === 0) $content = substr($content, 3);
        if (!preg_match('//u', $content)) $content = function_exists('mb_convert_encoding') ? mb_convert_encoding($content, 'UTF-8', 'Windows-1252') : utf8_encode($content);
        $first = strtok($content, "\n");
        $sep = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
        $f = fopen('php://temp', 'w+');
        fwrite($f, $content);
        rewind($f);
        $rows = array();
        while (($row = fgetcsv($f, 0, $sep)) !== false) $rows[] = $row;
        fclose($f);
        return $rows;
    }

    /* Valores legíveis na prévia */
    public static function human($v)
    {
        $h = array('yes' => 'Sim', 'no' => 'Não', 'always' => 'Sempre', 'never' => 'Nunca', 'dontcare' => 'Seguir a regra', 'route' => 'Route');
        return isset($h[$v]) ? $h[$v] : $v;
    }

    public static function label($key)
    {
        foreach (self::map() as $col => $m) if ($m[0] === $key) return $m[1];
        return $key;
    }

    public static function pick($a, $path)
    {
        foreach (explode('.', $path) as $k) {
            if (!is_array($a) || !array_key_exists($k, $a)) return '';
            $a = $a[$k];
        }
        return $a;
    }
}
