import { test, expect } from '@playwright/test';

/**
 * Pruebas E2E del wizard POS y sus acciones táctiles principales.
 *
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-09-18
 * @módulo POS - RF-POS-001
 */

async function entrarParaLlevar(page) {
  await page.getByText('Para Llevar', { exact: true }).click();
  await expect(page.getByRole('tab', { name: 'Armar bandeja' })).toBeVisible();
}

async function seleccionarPrimeraPorcion(page) {
  const primeraCategoria = page.locator('section').filter({ hasText: 'opciones' }).first();
  const primeraOpcion = primeraCategoria.getByRole('button').first();

  await expect(primeraOpcion).toBeVisible();
  await primeraOpcion.click();
  await expect(page.getByTestId('agregar-plato')).toBeVisible();
}

test.describe('POS - Wizard de toma de pedidos', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/pos');
    await expect(page.locator('#vista-pos')).toBeVisible();
  });

  test('muestra los canales de venta y las mesas disponibles', async ({ page }) => {
    await expect(page.getByRole('heading', { name: '¿Dónde comerá el cliente?' })).toBeVisible();
    await expect(page.getByText('En Mesa', { exact: true })).toBeVisible();
    await expect(page.getByText('Para Llevar', { exact: true })).toBeVisible();
    await expect(page.getByText('WhatsApp', { exact: true })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Selecciona la Mesa' })).toBeVisible();
  });

  test('entra al menú y muestra las dos formas de agregar comida', async ({ page }) => {
    await entrarParaLlevar(page);

    await expect(page.getByText('Orden actual')).toBeVisible();
    await expect(page.getByText('Bandeja en curso')).toBeVisible();
    await expect(page.getByRole('tab', { name: 'A la carta' })).toBeVisible();
  });

  test('arma una bandeja y la agrega al ticket', async ({ page }) => {
    await entrarParaLlevar(page);
    await seleccionarPrimeraPorcion(page);

    await page.getByTestId('agregar-plato').click();

    await expect(page.getByText('Bandeja Personalizada')).toBeVisible();
    await expect(page.getByText('Plato agregado al ticket')).toBeVisible();
  });

  test('agrega un producto a la carta al ticket', async ({ page }) => {
    await entrarParaLlevar(page);
    await page.getByRole('tab', { name: 'A la carta' }).click();

    const primerProducto = page.getByRole('tabpanel').getByRole('button', { name: /^Agregar / }).first();
    const nombreAccesible = await primerProducto.getAttribute('aria-label');
    const nombreProducto = nombreAccesible.replace(/^Agregar /, '').replace(/ - \$\d+(\.\d{2})?$/, '');

    await primerProducto.click();
    await expect(page.getByRole('listitem').getByText(nombreProducto, { exact: true })).toBeVisible();
  });

  test('registra una orden sin escribir en el backend durante la prueba', async ({ page }) => {
    await page.route('**/api/comandas', async route => {
      await route.fulfill({
        status: 201,
        contentType: 'application/json',
        body: JSON.stringify({ status: 'success', message: 'Comanda registrada', data: { id: 321 } }),
      });
    });

    await entrarParaLlevar(page);
    await page.getByRole('tab', { name: 'A la carta' }).click();
    await page.getByRole('tabpanel').getByRole('button', { name: /^Agregar / }).first().click();
    await page.getByRole('button', { name: 'Registrar Orden' }).click();

    await expect(page.getByText('Comanda #321 registrada')).toBeVisible();
  });
});

test.describe('POS - Regresiones responsive de acciones', () => {
  test('en móvil, Agregar plato y Ticket no se solapan', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/pos');
    await entrarParaLlevar(page);
    await seleccionarPrimeraPorcion(page);

    const agregar = await page.getByTestId('agregar-plato').boundingBox();
    const ticket = await page.getByTestId('abrir-ticket').boundingBox();

    expect(agregar).not.toBeNull();
    expect(ticket).not.toBeNull();
    expect(agregar.x + agregar.width).toBeLessThanOrEqual(ticket.x);

    await page.waitForTimeout(250);
    await page.screenshot({ path: 'test-results/pos-mobile-step2.png', fullPage: true });
  });

  test('en tablet, Agregar plato permanece dentro del menú y fuera del ticket', async ({ page }) => {
    await page.setViewportSize({ width: 768, height: 1024 });
    await page.goto('/pos');
    await entrarParaLlevar(page);
    await seleccionarPrimeraPorcion(page);

    const agregar = await page.getByTestId('agregar-plato').boundingBox();
    const panelTicket = await page.getByRole('heading', { name: 'Ticket Actual' }).locator('..').boundingBox();

    expect(agregar).not.toBeNull();
    expect(panelTicket).not.toBeNull();
    expect(agregar.x + agregar.width).toBeLessThanOrEqual(panelTicket.x);

    await page.waitForTimeout(250);
    await page.screenshot({ path: 'test-results/pos-tablet-step2.png', fullPage: true });
  });
});
