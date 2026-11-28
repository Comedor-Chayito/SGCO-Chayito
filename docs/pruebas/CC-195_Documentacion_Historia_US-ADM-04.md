# CC-195: Documentación de Cierre de Historia de Usuario US-ADM-04 (CC-120)
## Matriz de Permisos y Bitácora de Auditoría
**Módulo:** Administración y Seguridad (US-ADM-04 / RF-ADM-008 / CC-120)  
**Autor / Responsable:** César Josué  
**Materia:** Diseño de Sistemas II — Universidad de El Salvador (FMO)  
**Fecha de Cierre:** Noviembre 2026  
**Rama Git:** `feature/CC-120-matriz-permisos-bitacora`  
**Estado:** **Completada y Certificada al 100%**  

---

## 1. Definición de la Historia de Usuario

### Descripción Formal:
> **Como:** Administrador General o Socio del Comedor Chayito,  
> **Quiero:** Contar con una matriz interactiva para consultar y actualizar los permisos asignados a cada rol del sistema, manteniendo un registro inalterable en la bitácora de auditoría de cada modificación realizada (quién modificó, qué rol, qué permisos se agregaron/quitaron, dirección IP y fecha/hora),  
> **Para:** Garantizar el principio de menor privilegio, cumplir con las normativas de seguridad de la información y prevenir acciones no autorizadas en el sistema operativo del comedor.

---

## 2. Criterios de Aceptación Oficiales (ERS)

1. **CA-01 (Consulta de Roles y Permisos):** El sistema debe listar todos los roles oficiales (`administrador`, `cajero`, `mesero`, `cocinero`, `cocinera`, `contadora`, `socio`) y el catálogo completo de permisos agrupados por módulo.
2. **CA-02 (Actualización Atómica de Permisos):** El endpoint de actualización debe sincronizar exactamente el conjunto de permisos enviado para el rol seleccionado en una transacción de base de datos segura.
3. **CA-03 (Registro Obligatorio en Bitácora de Auditoría):** Toda modificación exitosa de permisos debe generar automáticamente una entrada en la tabla `bitacora_auditoria` que almacene:
   - Identificador del usuario que ejecutó la acción.
   - Acción: `ACTUALIZAR_PERMISOS_ROL`.
   - Módulo: `seguridad` / `administracion`.
   - Detalle estructurado en formato JSON con: permisos anteriores, nuevos permisos, permisos agregados y permisos revocados.
   - Dirección IP del cliente y User-Agent.
4. **CA-04 (Protección de Rutas y RBAC):** Solo los usuarios con rol de `administrador` o `socio` pueden consultar y modificar la matriz de permisos. Cualquier otro rol debe recibir respuesta HTTP `403 Forbidden`. Peticiones no autenticadas deben recibir `401 Unauthorized`.
5. **CA-05 (Validación Estricta de Carga Útil):** La solicitud debe validar que los identificadores de permisos existan en la tabla `permissions`. Permite array vacío `[]` para revocar todos los permisos si fuese necesario.

---

## 3. Arquitectura Técnica Implementada

```mermaid
flowchart TD
    Cliente["Frontend SPA / Cliente HTTP"] -->|POST /api/admin/roles/{role}/permisos| Route["Route::middleware(['auth:sanctum', 'role:administrador|socio'])"]
    Route --> Request["ActualizarPermisosRolRequest\n(Validación de matriz y permisos)"]
    Request --> Controller["ActualizarPermisosRolController"]
    Controller --> DBTrans["DB::transaction()"]
    DBTrans --> Sync["Spatie Permission\n$role->syncPermissions()"]
    DBTrans --> Auditoria["BitacoraAuditoriaService::registrarAccion()\n(Diff permisos agregados/removidos)"]
    Auditoria --> TablaBitacora[("Tabla: bitacora_auditoria")]
    Sync --> TablaRoles[("Tablas: roles / role_has_permissions")]
    Controller -->|200 OK + JSON| Cliente
```

