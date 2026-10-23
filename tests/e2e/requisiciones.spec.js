import { test, expect } from '@playwright/test';

/**
 * Pruebas E2E del formulario móvil de requisiciones internas de insumos.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-22
 * @módulo Inventario – RF-INV-004 / CC-52
 */

/**
 * Intercepta el catálogo y el envío para probar la interfaz sin alterar bodega.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-22
 * @módulo Inventario – RF-INV-004 / CC-52
 *
 * @param   {import('@playwright/test').Page} page Página que ejecuta la prueba.
 * @param   {(payload: object) => void} alEnviar Callback que recibe el payload enviado.
 * @returns {Promise<void>}
 */
async function interceptarApiRequisiciones(page, alEnviar) {
  await page.route('**/api/insumos*', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        status: 'ok',
        message: 'Insumos obtenidos.',
        data: [
          { id: 1, nombre: 'Arroz', unidad_medida: 'kg', stock_actual: '20.000000' },
          { id: 2, nombre: 'Aceite vegetal', unidad_medida: 'lt', stock_actual: '10.000000' },
        ],
      }),
    });
  });

  await page.route('**/api/requisiciones', async (route) => {
    alEnviar(route.request().postDataJSON());
    await route.fulfill({
      status: 201,
      contentType: 'application/json',
      body: JSON.stringify({
        status: 'ok',
        message: 'Requisición enviada para aprobación.',
        data: { id: 91, estado: 'pendiente' },
      }),
    });
  });
}

/**
 * Prepara una sesión de cocinero para atravesar el guard de rutas de la SPA.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-22
 * @módulo Inventario – RF-INV-004 / CC-52
 *
 * @param   {import('@playwright/test').Page} page Página que ejecuta la prueba.
 * @returns {Promise<void>}
 */
async function prepararSesionCocinero(page) {
  await page.addInitScript(() => {
    localStorage.setItem('sgco_token', 'token-e2e-requisiciones');
    localStorage.setItem('sgco_usuario', JSON.stringify({
      id: 1,
      name: 'Cocinero de prueba',
      rol: 'cocinero',
    }));
  });
}

test.describe('CC-52 - Formulario de requisiciones', () => {
  test('agrega insumos y envía una requisición pendiente', async ({ page }) => {
    let payloadEnviado;
    await interceptarApiRequisiciones(page, (payload) => {
      payloadEnviado = payload;
    });
    await prepararSesionCocinero(page);

    await page.goto('/requisiciones/nueva');
    await expect(page.locator('#vista-formulario-requisicion')).toBeVisible();

    await page.locator('#select-area-solicitante').selectOption('cocina');
    await page.locator('#select-insumo-0').selectOption('1');
    await page.locator('#input-cantidad-0').fill('3.5');
    await page.locator('#input-observaciones').fill('Preparación de almuerzo.');
    await page.locator('#btn-agregar-detalle').click();
    await page.locator('#select-insumo-1').selectOption('2');
    await page.locator('#input-cantidad-1').fill('1');
    await page.locator('#btn-enviar-requisicion').click();

    await expect(page.getByText('Requisición #91 enviada para aprobación.')).toBeVisible();
    expect(payloadEnviado).toEqual({
      area_solicitante: 'cocina',
      observaciones: 'Preparación de almuerzo.',
      detalles: [
        { insumo_id: 1, cantidad_solicitada: 3.5 },
        { insumo_id: 2, cantidad_solicitada: 1 },
      ],
    });
  });

  test('mantiene controles táctiles sin solaparse en móvil', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await interceptarApiRequisiciones(page, () => {});
    await prepararSesionCocinero(page);

    await page.goto('/requisiciones/nueva');
    await expect(page.locator('#select-insumo-0')).toBeVisible();

    const selectorInsumo = await page.locator('#select-insumo-0').boundingBox();
    const botonEliminar = await page.locator('#btn-eliminar-detalle-0').boundingBox();
    const botonEnviar = await page.locator('#btn-enviar-requisicion').boundingBox();

    expect(selectorInsumo).not.toBeNull();
    expect(botonEliminar).not.toBeNull();
    expect(botonEnviar).not.toBeNull();
    expect(botonEliminar.y).toBeGreaterThanOrEqual(selectorInsumo.y + selectorInsumo.height);
    expect(botonEnviar.width).toBeGreaterThanOrEqual(340);

    await page.screenshot({ path: 'test-results/requisicion-movil.png', fullPage: true });
  });
});
