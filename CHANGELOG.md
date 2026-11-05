# Changelog

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
