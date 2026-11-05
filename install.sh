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
    command -v issabel-menumerge >/dev/null || fail "issabel-menumerge não encontrado (necessário para registrar a Visão geral). Use --sem-menu para pular o menu."
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
# Guarda só os 10 backups mais recentes (o original nunca é apagado)
ls -1dt "$STATE"/backup-* 2>/dev/null | tail -n +11 | xargs -r rm -rf || true

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

# ---------- Módulos próprios ----------
if [ "$DO_THEME" = 1 ]; then
    for m in "$DIR"/modules/*/; do
        [ -d "$m" ] || continue
        name=$(basename "$m")
        say "Instalando o módulo $name"
        rm -rf "$WEB/modules/$name.new"
        cp -a "$m" "$WEB/modules/$name.new"
        cp "$DIR/VERSION" "$WEB/modules/$name.new/VERSION"
        chown -R asterisk:asterisk "$WEB/modules/$name.new"
        find "$WEB/modules/$name.new" -type d -exec chmod 755 {} +
        find "$WEB/modules/$name.new" -type f -exec chmod 644 {} +
        rm -rf "$WEB/modules/$name.old"
        [ -d "$WEB/modules/$name" ] && mv "$WEB/modules/$name" "$WEB/modules/$name.old"
        mv "$WEB/modules/$name.new" "$WEB/modules/$name"
        rm -rf "$WEB/modules/$name.old"
    done
fi

# ---------- Menu ----------
if [ "$DO_MENU" = 1 ]; then
    say "Registrando a Visão geral no menu"
    issabel-menumerge "$DIR/menu/menu.xml"
    [ "$(sqlite3 "$MENUDB" "SELECT COUNT(*) FROM menu WHERE id='bv_visao_geral';")" = 1 ] || fail "o item bv_visao_geral não foi criado pelo issabel-menumerge."
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

COMMIT=$(git -C "$DIR" rev-parse --short HEAD 2>/dev/null || echo "sem git")
printf 'versao=%s\ncommit=%s\ndata=%s\n' "$VERSION" "$COMMIT" "$STAMP" > "$STATE/instalado.txt"

echo
say "Pronto. Versão $VERSION ($COMMIT)."
info "Recarregue o Issabel no navegador (Ctrl+Shift+R / Cmd+Shift+R)."
info "Para desfazer: bash $DIR/uninstall.sh"
