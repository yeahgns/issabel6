-- Bradial Voz: reorganização do menu do Issabel (/var/www/db/menu.db).
-- Só altera posição (IdParent), nome (Name), ordem (order_no) e, nas seções, o tipo.
-- Nenhum item é apagado: o que sai de vista fica no fim da sua seção e continua
-- controlado pelas permissões de grupo. Pode ser executado mais de uma vez.
BEGIN TRANSACTION;

-- Seções (1º nível)
UPDATE menu SET IdParent='', Type='', Name='Operação',    order_no=0 WHERE id='sysdash';
UPDATE menu SET Name='PBX',         order_no=1 WHERE id='pbxconfig';
UPDATE menu SET Name='Relatórios',  order_no=20 WHERE id='reports';
UPDATE menu SET Name='Call center', order_no=2 WHERE id='call_center';
UPDATE menu SET Name='Sistema',     order_no=3 WHERE id='system';
-- Demais seções vêm depois, na ordem original
UPDATE menu SET order_no=21 WHERE id='email_admin';
UPDATE menu SET order_no=22 WHERE id='agenda';
UPDATE menu SET order_no=23 WHERE id='fax';
UPDATE menu SET order_no=24 WHERE id='addons';
UPDATE menu SET order_no=25 WHERE id='my_extension';
UPDATE menu SET order_no=26 WHERE id='extras';
UPDATE menu SET order_no=27 WHERE id='security';
-- Desenvolvedor vira o grupo "Avançado", dentro de Sistema (mais abaixo)

-- Operação: tudo o que se acompanha no dia a dia, no topo. A Visão geral do tema
-- (bv_visao_geral, criada pelo menu.xml) é a tela inicial.
UPDATE menu SET IdParent='sysdash', Type='module', Name='Visão geral', order_no=1 WHERE id='bv_visao_geral';
UPDATE menu SET IdParent='sysdash', Name='Painel do operador',    order_no=2 WHERE id='painel_operador';
UPDATE menu SET IdParent='sysdash', Name='Painel do call center', order_no=3 WHERE id='ccx_painel';
UPDATE menu SET IdParent='sysdash', Name='Console do agente',     order_no=4 WHERE id='agent_console';
UPDATE menu SET IdParent='sysdash', Name='Registro de chamadas',  order_no=5 WHERE id='registro_de_chamadas';

-- PBX: os atalhos para as telas do IssabelPBX, direto na seção (sem grupo). "Configurações
-- do PBX" (o /admin inteiro) e "Ramais em lote" saem do menu lateral do tema
-- (bradialHiddenMenus), mas continuam acessíveis no tema v5.
-- Os grupos da 1.5.0 (bv_pbx_config, bv_cc_config) e o grupo Módulos (bv_modulos) saem.
DELETE FROM menu WHERE id IN ('bv_ramais','bv_filas','bv_troncos','bv_rotas_entrada','bv_rotas_saida');
UPDATE menu SET IdParent='pbxconfig', Type='framed', Link='/admin/config.php?display=extensions', Name='Ramais', order_no=1 WHERE id='bv_pbx_ramais';
UPDATE menu SET IdParent='pbxconfig', Type='framed', Link='/admin/config.php?display=queues', Name='Filas', order_no=2 WHERE id='bv_pbx_filas';
UPDATE menu SET IdParent='pbxconfig', Type='framed', Link='/admin/config.php?display=ivr', Name='URA', order_no=3 WHERE id='bv_pbx_ura';
UPDATE menu SET IdParent='pbxconfig', Type='framed', Link='/admin/config.php?display=trunks', Name='Troncos', order_no=4 WHERE id='bv_pbx_troncos';
UPDATE menu SET IdParent='pbxconfig', Type='framed', Link='/admin/config.php?display=did', Name='Rotas', order_no=5 WHERE id='bv_pbx_rotas';
UPDATE menu SET IdParent='pbxconfig', Type='framed', Link='/admin/config.php?display=announcement', Name='Anúncios', order_no=6 WHERE id='bv_pbx_anuncios';
UPDATE menu SET IdParent='pbxconfig', Type='framed', Link='/admin/config.php?display=timeconditions', Name='Condições de tempo', order_no=7 WHERE id='bv_pbx_tempo';
UPDATE menu SET IdParent='pbxconfig', Type='framed', Link='/admin/config.php?display=conferences', Name='Conferências', order_no=8 WHERE id='bv_pbx_conferencias';
UPDATE menu SET IdParent='pbxconfig', Type='framed', Link='/admin/config.php?display=parking', Name='Estacionamento', order_no=9 WHERE id='bv_pbx_estacionamento';
UPDATE menu SET IdParent='pbxconfig', Type='framed', Link='/admin/config.php?display=blacklist', Name='Lista de bloqueio', order_no=10 WHERE id='bv_pbx_blacklist';
UPDATE menu SET Name='Configurações do PBX', order_no=50 WHERE id='pbxadmin';
UPDATE menu SET IdParent='pbxconfig', Name='Ramais em lote', order_no=59 WHERE id='extensions_batch';
UPDATE menu SET Name='Gravações', order_no=60 WHERE id='monitoring';
UPDATE menu SET order_no=51 WHERE id='voicemail';
UPDATE menu SET order_no=52 WHERE id='endpoints';
UPDATE menu SET order_no=53 WHERE id='conference';
UPDATE menu SET order_no=54 WHERE id='tools';
UPDATE menu SET order_no=55 WHERE id='fop2';
UPDATE menu SET order_no=56 WHERE id='endpoint_configurator';
UPDATE menu SET order_no=57 WHERE id='control_panel';
UPDATE menu SET order_no=58 WHERE id='avalia_o';

