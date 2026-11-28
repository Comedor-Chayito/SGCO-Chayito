package com.sgco.chayito.pages;

import org.openqa.selenium.By;
import org.openqa.selenium.JavascriptExecutor;
import org.openqa.selenium.WebDriver;
import org.openqa.selenium.WebElement;
import org.openqa.selenium.support.ui.ExpectedConditions;

import java.util.List;

/**
 * Page Object para la vista de Punto de Venta (POS) de SGCO-Chayito.
 * Mapea los controles de resources/js/features/pos/views/POS.vue (CC-181 / CC-153).
 *
 * @autor César Josué
 * @módulo B5 - Cuadrante 3: Pruebas de Aceptación
 */
public class PosPage extends BasePage {

    private final By contenedorPos = By.id("vista-pos");
    private final By canalParaLlevar = By.xpath("//div[contains(@class, 'cursor-pointer') and .//*[contains(text(), 'Para Llevar')]] | //*[contains(text(), 'Para Llevar')]");
    private final By canalMesa = By.xpath("//div[contains(@class, 'cursor-pointer') and .//*[contains(text(), 'En Mesa')]] | //*[contains(text(), 'En Mesa')]");
    private final By botonesMesasLibres = By.xpath("//button[not(@disabled) and contains(@class, 'rounded-2xl')]");
    
    // Paso 2: Menú y Armador
    private final By tabAlaCarta = By.xpath("//button[contains(., 'A la carta')] | //*[contains(text(), 'A la carta')]");
    private final By selectorPlatillo = By.xpath("//button[contains(@aria-label, 'Agregar ')] | //button[contains(@class, 'touch-manipulation')] | //div[contains(@class, 'cursor-pointer') and contains(., '$')]");
    private final By botonAgregarPlato = By.xpath("//button[@data-testid='agregar-plato'] | //button[contains(., 'Agregar plato')]");
    private final By inputObservaciones = By.id("input-obs-pedido");
    private final By botonRegistrarOrden = By.xpath("//button[contains(., 'Registrar Orden')]");

    public PosPage(WebDriver driver) {
        super(driver);
    }

    public boolean estaEnVistaPos() {
        return estaVisible(contenedorPos);
    }

    public void seleccionarCanalParaLlevar() {
        try {
            wait.until(ExpectedConditions.invisibilityOfElementLocated(By.xpath("//*[contains(@class, 'animate-pulse')]")));
        } catch (Exception ignored) {}

        WebElement elemento = wait.until(ExpectedConditions.presenceOfElementLocated(canalParaLlevar));
        ((JavascriptExecutor) driver).executeScript("arguments[0].click();", elemento);

        wait.until(ExpectedConditions.presenceOfElementLocated(
                By.xpath("//*[contains(text(), 'Orden actual') or contains(text(), 'Armar bandeja') or contains(text(), 'A la carta')]")
        ));
    }

    public void seleccionarCanalMesa() {
        WebElement elemento = wait.until(ExpectedConditions.presenceOfElementLocated(canalMesa));
        ((JavascriptExecutor) driver).executeScript("arguments[0].click();", elemento);
    }

    public void seleccionarPrimeraMesaDisponible() {
        List<WebElement> mesas = obtenerElementos(botonesMesasLibres);
        if (!mesas.isEmpty()) {
            ((JavascriptExecutor) driver).executeScript("arguments[0].click();", mesas.get(0));
            wait.until(ExpectedConditions.presenceOfElementLocated(
                    By.xpath("//*[contains(text(), 'Orden actual') or contains(text(), 'Armar bandeja')]")
            ));
        } else {
            throw new RuntimeException("No hay mesas libres disponibles para seleccionar.");
        }
    }

    public void seleccionarPrimerPlatillo() {
        // Cambiar a pestaña 'A la carta' para selección directa
        try {
            if (!driver.findElements(tabAlaCarta).isEmpty()) {
                WebElement tab = driver.findElement(tabAlaCarta);
                ((JavascriptExecutor) driver).executeScript("arguments[0].click();", tab);
                try {
                    Thread.sleep(400);
                } catch (InterruptedException ignored) {}
            }
        } catch (Exception ignored) {}

        WebElement platillo = wait.until(ExpectedConditions.presenceOfElementLocated(selectorPlatillo));
        ((JavascriptExecutor) driver).executeScript("arguments[0].click();", platillo);
    }

    public void confirmarPlatoAlTicket() {
        try {
            // Si el armador de bandeja está activo y tiene botón Agregar plato, hacer click
            if (!driver.findElements(botonAgregarPlato).isEmpty()) {
                WebElement btn = driver.findElement(botonAgregarPlato);
                ((JavascriptExecutor) driver).executeScript("arguments[0].click();", btn);
            }
        } catch (Exception ignored) {}
    }

    public void escribirObservaciones(String texto) {
        try {
            if (!driver.findElements(inputObservaciones).isEmpty()) {
                WebElement input = driver.findElement(inputObservaciones);
                input.clear();
                input.sendKeys(texto);
            }
        } catch (Exception ignored) {}
    }

    public void registrarOrden() {
        // Asegurarse de confirmar el plato si el armador de bandeja sigue con plato pendiente
        try {
            if (!driver.findElements(botonAgregarPlato).isEmpty()) {
                WebElement btnPlato = driver.findElement(botonAgregarPlato);
                ((JavascriptExecutor) driver).executeScript("arguments[0].click();", btnPlato);
            }
        } catch (Exception ignored) {}

        WebElement btn = wait.until(ExpectedConditions.elementToBeClickable(botonRegistrarOrden));
        ((JavascriptExecutor) driver).executeScript("arguments[0].click();", btn);
    }

    public boolean esperarConfirmacionOrden() {
        try {
            return wait.until(d -> {
                String textoPagina = d.getPageSource();
                return textoPagina.contains("registrada") || textoPagina.contains("cola de cocina") || textoPagina.contains("Comanda #");
            });
        } catch (Exception e) {
            return false;
        }
    }
}
