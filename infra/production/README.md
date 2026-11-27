# Producción

Este directorio no se usa para desarrollo local. Sail es el entorno de desarrollo;
este compose consume imágenes publicadas por CI y debe ejecutarse únicamente en el
servidor o plataforma de destino.

## Requisitos de infraestructura

- MySQL administrado o privado, con backups automáticos y una restauración probada.
- Un bucket S3/R2 para archivos de usuarios. No monte `storage/app` como solución de
  uploads en producción.
- Un proxy TLS delante del puerto local `127.0.0.1:8080` (Caddy, Nginx del host,
  Traefik o Cloudflare Tunnel). No exponga MySQL ni Redis.
- Un registro de imágenes, por ejemplo GHCR.

## Primer despliegue

1. Copie `.env.production.example` a `.env.production` en el servidor. Nunca copie
   este archivo al repositorio ni lo incluya en una imagen.
2. Complete secretos y las dos referencias de imagen. Use tags SHA o digests, nunca
   `latest`.
3. Descargue las imágenes y ejecute migraciones **una sola vez**:

   ```bash
   docker compose --env-file .env.production -f compose.yaml pull
   docker compose --env-file .env.production -f compose.yaml run --rm app php artisan migrate --force
   docker compose --env-file .env.production -f compose.yaml up -d --remove-orphans
   ```

4. Compruebe `http://127.0.0.1:8080/up` desde el host y después el dominio HTTPS.

## Despliegues posteriores

1. Cambie únicamente `APP_IMAGE` y `NGINX_IMAGE` al nuevo SHA/digest.
2. Revise primero las migraciones. Deben ser compatibles tanto con la versión vieja
   como con la nueva durante el rollout.
3. Ejecute los mismos tres comandos del primer despliegue.
4. Si falla el healthcheck, vuelva ambas referencias a la versión previa. No use
   `migrate:rollback` a ciegas: algunas migraciones productivas son irreversibles.

## Despliegue automático (servidor actual)

El servidor actual está en una red privada y publica la app por Cloudflare Tunnel, así
que GitHub no puede entrar a él. En su lugar, el servidor sondea `main`:

- `compose.server.yaml` es el override del servidor: construye las imágenes ahí mismo
  (`APP_IMAGE`/`NGINX_IMAGE` apuntan a `sgco-chayito-*:local`) y agrega MySQL y el túnel.
- `systemd/sgco-deploy.timer` ejecuta `deploy.sh` un minuto después de que termina el
  ciclo anterior.
- `deploy.sh` hace `git fetch` de `main` (el repo es público, no necesita credenciales). Si
  hay un commit nuevo **y el CI de GitHub lo aprobó**, mueve el checkout a ese commit,
  construye `app` y `nginx`, corre `migrate --force`, levanta el stack y espera que
  `/up` responda 200. Si algo falla, restaura las imágenes `:rollback` y el commit anterior,
  y no reintenta ese commit hasta que llegue uno nuevo.

El estado vive en `~/.local/state/sgco-deploy/` (`desplegado`, `fallido`, `lock`).

> Las migraciones corren solas. Revíselas en el Pull Request antes de mezclar a `main`.

### Instalación (una sola vez)

```bash
mkdir -p ~/.config/systemd/user
cp infra/production/systemd/sgco-deploy.* ~/.config/systemd/user/
sudo loginctl enable-linger "$USER"   # el timer corre sin sesión abierta
systemctl --user daemon-reload
systemctl --user enable --now sgco-deploy.timer
```

### Operación

```bash
journalctl --user -u sgco-deploy -f              # ver despliegues en vivo
infra/production/deploy.sh --forzar              # redesplegar el commit actual
rm ~/.local/state/sgco-deploy/fallido            # reintentar un commit marcado como fallido
DEPLOY_REQUIRE_CI=0 infra/production/deploy.sh   # desplegar sin esperar al CI (emergencia)
```
