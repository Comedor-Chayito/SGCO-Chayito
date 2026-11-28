# CC-184: Guía Rápida de Uso del Sistema SGCO-Chayito
## Manual Operativo para el Personal y Administración del Comedor Chayito
**Módulo:** B5 - Cuadrante 3: Pruebas de Aceptación  
**Autor:** César Josué  
**Fecha:** Noviembre 2026  
**Versión:** 1.0  
**Audiencia:** Administrador(a), Cajero(a), Mesero(a), Cocinero(a)  

---

## 1. Introducción y Bienvenida

¡Bienvenido(a) al **Sistema de Gestión del Comedor Chayito (SGCO-Chayito)**!  
Esta guía rápida ha sido diseñada para brindarle al equipo del comedor los conocimientos esenciales para operar el sistema en el día a día sin complicaciones. El sistema funciona a través de un navegador web y está adaptado tanto para computadoras de escritorio como para tablets y teléfonos móviles.

---

## 2. Acceso al Sistema (Inicio de Sesión)

1. Abra el navegador web e ingrese a la dirección del sistema: `http://localhost:8084` (o la dirección web que se le haya indicado).
2. Se presentará la pantalla de **Inicio de Sesión**:
   - Ingrese su **Correo corporativo** (ejemplo: `cajero@sgco-chayito.local` o `mesero@sgco-chayito.local`).
   - Ingrese su **Contraseña** asignada.
   - Puede hacer clic en el ícono del **ojo** para verificar que la contraseña esté bien escrita.
3. Presione el botón naranja **"Iniciar sesión"**.
4. **Redirección automática según su puesto:**
   - Si usted es **Mesero(a)**, el sistema lo llevará directo a la pantalla de **Punto de Venta (POS)** para tomar pedidos.
   - Si usted es **Cajero(a)**, irá directo a la pantalla de **Caja y Cobro**.
   - Si usted es **Cocinero(a)**, irá directo a la **Cola de Cocina**.
   - Si es **Administrador(a)** o **Socio(a)**, tendrá acceso completo a reportes, inventario y configuración.

> [!TIP]
> Si olvida su contraseña o tiene problemas para ingresar, solicite al Administrador el restablecimiento desde el módulo de usuarios.

---

## 3. Guía de Operación Diaria por Roles

### A. Para el Cajero: Apertura de Caja (Inicio del Día)
Antes de poder registrar cobros o movimientos, la caja debe estar abierta con su saldo base:
1. En el menú lateral izquierdo, seleccione **"Apertura de caja"**.
2. Aparecerá el formulario de desglose de efectivo en dólares (USD).
3. Ingrese la cantidad de piezas de cada billete (\$20, \$10, \$5, \$1) y cada moneda (\$0.25, \$0.10, \$0.05, \$0.01) que tiene en el cajón de cambio.
4. El sistema calculará automáticamente el total acumulado en tiempo real.
5. Verifique que coincida con el dinero físico del cajón y presione **"Confirmar Apertura"**.
6. A partir de este momento, la caja está activa para procesar cobros de ventas.

---

### B. Para el Mesero: Tomar una Comanda en el POS Táctil
1. En la barra lateral o al ingresar, estará en el **Punto de Venta (POS)**.
2. **Paso 1: ¿Dónde comerá el cliente?**
   - **En Mesa:** Toque la opción "En Mesa" y seleccione el número de mesa que se iluminará en pantalla (las mesas libres aparecen disponibles para seleccionar).
   - **Para Llevar / WhatsApp:** Toque la tarjeta correspondiente si el cliente retirará su comida. El sistema pasará automáticamente al menú.
3. **Paso 2: Selección de Platillos**
   - Toque los platillos que el cliente solicita (ej. Almuerzo Ejecutivo, Pupusas, Bebidas).
   - Si el platillo tiene guarniciones o extras, selecciónelos y presione **"Agregar plato"**.
4. **Paso 3: Observaciones de Cocina (Opcional)**
   - Si el cliente tiene una petición especial (ej. *"Sin cebolla"*, *"Salsa aparte"*), escríbala en el campo de texto ubicado abajo del ticket.
