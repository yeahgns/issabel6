<?php
/*
 * Bradial Voz: cliente da API REST do IssabelPBX (/pbxapi), usado pelas telas de PBX.
 *
 * A API aceita qualquer token assinado com a chave "pbxapijwtsecret" de /etc/issabel.conf
 * e não distingue usuários: quem tem token pode tudo no PBX. Por isso:
 *   - o token é gerado aqui, no servidor, com validade de 5 minutos, e nunca vai para o
 *     navegador;
 *   - as telas só chamam este cliente depois que o Issabel confirmou a permissão do
 *     usuário naquele item de menu.
 *
 * Gravações (POST/PUT) vão com "reload": false; o "Apply Config" fica com applyChanges(),
 * acionado pelo botão "Aplicar alterações". Atenção: o DELETE da API sempre aplica na hora.
 * Compatível com PHP 7.4, sem dependências (usa streams, não exige a extensão curl).
 */
class BvPbxApi
{
    const CONF = '/etc/issabel.conf';
    const BASE = 'https://127.0.0.1/pbxapi';
    const PENDING = 'bv-pbx-pendente.json';

    private $key;

    public function __construct()
    {
        $conf = self::readConf(self::CONF);
        if (empty($conf['pbxapijwtsecret'])) {
            throw new RuntimeException('A chave da API do PBX (pbxapijwtsecret) não foi encontrada em ' . self::CONF . '.');
        }
        $key = base64_decode($conf['pbxapijwtsecret'], true);
        if ($key === false || strlen($key) < 32) {
            throw new RuntimeException('A chave da API do PBX em ' . self::CONF . ' é inválida.');
        }
        $this->key = $key;
    }

    /* ---------------------------------------------------------------- requisições */

    public function get($resource, $id = null, array $query = array())
    {
        $path = $resource . ($id !== null && $id !== '' ? '/' . rawurlencode($id) : '');
        if ($query) $path .= '?' . http_build_query($query);
        return $this->request('GET', $path);
    }

    /* Os dados podem vir "achatados" ("extension_options.call_waiting" => "no"); o cliente
     * sempre envia aninhado. Motivo: o PUT da API converte chaves achatadas para os nomes
     * internos antes de validar e gravar, e aí os campos mapeados (chamada em espera, correio
     * de voz, opções do aparelho...) são ignorados. Aninhado, eles passam pelo mesmo caminho
     * da criação. Confirmado em teste no servidor (tools/pbxapi-teste.php). */
    public function post($resource, array $data)
    {
        $data = self::nest($data);
        $data['reload'] = false;
        return $this->request('POST', $resource, $data);
    }

    public function put($resource, $id, array $data)
    {
        $data = self::nest($data);
        $data['reload'] = false;
        return $this->request('PUT', $resource . '/' . rawurlencode($id), $data);
    }

    /* "a.b.c" => v  vira  array("a" => array("b" => array("c" => v))) */
    public static function nest(array $flat)
    {
        $out = array();
        foreach ($flat as $k => $v) {
            if (is_array($v)) $v = self::nest($v);
            if (strpos((string) $k, '.') === false) {
                $out[$k] = (isset($out[$k]) && is_array($out[$k]) && is_array($v)) ? array_replace_recursive($out[$k], $v) : $v;
                continue;
            }
            $ref = &$out;
            $parts = explode('.', $k);
            $last = array_pop($parts);
            foreach ($parts as $p) {
                if (!isset($ref[$p]) || !is_array($ref[$p])) $ref[$p] = array();
                $ref = &$ref[$p];
            }
            $ref[$last] = $v;
            unset($ref);
        }
        return $out;
    }

    public function delete($resource, $id)
    {
        return $this->request('DELETE', $resource . '/' . rawurlencode($id));
    }

