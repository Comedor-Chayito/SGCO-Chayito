package com.sgco.chayito.tests;

import com.sgco.chayito.pages.NavigationPage;
import org.testng.Assert;
import org.testng.annotations.Test;

/**
 * Pruebas automatizadas de Control de Acceso y Mínimo Privilegio (CC-183 / CP-RBAC-01 y CP-RBAC-02).
 * Valida la protección del router SPA ante intentos de acceso directo por URL no autorizadas.
 *
 * @autor César Josué
 * @módulo B5 - Cuadrante 3: Pruebas de Aceptación
 */
public class RestriccionVistasTest extends BaseTest {

    @Test(description = "CP-RBAC-01: Intento de acceso a ruta protegida sin autenticación")
    public void testAccesoAnonimoProtegido() {
        driver.get(baseUrl + "/pos");
        NavigationPage nav = new NavigationPage(driver);
        nav.esperarUrlContiene("/login");

        Assert.assertTrue(driver.getCurrentUrl().contains("/login"),
                "Un usuario sin sesión activa debe ser redirigido obligatoriamente a /login.");
    }

    @Test(description = "CP-RBAC-02-A: Mesero intentando acceder directamente a /admin")
    public void testMeseroAccediendoAdmin() {
        iniciarSesionComo("mesero@sgco-chayito.local", "Password123!");
        NavigationPage nav = new NavigationPage(driver);
        nav.esperarUrlContiene("/pos");

        // Intentar navegar manualmente a la ruta de administración
        driver.get(baseUrl + "/admin");

        // El Route Guard debe interceptar y redirigir al inicio del rol (/pos) o a no-encontrado/error
        nav.esperarUrlContiene("/pos");
        Assert.assertFalse(driver.getCurrentUrl().contains("/admin"),
                "El mesero no debe tener acceso a la vista /admin.");
        Assert.assertTrue(driver.getCurrentUrl().contains("/pos"),
                "El mesero debe ser reencaminado a su vista autorizada (/pos).");
    }

    @Test(description = "CP-RBAC-02-B: Cajero intentando acceder directamente a /contabilidad")
    public void testCajeroAccediendoContabilidad() {
        iniciarSesionComo("cajero@sgco-chayito.local", "Password123!");
        NavigationPage nav = new NavigationPage(driver);
        nav.esperarUrlContiene("/caja");

        // Intentar navegar a contabilidad
        driver.get(baseUrl + "/contabilidad");

        nav.esperarUrlContiene("/caja");
        Assert.assertFalse(driver.getCurrentUrl().contains("/contabilidad"),
                "El cajero no debe tener acceso a la vista de Contabilidad.");
        Assert.assertTrue(driver.getCurrentUrl().contains("/caja"),
                "El cajero debe ser reencaminado a su vista autorizada (/caja).");
    }
}
