<?php
/* Tema Bradial Voz para Issabel 5.
 * Baseado no themesetup.php do tema tenant (Issabel Foundation, GPL v2).
 * Diferenças: ícones Lucide por item de menu, sem reordenar as seções do 1º nível
 * (a barra lateral é vertical, a ordem vem do menu.db) e dados do usuário para o rodapé
 * da barra lateral. */

function themeSetup(&$smarty, $selectedMenu, $pdbACL, $pACL, $idUser)
{
    $lang = get_language();
    $arrMainMenu = $smarty->get_template_vars('arrMainMenu');
    if (!is_array($arrMainMenu)) $arrMainMenu = array();

    $L = bradialLang($lang);
    $arrMainMenu = bradialTranslateMenu($arrMainMenu, $L);
    $fullMenu = $arrMainMenu;
    $arrMainMenu = bradialPruneMenu($arrMainMenu);
    foreach ($arrMainMenu as $idMenu => $menu) {
        $arrMainMenu[$idMenu]['bicon'] = bradialIcon($idMenu);
        if (!empty($menu['children']) && is_array($menu['children'])) {
            foreach ($menu['children'] as $idSub => $sub) {
                $arrMainMenu[$idMenu]['children'][$idSub]['bicon'] = bradialIcon($idSub);
            }
        }
    }

    $smarty->assign('arrMainMenu', $arrMainMenu);
    $smarty->assign('LANG', $lang);
    $extension = $pACL->getUserExtension($_SESSION['issabel_user']);
    $user = isset($_SESSION['issabel_user']) ? $_SESSION['issabel_user'] : '';
    $smarty->assign(array(
        'ABOUT_ISSABEL2'     => _tr('About Issabel'),
        'HELP'               => _tr('HELP'),
        'USER_LOGIN'         => $user,
        'USER_INITIALS'      => bradialInitials($user),
        'SERVER_NAME'        => isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : '',
        'USER_ID'            => $idUser,
        'EXTENSION'          => $extension,
        'CHANGE_PASSWORD'    => _tr('Change Issabel Password'),
        'MODULES_SEARCH'     => _tr('Search modules'),
        'ADD_BOOKMARK'       => _tr('Add Bookmark'),
        'REMOVE_BOOKMARK'    => _tr('Remove Bookmark'),
        'ADDING_BOOKMARK'    => _tr('Adding Bookmark'),
        'REMOVING_BOOKMARK'  => _tr('Removing Bookmark'),
        'HIDING_IZQTAB'      => _tr('Hiding left panel'),
        'SHOWING_IZQTAB'     => _tr('Loading left panel'),
        'HIDE_IZQTAB'        => _tr('Hide left panel'),
        'SHOW_IZQTAB'        => _tr('Load left panel'),
        'viewMenuTab'        => getStatusNeoTabToggle($pdbACL, $idUser),
        'MENU_COLOR'         => getMenuColorByMenu($pdbACL, $idUser),
        'IMG_BOOKMARKS'      => menuIsBookmark($pdbACL, $idUser, $selectedMenu) ? 'bookmarkon.png' : 'bookmark.png',
        'SHORTCUT'           => loadShortcut($pdbACL, $idUser, $smarty),
        'BREADCRUMB'         => bradialBreadcrumb($fullMenu, $selectedMenu),
        // Módulos com visual próprio: não recebem o CSS das telas nativas
        'BV_CUSTOM_MODULE'   => bradialIsCustomModule($selectedMenu),
        // Cabeçalho das telas nativas (título e descrição, como no protótipo)
        'BV_PAGE_HEAD'       => !bradialIsCustomModule($selectedMenu) && bradialMenuType($fullMenu, $selectedMenu) !== 'framed',
        'BV_PAGE_TITLE'      => bradialPageTitle($fullMenu, $selectedMenu),
        'BV_PAGE_DESC'       => bradialPageDesc($selectedMenu, $L),
        'BV_LANG'            => $L,
        'BVT'                => bradialStrings($L),
        'BV_MODULE'          => (string)$selectedMenu,
    ));
}

