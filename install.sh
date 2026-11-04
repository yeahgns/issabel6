#!/bin/bash
# Tema Bradial Voz para Issabel 5: instalação.
#
#   bash install.sh             instala ou atualiza tudo
#   bash install.sh --so-menu   só reaplica a organização do menu (use depois de
#                               instalar ou atualizar módulos, que podem recriar
#                               os próprios itens no lugar antigo)
#   bash install.sh --sem-menu  instala o tema sem mexer no menu
#   bash install.sh --sem-pbx   instala sem alterar a marca do IssabelPBX (/admin)
#
# O backup do estado original fica em /var/lib/bradial-theme/original e é usado
# pelo uninstall.sh. Cada execução também guarda um backup com data e hora.
set -euo pipefail

DIR=$(cd "$(dirname "$0")" && pwd)
VERSION=$(cat "$DIR/VERSION" 2>/dev/null || echo "?")
WEB=/var/www/html
THEME=$WEB/themes/bradial
STATE=/var/lib/bradial-theme
ORIG=$STATE/original
STAMP=$(date +%Y%m%d-%H%M%S)
BK=$STATE/backup-$STAMP
MENUDB=/var/www/db/menu.db
SETDB=/var/www/db/settings.db
AMP=/etc/amportal.conf
ASSETS_URL=/themes/bradial/assets

DO_THEME=1; DO_MENU=1; DO_PBX=1
for a in "$@"; do
    case "$a" in
        --so-menu)  DO_THEME=0; DO_PBX=0 ;;
        --sem-menu) DO_MENU=0 ;;
        --sem-pbx)  DO_PBX=0 ;;
        -h|--help)  sed -n '2,13p' "$0"; exit 0 ;;
        *) echo "Opção desconhecida: $a (veja bash install.sh --help)" >&2; exit 1 ;;
    esac
done

say()  { echo "==> $*"; }
info() { echo "    $*"; }
warn() { echo "    Aviso: $*" >&2; }
fail() { echo "Erro: $*" >&2; exit 1; }

# ---------- Verificações ----------
[ "$(id -u)" = 0 ] || fail "rode como root."
[ -d "$WEB/themes/tenant/_common" ] || fail "tema tenant não encontrado em $WEB/themes. Isto não parece um Issabel 5."
[ -d "$DIR/theme/_common" ] || fail "rode o script de dentro da pasta do pacote (faltam os arquivos em theme/)."
command -v sqlite3 >/dev/null || fail "sqlite3 não encontrado."
[ -f "$MENUDB" ] || fail "$MENUDB não encontrado."
[ -f "$SETDB" ]  || fail "$SETDB não encontrado."
if [ "$DO_MENU" = 1 ]; then
    command -v issabel-menumerge >/dev/null || fail "issabel-menumerge não encontrado (necessário para criar os atalhos do PBX). Use --sem-menu para pular o menu."
fi

MYSQLPWD=$(grep -i '^mysqlrootpwd' /etc/issabel.conf 2>/dev/null | head -1 | cut -d= -f2- || true)
sqlq() { if [ -n "$MYSQLPWD" ]; then mysql -uroot -p"$MYSQLPWD" asterisk -N -B -e "$1"; else mysql -uroot asterisk -N -B -e "$1"; fi; }
sqlesc() { printf "%s" "${1//\'/\'\'}"; }

say "Tema Bradial Voz $VERSION"

# ---------- Backup ----------
backup_to() {
    local d=$1
    mkdir -p "$d"
    sqlite3 "$MENUDB" ".backup '$d/menu.db'"
    sqlite3 "$SETDB" "SELECT value FROM settings WHERE key='theme';" > "$d/theme.txt" || true
    if [ "$DO_PBX" = 1 ] || [ -f "$ORIG/brand.tsv" ]; then
        sqlq "SELECT keyword, value FROM issabelpbx_settings WHERE keyword LIKE 'BRAND%';" > "$d/brand.tsv" 2>/dev/null || warn "não consegui ler as configurações BRAND_* do IssabelPBX."
        grep '^BRAND_' "$AMP" > "$d/amportal-brand.txt" 2>/dev/null || true
    fi
}
mkdir -p "$STATE"; chmod 700 "$STATE"
if [ ! -d "$ORIG" ]; then
    say "Guardando o estado original em $ORIG"
    backup_to "$ORIG"
