# CC-186 y CC-189: Ejecución de Pruebas de Aceptación (Ciclos v1 y v2) y Matriz de Control de Incidencias
## B5 - Cuadrante 3: Pruebas de Aceptación — SGCO-Chayito
**Responsable de Ejecución:** César Josué  
**Materia:** Diseño de Sistemas II — Universidad de El Salvador (FMO)  
**Entorno de Ejecución:** Docker Container (`laravel.test` / PHP 8.2 / MariaDB 10.11 / Vite / Vue 3 / Selenium WebDriver / Chrome 131)  
**URL Base de Pruebas:** `http://localhost:8084`  

---

## 1. Resumen Ejecutivo de los Ciclos de Ejecución

El Cuadrante 3 (Pruebas de Aceptación) abarca **13 casos de prueba de diseño formal** clasificados en:
- Navegación y Vínculos: `CP-NAV-01`, `CP-NAV-02`, `CP-NAV-03`.
- Aceptación por Pantalla (Entrada, Proceso y Salida): `CP-ACEP-01` a `CP-ACEP-08`.
- Control de Acceso y Mínimo Privilegio (RBAC): `CP-RBAC-01`, `CP-RBAC-02`.

### Comparativa de Resultados entre Ciclos:
```text
+-----------------------+------------+------------+---------------+
| Métrica               | Ciclo v1   | Ciclo v2   | Variación     |
+-----------------------+------------+------------+---------------+
| Total Casos Ejecutados| 13         | 13         | 0             |
| Casos Aprobados       | 10 (76.9%) | 13 (100%)  | +23.1%        |
| Casos No Aprobados    | 3 (23.1%)  | 0 (0.0%)   | -23.1%        |
| Incidencias Detectadas| 3          | 0 (Nuevas) | -3 (Resueltas)|
| Tasa de Éxito Final   | 76.9%      | 100.0%     | Meta Cumplida |
+-----------------------+------------+------------+---------------+
```

---

## 2. Ejecución v1 — Cuadrante 3 (CC-186)

A continuación se detalla la ejecución inicial de los 13 casos de prueba aplicando la **Plantilla Oficial de Ejecución de Pruebas**:

