# Implementación de CC-75 y CC-76 — Dashboard Gerencial

**Autor:** Jeferson De La Cruz  
**Historia:** US-REP-01 — Dashboard Gerencial e Indicadores Financieros  
**Referencia ERS:** RF-REP-007  
**Fecha:** 2026-10-23

## CC-75 — Mostrar KPIs de salud financiera y margen bruto

Se creó `DashboardGerencialService`, que consolida en tiempo real los datos ya registrados
en ventas y compras. El dashboard presenta:

- Ingresos: suma de ventas del periodo.
- Gastos netos: suma de `costo_neto` de compras, sin considerar el IVA crédito fiscal como gasto.
- Utilidad bruta: ingresos menos gastos netos.
- Margen bruto: `(utilidad bruta / ingresos) × 100`.
- Cantidad de ventas y compras que integran el cálculo.
- Gráfica de barras interactiva con las ventas de los últimos seis meses.
- Gráfica de dona interactiva con el aporte estimado de rentabilidad por platillo.

El periodo puede filtrarse por fecha inicial y final; por defecto muestra desde el primer día
del mes hasta la fecha actual. Los datos se obtienen desde `GET /api/reportes/dashboard`.

Para la daily: “Trabajé en la CC-75 implementando los KPIs financieros del dashboard. Ahora
se consolidan ingresos de ventas, gastos netos de compras, utilidad y margen bruto en tiempo
real, con filtro por periodo para apoyar decisiones gerenciales. También agregué gráficas
interactivas de ventas mensuales y de rentabilidad estimada por platillo.”

### Criterio de estimación de rentabilidad por platillo

El sistema actual guarda precio y cantidad vendida, pero todavía no guarda el costo de receta
por platillo. Por eso, la dona usa datos reales de ventas y distribuye la utilidad bruta global
del periodo de forma proporcional al ingreso de cada platillo. Tanto el API como la interfaz la
identifican explícitamente como **estimada**; no se presenta como costo individual exacto.

Las dos gráficas son navegables con ratón, foco y teclado: al seleccionar una barra o segmento,
se actualiza su detalle con importe, unidades e ingresos correspondientes.

### Datos históricos de demostración

Para visualizar el comportamiento de las gráficas se agregó el seeder opcional
`DashboardHistoricoSeeder`. Genera seis meses de ventas pagadas, detalle por cinco platillos y
una compra con IVA por mes. Todos los registros son ficticios, llevan el marcador
`DEMO-DASHBOARD` y el seeder es idempotente: ejecutarlo de nuevo no duplica la información.

Úsalo únicamente en desarrollo local:

```bash
docker compose exec laravel.test php artisan db:seed --class=DashboardHistoricoSeeder
```

Después, recarga `/reportes` como Administrador o Socio. El seeder no se invoca desde
`DatabaseSeeder`, por lo que no se carga automáticamente en otros ambientes.

## CC-76 — Restringir el dashboard al rol Socio/Administrador

Se incorporó el rol oficial `socio` a la matriz RBAC. Tanto Socio como Administrador tienen
acceso completo a los permisos, pero el dashboard se restringe específicamente a esos dos
roles en tres capas:

1. API: middleware `auth:sanctum` y `role:socio|administrador`.
2. Frontend: ruta `/reportes` con metadatos de roles permitidos.
3. Inicio: el botón “Dashboard gerencial” solo se muestra para esos roles.

La Contadora mantiene acceso a Contabilidad, pero recibe `403` si intenta consultar el
dashboard gerencial directamente.

Para la daily: “También completé la CC-76: protegí el dashboard en backend y frontend para
que únicamente Socio o Administrador consulten información financiera estratégica.”

## Puntos de prueba manual

- Iniciar sesión como `administrador` o `socio`.
- Desde Inicio, presionar `Dashboard gerencial`, o navegar a `/reportes`.
- Ajustar las fechas y presionar `Actualizar`.
- Verificar las tarjetas: Ingresos, Gastos netos, Utilidad bruta y Margen bruto.
- En `Ventas mensuales`, seleccionar una barra para consultar el importe de ese mes.
- En `Rentabilidad por platillo`, seleccionar un segmento de dona o un elemento de la leyenda
  para consultar unidades, ingresos y utilidad estimada.
- Iniciar sesión como `contadora` e intentar `/reportes`: debe redirigir en la SPA y el API
  `/api/reportes/dashboard` debe rechazar el acceso.
