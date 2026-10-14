import { test, expect } from '@playwright/test';

/**
 * Pruebas E2E de la pantalla de caja: formulario de retiros y métodos de pago (US-POS-03).
 * No confirman cobros ni registran retiros, para no alterar los datos de la base.
 *
 * @autor  manuelmv15
 * @fecha  2026-10-14
 * @módulo POS – US-POS-03 / RF-POS-002 (CC-38)
 */

/**
 * Inicia sesión como cajero con el usuario del seeder y abre la pantalla de caja.
 *
 * @autor  manuelmv15
 * @fecha  2026-10-14
 * @módulo POS – US-POS-03 / RF-POS-002 (CC-38)
 *
 * @param   {import('@playwright/test').Page} page Página de Playwright.
 * @returns {Promise<void>}
 */
async function entrarComoCajeroACaja(page) {
  await page.goto('/login');
  await page.locator('#campo-correo').fill('cajero@sgco-chayito.local');
  await page.locator('#campo-contrasena').fill('Password123!');
  await page.locator('#boton-login').click();
  await page.waitForURL('**/pos');

  await page.goto('/caja');
  await expect(page.locator('#vista-pantalla-cobro')).toBeVisible();
}

test.describe('Caja - retiros y métodos de pago', () => {
  test.beforeEach(async ({ page }) => {
    await entrarComoCajeroACaja(page);
  });

  test('el monto del retiro no muestra más de 2 decimales al teclear', async ({ page }) => {
    await page.locator('#btn-abrir-retiros').click();
    const campoMonto = page.locator('#campo-monto');

    await campoMonto.pressSequentially('3.33333');
    await expect(campoMonto).toHaveValue('3.33');

    await campoMonto.fill('');
    await campoMonto.pressSequentially('12a.5.55');
    await expect(campoMonto).toHaveValue('12.55');
  });

  test('con tarjeta o transferencia no se pide monto ni se muestra cambio', async ({ page }) => {
    const primeraComanda = page.locator('aside button').first();
    test.skip(!(await primeraComanda.isVisible()), 'No hay comandas en estado lista para cobrar');

    await primeraComanda.click();
    await expect(page.locator('#monto-recibido')).toBeVisible();

    await page.locator('#btn-metodo-tarjeta').click();
    await expect(page.locator('#monto-recibido')).toBeHidden();
    await expect(page.getByText('Se cobra el total exacto, sin cambio')).toBeVisible();
    await expect(page.locator('#btn-confirmar-cobro')).toBeEnabled();

    await page.locator('#btn-metodo-transferencia').click();
    await expect(page.locator('#monto-recibido')).toBeHidden();

    await page.locator('#btn-metodo-efectivo').click();
    await expect(page.locator('#monto-recibido')).toBeVisible();
  });
});
