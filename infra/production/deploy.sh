#!/usr/bin/env bash
# Despliegue continuo por sondeo para el servidor de producción.
#
# El servidor vive en una red privada (sin SSH entrante desde GitHub), así que es él quien
# pregunta: un timer de systemd ejecuta este script cada minuto. Si `main` tiene un commit
# nuevo y el CI de GitHub lo aprobó, reconstruye las imágenes, migra y reinicia el stack.
# Si el healthcheck falla, vuelve a las imágenes y al commit anteriores.
#
# Uso:
#   infra/production/deploy.sh            # revisa y despliega si hay cambios
#   infra/production/deploy.sh --forzar   # despliega aunque el commit ya esté desplegado
#
# Variables opcionales:
#   DEPLOY_BRANCH       rama a desplegar (por defecto main)
#   DEPLOY_GITHUB_REPO  owner/repo para consultar el CI (por defecto Comedor-Chayito/SGCO-Chayito)
#   DEPLOY_REQUIRE_CI   1 exige CI en verde antes de desplegar (por defecto 1)

set -Eeuo pipefail

INFRA_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_DIR="$(cd "$INFRA_DIR/../.." && pwd)"
RAMA="${DEPLOY_BRANCH:-main}"
REPO_GITHUB="${DEPLOY_GITHUB_REPO:-Comedor-Chayito/SGCO-Chayito}"
EXIGIR_CI="${DEPLOY_REQUIRE_CI:-1}"
ESTADO_DIR="${XDG_STATE_HOME:-$HOME/.local/state}/sgco-deploy"
ARCHIVO_DESPLEGADO="$ESTADO_DIR/desplegado"
ARCHIVO_FALLIDO="$ESTADO_DIR/fallido"
ENV_FILE="$INFRA_DIR/.env.production"
TIEMPO_SALUD_SEG=120

mkdir -p "$ESTADO_DIR"

# Escribe un mensaje con marca de tiempo en la salida estándar (journald lo recoge).
#
# @autor  manuelmv15
# @fecha  2026-11-25
# @módulo Infraestructura – despliegue continuo
#
# @param  string $* Mensaje a registrar.
# @return void
log() {
    printf '[%s] %s\n' "$(date '+%F %T')" "$*"
}

# Ejecuta docker compose con los dos archivos del stack y el .env de producción.
#
# @autor  manuelmv15
# @fecha  2026-11-25
# @módulo Infraestructura – despliegue continuo
#
# @param  string $@ Argumentos para docker compose.
# @return int Código de salida de docker compose.
dc() {
    docker compose \
        --env-file "$ENV_FILE" \
        -f "$INFRA_DIR/compose.yaml" \
        -f "$INFRA_DIR/compose.server.yaml" \
        "$@"
}

# Lee el valor de una variable del archivo .env.production.
#
# @autor  manuelmv15
# @fecha  2026-11-25
# @módulo Infraestructura – despliegue continuo
#
# @param  string $1 Nombre de la variable.
# @return string Valor de la variable sin comillas.
leer_env() {
    grep -E "^$1=" "$ENV_FILE" | tail -n 1 | cut -d= -f2- | tr -d '"'
}

# Consulta en la API pública de GitHub el resultado del CI para un commit.
#
# @autor  manuelmv15
# @fecha  2026-11-25
# @módulo Infraestructura – despliegue continuo
#
# @param  string $1 SHA completo del commit.
# @return string "ok", "pendiente" o "fallo".
estado_ci() {
    local respuesta

    if ! respuesta="$(curl -fsS --max-time 15 \
        -H 'Accept: application/vnd.github+json' \
        "https://api.github.com/repos/$REPO_GITHUB/commits/$1/check-runs?per_page=100")"; then
        echo pendiente
        return
    fi

    jq -r '
        if .total_count == 0 then "pendiente"
        elif any(.check_runs[]; .status != "completed") then "pendiente"
        elif all(.check_runs[]; .conclusion == "success" or .conclusion == "skipped"
            or .conclusion == "neutral") then "ok"
        else "fallo" end
    ' <<<"$respuesta"
}

