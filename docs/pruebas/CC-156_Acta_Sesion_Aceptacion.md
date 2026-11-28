# CC-156: Acta de Sesión de Aceptación con el Usuario Final
## Validación en Entorno Operativo - Comedor Chayito
**Módulo:** B5 - Cuadrante 3: Pruebas de Aceptación  
**Responsable Técnico / Estudiante:** César Josué  
**Materia:** Diseño de Sistemas II — Universidad de El Salvador (FMO)  
**Fecha de Ejecución:** 26 de Noviembre de 2026  
**Lugar:** Instalaciones de Comedor Chayito / Laboratorio de Cómputo  

---

## 1. Datos Generales de la Sesión

- **Proyecto:** Sistema de Gestión y Control Operativo para Comedor Chayito (SGCO-Chayito).
- **Objetivo de la Sesión:** Validar directamente con los usuarios finales del comedor que las pantallas de Entrada, Proceso y Salida cumplan con los requerimientos acordados en el ERS, verificar la facilidad de uso y registrar formalmente la encuesta de satisfacción del usuario.
- **Participantes:**
  1. **Doña María del Rosario Hernández de Romero:** Propietaria y Administradora General de Comedor Chayito (Usuario Evaluador Principal).
  2. **Marcos Antonio Gómez:** Encargado de Caja y Atención (Usuario de Operación).
  3. **César Josué:** Responsable de Pruebas de Aceptación y Facilitador (Equipo de Desarrollo SGCO-Chayito).

---

## 2. Agenda de la Sesión y Actividades Realizadas

| Hora | Actividad Desarrollada | Responsable | Evidencia Generada |
| :---: | :--- | :--- | :--- |
| **09:00 - 09:15** | Entrega y lectura de la **Guía Rápida de Uso (CC-184)** en formato impreso y digital. | César Josué | Guía rápida en manos del usuario |
| **09:15 - 09:40** | Demostración guiada del flujo integral: Entrada (Login/Apertura), Proceso (POS), Salida (Cobro/Cierre). | César Josué | Navegación en vivo sobre ambiente local |
| **09:40 - 10:45** | **Interacción autónoma de los usuarios:** Doña Rosario y Marcos operaron el sistema por su cuenta registrando comandas reales, calculando cambio y revisando reportes. | Doña Rosario y Marcos | Registro de órdenes #101, #102 y cobros |
| **10:45 - 11:15** | Aplicación y llenado individual de la **Encuesta de Satisfacción y Usabilidad SUS (CC-155)**. | Doña Rosario | Cuestionario diligenciado y firmado |
| **11:15 - 11:30** | Retroalimentación cualitativa, acuerdos finales y firma del acta de aceptación. | Todos los participantes | Acta oficial firmada |

---

## 3. Escenarios Evaluados en Vivo con el Usuario

Los usuarios ejecutaron de manera práctica los siguientes casos de prueba definidos en el ERS:

1. **Apertura de Caja (Entrada):**
   - El cajero ingresó el desglose de monedas y billetes para un saldo inicial de \$50.00.
   - *Resultado observado por el usuario:* El total se calculó al instante sin necesidad de calculadora manual. Aprobado con entusiasmo.
2. **Toma de Comanda Táctil (Proceso):**
   - Se seleccionó "En Mesa" -> Mesa 3 -> 2 Almuerzos Ejecutivos con bebida -> Nota: "Carne bien cocida".
   - *Resultado observado por el usuario:* Los botones son grandes y claros. La orden pasó de inmediato a la cola de cocina.
3. **Cobro y Cambio en Efectivo (Salida):**
   - Cuenta total: \$7.50. Se seleccionó pago en efectivo con un billete de \$20.00.
   - *Resultado observado por el usuario:* El sistema mostró claramente en color verde: **Cambio a entregar: \$12.50**, indicando además el desglose del 13% de IVA implícito.
4. **Cierre de Caja y Arqueo (Salida):**
   - Se visualizó el reporte de cierre de caja con el resumen de transacciones, retiros y efectivo esperado.

---

## 4. Registro y Resultados de la Encuesta de Satisfacción Respondida

### A. Respuestas Obtenidas de Doña Rosario Hernández (Propietaria / Administradora):

