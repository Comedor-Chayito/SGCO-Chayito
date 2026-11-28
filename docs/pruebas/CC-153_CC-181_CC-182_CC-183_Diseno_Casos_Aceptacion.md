# UNIVERSIDAD DE EL SALVADOR
## FACULTAD MULTIDISCIPLINARIA ORIENTAL
### DEPARTAMENTO DE INGENIERÍA Y ARQUITECTURA
### CARRERA DE INGENIERÍA EN SISTEMAS INFORMÁTICOS
**DISEÑO DE SISTEMAS II – CICLO II-2026**

---

# CASOS DE PRUEBA DE ACEPTACIÓN (CUADRANTE 3)
### TAREAS CC-153, CC-181, CC-182 Y CC-183
**Sistema de Gestión para el Comedor Chayito (SGCO-Chayito)**

- **Responsable:** César Josué
- **Sprint:** Sprint 2 (Completar 100 % del sistema y Plan de Pruebas v1 y v2)
- **Metodología:** Plantilla de Diseño Oficial de Pruebas (UES FMO - Diseño de Sistemas II)

---

## 1. Casos de Navegación, Vínculos y Redirecciones (CC-153)

---

### Caso de Prueba: CP-NAV-01
- **Nombre del caso:** Redirección automática inicial según el rol del usuario tras autenticación exitosa
- **Código:** `CP-NAV-01`
- **Versión:** 1.0
- **Sprint a realizarse:** Sprint 2
- **Responsable de escribir el caso:** César Josué
- **Descripción general:**
  - **Propósito:** Comprobar que al iniciar sesión, la guarda de navegación de Vue Router redirija al usuario a la pantalla de inicio designada para su rol funcional específico.
  - **Historia de usuario a probar:** US-ADM-02 (Autenticación y Sesiones) / RF-ADM-008.
  - **Módulo al que pertenece:** Core / Navegación y Seguridad.
- **Ambiente de Pruebas:**
  - **Precondiciones:**
    1. El servidor web de frontend (Vite) y la API de Laravel están levantados.
    2. Existen usuarios activos en la base de datos para cada rol oficial: `cajero`, `mesero`, `cocinero`, `contadora`, `socio` y `administrador`.
  - **Postcondiciones:** La URL del navegador y la vista renderizada corresponden a la pantalla inicial oficial de cada rol sin errores de redirección.
- **Desarrollo de la prueba:**
  - **Acciones:**
    1. Navegar a la pantalla de login: `http://localhost:8084/login`.
    2. Ingresar el correo y contraseña del usuario con rol `mesero` y pulsar "Iniciar Sesión". Verificar redirección. Cerrar sesión.
    3. Ingresar credenciales del usuario `cajero` y pulsar "Iniciar Sesión". Verificar redirección. Cerrar sesión.
    4. Ingresar credenciales del usuario `cocinero` y pulsar "Iniciar Sesión". Verificar redirección. Cerrar sesión.
    5. Ingresar credenciales de la usuaria `contadora` y pulsar "Iniciar Sesión". Verificar redirección. Cerrar sesión.
    6. Ingresar credenciales del usuario `socio` y pulsar "Iniciar Sesión". Verificar redirección. Cerrar sesión.
    7. Ingresar credenciales del usuario `administrador` y pulsar "Iniciar Sesión". Verificar redirección.
  - **Datos de Entrada:**
    - Mesero: `mesero@sgco-chayito.local` / `Password123!` $\rightarrow$ Esperado: `/pos`
    - Cajero: `cajero@sgco-chayito.local` / `Password123!` $\rightarrow$ Esperado: `/caja`
    - Cocinero: `cocina@sgco-chayito.local` / `Password123!` $\rightarrow$ Esperado: `/cocina`
    - Contadora: `contadora@sgco-chayito.local` / `Password123!` $\rightarrow$ Esperado: `/contabilidad`
    - Socio: `socio@sgco-chayito.local` / `Password123!` $\rightarrow$ Esperado: `/reportes`
    - Administrador: `admin@sgco-chayito.local` / `Admin1234!` $\rightarrow$ Esperado: `/pos`
