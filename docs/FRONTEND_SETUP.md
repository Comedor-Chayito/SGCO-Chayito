# Frontend Setup — SGCO-Chayito

Documento de instalación, configuración y pruebas del stack frontend Vue 3 + Pinia + Vue Router + Playwright sobre el entorno Docker/Laravel Sail.

---

## Stack instalado

| Tecnología | Versión | Propósito |
|---|---|---|
| Vue 3 | `^3.x` | Framework UI principal (Composition API, `<script setup>`) |
| @vitejs/plugin-vue | última | Integración Vite ↔ Vue |
| Pinia | última | Estado global (stores) |
| Vue Router 4 | `^4.x` | Enrutamiento SPA |
| Axios | `^1.11.0` | Cliente HTTP centralizado (ya existía) |
| Tailwind CSS | `^4.0.0` | Estilos (ya existía) |
| Playwright | última | Pruebas E2E con Chromium |

---

## Pasos realizados

### 1. Instalación de dependencias npm

```bash
./vendor/bin/sail npm install vue@3 @vitejs/plugin-vue pinia vue-router@4 @playwright/test --save
```

Ejecutado dentro del contenedor Sail (Node.js LTS 20.x), evitando instalaciones en el host.

---

### 2. Configuración de Vite (`vite.config.js`)

Se agregó el plugin `@vitejs/plugin-vue` y el alias `@` apuntando a `resources/js/`:

```js
import vue from '@vitejs/plugin-vue';

export default defineConfig({
  plugins: [laravel({...}), tailwindcss(), vue()],
  resolve: { alias: { '@': '/resources/js' } },
  // ...
});
```

---

### 3. Estructura de directorios creada

```
resources/js/
├── app.js                 ← punto de entrada (crea app Vue, monta Pinia y Router)
├── App.vue                ← componente raíz con <RouterView />
├── router/
│   └── index.js           ← rutas por módulo + guarda de navegación por rol
├── stores/
│   └── sesion.js          ← store Pinia: autenticación, token, rol
├── services/
│   └── api.js             ← cliente Axios centralizado con interceptores
└── views/
    ├── Inicio.vue          ← vista de bienvenida (visible)
    ├── POS.vue             ← módulo POS (placeholder)
    ├── Inventario.vue      ← módulo Inventario (placeholder)
    ├── Contabilidad.vue    ← módulo Contabilidad (placeholder)
    ├── Reportes.vue        ← módulo Reportes (placeholder)
    ├── Admin.vue           ← módulo Admin (placeholder)
    └── NoEncontrado.vue    ← vista 404
```

---

### 4. Vista Blade SPA (`resources/views/app.blade.php`)

Vista mínima que sirve el `<div id="app">` donde Vue se monta:

```html
<div id="app"></div>
@vite(['resources/css/app.css', 'resources/js/app.js'])
```

---

### 5. Ruta catch-all en Laravel (`routes/web.php`)

Todas las rutas de Laravel devuelven `app.blade.php` para que Vue Router maneje la navegación interna:

```php
Route::get('/{any}', fn() => view('app'))->where('any', '.*');
```

---

### 6. Paleta de colores en CSS (`resources/css/app.css`)

Variables CSS globales con la paleta oficial de CLAUDE.md (sección 10):

```css
:root {
  --color-naranja-primario: #F26A21;
  --color-naranja-claro:    #FF8C42;
  --color-naranja-oscuro:   #C24E12;
  --color-carbon:           #3D3D3D;
  --color-exito:            #2E7D4F;
  --color-peligro:          #C62828;
  /* ...etc */
}
```

---

### 7. Playwright — pruebas E2E (`playwright.config.js`)

Configurado para correr contra `http://localhost` (puerto 80 del contenedor Sail) usando Chromium headless.

Servicio `playwright` agregado a `compose.yaml` con `profiles: [testing]` (no arranca por defecto).

---

## Comandos de uso diario

### Desarrollo (con HMR)

```bash
# Iniciar todos los contenedores (si no están corriendo)
./vendor/bin/sail up -d

# Correr Vite en modo desarrollo dentro del contenedor
./vendor/bin/sail npm run dev
```

Abrir en el navegador: **http://localhost**

---

### Build de producción

```bash
./vendor/bin/sail npm run build
```

---

## Pruebas E2E con Playwright

### Opción A — Playwright en el host (recomendado para desarrollo local rápido)

> Requiere que Sail esté corriendo (`./vendor/bin/sail up -d`) y que Node.js esté instalado en el host.

```bash
# Instalar navegadores de Playwright (solo la primera vez)
npx playwright install chromium

# Correr todas las pruebas E2E
npx playwright test

# Ver reporte HTML de resultados
npx playwright show-report tests/e2e/playwright-report

# Modo UI interactivo (útil para depurar)
npx playwright test --ui
```

### Opción B — Playwright dentro de Docker (sin instalar nada en el host)

```bash
# Levantar solo el servicio playwright (profile testing)
docker compose --profile testing run --rm playwright
```

> El servicio `playwright` usa `mcr.microsoft.com/playwright:v1.54.0-noble` y apunta a `http://laravel.test` (red interna de Docker).

---

## Tests disponibles

| Archivo | Prueba |
|---|---|
| `tests/e2e/inicio.spec.js` | Carga de la SPA, vista de inicio, indicador de estado, ruta 404 |

---

## Verificación rápida

```bash
# 1. Verificar que el build compila sin errores
./vendor/bin/sail npm run build

# 2. Iniciar Vite dev
./vendor/bin/sail npm run dev

# 3. Abrir http://localhost y verificar que aparece la pantalla de SGCO-Chayito
# 4. Probar ruta inexistente http://localhost/ruta-falsa → debe aparecer la pantalla 404
```

---

## Notas importantes

- **Nunca** llames a Axios directamente desde un componente Vue; usa siempre `@/services/api.js`.
- Las rutas privadas están protegidas por la guarda de navegación en `router/index.js` que verifica el rol del store `sesion.js`.
- El token de sesión se persiste en `localStorage` bajo la clave `sgco_token`.
- Toda función/método en JS/Vue debe llevar el encabezado JSDoc definido en CLAUDE.md sección 6.
- Clases Tailwind se agrupan por función: layout, color, tipografía (CLAUDE.md sección 7).