| N° | Declaración de Usabilidad (SUS) | Calificación (1 al 5) | Conversión SUS |
| :-: | :--- | :-: | :-: |
| **1** | Creo que me gustaría utilizar este sistema con frecuencia en el comedor. | **5** (Totalmente de acuerdo) | $5 - 1 = 4$ |
| **2** | Encontré el sistema innecesariamente complejo de entender. | **1** (Totalmente en desacuerdo) | $5 - 1 = 4$ |
| **3** | Pensé que el sistema era fácil y amigable de usar. | **5** (Totalmente de acuerdo) | $5 - 1 = 4$ |
| **4** | Creo que necesitaría el apoyo de un técnico para poder utilizar este sistema. | **2** (En desacuerdo) | $5 - 2 = 3$ |
| **5** | Encontré que las diversas funciones del sistema estaban bien integradas. | **5** (Totalmente de acuerdo) | $5 - 1 = 4$ |
| **6** | Pensé que había demasiada inconsistencia o contradicciones en este sistema. | **1** (Totalmente en desacuerdo) | $5 - 1 = 4$ |
| **7** | Imagino que la mayoría de mis compañeros de trabajo aprenderían a usar este sistema muy rápidamente. | **5** (Totalmente de acuerdo) | $5 - 1 = 4$ |
| **8** | Encontré el sistema muy engorroso o frustrante de utilizar. | **1** (Totalmente en desacuerdo) | $5 - 1 = 4$ |
| **9** | Me sentí muy segura y confiada al utilizar el sistema para registrar ventas y cobros. | **5** (Totalmente de acuerdo) | $5 - 1 = 4$ |
| **10** | Necesité aprender muchas cosas técnicas antes de poder empezar a trabajar con el sistema. | **2** (En desacuerdo) | $5 - 2 = 3$ |
| **TOTAL** | **Suma de puntuaciones convertidas:** | — | **34 / 40** |

$$\text{Puntaje Final SUS} = 34 \times 2.5 = \mathbf{85.0} \text{ sobre 100}$$

> **Interpretación:** Con un puntaje SUS de **85.0**, el sistema se ubica en el rango de **"Excelente"** (Grado A), superando ampliamente el promedio de la industria (68 puntos) y satisfaciendo el criterio de éxito del Sprint.

---

### B. Respuestas a Preguntas Operativas del Comedor:

| Criterio | Calificación (1 a 5) | Comentario del Usuario |
| :--- | :---: | :--- |
| Claridad de la pantalla táctil POS | **5** | *"Los platillos se ven claritos y seleccionar la mesa es muy rápido."* |
| Precisión del cambio y desglose de IVA | **5** | *"Nos ayuda mucho porque a veces en las horas pico calcular el vuelto con billetes grandes quita tiempo."* |
| Claridad visual y diseño responsivo | **5** | *"Los colores naranja y blanco son muy bonitos y descansados a la vista."* |
| Utilidad de la Guía Rápida entregada | **5** | *"La guía explica en cristiano y con pasos sencillos lo que hay que hacer."* |

---

### C. Retroalimentación Cualitativa Directa del Usuario:

1. **Lo que más gustó:**
   > *"La rapidez con la que se pasa la orden de la mesa a la cocina y a la caja sin tener que andar gritando ni llevando papeles que se mojan en la cocina."*
2. **Dificultades encontradas durante la sesión:**
   > *"Al principio no sabíamos que si la caja no se abría en la mañana no dejaba cobrar, pero el mensaje de advertencia nos indicó clarito que debíamos ir primero a abrir caja con el saldo inicial."*
3. **Sugerencias de mejora:**
   > *"Que cuando se imprima el ticket de venta salga el nombre del comedor bien grande arriba y un mensaje de 'Gracias por su preferencia'."* (Observación atendida e integrada).

---

## 5. Dictamen y Declaración Formal de Aceptación

Los usuarios evaluadores declaran que el sistema **SGCO-Chayito** cumple a cabalidad con lo pactado en la Especificación de Requerimientos de Software (ERS), validando satisfactoriamente las pantallas de Entrada, Proceso y Salida, la guía rápida de uso y la segregación de funciones por roles.

Por tanto, se emite el presente dictamen de: **ACEPTADO CONFORMEMENTE**.

---

## 6. Firmas de Conformidad

| __________________________________________ | __________________________________________ |
| :---: | :---: |
| **Doña María del Rosario Hernández** | **Marcos Antonio Gómez** |
| Propietaria / Administradora General | Cajero / Atención al Cliente |
| Comedor Chayito | Comedor Chayito |

| __________________________________________ |
| :---: |
| **César Josué** |
| Responsable de Pruebas de Aceptación |
| Equipo de Desarrollo SGCO-Chayito |
