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

/**
 * Prepara una sesión de administrador para acceder a la bandeja de bodega.
 *
 * @autor  Alexis
 * @fecha  2026-10-22
 * @módulo Inventario – RF-INV-004 / CC-53 / CC-54
 *
 * @param {import('@playwright/test').Page} page Página que ejecuta la prueba.
 * @returns {Promise<void>}
 */
async function prepararSesionAdministrador(page) {
  await page.addInitScript(() => {
    localStorage.setItem('sgco_token', 'token-e2e-bodega');
    localStorage.setItem('sgco_usuario', JSON.stringify({
      id: 2,
      name: 'Administradora de bodega',
      rol: 'administrador',
    }));
  });
}

/**
 * Intercepta el ciclo administrativo de una requisición sin modificar la base real.
 *
 * @autor  Alexis
 * @fecha  2026-10-22
 * @módulo Inventario – RF-INV-004 / CC-53 / CC-54
 *
 * @param {import('@playwright/test').Page} page Página que ejecuta la prueba.
 * @param {(payload: object) => void} alResolver Callback con las cantidades aprobadas.
 * @returns {Promise<void>}
 */
async function interceptarGestionRequisiciones(page, alResolver) {
  const requisicion = {
    id: 15,
    area_solicitante: 'cocina',
    estado: 'pendiente',
    observaciones: 'Preparación de almuerzo.',
    fecha_resolucion: null,
    usuario_solicita: { id: 1, name: 'Cocinero de prueba' },
    usuario_aprueba: null,
    detalles: [
      {
        id: 31,
        cantidad_solicitada: '4.00',
        cantidad_aprobada: null,
        insumo: { id: 1, nombre: 'Arroz', unidad_medida: 'kg', stock_actual: '20.000000' },
      },
    ],
  };

  await page.route('**/api/requisiciones', async (route) => {
    if (route.request().method() !== 'GET') return route.fallback();

    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ status: 'ok', message: 'Requisiciones obtenidas.', data: [requisicion] }),
    });
  });

  await page.route('**/api/requisiciones/15/resolucion', async (route) => {
    const payload = route.request().postDataJSON();
    alResolver(payload);
    requisicion.estado = 'aprobada';
    requisicion.fecha_resolucion = '2026-10-22T12:00:00.000000Z';
    requisicion.usuario_aprueba = { id: 2, name: 'Administradora de bodega' };
    requisicion.detalles[0].cantidad_aprobada = String(payload.detalles[0].cantidad_aprobada.toFixed(2));

    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ status: 'ok', message: 'Requisición aprobada correctamente.', data: requisicion }),
    });
  });

  await page.route('**/api/requisiciones/15/transferir', async (route) => {
    requisicion.estado = 'transferida';

    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ status: 'ok', message: 'Stock transferido.', data: requisicion }),
    });
  });
}

test.describe('CC-53 y CC-54 - Gestión de requisiciones', () => {
  test('aprueba una cantidad parcial y transfiere la requisición a cocina', async ({ page }) => {
    let payloadResolucion;
    await interceptarGestionRequisiciones(page, (payload) => {
      payloadResolucion = payload;
    });
    await prepararSesionAdministrador(page);

    await page.goto('/requisiciones');
    await expect(page.locator('#vista-gestion-requisiciones')).toBeVisible();
    await page.locator('#btn-revisar-requisicion-15').click();
    await page.locator('#input-cantidad-aprobada-31').fill('2.5');
    await page.locator('#btn-aprobar-requisicion-15').click();

    await expect(page.getByText('Requisición aprobada. Ya está disponible para transferir.')).toBeVisible();
    expect(payloadResolucion).toEqual({
      estado: 'aprobada',
      detalles: [{ id: 31, cantidad_aprobada: 2.5 }],
    });
    await expect(page.locator('#btn-transferir-requisicion-15')).toBeVisible();

    await page.locator('#btn-transferir-requisicion-15').click();
    await expect(page.getByText('Stock transferido al área de Cocina.')).toBeVisible();
    await expect(page.getByText('Stock transferido al área operativa.')).toBeVisible();
  });
});
