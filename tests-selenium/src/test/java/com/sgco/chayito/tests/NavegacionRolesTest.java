package com.sgco.chayito.tests;

import com.sgco.chayito.pages.LoginPage;
import com.sgco.chayito.pages.NavigationPage;
import org.testng.Assert;
import org.testng.annotations.Test;

import java.util.List;

/**
 * Pruebas automatizadas de Navegación por Rol y Redirección inicial (CC-153 / CP-NAV-01 y CP-NAV-02).
 * Valida que cada rol de usuario sea dirigido a su vista correspondiente y visualice los menús permitidos.
 *
 * @autor César Josué
 * @módulo B5 - Cuadrante 3: Pruebas de Aceptación
 */
public class NavegacionRolesTest extends BaseTest {

    @Test(description = "CP-NAV-01-A: Redirección inicial de Administrador a /pos")
    public void testRedireccionAdministrador() {
        iniciarSesionComo("admin@sgco-chayito.local", "Admin1234!");
        NavigationPage nav = new NavigationPage(driver);
        nav.esperarUrlContiene("/pos");

        Assert.assertTrue(driver.getCurrentUrl().contains("/pos"),
                "El administrador debe ser redirigido a la vista de POS.");
        Assert.assertEquals(nav.obtenerRolUsuario(), "administrador",
                "El rol mostrado en la barra lateral debe ser administrador.");
    }

    @Test(description = "CP-NAV-01-B: Redirección inicial de Cajero a /caja")
    public void testRedireccionCajero() {
        iniciarSesionComo("cajero@sgco-chayito.local", "Password123!");
        NavigationPage nav = new NavigationPage(driver);
        nav.esperarUrlContiene("/caja");

        Assert.assertTrue(driver.getCurrentUrl().contains("/caja"),
                "El cajero debe ser redirigido a la vista de Caja.");
        Assert.assertEquals(nav.obtenerRolUsuario(), "cajero",
                "El rol mostrado en la barra lateral debe ser cajero.");
    }

    @Test(description = "CP-NAV-01-C: Redirección inicial de Mesero a /pos")
    public void testRedireccionMesero() {
        iniciarSesionComo("mesero@sgco-chayito.local", "Password123!");
        NavigationPage nav = new NavigationPage(driver);
        nav.esperarUrlContiene("/pos");

        Assert.assertTrue(driver.getCurrentUrl().contains("/pos"),
                "El mesero debe ser redirigido a la vista de POS.");
        Assert.assertEquals(nav.obtenerRolUsuario(), "mesero",
                "El rol mostrado en la barra lateral debe ser mesero.");
    }

    @Test(description = "CP-NAV-01-D: Redirección inicial de Cocinero a /cocina")
    public void testRedireccionCocinero() {
        iniciarSesionComo("cocinero@sgco-chayito.local", "Password123!");
        NavigationPage nav = new NavigationPage(driver);
        nav.esperarUrlContiene("/cocina");

        Assert.assertTrue(driver.getCurrentUrl().contains("/cocina"),
                "El cocinero debe ser redirigido a la cola de Cocina.");
        Assert.assertEquals(nav.obtenerRolUsuario(), "cocinero",
                "El rol mostrado en la barra lateral debe ser cocinero.");
    }

    @Test(description = "CP-NAV-01-E: Redirección inicial de Contadora a /contabilidad")
    public void testRedireccionContadora() {
        iniciarSesionComo("contadora@sgco-chayito.local", "Password123!");
        NavigationPage nav = new NavigationPage(driver);
        nav.esperarUrlContiene("/contabilidad");

        Assert.assertTrue(driver.getCurrentUrl().contains("/contabilidad"),
                "La contadora debe ser redirigida a la vista de Contabilidad.");
        Assert.assertEquals(nav.obtenerRolUsuario(), "contadora",
                "El rol mostrado en la barra lateral debe ser contadora.");
    }

    @Test(description = "CP-NAV-01-F: Redirección inicial de Socio a /reportes")
    public void testRedireccionSocio() {
        iniciarSesionComo("socio@sgco-chayito.local", "Password123!");
        NavigationPage nav = new NavigationPage(driver);
        nav.esperarUrlContiene("/reportes");

        Assert.assertTrue(driver.getCurrentUrl().contains("/reportes"),
                "El socio debe ser redirigido al Dashboard de Reportes.");
        Assert.assertEquals(nav.obtenerRolUsuario(), "socio",
                "El rol mostrado en la barra lateral debe ser socio.");
    }

    @Test(description = "CP-NAV-02: Verificación de menú lateral según Mínimo Privilegio (Mesero vs Administrador)")
    public void testMenuLateralMesero() {
        iniciarSesionComo("mesero@sgco-chayito.local", "Password123!");
        NavigationPage nav = new NavigationPage(driver);
        nav.esperarUrlContiene("/pos");

        List<String> menus = nav.obtenerEtiquetasMenus();
        // Mesero solo debe ver Punto de Venta
        Assert.assertTrue(nav.existeEnlaceConTexto("Punto de venta"),
                "El mesero debe visualizar el acceso a Punto de venta.");
        Assert.assertFalse(nav.existeEnlaceConTexto("Caja"),
                "El mesero NO debe visualizar el módulo de Caja.");
        Assert.assertFalse(nav.existeEnlaceConTexto("Administración"),
                "El mesero NO debe visualizar el módulo de Administración.");
        Assert.assertFalse(nav.existeEnlaceConTexto("Contabilidad"),
                "El mesero NO debe visualizar el módulo de Contabilidad.");
    }
}