- **Resultados:**
  - **Resultados Esperados:** Cada rol aterriza en su ruta designada conforme a la función `obtenerRutaInicioPorRol(rol)`. Se muestra la interfaz correspondiente con los datos cargados.

---

### Caso de Prueba: CP-NAV-02
- **Nombre del caso:** Verificación de enlaces visibles en la barra lateral según la matriz de navegación
- **Código:** `CP-NAV-02`
- **Versión:** 1.0
- **Sprint a realizarse:** Sprint 2
- **Responsable de escribir el caso:** César Josué
- **Descripción general:**
  - **Propósito:** Validar que la barra lateral de navegación renderice única y exclusivamente los módulos permitidos para el rol en sesión según el archivo de configuración `navegacion.js`.
  - **Historia de usuario a probar:** US-ADM-01 / RF-ADM-008.
  - **Módulo al que pertenece:** Interfaz de Usuario / Navegación Global.
- **Ambiente de Pruebas:**
  - **Precondiciones:** Sesión iniciada con un rol determinado.
  - **Postcondiciones:** Los enlaces visibles en el DOM coinciden exactamente con la lista autorizada en `ITEMS_NAVEGACION`.
- **Desarrollo de la prueba:**
  - **Acciones:**
    1. Iniciar sesión como `mesero`. Inspeccionar la barra lateral.
    2. Iniciar sesión como `cajero`. Inspeccionar la barra lateral.
    3. Iniciar sesión como `cocinero`. Inspeccionar la barra lateral.
    4. Iniciar sesión como `contadora`. Inspeccionar la barra lateral.
    5. Iniciar sesión como `administrador`. Inspeccionar la barra lateral.
  - **Datos de Entrada:** Cuentas con roles `mesero`, `cajero`, `cocinero`, `contadora`, `administrador`.
- **Resultados:**
  - **Resultados Esperados:**
    - El mesero solo visualiza el grupo "Operación" con "Punto de venta".
    - El cajero visualiza "Punto de venta" y "Caja".
    - El cocinero visualiza "Cocina", "Consultar inventario" y "Solicitar insumos".
    - La contadora visualiza "Contabilidad", "Compras e IVA" y "Exportar reportes".
    - El administrador visualiza los 4 grupos: Operación, Inventario, Finanzas y Configuración (con Usuarios y Catálogos).

---

### Caso de Prueba: CP-NAV-03
- **Nombre del caso:** Bloqueo e intercepción de navegación directa por URL a rutas no autorizadas
- **Código:** `CP-NAV-03`
- **Versión:** 1.0
- **Sprint a realizarse:** Sprint 2
- **Responsable de escribir el caso:** César Josué
- **Descripción general:**
  - **Propósito:** Comprobar que si un usuario con rol operativo intenta escribir manualmente en la barra de direcciones una ruta protegida (ej. `/admin` o `/configuracion`), la guarda global de Vue Router intercepte la acción y lo regrese a su pantalla de inicio.
  - **Historia de usuario a probar:** US-ADM-01 / RF-ADM-008.
  - **Módulo al que pertenece:** Core / Seguridad de Enrutamiento.
- **Ambiente de Pruebas:**
  - **Precondiciones:** Usuario autenticado con rol `mesero` o `cajero`.
  - **Postcondiciones:** No se expone la pantalla restringida; el usuario permanece en su ruta autorizada.