-- Call center
-- Call center: só as configurações (Painel e Console do agente foram para Operação)
UPDATE menu SET IdParent='call_center', Name='Campanhas',   order_no=1 WHERE id='ccx_campanhas';
UPDATE menu SET IdParent='call_center', Name='Formulários', order_no=2 WHERE id='ccx_formularios';
UPDATE menu SET IdParent='call_center', Name='Agentes',     order_no=3 WHERE id='ccx_agentes';
UPDATE menu SET IdParent='call_center', Name='Pausas',      order_no=4 WHERE id='ccx_pausas';
UPDATE menu SET order_no=51 WHERE id='outgoing_calls';
UPDATE menu SET order_no=52 WHERE id='ingoing_calls';
UPDATE menu SET order_no=53 WHERE id='agentoptions';
UPDATE menu SET order_no=54 WHERE id='break_administrator';
UPDATE menu SET order_no=55 WHERE id='forms';
UPDATE menu SET order_no=56 WHERE id='reports_ingoing_call';
UPDATE menu SET order_no=57 WHERE id='callcenter_config';

-- Relatórios: um só item visível, "Registro de chamadas" (relatório de ligações v2)
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


-- Preferências: Backup e restauração, Reiniciar ou desligar, Idioma, Data e hora, Temas.
-- Reiniciar ou desligar é a tela do tema (bv_energia); o Shutdown nativo sai do menu lateral.
UPDATE menu SET IdParent='system', Name='Preferências', order_no=4 WHERE id='preferences';
UPDATE menu SET IdParent='preferences', Name='Backup e restauração', order_no=1 WHERE id='backup_restore';
UPDATE menu SET IdParent='preferences', Type='module', Name='Reiniciar ou desligar', order_no=2 WHERE id='bv_energia';
UPDATE menu SET Name='Idioma',      order_no=3 WHERE id='language';
UPDATE menu SET Name='Data e hora', order_no=4 WHERE id='time_config';
UPDATE menu SET Name='Temas',       order_no=5 WHERE id='themes_system';
UPDATE menu SET Name='Moeda',       order_no=98 WHERE id='currency';
UPDATE menu SET IdParent='preferences', Name='Widgets do painel', order_no=99 WHERE id='applet_admin';
UPDATE menu SET IdParent='system', Name='Shutdown (Issabel)', order_no=94 WHERE id='shutdown';
UPDATE menu SET order_no=91 WHERE id='hardware_detector';
UPDATE menu SET order_no=92 WHERE id='betachannel';
UPDATE menu SET order_no=93 WHERE id='theme_designer';

-- Sistema > Avançado: o antigo Desenvolvedor vira grupo dentro de Sistema, com o
-- Dashboard do Issabel e as ferramentas de módulo (sem subgrupo: o menu tem 3 níveis).
UPDATE menu SET IdParent='system', Type='module', Link='', Name='Avançado', order_no=5 WHERE id='developer';
UPDATE menu SET IdParent='developer', Name='Dashboard do Issabel', order_no=1 WHERE id='dashboard';
UPDATE menu SET IdParent='developer', Name='Construtor de módulo', order_no=2 WHERE id='build_module';
UPDATE menu SET IdParent='developer', Name='Remover módulo',       order_no=3 WHERE id='delete_module';
UPDATE menu SET IdParent='developer', Name='Traduções',            order_no=9 WHERE id='language_admin';

-- Grupos que existiram em versões anteriores do tema
DELETE FROM menu WHERE id IN ('bv_pbx_config', 'bv_cc_config', 'bv_modulos');

COMMIT;