### Tabla de Ejecución v1:
| ID Caso | Nombre del Caso | Precondiciones Verificadas | Datos de Entrada Utilizados | Resultado Obtenido | Estado v1 | Incidencia Asociada |
| :---: | :--- | :--- | :--- | :--- | :---: | :---: |
| **CP-NAV-01** | Redirección inicial según rol | BD sembrada con 6 roles activos | Credenciales de admin, cajero, mesero, cocinero, contadora, socio | Cada rol es redirigido a su vista inicial respectiva tras login exitoso. | **Aprobado** | N/A |
| **CP-NAV-02** | Visualización de menús permitidos | Sesiones activas por rol en AppShell | Exploración de enlaces en `<aside>` | Se validó que el mesero solo ve POS y el cocinero solo ve Cocina/Inventario. | **Aprobado** | N/A |
| **CP-NAV-03** | Consistencia de enlaces y migas de pan | Navegación entre vistas secundarias | Clics en `/caja/apertura`, `/reportes/exportar` | La navegación secundaria mantiene la selección de sección activa. | **Aprobado** | N/A |
| **CP-ACEP-01** | Pantalla de Entrada: Login con credenciales válidas e inválidas | Sistema en `/login` | `admin@sgco-chayito.local` / `Admin1234!`; prueba negativa con clave errónea | Valida obligatoriedad y formato. Con datos incorrectos despliega alerta roja de error. | **Aprobado** | N/A |
| **CP-ACEP-02** | Pantalla de Entrada: Registro de apertura y saldo inicial de caja | Usuario cajero en `/caja/apertura` | 2 billetes \$20, 1 billete \$10 (\$50.00 total) | El formulario sumó \$50.00 en tiempo real y registró la apertura habilitando la caja. | **Aprobado** | N/A |
| **CP-ACEP-03** | Pantalla de Proceso: Registro de comanda táctil en POS | Menú activo con platillos disponibles | Canal "En Mesa", Mesa 2, 2 Pupusas, Nota: "Bien tostadas" | Se agregó al ticket, generó comanda #15 y envió notificación toast a cocina. | **Aprobado** | N/A |
| **CP-ACEP-04** | Pantalla de Salida: Cobro de ticket, desglose de IVA y cálculo de cambio | Comanda lista en `/caja`, caja abierta | Comanda total \$6.00, pago en efectivo con \$10.00 | Al ingresar \$10.00 en campo decimal rápido, el vuelto en centavos presentó desfase de precisión `3.9999999` en lugar de `4.00`. | **No Aprobado** | **IN-CP-ACEP-04** |
| **CP-ACEP-05** | Pantalla de Salida: Reporte de cierre de caja y arqueo | Cobros realizados durante el turno | Turno con \$50 inicial + \$6 cobrado | La pantalla mostró el arqueo teórico (\$56.00), pero el botón de imprimir no formateaba correctamente en vista móvil estrecha. | **No Aprobado** | **IN-CP-ACEP-05** |
| **CP-ACEP-06** | Pantalla de Salida: Dashboard Gerencial de ventas | Reportes con datos históricos en BD | Filtro de rango semanal y mensual | Gráficos cargan métricas agregadas de ventas e ingresos por categoría. | **Aprobado** | N/A |
| **CP-ACEP-07** | Pantalla de Salida: Exportación de reportes contables | Usuario con rol contadora/socio | Exportación de Libro de Compras IVA a CSV/Excel | Archivo descargado con estructura de columnas requeridas por el Ministerio de Hacienda. | **Aprobado** | N/A |
| **CP-ACEP-08** | Pantalla de Proceso: Gestión de insumos y requisiciones | Cocinero en `/requisiciones/nueva` | Solicitud de 10 lbs de Harina de Maíz | Se registró la requisición en estado pendiente de aprobación gerencial. | **Aprobado** | N/A |
| **CP-RBAC-01** | Protección de rutas sin autenticación | Sesión cerrada en navegador | Intento de URL directa a `http://localhost:8084/caja` | Router interceptó la petición y redirigió a `/login` protegiendo la ruta. | **Aprobado** | N/A |
| **CP-RBAC-02** | Mínimo privilegio: Acceso no autorizado por rol en URL | Mesero autenticado en `/pos` | Mesero intentando navegar a `/admin/permisos` | El Router redirigió al mesero a `/admin` en lugar de retornarlo a su inicio `/pos`, mostrando pantalla vacía sin permisos. | **No Aprobado** | **IN-CP-RBAC-02** |

---

## 3. Formato Oficial de Control de Incidencias (Bugs Detectados en v1)

| ID Incidencia | Caso Origen | Severidad | Descripción del Defecto | Causa Raíz Identificada | Solución Técnica Aplicada | Estado Final |
| :---: | :---: | :---: | :--- | :--- | :--- | :---: |
| **IN-CP-ACEP-04** | CP-ACEP-04 | **Media** | El cálculo reactivo del cambio a entregar en efectivo presentaba errores de redondeo de punto flotante en Javascript (`3.9999999` en vez de `4.00`). | Uso de resta aritmética directa en coma flotante `montoRecibido - total` sin truncamiento/redondeo a 2 decimales en el composable `useCalculoCambio.js`. | Se aplicó `Math.round((monto - total) * 100) / 100` y formato monetario con `toFixed(2)` en `useCalculoCambio.js`. | **Resuelta y Verificada** |
| **IN-CP-ACEP-05** | CP-ACEP-05 | **Baja** | En pantallas con ancho menor a 380px, la cabecera del reporte de cierre de caja desbordaba el botón de impresión fuera del viewport. | Falta de regla responsiva `flex-wrap` en el contenedor de acciones de `ReporteCierreCaja.vue`. | Se añadieron clases Tailwind `flex-wrap gap-2 sm:flex-nowrap` asegurando adaptabilidad en dispositivos móviles. | **Resuelta y Verificada** |
| **IN-CP-RBAC-02** | CP-RBAC-02 | **Alta** | Al ingresar directamente a `/admin/permisos` siendo mesero, el alias de redirección procesaba el redirect antes de verificar el guard de rol, exponiendo ruta intermedia. | La ruta `/admin/permisos` tenía redirect antes de validar `meta.roles` en la jerarquía del router de Vue 3. | Se ajustó `router.beforeEach` en `resources/js/router/index.js` para evaluar los roles del destino final (`to.matched`) antes de resolver redirects hijos. | **Resuelta y Verificada** |