- **Desarrollo de la prueba:**
  - **Acciones:**
    1. Iniciar sesión como `mesero` (redirecciona a `/pos`).
    2. Escribir en la barra de direcciones del navegador: `http://localhost:8084/admin` y pulsar Enter.
    3. Escribir en la barra de direcciones: `http://localhost:8084/configuracion` y pulsar Enter.
    4. Escribir en la barra de direcciones: `http://localhost:8084/caja` y pulsar Enter.
    5. Escribir en la barra de direcciones: `http://localhost:8084/contabilidad` y pulsar Enter.
  - **Datos de Entrada:** URLs restringidas: `/admin`, `/configuracion`, `/caja`, `/contabilidad`.
- **Resultados:**
  - **Resultados Esperados:** La guarda `router.beforeEach` detecta que el rol `mesero` no forma parte de `to.meta.roles`. En todos los casos cancela la navegación no autorizada y redirige automáticamente de vuelta a `/pos`.

---

## 2. Casos de Aceptación Entrada, Proceso y Salida según ERS (CC-181)

---

### Caso de Prueba: CP-ACEP-01 (Pantalla de Entrada)
- **Nombre del caso:** Autenticación de usuario con validación de credenciales y feedback visual
- **Código:** `CP-ACEP-01`
- **Versión:** 1.0
- **Sprint a realizarse:** Sprint 2
- **Responsable de escribir el caso:** César Josué
- **Descripción general:**
  - **Propósito:** Evaluar la pantalla de entrada del sistema (Login) contra lo especificado en el ERS (campos requeridos, enmascaramiento de contraseña, bloqueo de credenciales inválidas y token Sanctum).
  - **Historia de usuario a probar:** US-ADM-02 (Autenticación de Usuarios).
  - **Módulo al que pertenece:** Seguridad / Autenticación.
- **Ambiente de Pruebas:**
  - **Precondiciones:** Servidor disponible, usuario creado en BD.
  - **Postcondiciones:** Token guardado en `localStorage` / store y transición al sistema.
- **Desarrollo de la prueba:**
  - **Acciones:**
    1. Cargar la pantalla de login.
    2. Intentar enviar el formulario vacío. Verificar validaciones HTML5/Vue.
    3. Ingresar correo inexistente o contraseña errónea y pulsar "Iniciar Sesión".
    4. Ingresar credenciales válidas y pulsar "Iniciar Sesión".
  - **Datos de Entrada:**
    - Intento 1: Campos vacíos.
    - Intento 2: `usuario_invalido@sgco.local` / `ClaveErronea123`.
    - Intento 3: `admin@sgco-chayito.local` / `Admin1234!`.
- **Resultados:**
  - **Resultados Esperados:**
    - Intento 1 y 2: Muestra mensaje de alerta claro ("Credenciales incorrectas" o "Campos obligatorios") con código HTTP 422/401. El usuario permanece en login.
    - Intento 3: Inicio de sesión exitoso, almacena token de sesión y redirige inmediatamente sin retrasos.

---

### Caso de Prueba: CP-ACEP-02 (Pantalla de Entrada)
- **Nombre del caso:** Apertura de turno de caja con registro de fondo inicial por denominación
- **Código:** `CP-ACEP-02`
- **Versión:** 1.0
- **Sprint a realizarse:** Sprint 2
- **Responsable de escribir el caso:** César Josué
- **Descripción general:**
  - **Propósito:** Validar la pantalla de entrada operativa de caja donde el cajero ingresa el fondo de cambio al iniciar su jornada.
  - **Historia de usuario a probar:** US-POS-03 (Apertura y Cierre de Caja / RF-POS-003).
  - **Módulo al que pertenece:** Caja / Operación.
- **Ambiente de Pruebas:**
  - **Precondiciones:** Usuario con rol `cajero` autenticado. No debe existir una apertura previa abierta en la misma fecha.
  - **Postcondiciones:** Registro de apertura creado en la tabla `aperturas_caja` con estado de caja abierta.