    /* Resultado: array('code' => HTTP, 'body' => array|null, 'raw' => string, 'location' => string|null) */
    public function request($method, $path, $data = null)
    {
        $headers = array(
            'Authorization: Bearer ' . $this->token(),
            'Accept: application/json',
        );
        $body = null;
        if ($data !== null) {
            $body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $headers[] = 'Content-Type: application/json';
            $headers[] = 'Content-Length: ' . strlen($body);
        }
        $ctx = stream_context_create(array(
            'http' => array(
                'method' => $method, 'header' => implode("\r\n", $headers), 'content' => $body,
                'ignore_errors' => true, 'timeout' => 30, 'follow_location' => 0,
            ),
            // Conexão local (127.0.0.1): o certificado é do domínio, não do endereço.
            'ssl' => array('verify_peer' => false, 'verify_peer_name' => false),
        ));
        $raw = @file_get_contents(self::BASE . '/' . ltrim($path, '/'), false, $ctx);
        $hdrs = isset($http_response_header) ? $http_response_header : array();
        if ($raw === false && !$hdrs) {
            throw new RuntimeException('Não foi possível falar com a API do PBX em ' . self::BASE . '.');
        }
        $code = 0; $location = null;
        foreach ($hdrs as $h) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) $code = (int) $m[1];
            if (stripos($h, 'Location:') === 0) $location = trim(substr($h, 9));
        }
        $json = json_decode((string) $raw, true);
        return array('code' => $code, 'body' => is_array($json) ? $json : null, 'raw' => (string) $raw, 'location' => $location);
    }

    /* Mensagem legível a partir de uma resposta de erro da API */
    public static function errorText(array $r)
    {
        if (!empty($r['body']['errors']) && is_array($r['body']['errors'])) {
            $parts = array();
            foreach ($r['body']['errors'] as $e) {
                $parts[] = trim((isset($e['source']) ? $e['source'] . ': ' : '') . (isset($e['detail']) ? $e['detail'] : ''));
            }
            return implode('; ', $parts);
        }
        if (isset($r['body']['status']) && $r['body']['status'] === 'expired') return 'token expirado';
        return 'HTTP ' . $r['code'] . ($r['raw'] !== '' ? ': ' . substr(strip_tags($r['raw']), 0, 200) : '');
    }

    /* ---------------------------------------------------------------- aplicar */

    /* "Apply Config" do IssabelPBX: o mesmo comando que a própria API usa. */
    public function applyChanges()
    {
        $out = array(); $rc = 1;
        exec('/usr/bin/issabel-helper applychanges 2>&1', $out, $rc);
        if ($rc === 0) self::clearPending();
        return array('ok' => $rc === 0, 'output' => implode("\n", $out));
    }

    public static function markPending($what)
    {
        $f = sys_get_temp_dir() . '/' . self::PENDING;
        $p = self::pending();
        $p['desde'] = $p['desde'] ?: time();
        $p['itens'][] = array('quando' => time(), 'o_que' => (string) $what);
        $p['itens'] = array_slice($p['itens'], -50);
        @file_put_contents($f, json_encode($p));
    }

    public static function pending()
    {
        $f = sys_get_temp_dir() . '/' . self::PENDING;
        $p = @json_decode((string) @file_get_contents($f), true);
        if (!is_array($p)) $p = array();
        return array('desde' => isset($p['desde']) ? (int) $p['desde'] : 0, 'itens' => isset($p['itens']) ? (array) $p['itens'] : array());
    }

    public static function clearPending()
    {
        @unlink(sys_get_temp_dir() . '/' . self::PENDING);
    }

    /* ---------------------------------------------------------------- token */

    private function token()
    {
        $now = time();
        $h = array('typ' => 'JWT', 'alg' => 'HS256');
        $p = array('iat' => $now, 'exp' => $now + 300, 'data' => array('name' => 'admin', 'via' => 'bradial-voz'));
        $seg = self::b64(json_encode($h)) . '.' . self::b64(json_encode($p));
        return $seg . '.' . self::b64(hash_hmac('sha256', $seg, $this->key, true));
    }

    private static function b64($s)
    {
        return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    }

    private static function readConf($file)
    {
        $out = array();
        foreach ((array) @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) {
            $l = trim((string) $l);
            if ($l === '' || $l[0] === '#' || $l[0] === ';' || strpos($l, '=') === false) continue;
            list($k, $v) = explode('=', $l, 2);
            $out[trim($k)] = trim($v);
        }
        return $out;
    }
}
