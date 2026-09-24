import { expect, test } from '@playwright/test'

test('operator can choose form or Excel worksheet for Table 1', async ({ page }) => {
  test.setTimeout(60_000)
  await page.goto('/login')
  await page.getByLabel('Alamat email').fill('operator.bangka@example.com')
  await page.getByLabel('Kata sandi').fill('password')
  await page.getByRole('button', { name: 'Masuk' }).click()
  await expect(page).toHaveURL(/\/dashboard$/)

  await page.goto('/reporting-tables/1')
  await expect(page.getByRole('button', { name: 'Form Indikator' })).toHaveAttribute('aria-pressed', 'true', { timeout: 30_000 })
  await expect(page.getByLabel('Nilai Luas Wilayah')).toBeVisible()
  await expect(page.getByRole('button', { name: 'Simpan draft' })).toBeVisible()

  await page.getByRole('button', { name: 'Worksheet Excel' }).click()
  await expect(page.getByRole('table')).toContainText('BELITUNG TIMUR', { timeout: 30_000 })
  await expect(page.getByRole('table')).toContainText('PANGKALPINANG')
  await expect(page.getByRole('textbox')).toHaveCount(5)

  const households = page.getByLabel('Jumlah rumah tangga Bangka')
  const nextHouseholds = String(Number(await households.inputValue()) + 1)
  const saved = page.waitForResponse((response) => response.url().endsWith('/api/submissions/draft'))
  await households.fill(nextHouseholds)
  await households.press('Enter')
  const response = await saved
  expect(response.ok(), await response.text()).toBe(true)
  const request = response.request()
  const payload = request.postDataJSON() as { values: Array<{ indicator_id: number; value: string }> }
  expect(payload.values.at(-1)?.value).toBe(nextHouseholds)
  const result = await response.json() as { values: Array<{ indicator_id: number; numeric_value: number }> }
  const householdIndicator = payload.values.at(-1)!.indicator_id
  expect(result.values.find((value) => value.indicator_id === householdIndicator)?.numeric_value).toBe(Number(nextHouseholds))

  await expect(page.getByRole('status')).toContainText('Tersimpan')
  const bangka = page.getByRole('row').filter({ hasText: 'BANGKA' }).first()
  await expect(bangka).toContainText('81')
  await expect(bangka).toContainText('3.1')
  await expect(bangka).toContainText('114.5')
  await expect(page.getByRole('button', { name: 'Simpan draft' })).toHaveCount(0)

  await page.getByRole('button', { name: 'Form Indikator' }).click()
  await expect(page.getByLabel('Nilai Jumlah Rumah Tangga')).toHaveValue(nextHouseholds, { timeout: 30_000 })
  await expect(page.getByRole('button', { name: 'Simpan draft' })).toBeVisible()
  await page.screenshot({ path: 'test-results/table-one-worksheet.png', fullPage: true })
})