/* Remove do menu os grupos que ficaram vazios. Um grupo (ex.: "Batch Configurations")
 * aparece quando o usuário tem permissão no próprio grupo mas em nenhum filho visível;
 * como não há módulo com esse nome, clicar nele dá "module could not be found".
 * Regra: item do tipo módulo, sem filhos e sem pasta em modules/ sai do menu.
 * Seções de 1º nível que ficam sem itens também saem. */
function bradialPruneMenu($menu, $depth = 1)
{
    if (!is_array($menu)) return array();
    foreach ($menu as $id => $item) {
        $kids = (!empty($item['children']) && is_array($item['children'])) ? $item['children'] : array();
        if ($kids) {
            $kids = bradialPruneMenu($kids, $depth + 1);
            $menu[$id]['children'] = $kids;
            $menu[$id]['HasChild'] = count($kids) > 0;
        }
        $type = isset($item['Type']) ? $item['Type'] : '';
        $isGroupShell = !$kids && ($type === 'module' || $type === '')
            && !is_file('modules/' . basename((string)$id) . '/index.php');
        if ($isGroupShell || in_array((string)$id, bradialHiddenMenus(), true)) unset($menu[$id]);
    }
    return $menu;
}

/* Itens que não aparecem no menu lateral. Continuam instalados e acessíveis pelas
 * permissões de grupo; só saem de vista. */
function bradialHiddenMenus()
{
    return array(
        'packet_capture',  // Rede > Captura de pacotes
        'upnpc',           // Rede > UPnP
        'applet_admin',    // Preferências > Widgets do painel (dashboard antigo)
        'shutdown',        // substituído por bv_energia (Reiniciar ou desligar)
        'currency',        // Preferências > Moeda
        'monitoring',      // PBX > Gravações
        'pbxadmin',        // Configurações do PBX (o /admin inteiro): segue acessível no tema v5
        'extensions_batch',// Ramais em lote: vai para dentro da tela de Ramais
        'language_admin',  // Avançado > Traduções
        'cdrreport',       // um único relatório de chamadas: o Registro de chamadas
    );
}

function bradialFindItem($menu, $id)
{
    foreach ((array)$menu as $k => $v) {
        if ((string)$k === (string)$id) return $v;
        if (!empty($v['children'])) {
            $r = bradialFindItem($v['children'], $id);
            if ($r !== null) return $r;
        }
    }
    return null;
}

function bradialMenuType($menu, $id)
{
    $it = bradialFindItem($menu, $id);
    return ($it && isset($it['Type'])) ? $it['Type'] : '';
}

function bradialPageTitle($menu, $id)
{
    $it = bradialFindItem($menu, $id);
    return ($it && isset($it['Name'])) ? $it['Name'] : '';
}

/* Idioma da interface: "pt" para português (br, pt, pt-br) e "en" para o resto.
 * Segue o que está em Sistema > Preferências > Idioma. */
function bradialLang($lang)
{
    $l = strtolower((string)$lang);
    return (strpos($l, 'br') === 0 || strpos($l, 'pt') === 0) ? 'pt' : 'en';
}

/* Textos da casca (barra lateral e barra superior) */
function bradialStrings($L)
{
    $s = array(
        'search'   => array('pt' => 'Buscar módulo', 'en' => 'Search module'),
        'toggle'   => array('pt' => 'Recolher ou expandir o menu', 'en' => 'Collapse or expand the menu'),
        'logout'   => array('pt' => 'Sair', 'en' => 'Log out'),
        'crumb'    => array('pt' => 'Você está em', 'en' => 'You are here'),
        'menu'     => array('pt' => 'Menu', 'en' => 'Menu'),
        'close'    => array('pt' => 'Fechar', 'en' => 'Close'),
    );
    $out = array();
    foreach ($s as $k => $v) $out[$k] = $v[$L];
    return $out;
}

