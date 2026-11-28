package com.sgco.chayito.pages;

import org.openqa.selenium.By;
import org.openqa.selenium.JavascriptExecutor;
import org.openqa.selenium.WebDriver;
import org.openqa.selenium.WebElement;
import org.openqa.selenium.support.ui.ExpectedConditions;
import org.openqa.selenium.support.ui.WebDriverWait;

import java.time.Duration;
import java.util.List;

/**
 * Clase base para todos los Page Objects del sistema SGCO-Chayito.
 * Proporciona métodos comunes de interacción y esperas explícitas.
 *
 * @autor César Josué
 * @módulo B5 - Cuadrante 3: Pruebas de Aceptación
 */
public abstract class BasePage {
    protected WebDriver driver;
    protected WebDriverWait wait;
    protected Duration defaultTimeout = Duration.ofSeconds(10);

    public BasePage(WebDriver driver) {
        this.driver = driver;
        this.wait = new WebDriverWait(driver, defaultTimeout);
    }

    public BasePage(WebDriver driver, Duration timeout) {
        this.driver = driver;
        this.wait = new WebDriverWait(driver, timeout);
    }

    protected WebElement esperarVisible(By locator) {
        return wait.until(ExpectedConditions.visibilityOfElementLocated(locator));
    }

    protected WebElement esperarClickeable(By locator) {
        return wait.until(ExpectedConditions.elementToBeClickable(locator));
    }

    protected boolean esperarDesaparicion(By locator) {
        return wait.until(ExpectedConditions.invisibilityOfElementLocated(locator));
    }

    protected void click(By locator) {
        esperarClickeable(locator).click();
    }

    protected void escribir(By locator, String texto) {
        WebElement elemento = esperarVisible(locator);
        elemento.clear();
        elemento.sendKeys(texto);
    }

    protected String obtenerTexto(By locator) {
        return esperarVisible(locator).getText().trim();
    }

    protected boolean estaVisible(By locator) {
        try {
            return esperarVisible(locator).isDisplayed();
        } catch (Exception e) {
            return false;
        }
    }

    protected List<WebElement> obtenerElementos(By locator) {
        wait.until(ExpectedConditions.presenceOfAllElementsLocatedBy(locator));
        return driver.findElements(locator);
    }

    public String obtenerUrlActual() {
        return driver.getCurrentUrl();
    }

    public boolean esperarUrlContiene(String fragmento) {
        return wait.until(ExpectedConditions.urlContains(fragmento));
    }

    public void navegarA(String url) {
        driver.get(url);
    }

    public void scrollHaciaElemento(WebElement elemento) {
        ((JavascriptExecutor) driver).executeScript("arguments[0].scrollIntoView(true);", elemento);
    }
}