5. **Paso 4: Registrar Orden**
   - Revise el resumen del pedido a la derecha (o en el ícono del carrito si usa celular).
   - Presione el botón naranja **"Registrar Orden"**.
   - Aparecerá un aviso verde confirmando el número de orden (ej. *Comanda #12 registrada*). La orden aparecerá de inmediato en la pantalla de cocina.

---

### C. Para el Cocinero: Preparación de Comandas
1. En la pantalla de **Cola de Cocina**, verá las comandas que van entrando ordenadas por hora de llegada.
2. Cada tarjeta indica: número de orden, mesa o si es para llevar, hora y platillos solicitados con sus notas.
3. Cuando un platillo o comanda esté lista para servir/cobrar:
   - Presione el botón **"Listo"** en la tarjeta.
   - La orden desaparecerá de cocina y pasará de inmediato a la pantalla de **Caja** lista para cobro.

---

### D. Para el Cajero: Cobro de Comandas y Emisión de Ticket
1. Ingrese a la pantalla de **Caja**.
2. En la lista de comandas listas, seleccione la orden que el cliente desea pagar.
3. A la derecha verá el desglose de la pre-cuenta:
   - Subtotal neto, IVA (13% implícito ya desglosado) y Total a pagar.
4. **Seleccione el método de pago:**
   - **Efectivo:** Ingrese la cantidad de dinero que entrega el cliente en el campo *"Monto recibido"*. El sistema le mostrará en un cuadro verde el **Cambio exacto a entregar**.
   - **Tarjeta / Transferencia:** El sistema fijará el total exacto sin cambio.
5. Presione el botón verde **"Confirmar Cobro"**.
6. El sistema registrará el cobro, liberará la mesa (si era en mesa) y mostrará el **Ticket de Venta** listo para imprimir o enviar.

---

### E. Para el Cajero y Administrador: Cierre de Caja (Final del Turno)
1. Al terminar la jornada o el turno de caja, vaya a **"Cierre de caja"**.
2. El sistema le mostrará el resumen de la jornada:
   - Saldo inicial con el que se abrió.
   - Total de ventas cobradas en efectivo, tarjeta y transferencia.
   - Retiros parciales registrados durante el día.
   - **Efectivo teórico esperado en caja**.
3. Realice el conteo físico del dinero en el cajón e ingrese los valores finales.
4. Si existe alguna diferencia (sobrante o faltante), el sistema lo reflejará con transparencia para la auditoría.
5. Presione **"Cerrar Turno"** e imprima el reporte de arqueo firmado.

---

## 4. Preguntas Frecuentes y Resolución de Dudas (FAQ)

| Pregunta / Duda | Solución / Procedimiento |
| :--- | :--- |
| **¿Por qué el botón "Confirmar Cobro" está deshabilitado?** | Verifique que la caja haya sido abierta en la mañana, que haya seleccionado una comanda y que el monto recibido en efectivo sea igual o mayor al total de la cuenta. |
| **¿Cómo anular un platillo que se marcó por error antes de enviar la comanda?** | En el panel derecho del ticket, presione el ícono de la papelera o el signo menos (-) al lado del platillo antes de presionar "Registrar Orden". |
| **¿Qué hago si se va el internet brevemente?** | El sistema funciona en la red local del comedor. No cierre la pestaña; una vez se restablezca la conexión, presione actualizar (F5) o vuelva a intentar el registro. |
| **¿Puedo cerrar la sesión para que otro compañero use la pantalla?** | Sí. En la parte inferior de la barra lateral izquierda, haga clic en el botón rojo con ícono de salida **"Cerrar sesión"**. |

---

## 5. Contacto de Soporte Técnico

Para reportar cualquier inconsistencia o solicitar apoyo técnico adicional:
- **Responsable de Soporte del Sistema:** César Josué (Equipo de Desarrollo SGCO-Chayito)
- **Horario de Asistencia:** Lunes a Sábado de 7:00 AM a 6:00 PM
- **Ubicación:** Laboratorio de Sistemas / Comedor Chayito