/* Nomes do menu nos dois idiomas. Itens fora da lista ficam com a tradução do próprio
 * Issabel. Assim o menu acompanha o idioma escolhido, em vez de misturar os dois. */
function bradialMenuNames()
{
    return array(
        // seções
        'sysdash' => array('Operação', 'Operations'), 'pbxconfig' => array('PBX', 'PBX'),
        'reports' => array('Relatórios', 'Reports'), 'call_center' => array('Call center', 'Call center'),
        'system' => array('Sistema', 'System'), 'developer' => array('Avançado', 'Advanced'),
        // Geral e PBX
        'bv_visao_geral' => array('Visão geral', 'Overview'),
        'pbxadmin' => array('Configurações do PBX', 'PBX settings'),
        'bv_pbx_config' => array('Configurações', 'Settings'),
        'bv_pbx_ramais' => array('Ramais', 'Extensions'), 'bv_pbx_filas' => array('Filas', 'Queues'),
        'bv_pbx_ura' => array('URA', 'IVR'), 'bv_pbx_troncos' => array('Troncos', 'Trunks'),
        'bv_pbx_rotas' => array('Rotas', 'Routes'), 'bv_pbx_anuncios' => array('Anúncios', 'Announcements'),
        'bv_pbx_tempo' => array('Condições de tempo', 'Time conditions'),
        'bv_pbx_conferencias' => array('Conferências', 'Conferences'),
        'bv_pbx_estacionamento' => array('Estacionamento', 'Parking'),
        'bv_pbx_blacklist' => array('Lista de bloqueio', 'Blacklist'),
        'bv_cc_config' => array('Configurações', 'Settings'),
        'extensions_batch' => array('Ramais em lote', 'Batch extensions'),
        'monitoring' => array('Gravações', 'Recordings'),
        'painel_operador' => array('Painel do operador', 'Operator panel'),
        // Relatórios
        'registro_de_chamadas' => array('Registro de chamadas', 'Call log'),
        'cdrreport' => array('Relatório CDR', 'CDR report'),
        // Call center
        'ccx_painel' => array('Painel do call center', 'Call center dashboard'), 'agent_console' => array('Console do agente', 'Agent console'),
        'ccx_campanhas' => array('Campanhas', 'Campaigns'), 'ccx_formularios' => array('Formulários', 'Forms'),
        'ccx_agentes' => array('Agentes', 'Agents'), 'ccx_pausas' => array('Pausas', 'Breaks'),
        // Sistema
        'usermgr' => array('Usuários', 'Users'), 'userlist' => array('Usuários', 'Users'),
        'grouplist' => array('Grupos', 'Groups'), 'group_permission' => array('Permissões', 'Permissions'),
        'network' => array('Rede', 'Network'), 'network_parameters' => array('Parâmetros de rede', 'Network parameters'),
        'dhcp_server' => array('Servidor DHCP', 'DHCP server'), 'dhcp_clientlist' => array('Clientes DHCP', 'DHCP clients'),
        'dhcp_by_mac' => array('IP fixo por MAC', 'Static IP by MAC'), 'ping' => array('Ping e traceroute', 'Ping and traceroute'),
        'fail2ban' => array('Segurança', 'Security'), 'sec_fb_admin' => array('Fail2ban', 'Fail2ban'),
        'sec_fb_banned' => array('IPs bloqueados', 'Banned IPs'),
        'backup_restore' => array('Backup e restauração', 'Backup and restore'),
        'preferences' => array('Preferências', 'Preferences'), 'language' => array('Idioma', 'Language'),
        'time_config' => array('Data e hora', 'Date and time'), 'currency' => array('Moeda', 'Currency'),
        'themes_system' => array('Temas', 'Themes'),
        'bv_energia' => array('Reiniciar ou desligar', 'Restart or shut down'),
        'dashboard' => array('Dashboard do Issabel', 'Issabel dashboard'),
        // Desenvolvedor
        'bv_modulos' => array('Módulos', 'Modules'),
        'build_module' => array('Construtor de módulo', 'Module builder'),
        'delete_module' => array('Remover módulo', 'Delete module'),
        'language_admin' => array('Traduções', 'Translations'),
    );
}

