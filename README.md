# sgco-chayito

Base Laravel 12 para sgco-chayito. Usa Sail para desarrollo y mantiene una ruta
independiente de imágenes inmutables para producción.

## Servicios y puertos locales

| Servicio | Host |
| --- | --- |
| Aplicación | http://localhost:8084 |
| Vite / HMR | http://localhost:5174 |
| Mailpit | http://localhost:8027 |
| MySQL | `localhost:3309` |
| Redis | `localhost:6381` |

Los nombres de Compose, imágenes y volúmenes usan el prefijo `sgco-chayito`;
pueden correr al mismo tiempo que
`comerdor-chayito` sin conflictos.

## Primer arranque

Después de clonar, instala dependencias PHP sin instalar Composer en el host:

```bash
docker run --rm -u "$(id -u):$(id -g)" -v "$PWD":/var/www/html -w /var/www/html composer:2 composer install
```

Luego:

```bash
cp .env.example .env
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
./vendor/bin/sail npm ci
```

Para desarrollar assets con recarga en caliente:

```bash
./vendor/bin/sail npm run dev
```

## Trabajo diario

```bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate
./vendor/bin/sail test
./vendor/bin/sail down
```

## Trabajar dentro del contenedor

Si prefieres una shell persistente dentro del contenedor Laravel, primero levanta
Sail y después ejecuta:

```bash
docker compose exec laravel.test bash
```

Desde esa terminal puedes trabajar sin prefijar cada comando con `sail`:

```bash
php artisan make:model MenuItem -m
php artisan migrate
php artisan test
composer require vendor/package
npm run dev
exit
```

`docker compose exec` es la opción recomendada porque apunta al servicio
`laravel.test` y no depende de un nombre fijo. Si específicamente prefieres
`docker exec`, obtén el ID del contenedor de forma dinámica:

```bash
docker exec -it "$(docker compose ps -q laravel.test)" bash
```

## Personalización posterior

1. Cambia `APP_NAME`, puertos y el `name`/`image` de `compose.yaml` si deseas
   ejecutarlo junto con más proyectos.
2. Reemplaza el README y comienza rutas, modelos y pruebas del producto.
3. Crea un repositorio GitHub desde esta carpeta o publícala como GitHub Template.
4. Antes de producción, revisa [infra/production/README.md](infra/production/README.md).

Sail nunca se usa en producción: CI publica las imágenes `app` y `nginx` al crear
un tag `v*`; el servidor consume esos artefactos versionados.