- **Desarrollo de la prueba:**
  - **Acciones:**
    1. Iniciar sesión como cajero y navegar a `/caja/apertura`.
    2. Verificar que se muestre el formulario con desglose por denominaciones ($0.01 a $100.00).
    3. Ingresar cantidades de monedas y billetes para sumar un saldo inicial de $50.00.
    4. Confirmar la apertura pulsando "Registrar Apertura".
    5. Intentar ingresar nuevamente a la pantalla de apertura.
  - **Datos de Entrada:** Fondo inicial: 50 billetes de $1.00 ($50.00). Observaciones: "Turno matutino".
- **Resultados:**
  - **Resultados Esperados:** Se registra la apertura exitosamente con código 201, se guarda la bitácora de auditoría y el sistema bloquea una segunda apertura para el mismo cajero en el mismo día.

---

### Caso de Prueba: CP-ACEP-03 (Pantalla de Proceso)
- **Nombre del caso:** Registro, selección de mesa y envío de comanda al área de cocina en el POS
- **Código:** `CP-ACEP-03`
- **Versión:** 1.0
- **Sprint a realizarse:** Sprint 2
- **Responsable de escribir el caso:** César Josué
- **Descripción general:**
  - **Propósito:** Probar el flujo del proceso central de venta: selección de mesa, agregación de platillos disponibles, cálculo reactivo de subtotal y envío a cocina.
  - **Historia de usuario a probar:** US-POS-01 (Toma de Comandas en Sala / RF-POS-001).
  - **Módulo al que pertenece:** Punto de Venta (POS).
- **Ambiente de Pruebas:**
  - **Precondiciones:** Sesión de `mesero` activa. Mesas y platillos disponibles en catálogo.
  - **Postcondiciones:** Registro de comanda en estado `pendiente` asociado a la mesa y visualización inmediata en cocina.
- **Desarrollo de la prueba:**
  - **Acciones:**
    1. Ingresar al POS (`/pos`).
    2. Seleccionar la Mesa 2 del salón.
    3. Agregar 2 órdenes de "Desayuno Típico" ($3.50 c/u) y 2 "Café de Olla" ($1.00 c/u).
    4. Comprobar que el carrito sume reactivamente $9.00.
    5. Agregar observación: "Sin crema en un desayuno".
    6. Presionar el botón "Enviar a Cocina".
  - **Datos de Entrada:** Mesa: 2. Canal: `mesa`. Detalles: 2x Desayuno ($7.00), 2x Café ($2.00). Total: $9.00.
- **Resultados:**
  - **Resultados Esperados:** La comanda se guarda con estado `pendiente`, el carrito se limpia, la mesa se marca con orden activa en el salón y la comanda aparece de inmediato en la cola de cocina.

---

### Caso de Prueba: CP-ACEP-04 (Pantalla de Proceso)
- **Nombre del caso:** Transición de estados de comanda en pantalla de preparación de cocina
- **Código:** `CP-ACEP-04`
- **Versión:** 1.0
- **Sprint a realizarse:** Sprint 2
- **Responsable de escribir el caso:** César Josué
- **Descripción general:**
  - **Propósito:** Validar que el personal de cocina visualice la orden entrante con sus observaciones y pueda actualizar los estados operativos del pedido.
  - **Historia de usuario a probar:** US-POS-01 (Gestión de Comandas en Cocina / RF-POS-001).
  - **Módulo al que pertenece:** Cocina / Procesos.
- **Ambiente de Pruebas:**
  - **Precondiciones:** Comanda en estado `pendiente` registrada. Sesión de `cocinero` activa en `/cocina`.
  - **Postcondiciones:** La comanda transiciona de estado y notifica al personal de sala.
- **Desarrollo de la prueba:**
  - **Acciones:**
    1. Entrar con rol `cocinero` a `/cocina`.
    2. Localizar la tarjeta de comanda de la Mesa 2.
    3. Verificar que se muestre el desglose: 2 Desayunos, 2 Cafés y la nota "Sin crema en un desayuno".
    4. Pulsar el botón "Tomar Pedido" (cambia a `en_cocina`).
    5. Una vez preparado, pulsar el botón "Marcar Listo".
  - **Datos de Entrada:** ID de comanda activa.
