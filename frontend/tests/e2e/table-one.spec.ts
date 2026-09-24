import { expect, test } from '@playwright/test'

test('operator edits Table 1 inline like its Excel worksheet', async ({ page }) => {
  test.setTimeout(60_000)
  await page.goto('/login')
  await page.getByLabel('Alamat email').fill('operator.bangka@example.com')
  await page.getByLabel('Kata sandi').fill('password')
  await page.getByRole('button', { name: 'Masuk' }).click()
  await expect(page).toHaveURL(/\/dashboard$/)

  await page.goto('/reporting-tables/1')
  await expect(page.getByRole('table')).toContainText('BELITUNG TIMUR', { timeout: 30_000 })
  await expect(page.getByRole('table')).toContainText('PANGKALPINANG')
  await expect(page.getByRole('textbox')).toHaveCount(5)

  const households = page.getByLabel('Jumlah rumah tangga Bangka')
  const nextHouseholds = String(Number(await households.inputValue()) + 1)
  const saved = page.waitForResponse((response) => response.url().endsWith('/api/submissions/draft') && response.ok())
  await households.fill(nextHouseholds)
  await households.press('Enter')
  await saved

  await expect(page.getByRole('status')).toContainText('Tersimpan')
  const bangka = page.getByRole('row').filter({ hasText: 'BANGKA' }).first()
  await expect(bangka).toContainText('81')
  await expect(bangka).toContainText('3.1')
  await expect(bangka).toContainText('114.5')
  await expect(page.getByRole('button', { name: 'Simpan draft' })).toHaveCount(0)
  await page.screenshot({ path: 'test-results/table-one-worksheet.png', fullPage: true })
})
