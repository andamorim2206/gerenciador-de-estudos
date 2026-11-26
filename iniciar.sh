#!/usr/bin/env bash
# Inicia o Gerenciador de Estudos e abre no navegador.
# Uso: ./iniciar.sh [porta]   (padrão: 8000)

set -euo pipefail

cd "$(dirname "$(readlink -f "$0")")"

PORTA="${1:-8000}"
HOST="127.0.0.1"

if ! command -v php >/dev/null 2>&1; then
    echo "Erro: PHP não encontrado. Instale o PHP 8.1+ com as extensões pdo_sqlite e mbstring." >&2
    exit 1
fi

# Se o PHP funciona sem o php.ini (evita o aviso do xdebug), usa assim.
PHP=(php)
if php -n -m 2>/dev/null | grep -qi '^pdo_sqlite$' && php -n -m 2>/dev/null | grep -qi '^mbstring$'; then
    PHP=(php -n)
elif ! php -m 2>/dev/null | grep -qi '^pdo_sqlite$' || ! php -m 2>/dev/null | grep -qi '^mbstring$'; then
    echo "Erro: o PHP precisa das extensões pdo_sqlite e mbstring." >&2
    exit 1
fi

# Aplica as migrações pendentes antes de subir (com backup automático do banco).
"${PHP[@]}" -r 'require "src/bootstrap.php"; App\Database::pdo();'

if [ "$("${PHP[@]}" -r 'require "src/bootstrap.php"; echo App\Database::pdo()->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();')" = "0" ]; then
    echo "Nenhuma conta criada ainda. Crie a primeira com:"
    echo "  ${PHP[*]} bin/criar-usuario.php seu@email.com \"Seu nome\""
    echo "(a primeira conta herda as matérias e tarefas que já existem)"
    exit 1
fi

# Procura uma porta livre a partir da escolhida.
porta_ocupada() { (exec 3<>"/dev/tcp/$HOST/$1") 2>/dev/null; }
while porta_ocupada "$PORTA"; do
    PORTA=$((PORTA + 1))
done

URL="http://localhost:$PORTA"

abrir_navegador() {
    if grep -qi microsoft /proc/version 2>/dev/null; then
        if command -v wslview >/dev/null 2>&1; then wslview "$URL"
        else cmd.exe /c start "" "$URL" >/dev/null 2>&1; fi
    elif command -v xdg-open >/dev/null 2>&1; then xdg-open "$URL"
    elif command -v open >/dev/null 2>&1; then open "$URL"
    fi
}

echo "Gerenciador de Estudos rodando em $URL"
echo "Pressione Ctrl+C para encerrar."

(sleep 1 && abrir_navegador) >/dev/null 2>&1 &

exec "${PHP[@]}" -S "$HOST:$PORTA" -t public public/index.php