- **Resultados:**
  - **Resultados Esperados:** La tarjeta de la comanda actualiza su color y estado a `en_cocina` y luego a `listo`, reflejando la actualización en tiempo real en la vista del mesero.

---

### Caso de Prueba: CP-ACEP-05 (Pantalla de Salida)
- **Nombre del caso:** Cobro de comanda en caja, deducción automática de stock y emisión de ticket
- **Código:** `CP-ACEP-05`
- **Versión:** 1.0
- **Sprint a realizarse:** Sprint 2
- **Responsable de escribir el caso:** César Josué
- **Descripción general:**
  - **Propósito:** Validar la fase de salida transaccional: recepción del pago, cálculo de cambio, deducción automática de ingredientes de bodega según receta y emisión del ticket de venta.
  - **Historia de usuario a probar:** US-POS-02 (Cobro de Comandas / RF-POS-002) y US-INV-03 (Deducción automática).
  - **Módulo al que pertenece:** Caja / Salidas.
- **Ambiente de Pruebas:**
  - **Precondiciones:** Caja abierta. Comanda de $9.00 lista para cobro. Stock suficiente en insumos vinculados.
  - **Postcondiciones:** Comanda marcada como `pagada`, ticket emitido con correlativo y existencias de inventario descontadas.
- **Desarrollo de la prueba:**
  - **Acciones:**
    1. Entrar como `cajero` a `/caja`.
    2. Seleccionar la comanda de la Mesa 2 ($9.00).
    3. Seleccionar método de pago: Efectivo.
    4. Ingresar monto recibido: $10.00. Comprobar que el cambio calculado sea $1.00.
    5. Pulsar "Cobrar e Imprimir Ticket".
    6. Verificar la pantalla modal del ticket (`/caja/ticket/:id`).
  - **Datos de Entrada:** Comanda ID, Método: Efectivo, Monto recibido: $10.00.
- **Resultados:**
  - **Resultados Esperados:** La venta se procesa (201), el ticket se despliega con el formato oficial del Comedor Chayito (nombre, fecha, desglose de platillos, subtotal, total, cambio), y los insumos de bodega vinculados se descuentan automáticamente con precisión de 6 decimales.

---

## 3. Casos de Aceptación de Reportes y Salidas Financieras (CC-182)

---

### Caso de Prueba: CP-ACEP-06
- **Nombre del caso:** Generación y conciliación del reporte de cierre de caja de turno
- **Código:** `CP-ACEP-06`
- **Versión:** 1.0
- **Sprint a realizarse:** Sprint 2
- **Responsable de escribir el caso:** César Josué
- **Descripción general:**
  - **Propósito:** Probar la pantalla de salida del Cierre de Caja (conciliación entre ventas esperadas del sistema y arqueo físico del cajero, cálculo de sobrante/faltante).
  - **Historia de usuario a probar:** US-POS-03 (Arqueo y Cierre de Caja / RF-POS-003).
  - **Módulo al que pertenece:** Caja / Reportes de Turno.
- **Ambiente de Pruebas:**
  - **Precondiciones:** Turno de caja abierto con ventas registradas en el día.
  - **Postcondiciones:** Registro de cierre en `cierres_caja`, caja bloqueada para nuevas transacciones y bitácora de auditoría generada.
- **Desarrollo de la prueba:**
  - **Acciones:**
    1. Iniciar sesión como `cajero` y navegar a `/caja/cierre`.
    2. Verificar que el sistema muestre el saldo inicial de apertura ($50.00) y las ventas totales en efectivo registradas.
    3. Ingresar el recuento físico por denominación.
    4. Comprobar la fórmula: $\text{Diferencia} = \text{Monto Físico} - (\text{Fondo Inicial} + \text{Ventas} - \text{Retiros})$.
    5. Confirmar el cierre del turno.
  - **Datos de Entrada:** Conteo físico igual al esperado (Diferencia = $0.00). Observación: "Cierre conforme sin novedades".
