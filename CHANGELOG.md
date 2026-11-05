# Changelog

## 1.4.3
- Permissões: ✓ em branco dentro do botão azul.
- Visão geral: serviços aparecem como "Online" (verde) ou "Offline" (vermelho).
- Botões que o módulo pinta de cor (ex.: "Apagar" no Backup e no IP fixo por MAC) com
  texto branco, para ficarem legíveis.

## 1.4.2
- Permissões: some o checkbox duplicado. O tema reposicionava todos os checkboxes, o que
  fazia reaparecer o checkbox que o módulo esconde atrás do botão dele.
- Reiniciar ou desligar: sai o botão de baixo; clicar em Reiniciar ou Desligar abre um
  diálogo de confirmação. Os dois cartões ocupam a largura da tela, alinhados aos demais.
- Construtor de módulo e Remover módulo em português quando o Issabel está em português
  (rótulos e opções; os valores das opções não mudam).
- Servidor DHCP e outros formulários com campos curtos (octetos de IP, portas): os campos
  ficam do tamanho do conteúdo e o cartão não passa da coluna.

## 1.4.1
- Construtor de módulo e Remover módulo voltam a mostrar os rótulos. Os dois foram feitos
  para o Issabel 4 e leem os textos de `$arrLangModule`, que o Issabel 5 não preenche mais.
  O `install.sh` insere uma linha em cada um (marcada `BV_LANG_SHIM`) que usa o
  `theme/lib/BvLangShim.php`: devolve o texto original, quando existe, ou a tradução do
  Issabel. O original fica em `/var/lib/bradial-theme/modulos-nativos` e o `uninstall.sh`
  o devolve. Como uma atualização do pacote `issabel-developer` desfaz a linha, basta rodar
  o `install.sh` de novo.

## 1.4.0
- **Idioma sincronizado** com Sistema > Preferências > Idioma: menu, cabeçalhos das telas,
  barra lateral, Visão geral e Reiniciar ou desligar em português ou inglês. Itens nativos
  ganham nome nos dois idiomas (ex.: "CDR Report" vira "Relatório CDR"). A tela de login
  continua em português.
- "Configurações do PABX" passa a "Configurações do PBX".
- Saem do menu lateral: Preferências > Moeda e PBX > Gravações.
- Desenvolvedor > **Módulos**: Construtor de módulo e Remover módulo agrupados.
- Construtor de módulo: os títulos de seção (Informações gerais, Localização, Descrição)
  voltam a aparecer e os campos cabem no cartão. Formulários com muitas colunas se ajustam.
- Botões com "remove" no nome deixam de ficar vermelhos (só "delete" fica).

## 1.3.0
- Telas nativas: corrige a primeira coluna colada na borda e os textos cortados nas
  listagens. A causa era o plugin colResizable do Issabel (zera o espaçamento das células e
  fixa o layout da tabela); o redimensionamento de colunas sai.
- Telas nativas ganham cabeçalho com título e uma frase de descrição, como no protótipo;
  o título antigo do módulo sai. O rodapé que repetia os títulos das colunas também sai.
- Formulários e caixas de seleção alinhados; cartões com a sombra do protótipo.
- Nova tela **Reiniciar ou desligar** (módulo `bv_energia`), no layout do protótipo, com
  confirmação, agendamento de 1 minuto e botão para cancelar. A tela nativa sai do menu.
- Saem do menu lateral: Rede > Captura de pacotes, Rede > UPnP, Preferências > Widgets do
  painel. Continuam instalados e controlados pelas permissões.
- Ping e traceroute: exemplo de teste passa a ser www.bradial.com.br.
- Preferências > Temas: o tema do projeto aparece como "Bradial v6" e o original (tenant)
  como "Bradial v5".

## 1.2.1
- Corrige o logo duplicado e o botão de recolher o menu que não apareciam: o navegador
  usava o CSS antigo em cache. Agora todo arquivo do tema leva `?v=<versão>-<commit>`,
  trocado pelo `install.sh` a cada atualização.
- Selo "v6" ao lado do logo, como no protótipo.
- Sai o ícone de troca de senha do rodapé do menu.
- Visão geral refeita no layout do protótipo: faixa com Chamadas ativas, Canais, Na fila,
  Ramais online (por tecnologia) e Troncos; recursos do servidor; avisos do PBX em português,
  com botão para dispensar os que o IssabelPBX permite; e o cartão Serviços só com Telefonia,
  Banco de dados, Servidor web e Call center.

## 1.2.0
- Nova **Visão geral** (módulo `bv_visao_geral`), tela inicial do Issabel: telefonia agora
  (mesmos números do Painel do Operador), recursos do servidor e avisos do PBX, atualizando
  a cada 5 s. Sem News, Processes Status e avisos de licença do Issabel Network.
  O dashboard nativo segue disponível em Sistema > Dashboard do Issabel.
- Menu lateral recolhível (só ícones), com a escolha salva no navegador.
- Relatórios agora vem antes de Call center.
- Barra superior sem os ícones de ajuda, anotação e avisos do Issabel.
- `uninstall.sh` remove os módulos `bv_*` e recria itens de menu que tenham sumido.

## 1.1.1
- Menu: grupos vazios (ex.: "Batch Configurations", que sobrava depois de Ramais em lote
  sair dele) não aparecem mais. Antes, clicar neles dava "module could not be found".

## 1.1.0
- PBX: os cinco atalhos (Ramais, Filas, Troncos, Rotas) foram trocados por um item único,
  "Configurações do PABX", que abre o IssabelPBX com a navegação própria dele.
- `install.sh` não depende mais do `issabel-menumerge`.

## 1.0.1
- Projeto preparado para o GitHub (`.gitignore`, `.gitattributes`, este changelog).
- `install.sh` guarda só os 10 backups mais recentes e registra versão e commit
  instalados em `/var/lib/bradial-theme/instalado.txt`.
- README com o fluxo de atualização por `git pull`.

## 1.0.0
- Tema Bradial Voz: login, barra lateral, barra superior, telas nativas no design system.
- Menu reorganizado (Geral, PBX, Call center, Relatórios, Sistema).
- Marca do IssabelPBX via `BRAND_*`.
- `install.sh` e `uninstall.sh` com backup e restauração item a item do menu.
