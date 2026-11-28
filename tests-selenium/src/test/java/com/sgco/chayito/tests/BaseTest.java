package com.sgco.chayito.tests;

import com.sgco.chayito.pages.LoginPage;
import com.sgco.chayito.pages.NavigationPage;
import org.openqa.selenium.WebDriver;
import org.openqa.selenium.chrome.ChromeDriver;
import org.openqa.selenium.chrome.ChromeOptions;
import org.testng.annotations.AfterMethod;
import org.testng.annotations.BeforeMethod;
import org.testng.annotations.Optional;
import org.testng.annotations.Parameters;

import java.io.File;
import java.time.Duration;

/**
 * Clase base de configuración para todas las suites de pruebas de aceptación Selenium/TestNG.
 * Maneja el ciclo de vida del WebDriver con resolución autónoma del driver local y binarios Chromium (Brave, Edge, Chrome).
 *
 * @autor César Josué
 * @módulo B5 - Cuadrante 3: Pruebas de Aceptación
 */
public abstract class BaseTest {

    protected WebDriver driver;
    protected String baseUrl;
    protected Duration implicitWait = Duration.ofSeconds(5);

    @BeforeMethod
    @Parameters({"baseUrl", "headless", "browser"})
    public void setup(
            @Optional("http://localhost:8084") String baseUrl,
            @Optional("true") String headless,
            @Optional("auto") String browser
    ) {
        this.baseUrl = baseUrl;
        boolean isHeadless = Boolean.parseBoolean(System.getProperty("headless", headless));

        // Silenciar advertencias informativas de CDP y logs internos de Chromium
        System.setProperty("webdriver.chrome.silentOutput", "true");
        java.util.logging.Logger.getLogger("org.openqa.selenium").setLevel(java.util.logging.Level.SEVERE);
        java.util.logging.Logger.getLogger("org.openqa.selenium.devtools").setLevel(java.util.logging.Level.OFF);

        // 1. Localizar chromedriver.exe en el proyecto o en la caché local sin requerir internet
        File driverFile = resolverDriver();
        if (driverFile != null && driverFile.exists()) {
            System.setProperty("webdriver.chrome.driver", driverFile.getAbsolutePath());
        }

        // 2. Configurar ChromeOptions con el motor Chromium disponible en Windows (Brave, Edge o Chrome)
        ChromeOptions options = new ChromeOptions();

        File browserBinary = resolverBinarioNavegador(browser);
        if (browserBinary != null && browserBinary.exists()) {
            options.setBinary(browserBinary.getAbsolutePath());
        }

        if (isHeadless) {
            options.addArguments("--headless=new");
        }

        options.addArguments("--window-size=1920,1080");
        options.addArguments("--start-maximized");
        options.addArguments("--disable-gpu");
        options.addArguments("--no-sandbox");
        options.addArguments("--disable-dev-shm-usage");
        options.addArguments("--remote-allow-origins=*");
        options.addArguments("--incognito");
        options.addArguments("--log-level=3");
        options.addArguments("--silent");

        driver = new ChromeDriver(options);
        driver.manage().timeouts().implicitlyWait(implicitWait);
        driver.manage().timeouts().pageLoadTimeout(Duration.ofSeconds(20));
    }

    /**
     * Resuelve la ubicación del ejecutable chromedriver.exe existente en el equipo.
     */
    private File resolverDriver() {
        // Opción A: Directorio local 'drivers/' dentro del proyecto (v158 compatible con navegador)
        File f1 = new File("drivers/chromedriver.exe");
        if (f1.exists()) return f1;

        File f2 = new File("tests-selenium/drivers/chromedriver.exe");
        if (f2.exists()) return f2;

        // Opción B: Carpeta .cache de Selenium en el perfil del usuario
        String userHome = System.getProperty("user.home");
        File f3 = new File(userHome, ".cache/selenium/chromedriver/win64/158.0.8127.88/chromedriver.exe");
        if (f3.exists()) return f3;

        // Opción C: Carpeta DSI II de descargas del usuario
        File f4 = new File(userHome, "Downloads\\DSI II\\chromedriver-win64\\chromedriver-win64\\chromedriver.exe");
        if (f4.exists()) return f4;

        return null;
    }

    /**
     * Detecta el binario del navegador basado en Chromium disponible en el sistema.
     */
    private File resolverBinarioNavegador(String preferido) {
        if ("brave".equalsIgnoreCase(preferido)) {
            File b = new File("C:\\Program Files\\BraveSoftware\\Brave-Browser\\Application\\brave.exe");
            if (b.exists()) return b;
        }

        if ("edge".equalsIgnoreCase(preferido)) {
            File e1 = new File("C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe");
            if (e1.exists()) return e1;
            File e2 = new File("C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe");
            if (e2.exists()) return e2;
        }

        if ("chrome".equalsIgnoreCase(preferido)) {
            File c1 = new File("C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe");
            if (c1.exists()) return c1;
            File c2 = new File("C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe");
            if (c2.exists()) return c2;
        }

        // Selección automática: Brave -> Edge -> Chrome
        File brave = new File("C:\\Program Files\\BraveSoftware\\Brave-Browser\\Application\\brave.exe");
        if (brave.exists()) return brave;

        File edge = new File("C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe");
        if (edge.exists()) return edge;

        File edge64 = new File("C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe");
        if (edge64.exists()) return edge64;

        File chrome = new File("C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe");
        if (chrome.exists()) return chrome;

        return null;
    }

    @AfterMethod(alwaysRun = true)
    public void tearDown() {
        if (driver != null) {
            try {
                driver.quit();
            } catch (Exception ignored) {
            }
        }
    }

    /**
     * Inicia sesión en la plataforma con las credenciales indicadas.
     */
    protected void iniciarSesionComo(String correo, String contrasena) {
        driver.get(baseUrl + "/login");
        LoginPage loginPage = new LoginPage(driver);
        loginPage.iniciarSesion(correo, contrasena);
    }

    /**
     * Cierra la sesión activa a través de la barra lateral.
     */
    protected void cerrarSesionActiva() {
        NavigationPage navPage = new NavigationPage(driver);
        navPage.cerrarSesion();
    }
}