- **Resultados:**
  - **Resultados Esperados:** El reporte de cierre se genera con el desglose exacto de ventas en efectivo/tarjeta, retiros, fondo inicial y diferencia neta. La caja queda formalmente cerrada.

---

### Caso de Prueba: CP-ACEP-07
- **Nombre del caso:** Visualización de métricas financieras e indicadores en el Dashboard Gerencial
- **Código:** `CP-ACEP-07`
- **Versión:** 1.0
- **Sprint a realizarse:** Sprint 2
- **Responsable de escribir el caso:** César Josué
- **Descripción general:**
  - **Propósito:** Validar que los socios y administradores puedan consultar de forma gráfica y consolidada los KPIs de ventas, utilidad estimada, platillos más vendidos y flujo financiero.
  - **Historia de usuario a probar:** US-REP-01 (Dashboard Gerencial / RF-REP-006).
  - **Módulo al que pertenece:** Reportes / Inteligencia de Negocio.
- **Ambiente de Pruebas:**
  - **Precondiciones:** Sesión activa con rol `socio` o `administrador`. Ventas y egresos sembrados en la base de datos.
  - **Postcondiciones:** Las métricas mostradas en las gráficas coinciden con los registros transaccionales.
- **Desarrollo de la prueba:**
  - **Acciones:**
    1. Iniciar sesión como `socio` (aterriza en `/reportes`).
    2. Verificar las tarjetas de métricas principales (Ventas del día, Ventas del mes, Total Egresos, Balance neto).
    3. Probar el filtro de período temporal (Hoy, Últimos 7 días, Este mes).
    4. Verificar la gráfica de barras de ventas diarias y el ranking de platillos más vendidos.
  - **Datos de Entrada:** Rango de fechas: Mes en curso.
- **Resultados:**
  - **Resultados Esperados:** Los datos cargan sin demoras (código 200), los números concuerdan con el libro contable de ventas y egresos, y los componentes de visualización renderizan responsivamente.

---

### Caso de Prueba: CP-ACEP-08
- **Nombre del caso:** Exportación de reportes consolidados para contabilidad y libro de IVA
- **Código:** `CP-ACEP-08`
- **Versión:** 1.0
- **Sprint a realizarse:** Sprint 2
- **Responsable de escribir el caso:** César Josué
- **Descripción general:**
  - **Propósito:** Probar la funcionalidad de exportación de datos en formato descargable compatible con hojas de cálculo para la declaración fiscal de la contadora.
  - **Historia de usuario a probar:** US-REP-02 (Exportación de Reportes / RF-REP-007).
  - **Módulo al que pertenece:** Reportes / Contabilidad.
- **Ambiente de Pruebas:**
  - **Precondiciones:** Sesión activa con rol `contadora` o `administrador`.
  - **Postcondiciones:** Descarga en el navegador de archivo `.xlsx` o `.csv` con la estructura oficial.
- **Desarrollo de la prueba:**
  - **Acciones:**
    1. Iniciar sesión como `contadora` y acceder a `/reportes/exportar`.
    2. Seleccionar tipo de reporte: "Libro de Compras e IVA Crédito Fiscal".
    3. Indicar rango de fechas del mes a declarar.
    4. Presionar el botón "Exportar Reporte".
    5. Abrir el archivo descargado y validar columnas (Fecha, Proveedor, Comprobante, Costo Neto, IVA, Total).
  - **Datos de Entrada:** Tipo: Compras IVA, Fecha inicial: 01/11/2026, Fecha final: 30/11/2026.
- **Resultados:**
  - **Resultados Esperados:** Se genera la descarga directa con cabecera `Content-Type` de hoja de cálculo. Los registros coinciden fielmente con los egresos registrados en el sistema.

