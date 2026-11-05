# Changelog

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
