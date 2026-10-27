import { expect, test } from '@playwright/test';

/**
 * Pruebas E2E de gráficas interactivas del dashboard gerencial.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-23
 * @módulo Reportes – RF-REP-007 / CC-75 / CC-76
 */

/**
 * Prepara una sesión administrativa aislada para acceder al dashboard protegido.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-23
 * @módulo Reportes – RF-REP-007 / CC-76
 *
 * @param {import('@playwright/test').Page} page Página que ejecuta la prueba.
 * @returns {Promise<void>}
 */
async function prepararSesionAdministrador(page) {
  await page.addInitScript(() => {
    localStorage.setItem('sgco_token', 'token-e2e-dashboard');
    localStorage.setItem('sgco_usuario', JSON.stringify({
      id: 1,
      name: 'Administrador General',
      rol: 'administrador',
    }));
  });
}

/**
 * Intercepta el dashboard para validar interacción visual sin modificar la base de datos local.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-23
 * @módulo Reportes – RF-REP-007 / CC-75
 *
 * @param {import('@playwright/test').Page} page Página que ejecuta la prueba.
 * @returns {Promise<void>}
 */
async function interceptarDashboardGerencial(page) {
  await page.route('**/api/reportes/dashboard**', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        status: 'ok',
        data: {
          periodo: { fecha_desde: '2026-10-01', fecha_hasta: '2026-10-23' },
          kpis: {
            ingresos: 1200,
            gastos_netos: 500,
            utilidad_bruta: 700,
            margen_bruto_porcentaje: 58.33,
            ventas_registradas: 18,
            compras_registradas: 4,
          },
          graficos: {
            ventas_mensuales: [
              { periodo: '2026-05', etiqueta: 'May', total: 420 },
              { periodo: '2026-06', etiqueta: 'Jun', total: 560 },
              { periodo: '2026-07', etiqueta: 'Jul', total: 680 },
              { periodo: '2026-08', etiqueta: 'Ago', total: 710 },
              { periodo: '2026-09', etiqueta: 'Sep', total: 910 },
              { periodo: '2026-10', etiqueta: 'Oct', total: 1200 },
            ],
            rentabilidad_por_platillo: [
              { platillo_id: 10, nombre: 'Pollo Asado', unidades: 12, ingresos: 720, utilidad_estimada: 420 },
              { platillo_id: 11, nombre: 'Sopa de Res', unidades: 8, ingresos: 480, utilidad_estimada: 280 },
            ],
          },
        },
      }),
    });
  });
}

test.describe('CC-75 - Dashboard gerencial', () => {
  test('muestra y permite explorar las gráficas financieras', async ({ page }) => {
    await prepararSesionAdministrador(page);
    await interceptarDashboardGerencial(page);

    await page.goto('/reportes');

    await expect(page.locator('#vista-dashboard-gerencial')).toBeVisible();
    await expect(page.locator('#grafico-ventas-mensuales')).toBeVisible();
    await expect(page.locator('#grafico-rentabilidad-platillos')).toBeVisible();

    await page.locator('#barra-venta-2026-08').click();
    await expect(page.locator('#grafico-ventas-mensuales output')).toContainText('Ago:');
    await expect(page.locator('#grafico-ventas-mensuales output')).toContainText('$710.00');

    await page.locator('#leyenda-rentabilidad-11').focus();
    await expect(page.locator('#grafico-rentabilidad-platillos output')).toContainText('Sopa de Res:');
    await expect(page.locator('#grafico-rentabilidad-platillos output')).toContainText('8 unidades');
  });
});
