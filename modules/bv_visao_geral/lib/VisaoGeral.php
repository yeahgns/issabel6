<?php
/*
 * Visão geral (Bradial Voz): coleta os dados da tela inicial.
 *
 *   telefonia() -> mesmos números da faixa do Painel do Operador (usa o snapshot
 *                  dele, que já fica em cache); sem o painel, cai para o CLI do Asterisk.
 *   servidor()  -> CPU, memória, swap, disco, rede, tempo ligado e último reload.
 *   avisos()    -> notificações do IssabelPBX (destinos inválidos, atualizações...).
 *
 * Nada aqui grava no sistema, exceto um arquivo pequeno em /tmp com a última
 * leitura de CPU e rede, para calcular o uso entre uma atualização e outra.
 * Compatível com PHP 7.4.
 */
class BvVisaoGeral
{
    const POP_DIR = '/var/www/html/modules/painel_operador';
    const ASTERISK = '/usr/sbin/asterisk';

    private $state;
    private $stateFile;

    public function __construct()
    {
        $this->stateFile = sys_get_temp_dir() . '/bv-visao-geral.json';
        $s = @json_decode((string) @file_get_contents($this->stateFile), true);
        $this->state = is_array($s) ? $s : array();
    }

    public function snapshot()
    {
        $out = array(
            'now'       => time(),
            'asterisk'  => $this->asterisk(),
            'telefonia' => $this->telefonia(),
            'servidor'  => $this->servidor(),
            'avisos'    => $this->avisos(),
        );
        @file_put_contents($this->stateFile . '.tmp', json_encode($this->state));
        @rename($this->stateFile . '.tmp', $this->stateFile);
        return $out;
    }

    /* ------------------------------------------------------------ Asterisk */

    private function cli($cmd)
    {
        if (!is_executable(self::ASTERISK)) return null;
        $out = array(); $rc = 1;
        @exec(self::ASTERISK . ' -rx ' . escapeshellarg($cmd) . ' 2>/dev/null', $out, $rc);
        return $rc === 0 ? $out : null;
    }

    private function asterisk()
    {
        $r = array('ativo' => false, 'uptime' => null, 'reload' => null);
        $lines = $this->cli('core show uptime seconds');
        if ($lines === null) return $r;
        foreach ($lines as $l) {
            if (preg_match('/System uptime:\s*(\d+)/i', $l, $m)) $r['uptime'] = (int) $m[1];
            if (preg_match('/Last reload:\s*(\d+)/i', $l, $m)) $r['reload'] = (int) $m[1];
        }
        $r['ativo'] = $r['uptime'] !== null;
        return $r;
    }

    /* ------------------------------------------------------------ Telefonia */

    public function telefonia()
    {
        if (is_file(self::POP_DIR . '/lib/bootstrap.php') && is_file(self::POP_DIR . '/lib/Snapshot.php')) {
            try {
                require_once self::POP_DIR . '/lib/bootstrap.php';
                require_once self::POP_DIR . '/lib/Snapshot.php';
                $cfg = pop_config();
                if (empty($cfg['demo'])) return $this->kpisDoPainel(PopSnapshot::cached($cfg), $cfg);
            } catch (Exception $e) {
                // segue para o modo simples
            } catch (Error $e) {
            }
        }
        return $this->kpisSimples();
    }

    /* Mesma regra da faixa do Painel do Operador (assets/painel.js). */
    private function kpisDoPainel(array $d, array $cfg)
    {
        $now = isset($d['now']) ? (int) $d['now'] : time();
        $sl = isset($cfg['service_level_seconds']) ? (int) $cfg['service_level_seconds'] : 20;
        $up = 0; $in = 0; $outN = 0; $int = 0;
        foreach ((array) @$d['calls'] as $c) {
            if (@$c['state'] !== 'up') continue;
            $up++;
            if (@$c['dir'] === 'in') $in++; elseif (@$c['dir'] === 'out') $outN++; else $int++;
        }
        $waiting = 0; $oldest = null;
        foreach ((array) @$d['queues'] as $q) {
            foreach ((array) @$q['waiting'] as $w) {
                $waiting++;
                if (isset($w['since']) && ($oldest === null || $w['since'] < $oldest)) $oldest = (int) $w['since'];
            }
        }
        $real = 0; $online = 0; $ringing = 0;
        foreach ((array) @$d['extensions'] as $e) {
            if (@$e['state'] === 'virtual') continue;
            $real++;
            if (!empty($e['online'])) $online++;
            if (@$e['state'] === 'ringing') $ringing++;
        }
        $tTotal = 0; $tOn = 0; $tOff = array();
        foreach ((array) @$d['trunks'] as $t) {
            if (@$t['status'] === 'disabled') continue;
            $tTotal++;
            if (@$t['status'] === 'online' || @$t['status'] === 'warn') $tOn++;
            elseif (@$t['status'] === 'offline') $tOff[] = (string) @$t['label'];
        }
        $mix = array();
        if ($in) $mix[] = $in . ' entrando';
        if ($outN) $mix[] = $outN . ' saindo';
        if ($int) $mix[] = $int . ($int === 1 ? ' interna' : ' internas');

        return array(
            'fonte'   => 'painel',
            'ligacao' => array('valor' => $up, 'detalhe' => $mix ? implode(', ', $mix) : 'nenhuma agora'),
            'tocando' => array('valor' => $ringing, 'detalhe' => $ringing ? 'ramais chamando' : 'nenhum ramal'),
            'fila'    => array('valor' => $waiting,
                               'detalhe' => $oldest ? 'maior espera ' . $this->dur($now - $oldest) : 'ninguém esperando',
                               'alerta' => $oldest !== null && ($now - $oldest) > $sl),
            'ramais'  => array('valor' => $online, 'total' => $real, 'detalhe' => 'registrados'),
            'troncos' => array('valor' => $tOn, 'total' => $tTotal,
                               'detalhe' => $tOff ? 'fora: ' . implode(', ', $tOff) : 'todos respondendo',
                               'alerta' => count($tOff) > 0),
        );
    }

