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
        'BREADCRUMB'         => bradialBreadcrumb($arrMainMenu, $selectedMenu),
        'NOTIFICATIONS'      => bradialNotifications($pdbACL, $idUser),
        // Módulos com visual próprio: não recebem o CSS das telas nativas
        'BV_CUSTOM_MODULE'   => bradialIsCustomModule($selectedMenu),
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
        if ($isGroupShell) unset($menu[$id]);
    }
    return $menu;
}

/* Ícones Lucide (nomes do sprite em assets/icons/sprite.svg) */
function bradialIcon($id)
{
    $map = array(
        // Geral
        'sysdash' => 'layout-dashboard', 'dashboard' => 'layout-dashboard',
        // PBX
        'bv_ramais' => 'phone', 'bv_filas' => 'users', 'bv_troncos' => 'cable',
        'bv_rotas_entrada' => 'phone-incoming', 'bv_rotas_saida' => 'phone-outgoing',
        'pbxadmin' => 'sliders-horizontal', 'extensions_batch' => 'list-plus',
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
        'backup_restore' => 'database-backup', 'preferences' => 'settings', 'shutdown' => 'power',
        'hardware_detector' => 'cpu', 'betachannel' => 'flask-conical', 'theme_designer' => 'settings',
        // Outras seções
        'security' => 'lock', 'sec_firewall' => 'shield', 'developer' => 'code', 'build_module' => 'code',
        'email_admin' => 'mail', 'agenda' => 'book-user', 'fax' => 'printer', 'addons' => 'puzzle',
        'addons_availables' => 'package', 'my_extension' => 'phone', 'extras' => 'puzzle',
    );
    return isset($map[$id]) ? $map[$id] : 'dot';
}

function bradialIsCustomModule($id)
{
    if (strpos((string)$id, 'ccx_') === 0) return TRUE;
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

function bradialNotifications($pdbACL, $idUser)
{
    require_once 'libs/paloSantoNotification.class.php';
    $pNot = new paloNotification($pdbACL);
    $pub = $pNot->listPublicNotifications(5);
    $priv = $pNot->listUserNotifications($idUser, 5);
    $pub = is_array($pub) ? $pub : array();
    $priv = is_array($priv) ? $priv : array();
    return array(
        'LBL_NOTIFICATION_SYSTEM' => _tr('System'),
        'LBL_NOTIFICATION_USER'   => _tr('User'),
        'NOTIFICATIONS_PUBLIC'    => $pub,
        'NOTIFICATIONS_PRIVATE'   => $priv,
        'TXT_NO_NOTIFICATIONS'    => _tr('No notifications'),
        'COUNT'                   => count($pub) + count($priv),
    );
}
?>