fi
say "Backup desta execução em $BK"
backup_to "$BK"

# ---------- Tema ----------
if [ "$DO_THEME" = 1 ]; then
    say "Instalando o tema em $THEME"
    rm -rf "$THEME.new"
    cp -a "$WEB/themes/tenant" "$THEME.new"
    cp -a "$DIR/theme/." "$THEME.new/"
    chown -R asterisk:asterisk "$THEME.new"
    find "$THEME.new" -type d -exec chmod 755 {} +
    find "$THEME.new" -type f -exec chmod 644 {} +
    if [ -d "$THEME" ]; then rm -rf "$THEME.old"; mv "$THEME" "$THEME.old"; fi
    mv "$THEME.new" "$THEME"
    rm -rf "$THEME.old"

    say "Ativando o tema"
    if [ "$(sqlite3 "$SETDB" "SELECT COUNT(*) FROM settings WHERE key='theme';")" = 0 ]; then
        sqlite3 "$SETDB" "INSERT INTO settings(key, value) VALUES('theme', 'bradial');"
    else
        sqlite3 "$SETDB" "UPDATE settings SET value='bradial' WHERE key='theme';"
    fi
    info "Tema ativo: $(sqlite3 "$SETDB" "SELECT value FROM settings WHERE key='theme';")"
fi

# ---------- Menu ----------
if [ "$DO_MENU" = 1 ]; then
    say "Criando os atalhos do PBX (Ramais, Filas, Troncos, Rotas)"
    issabel-menumerge "$DIR/menu/menu.xml"
    for id in bv_ramais bv_filas bv_troncos bv_rotas_entrada bv_rotas_saida; do
        [ "$(sqlite3 "$MENUDB" "SELECT COUNT(*) FROM menu WHERE id='$id';")" = 1 ] || fail "o item $id não foi criado pelo issabel-menumerge."
    done
    say "Reorganizando o menu"
    sqlite3 "$MENUDB" < "$DIR/menu/reorganize.sql"
    chown asterisk:asterisk "$MENUDB" 2>/dev/null || true
    info "Seções: $(sqlite3 "$MENUDB" "SELECT group_concat(Name, ', ') FROM (SELECT Name FROM menu WHERE IdParent='' ORDER BY order_no LIMIT 5);")"
fi

# ---------- Marca do IssabelPBX (/admin) ----------
set_brand() {
    local key=$1 val=$2 v
    sqlq "UPDATE issabelpbx_settings SET value='$(sqlesc "$val")' WHERE keyword='$key';"
    if grep -q "^$key=" "$AMP" 2>/dev/null; then
        v=$(printf '%s' "$val" | sed -e 's/[|&\\]/\\&/g')
        sed -i "s|^$key=.*|$key=$v|" "$AMP"
    fi
}
if [ "$DO_PBX" = 1 ]; then
    say "Aplicando a marca no IssabelPBX"
    if sqlq "SELECT 1;" >/dev/null 2>&1; then
        set_brand BRAND_CSS_CUSTOM                "$ASSETS_URL/css/pbx.css"
        set_brand BRAND_IMAGE_TANGO_LEFT          "$ASSETS_URL/img/bradial-voz-icone-white.png"
        set_brand BRAND_ISSABELPBX_ALT_LEFT       "Bradial Voz"
        set_brand BRAND_IMAGE_ISSABELPBX_LINK_LEFT "config.php"
        set_brand BRAND_IMAGE_FAVICON             "$ASSETS_URL/img/favicon.ico"
        set_brand BRAND_TITLE                     "Bradial Voz"
        info "CSS, logo, título e favicon do PBX atualizados (banco e $AMP)."
    else
        warn "não consegui acessar o MySQL; a marca do PBX não foi aplicada. Rode de novo depois de conferir /etc/issabel.conf."
    fi
fi

echo
say "Pronto."
info "Recarregue o Issabel no navegador (Ctrl+Shift+R / Cmd+Shift+R)."
if [ "$DO_MENU" = 1 ]; then
    info "Os atalhos novos do PBX já valem para o grupo administrador. Para outros grupos,"
    info "libere em Sistema > Usuários > Permissões: Ramais, Filas, Troncos, Rotas de entrada e Rotas de saída."
fi
info "Para desfazer: bash $DIR/uninstall.sh"
