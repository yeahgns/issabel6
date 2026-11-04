-- Bradial Voz: reorganização do menu do Issabel (/var/www/db/menu.db).
-- Só altera posição (IdParent), nome (Name), ordem (order_no) e, nas seções, o tipo.
-- Nenhum item é apagado: o que sai de vista fica no fim da sua seção e continua
-- controlado pelas permissões de grupo. Pode ser executado mais de uma vez.
BEGIN TRANSACTION;

-- Seções (1º nível)
UPDATE menu SET IdParent='', Type='', Name='Geral',       order_no=0 WHERE id='sysdash';
UPDATE menu SET Name='PBX',         order_no=1 WHERE id='pbxconfig';
UPDATE menu SET Name='Call center', order_no=2 WHERE id='call_center';
UPDATE menu SET Name='Relatórios',  order_no=3 WHERE id='reports';
UPDATE menu SET Name='Sistema',     order_no=4 WHERE id='system';
-- Demais seções vêm depois, na ordem original
UPDATE menu SET order_no=21 WHERE id='email_admin';
UPDATE menu SET order_no=22 WHERE id='agenda';
UPDATE menu SET order_no=23 WHERE id='fax';
UPDATE menu SET order_no=24 WHERE id='addons';
UPDATE menu SET order_no=25 WHERE id='my_extension';
UPDATE menu SET order_no=26 WHERE id='extras';
UPDATE menu SET order_no=27 WHERE id='security';
UPDATE menu SET order_no=28, Name='Desenvolvedor' WHERE id='developer';

-- Geral
UPDATE menu SET IdParent='sysdash', Name='Visão geral', order_no=1 WHERE id='dashboard';

-- PBX (os itens bv_* são criados pelo menu.xml; aqui só nome e ordem)
UPDATE menu SET IdParent='pbxconfig', Type='framed', Name='Ramais',           order_no=1, Link='/admin/config.php?display=extensions' WHERE id='bv_ramais';
UPDATE menu SET IdParent='pbxconfig', Type='framed', Name='Filas',            order_no=2, Link='/admin/config.php?display=queues'     WHERE id='bv_filas';
UPDATE menu SET IdParent='pbxconfig', Type='framed', Name='Troncos',          order_no=3, Link='/admin/config.php?display=trunks'     WHERE id='bv_troncos';
UPDATE menu SET IdParent='pbxconfig', Type='framed', Name='Rotas de entrada', order_no=4, Link='/admin/config.php?display=did'        WHERE id='bv_rotas_entrada';
UPDATE menu SET IdParent='pbxconfig', Type='framed', Name='Rotas de saída',   order_no=5, Link='/admin/config.php?display=routing'    WHERE id='bv_rotas_saida';
UPDATE menu SET Name='Configuração avançada', order_no=6 WHERE id='pbxadmin';
UPDATE menu SET IdParent='pbxconfig', Name='Ramais em lote', order_no=7 WHERE id='extensions_batch';
UPDATE menu SET Name='Gravações',          order_no=8 WHERE id='monitoring';
UPDATE menu SET IdParent='pbxconfig', Name='Painel do operador', order_no=9 WHERE id='painel_operador';
UPDATE menu SET order_no=51 WHERE id='voicemail';
UPDATE menu SET order_no=52 WHERE id='endpoints';
UPDATE menu SET order_no=53 WHERE id='conference';
UPDATE menu SET order_no=54 WHERE id='tools';
UPDATE menu SET order_no=55 WHERE id='fop2';
UPDATE menu SET order_no=56 WHERE id='endpoint_configurator';
UPDATE menu SET order_no=57 WHERE id='control_panel';
UPDATE menu SET order_no=58 WHERE id='avalia_o';