---

## 4. Casos de Verificación de Mínimo Privilegio y RBAC en UI (CC-183)

---

### Caso de Prueba: CP-RBAC-01
- **Nombre del caso:** Verificación con el usuario de ocultamiento estricto de opciones administrativas en roles operativos
- **Código:** `CP-RBAC-01`
- **Versión:** 1.0
- **Sprint a realizarse:** Sprint 2
- **Responsable de escribir el caso:** César Josué
- **Descripción general:**
  - **Propósito:** Demostrar y validar junto con el usuario final que los roles operativos de primera línea (mesero, cocinero) no visualicen ningún botón, menú ni enlace hacia finanzas, usuarios o catálogos.
  - **Historia de usuario a probar:** US-ADM-01 / RF-ADM-008.
  - **Módulo al que pertenece:** Seguridad / Control de Acceso UI.
- **Ambiente de Pruebas:**
  - **Precondiciones:** Sesión de `mesero` o `cocinero` iniciada.
  - **Postcondiciones:** Ningún elemento visual administrativo está presente en el árbol DOM.
- **Desarrollo de la prueba:**
  - **Acciones:**
    1. Abrir el sistema con el usuario de doña Chayito en rol de supervisión.
    2. Iniciar sesión como `mesero`. Inspeccionar interfaz general.
    3. Comprobar que en el encabezado, barra lateral y perfiles no exista acceso a "Configuración", "Usuarios", "Bitácora", "Caja", ni "Contabilidad".
    4. Repetir la inspección con el rol `cocinero`.
  - **Datos de Entrada:** Usuarios de sala y cocina.
- **Resultados:**
  - **Resultados Esperados:** La interfaz muestra únicamente las herramientas indispensables para el puesto operativo. No se observan elementos bloqueados en gris ni enlaces rotos. El usuario valida la conformidad de la vista.

---

### Caso de Prueba: CP-RBAC-02
- **Nombre del caso:** Actualización de permisos en la matriz por el Administrador y verificación de reflejo inmediato en el usuario
- **Código:** `CP-RBAC-02`
- **Versión:** 1.0
- **Sprint a realizarse:** Sprint 2
- **Responsable de escribir el caso:** César Josué
- **Descripción general:**
  - **Propósito:** Comprobar que al modificar la matriz de permisos de un rol desde la interfaz de administración (CC-122 / CC-123), los cambios de autorización se reflejen en tiempo real en los usuarios asignados a dicho rol.
  - **Historia de usuario a probar:** US-ADM-04 (Matriz de Permisos y Auditoría / RF-ADM-008).
  - **Módulo al que pertenece:** Administración y Configuración.
- **Ambiente de Pruebas:**
  - **Precondiciones:** Administrador autenticado en `/admin/permisos`.
  - **Postcondiciones:** Permisos actualizados en base de datos, bitácora registrada y comportamiento dinámico verificado.
- **Desarrollo de la prueba:**
  - **Acciones:**
    1. El Administrador accede a la pestaña "Matriz de Permisos" en `/admin`.
    2. Ubicar la fila del permiso `comandas.crear` para el rol `cajero` (inicialmente denegado).
    3. Asignar el permiso y guardar los cambios.
    4. Verificar en `/admin/bitacora` que la acción figure como `permisos_rol_actualizados`.
    5. Iniciar sesión en otra pestaña con el usuario `cajero`.
    6. Verificar que el cajero ahora pueda crear comandas en el POS.
  - **Datos de Entrada:** Permiso: `comandas.crear` para rol `cajero`.
- **Resultados:**
  - **Resultados Esperados:** La matriz guarda los cambios (200 OK), la bitácora registra al administrador como responsable con el detalle de permisos agregados, y el cajero adquiere la funcionalidad inmediatamente sin necesidad de reiniciar el servidor.
