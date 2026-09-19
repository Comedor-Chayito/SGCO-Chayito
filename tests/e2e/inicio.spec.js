import { test, expect } from '@playwright/test';

/**
 * Pruebas E2E — Vista de inicio del sistema SGCO-Chayito.
 * Verifica que la aplicación Vue cargue correctamente.
 *
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-09-18
 * @módulo Core – pruebas de integración
 */

test.describe('Inicio de la aplicación', () => {
  test('debe cargar la SPA y mostrar el título principal', async ({ page }) => {
    await page.goto('/');

    // El contenedor de montaje de Vue debe existir
    await expect(page.locator('#app')).toBeVisible();

    // La vista de inicio debe renderizarse
    await expect(page.locator('#vista-inicio')).toBeVisible();

    // Título de la aplicación
    await expect(page.locator('h1')).toContainText('SGCO-Chayito');
  });

  test('debe mostrar el indicador de sistema operativo', async ({ page }) => {
    await page.goto('/');

    await expect(page.locator('#estado-sistema')).toBeVisible();
    await expect(page.locator('#estado-sistema')).toContainText('Sistema operativo');
  });

  test('ruta inexistente debe mostrar 404', async ({ page }) => {
    await page.goto('/ruta-que-no-existe');

    await expect(page.locator('#vista-no-encontrado')).toBeVisible();
    await expect(page.locator('h1')).toContainText('404');
  });
});