    /* Sem o Painel do Operador: só o que o CLI dá de forma confiável. */
    private function kpisSimples()
    {
        $calls = null; $chans = null;
        $lines = $this->cli('core show channels count');
        if ($lines !== null) {
            foreach ($lines as $l) {
                if (preg_match('/(\d+)\s+active call/i', $l, $m)) $calls = (int) $m[1];
                if (preg_match('/(\d+)\s+active channel/i', $l, $m)) $chans = (int) $m[1];
            }
        }
        return array(
            'fonte'   => 'cli',
            'ligacao' => array('valor' => $calls, 'detalhe' => $chans === null ? 'sem leitura' : $chans . ' canais em uso'),
            'tocando' => null, 'fila' => null, 'ramais' => null, 'troncos' => null,
        );
    }

    /* ------------------------------------------------------------ Servidor */

    public function servidor()
    {
        $mem = $this->meminfo();
        $memTotal = @$mem['MemTotal'];
        $memAvail = isset($mem['MemAvailable']) ? $mem['MemAvailable'] : (@$mem['MemFree'] + @$mem['Buffers'] + @$mem['Cached']);
        $swTotal = @$mem['SwapTotal'];
        $swFree = @$mem['SwapFree'];

        $dTotal = @disk_total_space('/');
        $dFree = @disk_free_space('/');

        $up = @file_get_contents('/proc/uptime');
        $cpuInfo = $this->cpuinfo();

        return array(
            'cpu'     => array('pct' => $this->cpuPct(), 'modelo' => $cpuInfo['modelo'],
                               'nucleos' => $cpuInfo['nucleos'], 'mhz' => $cpuInfo['mhz'],
                               'carga' => $this->loadavg()),
            'memoria' => $this->uso($memTotal ? ($memTotal - $memAvail) * 1024 : null, $memTotal ? $memTotal * 1024 : null),
            'swap'    => $this->uso($swTotal ? ($swTotal - $swFree) * 1024 : null, $swTotal ? $swTotal * 1024 : null),
            'disco'   => $this->uso(($dTotal && $dFree !== false) ? $dTotal - $dFree : null, $dTotal ?: null),
            'rede'    => $this->rede(),
            'ligado'  => $up ? (int) floatval($up) : null,
        );
    }

    private function uso($usado, $total)
    {
        if (!$total) return null;
        return array('usado' => (float) $usado, 'total' => (float) $total, 'pct' => round($usado / $total * 100));
    }

    private function meminfo()
    {
        $r = array();
        foreach ((array) @file('/proc/meminfo') as $l) {
            if (preg_match('/^(\w+):\s+(\d+)/', $l, $m)) $r[$m[1]] = (int) $m[2];
        }
        return $r;
    }

    private function cpuinfo()
    {
        $modelo = null; $mhz = null; $n = 0;
        foreach ((array) @file('/proc/cpuinfo') as $l) {
            if (strpos($l, 'processor') === 0) $n++;
            if ($modelo === null && preg_match('/^model name\s*:\s*(.+)$/', $l, $m)) $modelo = trim($m[1]);
            if ($mhz === null && preg_match('/^cpu MHz\s*:\s*([\d.]+)/', $l, $m)) $mhz = round((float) $m[1]);
        }
        return array('modelo' => $modelo, 'nucleos' => $n ?: null, 'mhz' => $mhz);
    }

    private function loadavg()
    {
        $l = @file_get_contents('/proc/loadavg');
        if (!$l) return null;
        $p = explode(' ', $l);
        return (float) $p[0];
    }