-- Call center
UPDATE menu SET IdParent='call_center', Name='Painel',            order_no=1 WHERE id='ccx_painel';
UPDATE menu SET IdParent='call_center', Name='Console do agente', order_no=2 WHERE id='agent_console';
UPDATE menu SET IdParent='call_center', Name='Campanhas',         order_no=3 WHERE id='ccx_campanhas';
UPDATE menu SET IdParent='call_center', Name='Formulários',       order_no=4 WHERE id='ccx_formularios';
UPDATE menu SET IdParent='call_center', Name='Agentes',           order_no=5 WHERE id='ccx_agentes';
UPDATE menu SET IdParent='call_center', Name='Pausas',            order_no=6 WHERE id='ccx_pausas';
UPDATE menu SET order_no=51 WHERE id='outgoing_calls';
UPDATE menu SET order_no=52 WHERE id='ingoing_calls';
UPDATE menu SET order_no=53 WHERE id='agentoptions';
UPDATE menu SET order_no=54 WHERE id='break_administrator';
UPDATE menu SET order_no=55 WHERE id='forms';
UPDATE menu SET order_no=56 WHERE id='reports_ingoing_call';
UPDATE menu SET order_no=57 WHERE id='callcenter_config';

-- Relatórios: um só item visível, "Registro de chamadas" (relatório de ligações v2)
UPDATE menu SET IdParent='reports', Name='Registro de chamadas', order_no=1 WHERE id='registro_de_chamadas';
UPDATE menu SET order_no=51 WHERE id='cdrreport';
UPDATE menu SET order_no=52 WHERE id='channelusage';
UPDATE menu SET order_no=53 WHERE id='billing';
UPDATE menu SET order_no=54 WHERE id='asterisk_log';
UPDATE menu SET order_no=55 WHERE id='graphic_report';
UPDATE menu SET order_no=56 WHERE id='summary_by_extension';
UPDATE menu SET order_no=57 WHERE id='missed_calls';
UPDATE menu SET order_no=58 WHERE id='relatorio';
UPDATE menu SET order_no=59 WHERE id='teste';

-- Sistema
UPDATE menu SET IdParent='system', Name='Usuários', order_no=1 WHERE id='usermgr';
UPDATE menu SET Name='Usuários',   order_no=1 WHERE id='userlist';
UPDATE menu SET Name='Grupos',     order_no=2 WHERE id='grouplist';
UPDATE menu SET Name='Permissões', order_no=3 WHERE id='group_permission';

UPDATE menu SET IdParent='system', Name='Rede', order_no=2 WHERE id='network';
UPDATE menu SET Name='Parâmetros de rede', order_no=1 WHERE id='network_parameters';
UPDATE menu SET Name='Servidor DHCP',      order_no=2 WHERE id='dhcp_server';
UPDATE menu SET Name='Clientes DHCP',      order_no=3 WHERE id='dhcp_clientlist';
UPDATE menu SET Name='IP fixo por MAC',    order_no=4 WHERE id='dhcp_by_mac';
UPDATE menu SET Name='Ping e traceroute',  order_no=5 WHERE id='ping';
UPDATE menu SET Name='Captura de pacotes', order_no=6 WHERE id='packet_capture';
UPDATE menu SET Name='UPnP',               order_no=7 WHERE id='upnpc';

UPDATE menu SET IdParent='system', Name='Segurança', order_no=3 WHERE id='fail2ban';
UPDATE menu SET IdParent='fail2ban', Name='Fail2ban',       order_no=1 WHERE id='sec_fb_admin';
UPDATE menu SET IdParent='fail2ban', Name='IPs bloqueados', order_no=2 WHERE id='sec_fb_banned';

UPDATE menu SET IdParent='system', Name='Backup e restauração', order_no=4 WHERE id='backup_restore';

UPDATE menu SET IdParent='system', Name='Preferências', order_no=5 WHERE id='preferences';
UPDATE menu SET Name='Idioma',      order_no=1 WHERE id='language';
UPDATE menu SET Name='Data e hora', order_no=2 WHERE id='time_config';
UPDATE menu SET Name='Moeda',       order_no=3 WHERE id='currency';
UPDATE menu SET IdParent='preferences', Name='Widgets do painel', order_no=4 WHERE id='applet_admin';
UPDATE menu SET Name='Temas',       order_no=99 WHERE id='themes_system';

UPDATE menu SET IdParent='system', Name='Reiniciar ou desligar', order_no=6 WHERE id='shutdown';
UPDATE menu SET order_no=91 WHERE id='hardware_detector';
UPDATE menu SET order_no=92 WHERE id='betachannel';
UPDATE menu SET order_no=93 WHERE id='theme_designer';

COMMIT;
