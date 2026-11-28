# Suite de Pruebas de Aceptación con Selenium WebDriver y TestNG
## Sistema de Gestión del Comedor Chayito (SGCO-Chayito)
### Módulo: B5 - Cuadrante 3: Pruebas de Aceptación
**Autor / Responsable:** César Josué  
**Materia:** Diseño de Sistemas II — Universidad de El Salvador (FMO)  
**Herramientas:** Java 17+, Maven 3.8+, Selenium WebDriver 4.27.0, TestNG 7.10.2, WebDriverManager 5.9.2, Eclipse IDE.

---

## 1. Estructura del Proyecto

El proyecto sigue el patrón de diseño estándar de la industria **Page Object Model (POM)**:

```text
tests-selenium/
├── pom.xml                                      # Configuración Maven y dependencias
├── testng.xml                                   # Definición de la suite de pruebas TestNG
├── README.md                                    # Guía de importación y ejecución en Eclipse
└── src/test/java/com/sgco/chayito/
    ├── pages/                                   # Page Objects (Mapeo de Vistas)
    │   ├── BasePage.java                        # Métodos comunes y esperas explícitas
    │   ├── LoginPage.java                       # Vista de inicio de sesión (Login.vue)
    │   ├── NavigationPage.java                  # AppShell y barra de navegación lateral
    │   ├── PosPage.java                         # Pantalla táctil POS (Wizard Paso 1 y 2)
    │   └── CajaPage.java                        # Pantalla de cobro, desglose y arqueo
    └── tests/                                   # Clases de Prueba TestNG
        ├── BaseTest.java                        # Configuración del WebDriver y ciclo de vida
        ├── NavegacionRolesTest.java             # CC-153: Redirecciones iniciales y menús por rol
        ├── RestriccionVistasTest.java           # CC-183: Validación de mínimo privilegio y route guards
        └── FlujoEntradaProcesoSalidaTest.java   # CC-181/182: Entrada, proceso (POS) y salida (Caja)
```

---

## 2. Instrucciones para Importar y Ejecutar en Eclipse IDE

### Paso 1: Requisitos Previos
1. **Eclipse IDE for Java Developers** (versión 2023-09 o superior).
2. **Java Development Kit (JDK 17 o 21)** instalado y configurado como Installed JRE en Eclipse.
3. **Plugin de TestNG para Eclipse**:
   - En Eclipse, ir al menú `Help` -> `Eclipse Marketplace...`.
   - Buscar `TestNG for Eclipse`.
   - Hacer clic en `Install`, aceptar la licencia y reiniciar Eclipse.
4. Navegador **Google Chrome** instalado en el sistema operativo.
5. El sistema **SGCO-Chayito** debe estar levantado localmente:
   - Contenedor Docker activo en `http://localhost:8084` (o `http://localhost:8000`).

---

### Paso 2: Importar el Proyecto Maven en Eclipse
1. Abrir Eclipse IDE.
2. Ir a `File` -> `Import...`.
3. Seleccionar `Maven` -> `Existing Maven Projects` y presionar `Next`.
4. En **Root Directory**, hacer clic en `Browse...` y seleccionar la carpeta:
   `c:\Users\josue\Documents\GitHub\SGCO-Chayito\tests-selenium`
5. Verificar que el archivo `pom.xml` aparezca marcado con un check.
6. Hacer clic en `Finish`.
7. Esperar a que Maven descargue las dependencias (Selenium, TestNG, WebDriverManager). En la barra inferior de Eclipse dirá *Building workspace*.

---

### Paso 3: Ejecución de las Pruebas en Eclipse

#### Opción A: Ejecutar toda la Suite desde `testng.xml` (Recomendado)
1. En el explorador de paquetes de Eclipse (*Package Explorer*), expandir el proyecto `sgco-selenium-acceptance-tests`.
2. Hacer clic derecho sobre el archivo **`testng.xml`**.
3. Seleccionar **`Run As`** -> **`TestNG Suite`**.
4. Se abrirá la pestaña de **TestNG** mostrando los resultados en verde (*Passed*).

#### Opción B: Ejecutar una Clase de Prueba Individual
1. Navegar a `src/test/java/com/sgco/chayito/tests/`.
2. Hacer clic derecho sobre cualquier clase (por ejemplo `NavegacionRolesTest.java`).
3. Seleccionar **`Run As`** -> **`TestNG Test`**.

#### Opción C: Modo Gráfico con Navegador Visible
Por defecto, las pruebas se ejecutan en modo *Headless* (sin abrir ventana visual para mayor velocidad e integración CI/CD).
Si se desea observar la ventana de Chrome abriéndose e interactuando en pantalla:
1. Abrir `testng.xml`.
2. Cambiar `<parameter name="headless" value="true" />` por:
   ```xml
   <parameter name="headless" value="false" />
   ```
3. Guardar y volver a ejecutar la suite.

---

### Paso 4: Ejecución alternativa por Línea de Comandos (Maven)

Desde una terminal en el directorio `tests-selenium`:
```powershell
mvn clean test
```
Para forzar modo visual interactivo:
```powershell
mvn clean test -Dheadless=false
```

Los reportes HTML generados quedarán guardados en:
`tests-selenium/target/surefire-reports/index.html`