    /* Uso de CPU entre esta leitura e a anterior (guardada em /tmp). */
    private function cpuPct()
    {
        $l = @file('/proc/stat');
        if (!$l || !preg_match('/^cpu\s+(.+)$/', $l[0], $m)) return null;
        $v = array_map('intval', preg_split('/\s+/', trim($m[1])));
        $idle = $v[3] + (isset($v[4]) ? $v[4] : 0);
        $total = array_sum(array_slice($v, 0, 8));
        $prev = isset($this->state['cpu']) ? $this->state['cpu'] : null;
        $this->state['cpu'] = array($idle, $total);
        if (!$prev || $total <= $prev[1]) {
            // primeira leitura: amostra curta para não mostrar vazio
            usleep(200000);
            $l2 = @file('/proc/stat');
            if (!$l2 || !preg_match('/^cpu\s+(.+)$/', $l2[0], $m2)) return null;
            $v2 = array_map('intval', preg_split('/\s+/', trim($m2[1])));
            $prev = array($idle, $total);
            $idle = $v2[3] + (isset($v2[4]) ? $v2[4] : 0);
            $total = array_sum(array_slice($v2, 0, 8));
            $this->state['cpu'] = array($idle, $total);
        }
        $dt = $total - $prev[1];
        if ($dt <= 0) return null;
        return max(0, min(100, round(100 * (1 - ($idle - $prev[0]) / $dt))));
    }

    /* Taxa da interface principal (a de maior tráfego, ignorando lo). */
    private function rede()
    {
        $now = microtime(true);
        $best = null;
        foreach ((array) @file('/proc/net/dev') as $l) {
            if (!preg_match('/^\s*([^:\s]+):\s*(\d+)(?:\s+\d+){7}\s+(\d+)/', $l, $m)) continue;
            if ($m[1] === 'lo') continue;
            $rx = (float) $m[2]; $tx = (float) $m[3];
            if ($best === null || $rx + $tx > $best[1] + $best[2]) $best = array($m[1], $rx, $tx);
        }
        if ($best === null) return null;
        $prev = isset($this->state['rede']) ? $this->state['rede'] : null;
        $this->state['rede'] = array($best[0], $best[1], $best[2], $now);
        $r = array('iface' => $best[0], 'rx' => null, 'tx' => null);
        if ($prev && $prev[0] === $best[0] && $now > $prev[3] && $best[1] >= $prev[1]) {
            $dt = $now - $prev[3];
            $r['rx'] = ($best[1] - $prev[1]) / $dt;
            $r['tx'] = ($best[2] - $prev[2]) / $dt;
        }
        return $r;
    }

    /* ------------------------------------------------------------ Avisos do PBX */

    public function avisos()
    {
        try {
            $pdo = $this->pbxDb();
            $rows = $pdo->query('SELECT module, id, level, display_text, timestamp FROM notifications ORDER BY level ASC, timestamp DESC LIMIT 8')->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return array('erro' => 'Não foi possível ler os avisos do PBX.', 'itens' => array());
        }
        $itens = array();
        foreach ($rows as $r) {
            $lvl = (int) $r['level'];
            // Níveis do IssabelPBX/FreePBX: 100 crítico, 200 segurança, 250 assinado,
            // 300 atualização, 400 erro, 500 aviso, 600 informação.
            if ($lvl <= 200 || $lvl === 400) $tipo = 'erro';
            elseif ($lvl === 300 || $lvl === 250) $tipo = 'atualizacao';
            elseif ($lvl === 500) $tipo = 'aviso';
            else $tipo = 'info';
            $itens[] = array(
                'tipo'  => $tipo,
                'texto' => (string) $r['display_text'],
                'quando'=> $r['timestamp'] ? (int) $r['timestamp'] : null,
            );
        }
        return array('erro' => null, 'itens' => $itens);
    }

    private function pbxDb()
    {
        $amp = array();
        foreach ((array) @file('/etc/amportal.conf') as $l) {
            $l = trim($l);
            if ($l === '' || $l[0] === '#' || strpos($l, '=') === false) continue;
            list($k, $v) = explode('=', $l, 2);
            $amp[trim($k)] = trim(trim($v), "\"'");
        }
        $host = !empty($amp['AMPDBHOST']) ? $amp['AMPDBHOST'] : 'localhost';
        $name = !empty($amp['AMPDBNAME']) ? $amp['AMPDBNAME'] : 'asterisk';
        $user = !empty($amp['AMPDBUSER']) ? $amp['AMPDBUSER'] : 'asteriskuser';
        $pass = isset($amp['AMPDBPASS']) ? $amp['AMPDBPASS'] : '';
        return new PDO("mysql:host=$host;dbname=$name;charset=utf8", $user, $pass,
            array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 3));
    }

    /* ------------------------------------------------------------ util */

    private function dur($s)
    {
        $s = max(0, (int) $s);
        if ($s < 60) return $s . 's';
        if ($s < 3600) return floor($s / 60) . 'min';
        return floor($s / 3600) . 'h' . str_pad(floor(($s % 3600) / 60), 2, '0', STR_PAD_LEFT);
    }
}