function bradialTranslateMenu($menu, $L)
{
    static $names = null;
    if ($names === null) $names = bradialMenuNames();
    $i = $L === 'pt' ? 0 : 1;
    foreach ((array)$menu as $id => $item) {
        if (isset($names[$id])) $menu[$id]['Name'] = $names[$id][$i];
        if (!empty($item['children'])) $menu[$id]['children'] = bradialTranslateMenu($item['children'], $L);
    }
    return $menu;
}

/* Uma frase sob o título de cada tela nativa, no tom do protótipo. */
function bradialPageDesc($id, $L = 'pt')
{
    $d = array(
        'userlist'           => array('Quem pode entrar no Issabel, com qual grupo e qual ramal.', 'Who can sign in to Issabel, with which group and extension.'),
        'grouplist'          => array('Grupos reúnem usuários com as mesmas permissões.', 'Groups bring together users with the same permissions.'),
        'group_permission'   => array('O que cada grupo pode ver e usar no menu.', 'What each group can see and use in the menu.'),
        'network_parameters' => array('Nome do servidor, gateway, DNS e interfaces de rede.', 'Server name, gateway, DNS and network interfaces.'),
        'dhcp_server'        => array('Servidor DHCP para os aparelhos da rede local.', 'DHCP server for devices on the local network.'),
        'dhcp_clientlist'    => array('Aparelhos que receberam endereço deste servidor DHCP.', 'Devices that got an address from this DHCP server.'),
        'dhcp_by_mac'        => array('Endereço IP fixo para um aparelho, a partir do MAC.', 'A fixed IP address for a device, by its MAC.'),
        'ping'               => array('Teste de conexão com outro endereço da rede ou da internet.', 'Connectivity test to another address on the network or the internet.'),
        'sec_fb_admin'       => array('O Fail2ban bloqueia IPs após tentativas de acesso com falha.', 'Fail2ban blocks IPs after failed access attempts.'),
        'sec_fb_banned'      => array('Endereços bloqueados agora pelo Fail2ban.', 'Addresses currently blocked by Fail2ban.'),
        'backup_restore'     => array('Cópias de segurança da configuração, com restauração e envio por FTP.', 'Configuration backups, with restore and FTP upload.'),
        'language'           => array('Idioma da interface.', 'Interface language.'),
        'time_config'        => array('Data, hora e fuso horário do servidor.', 'Server date, time and time zone.'),
        'themes_system'      => array('Aparência da interface.', 'Interface appearance.'),
        'extensions_batch'   => array('Crie ou altere vários ramais de uma vez, por planilha CSV.', 'Create or change many extensions at once, from a CSV file.'),
        'cdrreport'          => array('Cada ligação registrada pelo Asterisk.', 'Every call recorded by Asterisk.'),
        'dashboard'          => array('Painel original do Issabel.', 'The original Issabel dashboard.'),
        'build_module'       => array('Crie um item de menu: um formulário, uma listagem ou uma página externa.', 'Create a menu item: a form, a list or an external page.'),
        'delete_module'      => array('Remova um item de menu e, se quiser, os arquivos dele.', 'Remove a menu item and, optionally, its files.'),
    );
    return isset($d[$id]) ? $d[$id][$L === 'pt' ? 0 : 1] : '';
}