# Espera a que /up responda 200 en el puerto local de nginx.
#
# @autor  manuelmv15
# @fecha  2026-11-25
# @módulo Infraestructura – despliegue continuo
#
# @return int 0 si la app respondió a tiempo, 1 si no.
esperar_salud() {
    local puerto limite

    puerto="$(leer_env HTTP_PORT)"
    limite=$((SECONDS + TIEMPO_SALUD_SEG))

    while ((SECONDS < limite)); do
        if [[ "$(curl -s -o /dev/null -w '%{http_code}' --max-time 5 \
            "http://127.0.0.1:${puerto:-8080}/up")" == "200" ]]; then
            return 0
        fi
        sleep 3
    done

    return 1
}

# Fase 1: busca un commit nuevo, valida el CI y mueve el código a ese commit.
# Después se re-ejecuta con la versión nueva de este script para la fase 2.
#
# @autor  manuelmv15
# @fecha  2026-11-25
# @módulo Infraestructura – despliegue continuo
#
# @param  string $1 "--forzar" para desplegar aunque el commit ya esté desplegado.
# @return void
revisar() {
    local objetivo desplegado ci

    exec 9>"$ESTADO_DIR/lock"
    if ! flock -n 9; then
        log "Otro despliegue está en curso; salgo."
        exit 0
    fi
    export SGCO_DEPLOY_LOCK=1

    git -C "$REPO_DIR" fetch --quiet origin "$RAMA"
    objetivo="$(git -C "$REPO_DIR" rev-parse "origin/$RAMA")"
    desplegado="$(cat "$ARCHIVO_DESPLEGADO" 2>/dev/null || true)"

    if [[ "${1:-}" != "--forzar" ]]; then
        if [[ "$objetivo" == "$desplegado" ]]; then
            exit 0
        fi
        if [[ "$objetivo" == "$(cat "$ARCHIVO_FALLIDO" 2>/dev/null || true)" ]]; then
            exit 0
        fi
    fi

    if [[ "$EXIGIR_CI" == "1" ]]; then
        ci="$(estado_ci "$objetivo")"
        if [[ "$ci" == "pendiente" ]]; then
            log "CI de ${objetivo:0:7} todavía no termina; reviso en el próximo ciclo."
            exit 0
        fi
        if [[ "$ci" == "fallo" ]]; then
            log "CI de ${objetivo:0:7} falló; no se despliega."
            echo "$objetivo" >"$ARCHIVO_FALLIDO"
            exit 0
        fi
    fi

    log "Desplegando ${objetivo:0:7} (antes: ${desplegado:0:7})."
    git -C "$REPO_DIR" checkout --quiet --force --detach "$objetivo"

    exec "$INFRA_DIR/deploy.sh" --aplicar "$objetivo" "$desplegado"
}

# Fase 2: construye, migra y levanta el stack. Revierte si el healthcheck falla.
#
# @autor  manuelmv15
# @fecha  2026-11-25
# @módulo Infraestructura – despliegue continuo
#
# @param  string $1 SHA a desplegar.
# @param  string $2 SHA desplegado antes (vacío en el primer despliegue).
# @return void
aplicar() {
    local objetivo="$1" previo="${2:-}" imagenApp imagenNginx

    imagenApp="$(leer_env APP_IMAGE)"
    imagenNginx="$(leer_env NGINX_IMAGE)"

    # Respaldo de las imágenes que están corriendo, para poder revertir.
    docker image tag "$imagenApp" "${imagenApp%%:*}:rollback" 2>/dev/null || true
    docker image tag "$imagenNginx" "${imagenNginx%%:*}:rollback" 2>/dev/null || true

    if ! dc build app nginx; then
        log "Falló el build de ${objetivo:0:7}; el stack anterior sigue en pie."
        revertir_todo "$objetivo" "$previo" "$imagenApp" "$imagenNginx"
        exit 1
    fi

    if ! dc run --rm --no-deps app php artisan migrate --force; then
        log "Fallaron las migraciones de ${objetivo:0:7}; revierto imágenes."
        revertir_todo "$objetivo" "$previo" "$imagenApp" "$imagenNginx"
        exit 1
    fi

    dc up -d --remove-orphans

    if ! esperar_salud; then
        log "/up no respondió 200 en ${TIEMPO_SALUD_SEG}s; revierto."
        revertir_todo "$objetivo" "$previo" "$imagenApp" "$imagenNginx"
        exit 1
    fi

    echo "$objetivo" >"$ARCHIVO_DESPLEGADO"
    rm -f "$ARCHIVO_FALLIDO"
    docker image prune -f >/dev/null
    log "Despliegue de ${objetivo:0:7} completado."
}

# Marca el commit como fallido y regresa el código al commit desplegado antes.
#
# @autor  manuelmv15
# @fecha  2026-11-25
# @módulo Infraestructura – despliegue continuo
#
# @param  string $1 SHA que falló.
# @param  string $2 SHA desplegado antes (vacío si no hay).
# @return void
revertir_codigo() {
    echo "$1" >"$ARCHIVO_FALLIDO"
    if [[ -n "$2" ]]; then
        git -C "$REPO_DIR" checkout --quiet --force --detach "$2"
    fi
}

# Restaura las imágenes de respaldo, levanta el stack anterior y regresa el código.
#
# @autor  manuelmv15
# @fecha  2026-11-25
# @módulo Infraestructura – despliegue continuo
#
# @param  string $1 SHA que falló.
# @param  string $2 SHA desplegado antes.
# @param  string $3 Referencia de la imagen app (APP_IMAGE).
# @param  string $4 Referencia de la imagen nginx (NGINX_IMAGE).
# @return void
revertir_todo() {
    docker image tag "${3%%:*}:rollback" "$3" 2>/dev/null || true
    docker image tag "${4%%:*}:rollback" "$4" 2>/dev/null || true
    dc up -d --remove-orphans || true
    revertir_codigo "$1" "$2"
}

if [[ "${1:-}" == "--aplicar" ]]; then
    if [[ "${SGCO_DEPLOY_LOCK:-}" != "1" ]]; then
        log "--aplicar solo se invoca desde la fase de revisión."
        exit 2
    fi
    aplicar "$2" "${3:-}"
else
    revisar "${1:-}"
fi
