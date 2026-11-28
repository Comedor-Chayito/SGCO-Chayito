package com.sgco.chayito.pages;

import org.openqa.selenium.By;
import org.openqa.selenium.WebDriver;
import org.openqa.selenium.WebElement;

import java.util.ArrayList;
import java.util.List;

/**
 * Page Object para el AppShell y la Barra Lateral de Navegación de SGCO-Chayito.
 * Mapea los controles de resources/js/shared/components/AppShell.vue (CC-86 / CC-152 / CC-183).
 *
 * @autor César Josué
 * @módulo B5 - Cuadrante 3: Pruebas de Aceptación
 */
public class NavigationPage extends BasePage {

    private final By barraLateral = By.xpath("//aside[contains(@class, 'fixed') or contains(@class, 'border-r')]");
    private final By navPrincipal = By.xpath("//nav[@aria-label='Navegación principal']");
    private final By enlacesNavegacion = By.xpath("//nav[@aria-label='Navegación principal']//a");
    private final By nombreUsuario = By.xpath("//aside//p[contains(@class, 'font-bold') and contains(@class, 'truncate')]");
    private final By rolUsuario = By.xpath("//aside//p[contains(@class, 'capitalize')]");
    private final By botonCerrarSesion = By.xpath("//aside//button[contains(., 'Cerrar sesión')]");

    public NavigationPage(WebDriver driver) {
        super(driver);
    }

    public boolean estaVisibleBarraLateral() {
        return estaVisible(barraLateral);
    }

    public String obtenerNombreUsuario() {
        return obtenerTexto(nombreUsuario);
    }

    public String obtenerRolUsuario() {
        return obtenerTexto(rolUsuario).toLowerCase();
    }

    public List<String> obtenerEtiquetasMenus() {
        List<WebElement> enlaces = obtenerElementos(enlacesNavegacion);
        List<String> etiquetas = new ArrayList<>();
        for (WebElement enlace : enlaces) {
            String texto = enlace.getText().trim();
            if (!texto.isEmpty()) {
                etiquetas.add(texto);
            }
        }
        return etiquetas;
    }

    public boolean existeEnlaceConTexto(String textoEsperado) {
        List<String> items = obtenerEtiquetasMenus();
        for (String item : items) {
            if (item.equalsIgnoreCase(textoEsperado) || item.contains(textoEsperado)) {
                return true;
            }
        }
        return false;
    }

    public void clickEnlacePorTexto(String texto) {
        By locatorEnlace = By.xpath("//nav[@aria-label='Navegación principal']//a[contains(., '" + texto + "')]");
        click(locatorEnlace);
    }

    public void cerrarSesion() {
        click(botonCerrarSesion);
    }
}
