package com.sgco.chayito.pages;

import org.openqa.selenium.By;
import org.openqa.selenium.JavascriptExecutor;
import org.openqa.selenium.WebDriver;
import org.openqa.selenium.WebElement;
import org.openqa.selenium.support.ui.ExpectedConditions;

/**
 * Page Object para la vista de Autenticación / Inicio de Sesión de SGCO-Chayito.
 * Mapea los controles de resources/js/features/admin/views/Login.vue (CC-89 / CC-153 / CC-181).
 *
 * @autor César Josué
 * @módulo B5 - Cuadrante 3: Pruebas de Aceptación
 */
public class LoginPage extends BasePage {

    private final By campoCorreo = By.id("campo-correo");
    private final By campoContrasena = By.id("campo-contrasena");
    private final By botonLogin = By.id("boton-login");
    private final By mensajeErrorGeneral = By.id("error-login");
    private final By erroresCampo = By.className("login-error-campo");
    private final By contenedorLogin = By.id("vista-login");

    public LoginPage(WebDriver driver) {
        super(driver);
    }

    public LoginPage ingresarCorreo(String correo) {
        escribir(campoCorreo, correo);
        return this;
    }

    public LoginPage ingresarContrasena(String contrasena) {
        escribir(campoContrasena, contrasena);
        return this;
    }

    public void hacerClickBotonLogin() {
        try {
            WebElement btn = wait.until(ExpectedConditions.elementToBeClickable(botonLogin));
            ((JavascriptExecutor) driver).executeScript("arguments[0].click();", btn);
        } catch (Exception e) {
            click(botonLogin);
        }
    }

    /**
     * Realiza el flujo completo de inicio de sesión.
     */
    public void iniciarSesion(String correo, String contrasena) {
        ingresarCorreo(correo);
        ingresarContrasena(contrasena);
        hacerClickBotonLogin();
    }

    public boolean estaEnVistaLogin() {
        return estaVisible(contenedorLogin);
    }

    /**
     * Espera de forma asíncrona a que aparezca un mensaje de error (general o de campo).
     */
    public boolean hayErrorVisible() {
        try {
            return wait.until(d -> {
                boolean general = !d.findElements(mensajeErrorGeneral).isEmpty() && d.findElement(mensajeErrorGeneral).isDisplayed();
                boolean campo = !d.findElements(erroresCampo).isEmpty() && d.findElement(erroresCampo).isDisplayed();
                return general || campo;
            });
        } catch (Exception e) {
            return false;
        }
    }

    public boolean hayErrorGeneralVisible() {
        return hayErrorVisible();
    }

    public String obtenerTextoError() {
        if (estaVisible(mensajeErrorGeneral)) {
            return obtenerTexto(mensajeErrorGeneral);
        }
        if (estaVisible(erroresCampo)) {
            return obtenerTexto(erroresCampo);
        }
        return "";
    }

    public String obtenerMensajeErrorGeneral() {
        return obtenerTextoError();
    }

    public boolean botonLoginHabilitado() {
        return esperarVisible(botonLogin).isEnabled();
    }
}
