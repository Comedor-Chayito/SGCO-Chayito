package com.sgco.chayito.pages;

import org.openqa.selenium.By;
import org.openqa.selenium.WebDriver;
import org.openqa.selenium.WebElement;

import java.util.List;

/**
 * Page Object para la vista de Caja y Cobro de SGCO-Chayito.
 * Mapea los controles de resources/js/features/pos/views/PantallaCobro.vue (CC-181 / CC-182).
 *
 * @autor César Josué
 * @módulo B5 - Cuadrante 3: Pruebas de Aceptación
 */
public class CajaPage extends BasePage {

    private final By listaComandas = By.xpath("//div[contains(@class, 'cursor-pointer') and contains(., 'Orden #')]");
    private final By botonMetodoEfectivo = By.id("btn-metodo-efectivo");
    private final By botonMetodoTarjeta = By.id("btn-metodo-tarjeta");
    private final By inputMontoRecibido = By.id("monto-recibido");
    private final By botonConfirmarCobro = By.id("btn-confirmar-cobro");
    private final By cambioEntregar = By.xpath("//span[contains(@class, 'text-[#2E7D4F]') and contains(., '$')]");
    private final By alertaCajaBloqueada = By.xpath("//*[contains(text(), 'Caja cerrada') or contains(text(), 'Sin apertura')]");

    public CajaPage(WebDriver driver) {
        super(driver);
    }

    public boolean hayComandasPendientes() {
        try {
            return !driver.findElements(listaComandas).isEmpty();
        } catch (Exception e) {
            return false;
        }
    }

    public void seleccionarPrimeraComanda() {
        List<WebElement> comandas = obtenerElementos(listaComandas);
        if (!comandas.isEmpty()) {
            comandas.get(0).click();
        } else {
            throw new RuntimeException("No se encontraron comandas pendientes de cobro.");
        }
    }

    public void seleccionarMetodoEfectivo() {
        click(botonMetodoEfectivo);
    }

    public void seleccionarMetodoTarjeta() {
        click(botonMetodoTarjeta);
    }

    public void ingresarMontoRecibido(String monto) {
        escribir(inputMontoRecibido, monto);
    }

    public void confirmarCobro() {
        click(botonConfirmarCobro);
    }

    public boolean esBotonCobroHabilitado() {
        return esperarVisible(botonConfirmarCobro).isEnabled();
    }

    public String obtenerCambioCalculado() {
        return obtenerTexto(cambioEntregar);
    }

    public boolean esCajaBloqueada() {
        try {
            return estaVisible(alertaCajaBloqueada);
        } catch (Exception e) {
            return false;
        }
    }
}
