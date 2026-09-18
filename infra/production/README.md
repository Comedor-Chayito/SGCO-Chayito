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
