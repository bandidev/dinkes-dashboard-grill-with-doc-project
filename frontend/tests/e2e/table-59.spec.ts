import { expect, test } from '@playwright/test'

test('operator fills Table 59 age-sex matrix and receives totals and proportions', async ({ page }) => {
  test.setTimeout(60_000)
  await page.goto('/login')
  await page.getByLabel('Alamat email').fill('operator.bangka@example.com')
  await page.getByLabel('Kata sandi').fill('password')
  await page.getByRole('button', { name: 'Masuk' }).click()
  await expect(page).toHaveURL(/\/dashboard$/)

  await page.goto('/reporting-tables/59')
  const ageGroups = ['≤ 4 Tahun', '5 - 14 Tahun', '15 - 19 Tahun', '20 - 24 Tahun', '25 - 49 Tahun', '≥ 50 Tahun']
  let total = 0
  let firstGroupTotal = 0
  for (const [index, ageGroup] of ageGroups.entries()) {
    const male = page.getByRole('row').filter({ hasText: `${ageGroup} • Laki-laki` }).getByRole('textbox')
    const female = page.getByRole('row').filter({ hasText: `${ageGroup} • Perempuan` }).getByRole('textbox')
    const maleValue = Number(await male.inputValue()) + 1
    const femaleValue = Number(await female.inputValue()) + 1
    await male.fill(String(maleValue))
    await female.fill(String(femaleValue))
    total += maleValue + femaleValue
    if (index === 0) firstGroupTotal = maleValue + femaleValue
  }
  const saved = page.waitForResponse((response) => response.url().endsWith('/api/submissions/draft') && response.ok())
  await page.getByRole('button', { name: 'Simpan draft' }).click()
  await saved

  await expect(page.getByRole('status')).toContainText('Draft berhasil disimpan', { timeout: 15_000 })
  await expect(page.getByRole('row').filter({ hasText: 'Semua Umur • L+P' })).toContainText(String(total))
  await expect(page.getByRole('row').filter({ hasText: '≤ 4 Tahun • L+P' }).filter({ hasText: 'Proporsi Kelompok Umur' })).toContainText(String(Math.round(firstGroupTotal / total * 10_000) / 100))
})