---

## 4. Ejecución v2 — Cuadrante 3 (CC-189)

Tras aplicar los parches y correcciones para las incidencias `IN-CP-ACEP-04`, `IN-CP-ACEP-05` e `IN-CP-RBAC-02`, se procedió con la **Re-ejecución Integral (Ciclo v2)**:

### Tabla de Re-ejecución v2:
| ID Caso | Nombre del Caso | Acción en v2 | Resultado Obtenido en v2 | Estado v2 | Verificación de Incidencia |
| :---: | :--- | :--- | :--- | :---: | :---: |
| **CP-NAV-01** | Redirección inicial según rol | Prueba de regresión | Redirecciones funcionales y estables para los 6 roles. | **Aprobado** | N/A |
| **CP-NAV-02** | Visualización de menús permitidos | Prueba de regresión | Barra lateral estricta según Mínimo Privilegio. | **Aprobado** | N/A |
| **CP-NAV-03** | Consistencia de enlaces y navegación | Prueba de regresión | Enlaces y navegación fluida sin enlaces rotos. | **Aprobado** | N/A |
| **CP-ACEP-01** | Pantalla Entrada: Login | Prueba de regresión | Autenticación y mensajes de validación confirmados. | **Aprobado** | N/A |
| **CP-ACEP-02** | Pantalla Entrada: Apertura de caja | Prueba de regresión | Apertura por denominación funcional. | **Aprobado** | N/A |
| **CP-ACEP-03** | Pantalla Proceso: Comanda POS | Prueba de regresión | Creación táctil y envío inmediato a cola de cocina. | **Aprobado** | N/A |
| **CP-ACEP-04** | Pantalla Salida: Cobro y cambio | **Re-ejecución de prueba fallida** | El cambio se calcula de manera exacta: `$10.00 - $6.00 = $4.00`. Formato de moneda impecable. | **Aprobado** | **IN-CP-ACEP-04 Cerrada** |
| **CP-ACEP-05** | Pantalla Salida: Cierre de caja | **Re-ejecución de prueba fallida** | Botón de impresión y encabezado se ajustan fluidamente en móviles sin desbordamiento. | **Aprobado** | **IN-CP-ACEP-05 Cerrada** |
| **CP-ACEP-06** | Pantalla Salida: Dashboard Gerencial | Prueba de regresión | Indicadores KPI y gráficos consolidados correctamente. | **Aprobado** | N/A |
| **CP-ACEP-07** | Pantalla Salida: Exportación Reportes | Prueba de regresión | Exportación a CSV/Excel completa y estructurada. | **Aprobado** | N/A |
| **CP-ACEP-08** | Pantalla Proceso: Insumos/Requisiciones | Prueba de regresión | Registro de requerimientos sin contratiempos. | **Aprobado** | N/A |
| **CP-RBAC-01** | Protección de rutas sin login | Prueba de regresión | Redirección forzada a `/login` garantizada. | **Aprobado** | N/A |
| **CP-RBAC-02** | Mínimo privilegio en URL directa | **Re-ejecución de prueba fallida** | El mesero que intenta ingresar a `/admin/permisos` es devuelto de inmediato a su vista `/pos`. | **Aprobado** | **IN-CP-RBAC-02 Cerrada** |

---

## 5. Conclusiones de las Pruebas de Aceptación (Cuadrante 3)

1. **Cumplimiento de Criterios de Aceptación:**
   - Se verificó al 100% el mapa de navegación para cada uno de los 6 roles oficiales del comedor.
   - Las pantallas de Entrada, Proceso y Salida fueron contrastadas contra el ERS y validadas con el usuario en vivo.
   - El usuario recibió previamente la Guía Rápida de Uso (CC-184).
   - La sesión de aceptación quedó formalizada con la encuesta respondida y acta firmada (CC-155 y CC-156).
2. **Efectividad del Proceso de Calidad:**
   - La ejecución v1 detectó con precisión 3 incidencias no críticas pero de alto impacto en usabilidad y seguridad.
   - Tras las correcciones implementadas, la re-ejecución v2 certificó una **tasa de aprobación del 100% (13/13 casos aprobados)**.
   - El sistema queda dictaminado como **APROBADO PARA PRODUCCIÓN**.
