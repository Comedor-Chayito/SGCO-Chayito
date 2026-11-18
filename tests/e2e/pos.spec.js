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

test.describe('POS - Cambios del catálogo con la pantalla abierta (US-ADM-03)', () => {
  /**
   * Mockea la API del POS con un menú mutable y cuenta los registros de comandas.
   *
   * @autor  Jeferson De La Cruz
   * @fecha  2026-11-17
   * @módulo POS – RF-POS-001 / US-ADM-03 (CC-112)
   */
  async function prepararApiMock(page, menu) {
    // Sesión de cajero aislada: el guard del router exige token y rol para entrar al POS.
    await page.addInitScript(() => {
      localStorage.setItem('sgco_token', 'token-e2e-pos');
      localStorage.setItem('sgco_usuario', JSON.stringify({ id: 1, name: 'Cajero E2E', rol: 'cajero' }));
    });
    await page.route('**/api/auth/usuario', (route) => route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        status: 'ok',
        message: '',
        data: { id: 1, name: 'Cajero E2E', rol: 'cajero', roles: [{ name: 'cajero' }] },
      }),
    }));

    // retrasoMenuMs simula red lenta en GET /platillos; alRegistrar permite simular el rechazo del servidor.
    const estado = {
      menu,
      comandasRegistradas: 0,
      solicitudesComanda: [],
      retrasoMenuMs: 0,
      alRegistrar: null,
    };
    const respuesta = (data, status = 200) => ({
      status,
      contentType: 'application/json',
      body: JSON.stringify({ status: 'ok', message: '', data }),
    });

    await page.route('**/api/platillos', async (route) => {
      if (estado.retrasoMenuMs > 0) await new Promise((r) => setTimeout(r, estado.retrasoMenuMs));
      return route.fulfill(respuesta(estado.menu));
    });
    await page.route('**/api/mesas', (route) => route.fulfill(respuesta([])));
    await page.route('**/api/comandas', (route) => {
      estado.solicitudesComanda.push(route.request().postDataJSON());

      if (estado.alRegistrar) return estado.alRegistrar(route);

      estado.comandasRegistradas += 1;
      return route.fulfill(respuesta({ id: 321 }, 201));
    });

    return estado;
  }

  async function agregarPrimerPlatoALaCarta(page) {
    await page.goto('/pos');
    await entrarParaLlevar(page);
    await page.getByRole('tab', { name: 'A la carta' }).click();
    await page.getByRole('tabpanel').getByRole('button', { name: /^Agregar / }).first().click();
  }

  test('un cambio de precio con el carrito abierto se avisa y no se registra a ciegas', async ({ page }) => {
    const sopa = { id: 901, nombre: 'Sopa de Prueba', descripcion: null, precio_unitario: '2.50', disponible: true };
    const estado = await prepararApiMock(page, [sopa]);

    await agregarPrimerPlatoALaCarta(page);
    await expect(page.getByText('$2.50').first()).toBeVisible();

    // Administración sube el precio mientras el POS sigue abierto.
    estado.menu = [{ ...sopa, precio_unitario: '4.75' }];
    await page.getByRole('button', { name: 'Registrar Orden' }).click();

    await expect(page.getByText('El precio de Sopa de Prueba cambió')).toBeVisible();
    await expect(page.getByText('Antes $2.50, ahora $4.75.')).toBeVisible();
    await expect(page.getByText('$4.75').first()).toBeVisible();
    expect(estado.comandasRegistradas).toBe(0);

    // Con el importe ya visible y actualizado, la segunda confirmación sí registra.
    await page.getByRole('button', { name: 'Registrar Orden' }).click();
    await expect(page.getByText('Comanda #321 registrada')).toBeVisible();
    expect(estado.comandasRegistradas).toBe(1);
  });

  test('un platillo desactivado con el carrito abierto se retira y no se registra', async ({ page }) => {
    const sopa = { id: 901, nombre: 'Sopa de Prueba', descripcion: null, precio_unitario: '2.50', disponible: true };
    const estado = await prepararApiMock(page, [sopa]);

    await agregarPrimerPlatoALaCarta(page);

    // Administración desactiva el platillo: el GET /platillos ya no lo devuelve.
    estado.menu = [];
    await page.getByRole('button', { name: 'Registrar Orden' }).click();

    await expect(page.getByText('Sopa de Prueba ya no está disponible')).toBeVisible();
    expect(estado.comandasRegistradas).toBe(0);
  });

  test('un doble clic con red lenta registra una sola comanda', async ({ page }) => {
    const sopa = { id: 901, nombre: 'Sopa de Prueba', descripcion: null, precio_unitario: '2.50', disponible: true };
    const estado = await prepararApiMock(page, [sopa]);

    await agregarPrimerPlatoALaCarta(page);

    // La verificación del menú tarda: el botón no debe aceptar un segundo envío mientras espera.
    estado.retrasoMenuMs = 400;
    await page.getByRole('button', { name: 'Registrar Orden' }).dblclick();

    await expect(page.getByText('Comanda #321 registrada')).toBeVisible();
    await page.waitForTimeout(800);

    expect(estado.solicitudesComanda).toHaveLength(1);
    expect(estado.comandasRegistradas).toBe(1);
  });

  test('envía el precio esperado y, si el servidor lo rechaza por cambio, no registra y actualiza', async ({ page }) => {
    const sopa = { id: 901, nombre: 'Sopa de Prueba', descripcion: null, precio_unitario: '2.50', disponible: true };
    const estado = await prepararApiMock(page, [sopa]);

    await agregarPrimerPlatoALaCarta(page);

    // El precio cambia después de la verificación del menú y antes de que el servidor registre:
    // el servidor responde 422 sobre precio_esperado y no crea la comanda.
    estado.alRegistrar = (route) => {
      estado.menu = [{ ...sopa, precio_unitario: '4.75' }];

      return route.fulfill({
        status: 422,
        contentType: 'application/json',
        body: JSON.stringify({
          status: 'error',
          message: 'El precio de Sopa de Prueba cambió a 4.75. Revisa la orden y vuelve a confirmarla.',
          errors: { 'items.0.precio_esperado': ['El precio cambió.'] },
          data: null,
        }),
      });
    };
    await page.getByRole('button', { name: 'Registrar Orden' }).click();

    await expect(page.getByText('Un precio cambió mientras se registraba la orden.')).toBeVisible();
    await expect(page.getByText('$4.75').first()).toBeVisible();
    expect(estado.comandasRegistradas).toBe(0);
    expect(estado.solicitudesComanda).toHaveLength(1);
    expect(estado.solicitudesComanda[0].items[0].precio_esperado).toBe(2.5);

    // Con el ticket actualizado, la reconfirmación envía el precio nuevo y registra.
    estado.alRegistrar = null;
    await page.getByRole('button', { name: 'Registrar Orden' }).click();

    await expect(page.getByText('Comanda #321 registrada')).toBeVisible();
    expect(estado.solicitudesComanda[1].items[0].precio_esperado).toBe(4.75);
  });
});
