# UNIVERSIDAD DE EL SALVADOR
## FACULTAD MULTIDISCIPLINARIA ORIENTAL
### DEPARTAMENTO DE INGENIERÍA Y ARQUITECTURA
### CARRERA DE INGENIERÍA EN SISTEMAS INFORMÁTICOS
**DISEÑO DE SISTEMAS II – CICLO II-2026**

---

# TAREA CC-152: MAPA CONCEPTUAL DE NAVEGACIÓN POR ROL
### SUB-MÓDULO B5: CUADRANTE 3 – PRUEBAS DE ACEPTACIÓN
**Sistema de Gestión para el Comedor Chayito (SGCO-Chayito)**

- **Responsable:** César Josué
- **Sprint:** Sprint 2 (Completar 100 % del sistema y Plan de Pruebas v1 y v2)
- **Criterio de Aceptación:** *Cada rol tiene su mapa de navegación verificado.*

---

## 1. Introducción y Fundamento Arquitectónico

En el sistema **SGCO-Chayito**, la navegación se gestiona mediante una arquitectura de aplicación de página única (**SPA - Single Page Application**) con **Vue Router 4** y un almacén de estado reactivo (**Pinia**). La experiencia de usuario y las interfaces disponibles están estrictamente gobernadas por el principio de **Mínimo Privilegio (Principle of Least Privilege)** y control de acceso basado en roles (**RBAC** con Spatie en el backend y meta-roles en Vue Router).

