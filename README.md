# issabel6: tema Bradial Voz para Issabel 5

Fase 1 da interface Bradial Voz: o Issabel atual com o visual do design system da
Bradial e o menu organizado como os admins usam. Não altera nenhum módulo, nenhum
arquivo de pacote RPM e nenhuma configuração de telefonia.

## O que muda

- **Tema próprio** em `themes/bradial`, criado a partir do `tenant`. Por estar fora do
  pacote `issabel-framework`, atualizações do Issabel não o sobrescrevem.
- **Login** no estilo do protótipo, com aviso claro de usuário ou senha incorretos.
- **Barra lateral clara**, com seções, ícones, grupos recolhíveis e busca de módulos;
  usuário, troca de senha e saída no rodapé. No celular, vira uma gaveta.
- **Barra superior** com o caminho da tela e o botão que recolhe o menu lateral (só ícones;
  a escolha fica salva no navegador).
- **Visão geral própria** (módulo `bv_visao_geral`) como tela inicial: telefonia agora,
  recursos do servidor, avisos do PBX e o estado de Telefonia, Banco de dados, Servidor web
  e Call center. Os números de telefonia vêm do Painel do Operador,
  quando instalado; sem ele, a tela mostra só o que o CLI do Asterisk informa.
- **Telas nativas** (listagens, formulários, botões, painéis, diálogos) com as cores,
  a tipografia e os cantos do design system.
- **Módulos próprios** (Call Center `ccx_*`, Painel do Operador, Console do agente)
  ficam de fora do CSS das telas nativas e mantêm o visual atual.
- **IssabelPBX (/admin)** com CSS, logo, título e favicon da Bradial, pelas
  configurações `BRAND_*` (banco e `/etc/amportal.conf`).
- **Fonte e ícones locais** (Inter e Lucide). Nada depende de internet.

## Menu

```
Geral        Visão geral
PBX          Configurações do PBX · Ramais em lote · Painel do operador
Relatórios   Registro de chamadas
Call center  Painel · Console do agente · Campanhas · Formulários · Agentes · Pausas
Sistema      Usuários ▸ · Rede ▸ · Segurança ▸ · Backup e restauração ·
             Preferências ▸ · Reiniciar ou desligar · Dashboard do Issabel
Desenvolvedor  Módulos ▸ (Construtor de módulo · Remover módulo)

Os nomes acompanham o idioma escolhido em Sistema > Preferências > Idioma (português ou
inglês); a lista fica em `bradialMenuNames()`, no `themesetup.php`.
```

Configurações do PBX abre o IssabelPBX inteiro dentro do layout, com a navegação
própria dele. Alguns itens nativos saem só do menu lateral (lista em `bradialHiddenMenus()`,
no `themesetup.php`): Captura de pacotes, UPnP, Widgets do painel, Moeda, Gravações e o Shutdown original.
Nenhum item nativo é apagado: o que não está na lista acima vai para o fim da sua
seção e continua controlado pelas permissões de grupo.

## Instalação

No servidor, como root:

```bash
git clone https://github.com/yeahgns/issabel6.git /opt/issabel6
cd /opt/issabel6 && bash install.sh
```

O clone fica em `/opt`, fora do `/var/www/html`: o `.git` nunca é servido pelo Apache.
O `install.sh` copia os arquivos para `themes/bradial` e aplica menu e marca do PBX.
Depois, recarregue o Issabel no navegador (Cmd+Shift+R ou Ctrl+Shift+R).

Opções:

| Comando | Para quê |
|---|---|
| `bash install.sh --so-menu` | Reaplicar só o menu. Use depois de instalar ou atualizar módulos, que podem recriar os próprios itens no lugar antigo. |
| `bash install.sh --sem-menu` | Instalar o tema sem mexer no menu. |
| `bash install.sh --sem-pbx` | Instalar sem alterar a marca do IssabelPBX. |

## Atualização

```bash
cd /opt/issabel6 && git pull && bash install.sh
```

