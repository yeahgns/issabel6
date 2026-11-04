#!/bin/bash
# Tema Bradial Voz para Issabel 5: remoção.
#
#   bash uninstall.sh                volta tema, menu e marca do PBX ao estado original
#   bash uninstall.sh --manter-tema  restaura tudo, mas deixa a pasta themes/bradial no disco
#
# Usa o backup feito na primeira instalação (/var/lib/bradial-theme/original).
# O menu é restaurado item a item: módulos instalados depois do tema continuam no menu.
set -euo pipefail

WEB=/var/www/html
THEME=$WEB/themes/bradial
STATE=/var/lib/bradial-theme
ORIG=$STATE/original
STAMP=$(date +%Y%m%d-%H%M%S)
MENUDB=/var/www/db/menu.db
SETDB=/var/www/db/settings.db
AMP=/etc/amportal.conf
KEEP=0
for a in "$@"; do
    case "$a" in
        --manter-tema) KEEP=1 ;;
        -h|--help) sed -n '2,9p' "$0"; exit 0 ;;
        *) echo "Opção desconhecida: $a" >&2; exit 1 ;;
    esac
done

say()  { echo "==> $*"; }
info() { echo "    $*"; }
warn() { echo "    Aviso: $*" >&2; }
fail() { echo "Erro: $*" >&2; exit 1; }

[ "$(id -u)" = 0 ] || fail "rode como root."
[ -d "$ORIG" ] || fail "backup original não encontrado em $ORIG. Nada a restaurar."
command -v sqlite3 >/dev/null || fail "sqlite3 não encontrado."

MYSQLPWD=$(grep -i '^mysqlrootpwd' /etc/issabel.conf 2>/dev/null | head -1 | cut -d= -f2- || true)
sqlq() { if [ -n "$MYSQLPWD" ]; then mysql -uroot -p"$MYSQLPWD" asterisk -N -B -e "$1"; else mysql -uroot asterisk -N -B -e "$1"; fi; }
sqlesc() { printf "%s" "${1//\'/\'\'}"; }

# Backup do estado atual antes de desfazer
mkdir -p "$STATE/antes-da-remocao-$STAMP"
sqlite3 "$MENUDB" ".backup '$STATE/antes-da-remocao-$STAMP/menu.db'"

# ---------- Tema ----------
OLDTHEME=$(cat "$ORIG/theme.txt" 2>/dev/null || true)
[ -n "$OLDTHEME" ] && [ "$OLDTHEME" != "bradial" ] || OLDTHEME=tenant
say "Voltando para o tema $OLDTHEME"
sqlite3 "$SETDB" "UPDATE settings SET value='$(sqlesc "$OLDTHEME")' WHERE key='theme';"

# ---------- Menu ----------
say "Restaurando o menu"
sqlite3 "$MENUDB" <<SQL
ATTACH DATABASE '$ORIG/menu.db' AS o;
BEGIN TRANSACTION;
UPDATE main.menu SET
  IdParent = (SELECT IdParent FROM o.menu AS om WHERE om.id = main.menu.id),
  Link     = (SELECT Link     FROM o.menu AS om WHERE om.id = main.menu.id),
  Name     = (SELECT Name     FROM o.menu AS om WHERE om.id = main.menu.id),
  Type     = (SELECT Type     FROM o.menu AS om WHERE om.id = main.menu.id),
  order_no = (SELECT order_no FROM o.menu AS om WHERE om.id = main.menu.id)
WHERE main.menu.id IN (SELECT id FROM o.menu);
DELETE FROM main.menu WHERE id IN ('bv_ramais','bv_filas','bv_troncos','bv_rotas_entrada','bv_rotas_saida')
  AND id NOT IN (SELECT id FROM o.menu);
COMMIT;
DETACH DATABASE o;
SQL
chown asterisk:asterisk "$MENUDB" 2>/dev/null || true
info "Itens do menu de volta aos lugares e nomes originais."

# ---------- Marca do IssabelPBX ----------
if [ -s "$ORIG/brand.tsv" ]; then
    say "Restaurando a marca original do IssabelPBX"
    if sqlq "SELECT 1;" >/dev/null 2>&1; then
        while IFS=$'\t' read -r key val; do
            [ -n "$key" ] || continue
            sqlq "UPDATE issabelpbx_settings SET value='$(sqlesc "$val")' WHERE keyword='$(sqlesc "$key")';"
        done < "$ORIG/brand.tsv"
    else
        warn "não consegui acessar o MySQL; as configurações BRAND_* do PBX não foram restauradas."
    fi
fi
if [ -s "$ORIG/amportal-brand.txt" ] && [ -f "$AMP" ]; then
    while IFS= read -r line; do
        key=${line%%=*}
        v=$(printf '%s' "${line#*=}" | sed -e 's/[|&\\]/\\&/g')
        grep -q "^$key=" "$AMP" && sed -i "s|^$key=.*|$key=$v|" "$AMP"
    done < "$ORIG/amportal-brand.txt"
fi

# ---------- Arquivos do tema ----------
if [ "$KEEP" = 0 ] && [ -d "$THEME" ]; then
    mv "$THEME" "$STATE/tema-removido-$STAMP"
    info "Pasta do tema movida para $STATE/tema-removido-$STAMP"
fi

echo
say "Pronto. Recarregue o Issabel no navegador."
info "Os recursos de permissão dos atalhos (bv_*) continuam cadastrados, sem efeito."
info "O backup original segue em $ORIG. Apague $STATE quando não precisar mais."