La fuente de verdad de los accesos visibles se encuentra centralizada en:
- [`resources/js/shared/config/navegacion.js`](file:///c:/Users/josue/Documents/GitHub/SGCO-Chayito/resources/js/shared/config/navegacion.js) (Define los grupos e ítems de navegación lateral).
- [`resources/js/router/index.js`](file:///c:/Users/josue/Documents/GitHub/SGCO-Chayito/resources/js/router/index.js) (Define las rutas, restricciones por rol y la guarda global `router.beforeEach`).

---

## 2. Matriz Consolidada de Rutas y Accesibilidad por Rol

| Módulo / Grupo | Ruta en SPA | Nombre de Ruta | Roles Autorizados | Redirección de Inicio por Defecto |
| :--- | :--- | :--- | :--- | :---: |
| **Público** | `/login` | `login` | Todos (pública sin token) | – |
| **Operación** | `/pos` | `pos` | `mesero`, `cajero`, `administrador`, `socio` | **Mesero**, **Administrador** |
| **Operación** | `/cocina` | `cocina` | `cocinero`, `cocinera`, `administrador`, `socio` | **Cocinero**, **Cocinera** |
| **Operación** | `/caja` | `caja` | `cajero`, `administrador`, `socio` | **Cajero** |
| **Operación** | `/caja/apertura` | `caja-apertura` | `cajero`, `administrador`, `socio` | – |
| **Operación** | `/caja/ticket/:id` | `ticket-venta` | `cajero`, `administrador`, `socio` | – |
| **Operación** | `/caja/cierre` | `cierre-caja` | `cajero`, `administrador`, `socio` | – |
| **Inventario** | `/inventario` | `inventario` | `cocinero`, `cocinera`, `administrador`, `socio` | – |
| **Inventario** | `/requisiciones/nueva`| `nueva-requisicion` | `cocinero`, `cocinera`, `administrador`, `socio` | – |
| **Inventario** | `/requisiciones` | `gestion-requisiciones` | `administrador`, `socio` | – |
| **Finanzas** | `/contabilidad` | `contabilidad` | `contadora`, `administrador`, `socio` | **Contadora** |
| **Finanzas** | `/contabilidad/compras`| `compras-iva` | `contadora`, `administrador`, `socio` | – |
| **Finanzas** | `/reportes` | `reportes` | `socio`, `administrador` | **Socio** |
| **Finanzas** | `/reportes/exportar`| `exportar-reportes` | `contadora`, `socio`, `administrador` | – |
| **Configuración** | `/admin` | `admin` | `administrador`, `socio` | – |
| **Configuración** | `/matriz-permisos` | `matriz-permisos` | `administrador`, `socio` | – |
| **Configuración** | `/bitacora` | `bitacora` | `administrador`, `socio` | – |
| **Configuración** | `/configuracion` | `configuracion` | `administrador`, `socio` | – |

---

## 3. Mapa Conceptual General de Navegación del Sistema

```mermaid
graph TD
    LOGIN["Pantalla de Autenticación (/login)"] -->|Autenticación Exitosa| DISPATCHER{"Evaluar Rol (obtenerRutaInicioPorRol)"}

    DISPATCHER -->|Rol: mesero| POS["Punto de Venta (/pos)"]
    DISPATCHER -->|Rol: cocinero/a| COCINA["Cola de Cocina (/cocina)"]
    DISPATCHER -->|Rol: cajero| CAJA["Pantalla de Cobro y Caja (/caja)"]
    DISPATCHER -->|Rol: contadora| CONTA["Contabilidad y Egresos (/contabilidad)"]
    DISPATCHER -->|Rol: socio| REPORTES["Dashboard Gerencial (/reportes)"]
    DISPATCHER -->|Rol: administrador| POS_ADMIN["Punto de Venta / Acceso Global (/pos)"]

    subgraph Modulo_Operacion ["Módulo Operación"]
        POS -->|Selección Mesa/Canal| COMANDA["Toma de Comanda y Pedidos"]
        CAJA --> CAJA_APER["Apertura de Caja (/caja/apertura)"]
        CAJA --> CAJA_TICKET["Emisión de Ticket (/caja/ticket/:id)"]
        CAJA --> CAJA_CIERRE["Cierre de Turno (/caja/cierre)"]
        COCINA --> PREPARACION["Gestión de Estados (En preparación / Listo)"]
    end

    subgraph Modulo_Inventario ["Módulo Inventario"]
        INV["Consulta Stock y Alertas (/inventario)"]
        REQ_NUEVA["Solicitar Insumos (/requisiciones/nueva)"]
        REQ_GESTION["Aprobar / Transferir Requisiciones (/requisiciones)"]
        MOV_BODEGA["Entradas y Ajustes de Bodega"]
    end

    subgraph Modulo_Finanzas ["Módulo Finanzas y Contabilidad"]
        CONTA --> EGRESOS["Carga de Facturas y Egresos"]
        CONTA --> COMPRAS_IVA["Compras y Crédito Fiscal IVA (/contabilidad/compras)"]
        REPORTES --> DASHBOARD["Gráficas de Ventas y Balance"]
        REPORTES --> EXPORTAR["Exportación de Reportes Excel (/reportes/exportar)"]
    end

    subgraph Modulo_Configuracion ["Módulo Administración y Configuración"]
        ADMIN_USUARIOS["Gestión de Usuarios y Roles (/admin)"]
        ADMIN_MATRIZ["Matriz de Permisos RBAC (/admin/permisos)"]
        ADMIN_BITACORA["Bitácora de Auditoría (/admin/bitacora)"]
        CONFIG_CATALOGOS["Catálogos: Platillos, Mesas, Subcategorías (/configuracion)"]
    end
```

---

## 4. Mapas Conceptuales Detallados por Rol

### 4.1. Rol: Administrador General
El Administrador dispone de navegación irrestricta a todos los módulos. Su vista de inicio por defecto es el Punto de Venta (`/pos`) o el módulo de gestión.

```mermaid
graph LR
    ADMIN((Administrador)) --> OP["Operación"]
    ADMIN --> INV["Inventario"]
    ADMIN --> FIN["Finanzas"]
    ADMIN --> CONF["Configuración"]

    OP --> OP_POS["/pos (Punto de Venta)"]
    OP --> OP_COCINA["/cocina (Cola Cocina)"]
    OP --> OP_CAJA["/caja (Gestión Caja)"]

    INV --> INV_CONS["/inventario (Stock)"]
    INV --> INV_REQ["/requisiciones (Gestión)"]
    INV --> INV_MOV["Entradas y Ajustes Bodega"]

    FIN --> FIN_CONTA["/contabilidad (Egresos)"]
    FIN --> FIN_IVA["/contabilidad/compras (IVA)"]
    FIN --> FIN_REP["/reportes (Dashboard)"]
    FIN --> FIN_EXP["/reportes/exportar (Excel)"]

    CONF --> CONF_USR["/admin (Usuarios)"]
    CONF --> CONF_MAT["/matriz-permisos (RBAC)"]
    CONF --> CONF_BIT["/bitacora (Auditoría)"]
    CONF --> CONF_CAT["/configuracion (Catálogos)"]
```

---

### 4.2. Rol: Socio Gerencial
El Socio accede con perfil de supervisión, auditoría y toma de decisiones. No realiza tareas operativas de sala ni puede desactivar la cuenta del administrador.

```mermaid
graph LR
    SOCIO((Socio)) --> FIN_S["Supervisión Financiera"]
    SOCIO --> AUD_S["Auditoría y Matriz"]
    SOCIO --> INV_S["Consulta de Inventario"]

    FIN_S --> S_REP["/reportes (Dashboard KPIs)"]
    FIN_S --> S_EXP["/reportes/exportar (Reportes)"]
    FIN_S --> S_CONTA["/contabilidad (Supervisión Egresos)"]

    AUD_S --> S_BIT["/bitacora (Historial de Auditoría)"]
    AUD_S --> S_MAT["/matriz-permisos (Consulta RBAC)"]

    INV_S --> S_INV["/inventario (Alertas y Existencias)"]
    INV_S --> S_CAT["/configuracion (Catálogos de Precios)"]
```

---

### 4.3. Rol: Cajero
El Cajero está enfocado en el ciclo del dinero y cobro de comandas. Redirección inicial obligatoria a `/caja`.

```mermaid
graph TD
    CAJERO((Cajero)) -->|Login Exitoso| INICIO_CAJA["/caja (Pantalla de Cobro)"]

    INICIO_CAJA -->|Paso 1: Inicio Turno| APERTURA["/caja/apertura (Registro Fondo Inicial)"]
    INICIO_CAJA -->|Paso 2: Cobro Operativo| COBRO["Cobrar Comanda (Efectivo/Transferencia)"]
    COBRO --> TICKET["/caja/ticket/:id (Emisión Ticket Venta)"]
    INICIO_CAJA -->|Paso 3: Retiro Parcial| RETIRO["Registrar Retiro de Efectivo"]
    INICIO_CAJA -->|Paso 4: Fin de Turno| CIERRE["/caja/cierre (Arqueo y Cierre)"]
    INICIO_CAJA -.->|Apoyo Sala| POS_C["/pos (Toma de Comanda)"]

    classDef restricted fill:#fee2e2,stroke:#ef4444,stroke-width:1px;
    BLOQUEADO_CAJA["BLOQUEADO: /admin, /configuracion, /contabilidad, /reportes"]:::restricted
```

---

### 4.4. Rol: Mesero
El Mesero tiene acceso exclusivo al Punto de Venta para registrar y atender mesas.

```mermaid
graph TD
    MESERO((Mesero)) -->|Login Exitoso| INICIO_POS["/pos (Punto de Venta)"]

    INICIO_POS --> SEL_MESA["Selección de Mesa o Canal (Para Llevar / WhatsApp)"]
    SEL_MESA --> AGREGAR_PLATILLOS["Seleccionar Platillos del Menú"]
    AGREGAR_PLATILLOS --> OBS["Añadir Observaciones de Cocina"]
    OBS --> ENVIAR["Enviar Comanda a Cocina (Estado: Pendiente)"]
    INICIO_POS --> CONSULTA["Visualizar Comandas Propias y Estados"]

    classDef restricted fill:#fee2e2,stroke:#ef4444,stroke-width:1px;
    BLOQUEADO_MESERO["BLOQUEADO: /caja, /cocina, /inventario, /contabilidad, /admin"]:::restricted
```

---

### 4.5. Rol: Cocinero / Cocinera
El personal de cocina interactúa con la cola de preparación, consulta existencias de insumos y solicita abastecimiento. Redirección inicial a `/cocina`.

```mermaid
graph TD
    COCINERO((Cocinero/a)) -->|Login Exitoso| INICIO_COCINA["/cocina (Cola de Pedidos)"]

    INICIO_COCINA --> VER_PEDIDOS["Visualizar Comandas por Orden de Llegada"]
    VER_PEDIDOS --> CAMBIAR_ESTADO["Marcar 'En Cocina' / 'Listo'"]

    COCINERO --> INV_C["/inventario (Existencias y Alertas Stock)"]
    COCINERO --> REQ_C["/requisiciones/nueva (Solicitud de Insumos a Bodega)"]

    classDef restricted fill:#fee2e2,stroke:#ef4444,stroke-width:1px;
    BLOQUEADO_COCINA["BLOQUEADO: /caja, /contabilidad, /reportes, /admin, /configuracion"]:::restricted
```

---

### 4.6. Rol: Contadora
La Contadora gestiona los egresos, el crédito fiscal de compras y la exportación de libros de IVA. Redirección inicial a `/contabilidad`.

```mermaid
graph TD
    CONTADORA((Contadora)) -->|Login Exitoso| INICIO_CONTA["/contabilidad (Libro de Egresos)"]

    INICIO_CONTA --> CARGA_EGRESO["Registrar Factura / Comprobante de Proveedor"]
    CARGA_EGRESO --> CALC_IVA["Cálculo Automático Costo Neto e IVA Crédito Fiscal"]
    INICIO_CONTA --> COMPRAS["/contabilidad/compras (Gestión Compras IVA)"]
    CONTADORA --> EXPORTAR_C["/reportes/exportar (Exportar Consolidado Excel)"]

    classDef restricted fill:#fee2e2,stroke:#ef4444,stroke-width:1px;
    BLOQUEADO_CONTA["BLOQUEADO: /pos, /cocina, /caja, /admin, /matriz-permisos"]:::restricted
```

---

## 5. Reglas de Validación de la Guarda Global (`router.beforeEach`)

El mapa de navegación no es meramente cosmético: está reforzado por una guarda de navegación programática implementada en [`resources/js/router/index.js`](file:///c:/Users/josue/Documents/GitHub/SGCO-Chayito/resources/js/router/index.js):

1. **Evaluación de Sesión:** Si la ruta requiere autenticación (`!meta.publica`) y no existe token en el store, redirige inmediatamente a `/login`.
2. **Evaluación de Meta-Roles:** Si la ruta posee un arreglo de roles autorizados (`to.meta.roles`), el enrutador verifica si el rol activo del usuario pertenece a dicho arreglo.
3. **Manejo de Intento de Acceso No Autorizado:**
   - Si un usuario con sesión activa intenta escribir en la barra de direcciones una URL prohibida (ej. un mesero navegando a `/admin` o `/caja`), la guarda intercepta la petición.
   - La guarda ejecuta `obtenerRutaInicioPorRol(sesion.rol)` y redirige automáticamente al usuario a su pantalla autorizada de inicio, evitando que visualice contenido sensible.
4. **Ciclo de Redirección Protegido:** Si un usuario no tuviese permisos ni siquiera para su ruta por defecto, la guarda destruye la sesión y lo remite a `/login` para prevenir bucles infinitos de redirección.

---

## 6. Verificación de Cumplimiento

- [x] Mapa conceptual de navegación general del sistema diagramado.
- [x] Mapa conceptual por rol documentado para los 6 roles oficiales del sistema.
- [x] Rutas de entrada por defecto verificadas contra la lógica de `obtenerRutaInicioPorRol`.
- [x] Delimitación de módulos bloqueados por principio de mínimo privilegio establecida.
- [x] Criterio de aceptación de la tarea **CC-152** plenamente cumplido y documentado para el informe final de pruebas.