### Componentes Clave Desarrollados:
1. **FormRequest de Validación:**
   - [`app/Http/Requests/Admin/ActualizarPermisosRolRequest.php`](file:///c:/Users/josue/Documents/GitHub/SGCO-Chayito/app/Http/Requests/Admin/ActualizarPermisosRolRequest.php): Reglas de validación para `permisos` (`present`, `array`, `permisos.* exists:permissions,name`).
2. **Controlador API:**
   - [`app/Http/Controllers/Api/Admin/ActualizarPermisosRolController.php`](file:///c:/Users/josue/Documents/GitHub/SGCO-Chayito/app/Http/Controllers/Api/Admin/ActualizarPermisosRolController.php): Orquesta la sincronización de permisos, calcula las diferencias (agregados/eliminados) y llama al servicio de auditoría dentro de una transacción atómica.
3. **Servicio Centralizado de Auditoría:**
   - [`app/Services/BitacoraAuditoriaService.php`](file:///c:/Users/josue/Documents/GitHub/SGCO-Chayito/app/Services/BitacoraAuditoriaService.php): Registra eventos de seguridad con metadatos de red y payload serializado.
4. **Rutas Protegidas:**
   - Registradas en `routes/api.php` bajo el grupo de administración con middleware de autenticación y rol.

---

## 4. Endpoints de la API

| Método | Endpoint | Middleware | Descripción |
| :---: | :--- | :--- | :--- |
| `GET` | `/api/admin/roles` | `auth:sanctum`, `role:administrador\|socio` | Lista todos los roles con sus permisos asignados actualmente. |
| `GET` | `/api/admin/permisos` | `auth:sanctum`, `role:administrador\|socio` | Obtiene el catálogo completo de permisos disponibles en el sistema. |
| `POST` | `/api/admin/roles/{role}/permisos` | `auth:sanctum`, `role:administrador\|socio` | Actualiza y sincroniza los permisos del rol especificado y genera auditoría. |
| `GET` | `/api/admin/bitacora` | `auth:sanctum`, `role:administrador\|socio` | Consulta paginada y filtrable de eventos de la bitácora de auditoría. |

---

## 5. Resumen de Pruebas Automatizadas y Certificación

La historia de usuario cuenta con un conjunto exhaustivo de pruebas backend ejecutadas mediante Pest / PHPUnit:

### A. Suite `ActualizarPermisosRolTest.php` (13 Pruebas - 100% Aprobadas):
- `POST /api/admin/roles/{role}/permisos actualiza permisos exitosamente`: **Passed**
- `POST /api/admin/roles/{role}/permisos registra entrada en bitacora`: **Passed**
- `POST /api/admin/roles/{role}/permisos calcula diferencias de permisos agregados y removidos`: **Passed**
- `POST /api/admin/roles/{role}/permisos permite enviar array vacio de permisos`: **Passed**
- `POST /api/admin/roles/{role}/permisos rechaza permisos inexistentes (422)`: **Passed**
- `POST /api/admin/roles/{role}/permisos retorna 401 si usuario no esta autenticado`: **Passed**
- `POST /api/admin/roles/{role}/permisos retorna 403 si usuario es cajero`: **Passed**
- `POST /api/admin/roles/{role}/permisos retorna 403 si usuario es mesero`: **Passed**
- `POST /api/admin/roles/{role}/permisos retorna 403 si usuario es cocinero`: **Passed**
- `POST /api/admin/roles/{role}/permisos retorna 403 si usuario es contadora`: **Passed**
- `POST /api/admin/roles/{role}/permisos permite acceso a socio`: **Passed**
- `POST /api/admin/roles/{role}/permisos rechaza rol inexistente (404)`: **Passed**
- `POST /api/admin/roles/{role}/permisos ejecuta cambios en transaccion atomica`: **Passed**

### B. Suite `BitacoraAuditoriaTest.php` (25 Pruebas - 100% Aprobadas):
- Pruebas de filtrado por usuario, rango de fechas, módulo y tipo de acción.
- Pruebas de inalterabilidad y registro de metadatos de red (IP, User-Agent).
- Todos los tests activos, con 0 pruebas ignoradas o en *skip*.

### C. Cobertura Global del Proyecto:
- **Total de pruebas en la suite:** 405 pruebas ejecutadas.
- **Aserciones verificadas:** 2,831 aserciones.
- **Fallos / Errores:** 0 fallos (100% de éxito).

---

## 6. Dictamen de Cierre

La Historia de Usuario **US-ADM-04 (CC-120)** cumple íntegramente con todos los criterios de aceptación técnicos y funcionales estipulados en el ERS, cuenta con pruebas de integración y de aceptación automatizadas, y se encuentra lista para su entrega final en el Cuadrante 3.

**Dictamen:** **HISTORIA CC-120 COMPLETADA Y APROBADA**.
