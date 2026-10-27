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
 * Inicia sesión como cajero con el usuario del seeder; la caja es su vista de inicio.
 *
 * @autor  manuelmv15
 * @fecha  2026-10-14
 * @módulo POS – US-POS-03 / RF-POS-002 (CC-38) / RF-ADM-008 (CC-86)
 *
 * @param   {import('@playwright/test').Page} page Página de Playwright.
 * @returns {Promise<void>}
 */
async function entrarComoCajeroACaja(page) {
  await page.goto('/login');
  await page.locator('#campo-correo').fill('cajero@sgco-chayito.local');
  await page.locator('#campo-contrasena').fill('Password123!');
  await page.locator('#boton-login').click();
  await page.waitForURL('**/caja');

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

  test('el formulario de saldo inicial calcula el total en tiempo real por denominación (CC-39)', async ({ page }) => {
    await page.locator('#btn-abrir-saldo-inicial').click();
    await expect(page.locator('#formulario-saldo-inicial')).toBeVisible();

    // Esperar a que el panel termine de consultar la apertura: muestra el conteo o el estado
    const estadoCaja = page.locator('#estado-caja');
    const btnInc20 = page.locator('#btn-incrementar-20');
    await expect(estadoCaja.or(btnInc20)).toBeVisible();

    // Si ya existe apertura del día, el formulario muestra la información de apertura inalterable
    if (await estadoCaja.isVisible()) {
      await expect(estadoCaja).toHaveText(/Caja (Abierta|Cerrada)/);
      await expect(page.getByText('Desglose de Denominaciones Registrado')).toBeVisible();
      return;
    }

    // Probar incremento de billetes de $20 y $10
    await btnInc20.click();
    await btnInc20.click(); // 2 * $20 = $40

    const input10 = page.locator('#input-cantidad-10');
    await input10.fill('1'); // 1 * $10 = $10

    // Verificar que el total se actualice a $50.00
    await expect(page.getByText('$50.00').first()).toBeVisible();

    // Probar botón de poner a cero
    await page.locator('#btn-reiniciar-conteo').click();
    await expect(page.getByText('$0.00').first()).toBeVisible();
  });

  test('la pantalla de cobro tiene acceso al reporte de cierre de caja y se muestra correctamente (CC-42)', async ({ page }) => {
    await page.locator('#btn-ir-cierre-caja').click();
    await page.waitForURL('**/caja/cierre');
    await expect(page.locator('#vista-reporte-cierre')).toBeVisible();
    await expect(page.getByText('Cierre Diario y Cuadre de Caja')).toBeVisible();

    // Probar cambio a la pestaña de historial
    await page.locator('#tab-historial').click();
    await expect(page.getByText('Historial de Cierres de Caja')).toBeVisible();

    // Regresar a pestaña de reporte
    await page.locator('#tab-reporte').click();
    await expect(page.locator('#tab-reporte')).toBeVisible();
  });
});