/* Ícones Lucide (nomes do sprite em assets/icons/sprite.svg) */
function bradialIcon($id)
{
    $map = array(
        // Geral
        'sysdash' => 'layout-dashboard', 'bv_visao_geral' => 'layout-dashboard', 'dashboard' => 'gauge',
        // PBX
        'bv_ramais' => 'phone', 'bv_filas' => 'users', 'bv_troncos' => 'cable',
        'bv_rotas_entrada' => 'phone-incoming', 'bv_rotas_saida' => 'phone-outgoing',
        'pbxadmin' => 'sliders-horizontal',
        'bv_pbx_ramais' => 'phone', 'bv_pbx_filas' => 'list-ordered', 'bv_pbx_ura' => 'workflow',
        'bv_pbx_troncos' => 'cable', 'bv_pbx_rotas' => 'route', 'bv_pbx_anuncios' => 'volume-2',
        'bv_pbx_tempo' => 'clock', 'bv_pbx_conferencias' => 'users', 'bv_pbx_estacionamento' => 'circle-parking',
        'bv_pbx_blacklist' => 'ban', 'extensions_batch' => 'list-plus',
        'monitoring' => 'audio-lines', 'painel_operador' => 'monitor',
        'voicemail' => 'mail', 'conference' => 'users-round', 'tools' => 'wrench',
        'endpoints' => 'list-plus', 'endpoint_configurator' => 'cpu', 'control_panel' => 'monitor',
        'fop2' => 'monitor',
        // Call center
        'ccx_painel' => 'headset', 'agent_console' => 'square-user', 'ccx_campanhas' => 'megaphone',
        'ccx_formularios' => 'clipboard-list', 'ccx_agentes' => 'users-round', 'ccx_pausas' => 'circle-pause',
        'outgoing_calls' => 'phone-outgoing', 'ingoing_calls' => 'phone-incoming', 'agentoptions' => 'users-round',
        'break_administrator' => 'circle-pause', 'forms' => 'clipboard-list',
        'reports_ingoing_call' => 'chart-column', 'callcenter_config' => 'settings',
        // Relatórios
        'registro_de_chamadas' => 'chart-column', 'cdrreport' => 'file-text', 'channelusage' => 'chart-column',
        'billing' => 'file-text', 'asterisk_log' => 'file-text', 'graphic_report' => 'chart-column',
        'summary_by_extension' => 'file-text', 'missed_calls' => 'phone-incoming',
        // Sistema
        'usermgr' => 'user-cog', 'network' => 'network', 'fail2ban' => 'shield',
        'backup_restore' => 'database-backup', 'preferences' => 'settings', 'shutdown' => 'power', 'bv_energia' => 'power',
        'hardware_detector' => 'cpu', 'betachannel' => 'flask-conical', 'theme_designer' => 'settings',
        // Outras seções
        'security' => 'lock', 'sec_firewall' => 'shield', 'developer' => 'wrench', 'build_module' => 'code', 'bv_modulos' => 'package', 'delete_module' => 'x', 'language_admin' => 'globe',
        'email_admin' => 'mail', 'agenda' => 'book-user', 'fax' => 'printer', 'addons' => 'puzzle',
        'addons_availables' => 'package', 'my_extension' => 'phone', 'extras' => 'puzzle',
    );
    return isset($map[$id]) ? $map[$id] : 'dot';
}

function bradialIsCustomModule($id)
{
    if (strpos((string)$id, 'ccx_') === 0 || strpos((string)$id, 'bv_') === 0) return TRUE;
    return in_array($id, array('painel_operador', 'agent_console'));
}

function bradialInitials($user)
{
    $u = preg_replace('/[^A-Za-z0-9]/', '', (string)$user);
    return strtoupper(substr($u === '' ? '?' : $u, 0, 2));
}

/* Caminho do item atual: [seção, item, subitem] */
function bradialBreadcrumb($arrMainMenu, $currentMenu)
{
    foreach ($arrMainMenu as $key => $value) {
        if ($key == $currentMenu) return array($value['Name']);
        if (empty($value['children']) || !is_array($value['children'])) continue;
        foreach ($value['children'] as $akey => $a) {
            if ($akey == $currentMenu) return array($value['Name'], $a['Name']);
            if (!empty($a['children']) && is_array($a['children'])) {
                foreach ($a['children'] as $bkey => $b) {
                    if ($bkey == $currentMenu) return array($value['Name'], $a['Name'], $b['Name']);
                }
            }
        }
    }
    return array();
}

?>