Rodar de novo é seguro: atualiza os arquivos e reaplica as configurações. A versão e o
commit instalados ficam em `/var/lib/bradial-theme/instalado.txt`.

## Fluxo de mudanças

1. A mudança chega como um arquivo `.patch` com só as linhas alteradas.
2. No clone local: `git apply ~/Downloads/arquivo.patch`, confira com `git diff`,
   depois `git commit -am "descrição"` e `git push`.
3. No servidor: o comando de atualização acima.

Se o `git apply` recusar o patch, é porque o arquivo mudou por fora desde que o patch
foi gerado; nada é aplicado pela metade.

**Repositório público:** nada de senhas, tokens, IPs de clientes ou dados de servidor no
código. O `install.sh` lê a senha do MySQL de `/etc/issabel.conf` na hora de rodar, e os
backups ficam só no servidor, em `/var/lib/bradial-theme`.

## Depois de instalar

1. **Relatórios.** O item visível é o Registro de chamadas (relatório de ligações v2,
   em `modules/report`). Para esconder o CDR Report nativo dos clientes, remova a
   permissão dele nos grupos.
2. **Build Module e Temas.** Sugerimos remover a permissão desses itens nos grupos de
   cliente; trocar de tema pela interface desfaz a identidade visual.

## Remoção

```bash
bash uninstall.sh
```

Volta o tema anterior, o menu (item a item, sem apagar módulos instalados depois) e a
marca original do IssabelPBX. A pasta do tema é movida para `/var/lib/bradial-theme`.
Use `--manter-tema` para deixá-la no lugar.

Backups: o estado original fica em `/var/lib/bradial-theme/original`, e cada execução
do `install.sh` guarda outro em `/var/lib/bradial-theme/backup-<data>`.

## Correções em módulos nativos

O Construtor de módulo e o Remover módulo (pacote `issabel-developer`) foram feitos para o
Issabel 4 e, no Issabel 5, aparecem sem rótulos. O `install.sh` insere uma linha em cada um
para corrigir isso (procure por `BV_LANG_SHIM`), guarda o original e o `uninstall.sh` o
devolve. Se o pacote for atualizado, rode o `install.sh` de novo.

## Limitações conhecidas

- A tela de **autenticação em dois fatores** continua no tema `tenant`: o `index.php`
  do Issabel fixa esse tema nela.
- O **CSS do IssabelPBX** é uma primeira versão (tipografia, cores, campos e botões).
  O layout das telas do PBX continua o do IssabelPBX.
- **Favoritos e histórico** da barra lateral antiga não foram trazidos.
- **Formulários nativos** continuam montados em tabelas; o tema muda a aparência, não a
  disposição dos campos. O Backup e restauração é a tela que mais destoa.

## Estrutura

```
install.sh, uninstall.sh, VERSION, CHANGELOG.md
menu/reorganize.sql        organização do menu (menu.db)
theme/_common/             index.tpl, _menu.tpl, login.tpl
theme/themesetup.php       ícones, usuário, breadcrumb, avisos
theme/assets/css/          tokens, components (.bl-*), shell, native, pbx
theme/assets/fonts/        Inter (SIL Open Font License)
theme/assets/icons/        sprite Lucide (ISC License)
theme/assets/img/          logos Bradial Voz e favicon
theme/assets/js/shell.js   barra lateral, menu recolhível, gaveta no celular
theme/lib/BvLangShim.php   rótulos dos módulos antigos do Issabel 4
modules/bv_visao_geral/    Visão geral (index.php, lib/VisaoGeral.php, assets/)
modules/bv_energia/        Reiniciar ou desligar (usa o sudo de /sbin/shutdown do Issabel)
menu/menu.xml              registro da Visão geral (issabel-menumerge)
```

Os componentes `.bl-*` do design system ficam disponíveis em todas as telas, para os
módulos próprios usarem quando migrarem para os mesmos tokens.
