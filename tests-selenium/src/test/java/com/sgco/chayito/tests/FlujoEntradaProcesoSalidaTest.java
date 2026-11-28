package com.sgco.chayito.tests;

import com.sgco.chayito.pages.CajaPage;
import com.sgco.chayito.pages.LoginPage;
import com.sgco.chayito.pages.NavigationPage;
import com.sgco.chayito.pages.PosPage;
import org.testng.Assert;
import org.testng.annotations.Test;

/**
 * Pruebas automatizadas del Flujo Integral de Entrada, Proceso y Salida (CC-181 / CC-182).
 * Cubre desde el inicio de sesión (Entrada), toma de comanda en POS (Proceso), hasta el cobro y arqueo (Salida).
 *
 * @autor César Josué
 * @módulo B5 - Cuadrante 3: Pruebas de Aceptación
 */
public class FlujoEntradaProcesoSalidaTest extends BaseTest {

    @Test(description = "CP-ACEP-01: Validación de entrada - Intento de inicio de sesión con credenciales inválidas")
    public void testLoginCredencialesInvalidas() {
        driver.get(baseUrl + "/login");
        LoginPage loginPage = new LoginPage(driver);

        loginPage.iniciarSesion("usuario.falso@comedorchayito.edu.sv", "PasswordErroneo123!");

        Assert.assertTrue(loginPage.hayErrorVisible(),
                "Debe mostrarse un mensaje de alerta cuando las credenciales no coinciden.");
        String mensaje = loginPage.obtenerTextoError();
        Assert.assertTrue(mensaje.contains("coinciden") || mensaje.contains("incorrectos") || mensaje.contains("Verifica"),
                "El mensaje debe alertar sobre error en correo o contraseña.");
    }

    @Test(description = "CP-ACEP-03: Validación de proceso - Registro de comanda en POS")
    public void testRegistroComandaPos() {
        iniciarSesionComo("mesero@sgco-chayito.local", "Password123!");
        PosPage pos = new PosPage(driver);
        pos.esperarUrlContiene("/pos");

        Assert.assertTrue(pos.estaEnVistaPos(), "El sistema debe cargar la pantalla del POS.");

        // Paso 1: Seleccionar canal Para Llevar (avanza automáticamente a Paso 2)
        pos.seleccionarCanalParaLlevar();

        // Paso 2: Seleccionar platillo del menú y agregar al ticket
        pos.seleccionarPrimerPlatillo();
        pos.confirmarPlatoAlTicket();

        // Añadir nota de cocina
        pos.escribirObservaciones("Prueba Aceptación Selenium E2E");

        // Registrar orden
        pos.registrarOrden();

        // Validar mensaje de confirmación
        boolean registrada = pos.esperarConfirmacionOrden();
        Assert.assertTrue(registrada, "Debe mostrarse la notificación de que la comanda fue registrada con éxito.");
    }

    @Test(description = "CP-ACEP-04: Validación de salida - Cobro de ticket con cálculo de cambio en tiempo real")
    public void testCobroTicketConCambio() {
        iniciarSesionComo("cajero@sgco-chayito.local", "Password123!");
        CajaPage caja = new CajaPage(driver);
        caja.esperarUrlContiene("/caja");

        if (caja.hayComandasPendientes()) {
            caja.seleccionarPrimeraComanda();
            caja.seleccionarMetodoEfectivo();
            caja.ingresarMontoRecibido("20.00");

            Assert.assertTrue(caja.esBotonCobroHabilitado(),
                    "El botón de confirmación debe habilitarse cuando el monto recibido es suficiente.");
            String cambio = caja.obtenerCambioCalculado();
            Assert.assertTrue(cambio.contains("$"),
                    "El panel debe mostrar el monto calculado del cambio a entregar.");
        } else {
            System.out.println("No hay comandas pendientes listas para cobro al momento de la ejecución de este test.");
        }
    }
}
