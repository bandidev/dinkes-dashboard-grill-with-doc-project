import { expect, test } from '@playwright/test'

test('operator can choose compact input or full table for Table 1', async ({ page }) => {
  test.setTimeout(90_000)
  await page.goto('/login')
  await page.getByLabel('Alamat email').fill('operator.bangka@example.com')
  await page.getByLabel('Kata sandi').fill('password')
  await page.getByRole('button', { name: 'Masuk' }).click()
  await expect(page).toHaveURL(/\/dashboard$/)

  await page.goto('/reporting-tables/1')
  await expect(page.getByRole('button', { name: /Input Ringkas/ })).toHaveAttribute('aria-pressed', 'true', { timeout: 60_000 })
  const reopen = page.getByRole('button', { name: 'Perbaiki Input' })
  if (await reopen.isVisible()) {
    await reopen.click()
    await page.getByLabel('Alasan perubahan status').fill('Persiapan pengujian otomatis.')
    await page.getByRole('button', { name: 'Buka untuk diperbaiki' }).click()
    await expect(page.getByRole('button', { name: 'Simpan draft' })).toBeVisible({ timeout: 30_000 })
  }
  await expect(page.getByLabel('Nilai Luas Wilayah')).toBeVisible()
  await expect(page.getByLabel('Nilai Luas Wilayah')).toBeEnabled()

  await page.getByRole('button', { name: 'Tabel Lengkap' }).click()
  await expect(page.getByRole('table')).toContainText('BELITUNG TIMUR', { timeout: 30_000 })
  await expect(page.getByRole('table')).toContainText('PANGKALPINANG')
  await expect(page.getByRole('textbox')).toHaveCount(5)

  const households = page.getByLabel('Jumlah rumah tangga Bangka')
  const nextHouseholds = String(Number(await households.inputValue()) + 1)
  const saved = page.waitForResponse((response) => {
    if (!response.url().endsWith('/api/submissions/draft')) return false
    const payload = response.request().postDataJSON() as { values?: Array<{ value?: string }> }
    return payload.values?.some((item) => item.value === nextHouseholds) || false
  })
  await households.fill(nextHouseholds)
  await households.press('Enter')
  const response = await saved
  expect(response.ok(), await response.text()).toBe(true)
  const request = response.request()
  const payload = request.postDataJSON() as { values: Array<{ indicator_id: number; value: string }> }
  expect(payload.values.at(-1)?.value).toBe(nextHouseholds)
  const result = await response.json() as { values: Array<{ indicator_id: number; numeric_value: number | string }> }
  const householdIndicator = payload.values.at(-1)!.indicator_id
  expect(Number(result.values.find((value) => value.indicator_id === householdIndicator)?.numeric_value)).toBe(Number(nextHouseholds))

  await expect(page.getByRole('status')).toContainText(/tersimpan/i)
  await expect(households).toHaveValue(nextHouseholds)
  await expect(page.getByRole('button', { name: 'Simpan draft' })).toHaveCount(0)

  let delayed = true
  await page.route('**/api/submissions/draft', async (route) => {
    if (delayed) {
      delayed = false
      await new Promise((resolve) => setTimeout(resolve, 500))
    }
    await route.continue()
  })
  const villages = page.getByLabel('Desa Bangka')
  const wards = page.getByLabel('Kelurahan Bangka')
  const nextVillages = String(Number(await villages.inputValue()) + 1)
  const nextWards = String(Number(await wards.inputValue()) + 1)
  await villages.fill(nextVillages)
  const expectedVillageWardTotal = String(Number(nextVillages) + Number(await wards.inputValue()))
  const bangkaRow = page.getByRole('row').filter({ hasText: 'BANGKA' }).first()
  await expect(bangkaRow).toContainText(expectedVillageWardTotal)
  await expect(page.getByRole('status')).toContainText('Belum tersimpan')
  await villages.press('Enter')
  page.once('dialog', async (dialog) => {
    expect(dialog.message()).toContain('Tabel Lengkap belum tersimpan')
    await dialog.dismiss()
  })
  await page.getByRole('link', { name: 'Dashboard' }).click()
  await expect(page).toHaveURL(/\/reporting-tables\/1/)
  await wards.fill(nextWards)
  await wards.press('Enter')
  await expect(page.getByRole('status')).toContainText(/tersimpan/i, { timeout: 30_000 })
  await expect(bangkaRow).toContainText(String(Number(nextVillages) + Number(nextWards)))

  await page.setViewportSize({ width: 390, height: 844 })
  await expect.poll(() => page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true)
  expect((await households.boundingBox())?.height).toBeGreaterThanOrEqual(44)

  await page.getByRole('button', { name: /Input Ringkas/ }).click()
  await expect(page.getByLabel('Nilai Jumlah Rumah Tangga')).toHaveValue(nextHouseholds, { timeout: 30_000 })
  await expect(page.getByLabel('Nilai Jumlah Desa')).toHaveValue(nextVillages)
  await expect(page.getByLabel('Nilai Jumlah Kelurahan')).toHaveValue(nextWards)
  await page.reload()
  await expect(page.getByLabel('Nilai Jumlah Desa')).toHaveValue(nextVillages, { timeout: 60_000 })
  await expect(page.getByLabel('Nilai Jumlah Kelurahan')).toHaveValue(nextWards)
  await expect(page.getByRole('button', { name: 'Simpan draft' })).toBeVisible()
  await page.screenshot({ path: 'test-results/table-one-worksheet.png', fullPage: true })
})

test('operator must save compact input before leaving or completing Table 1', async ({ page }) => {
  test.setTimeout(90_000)
  await page.goto('/login')
  await page.getByLabel('Alamat email').fill('operator.bangka@example.com')
  await page.getByLabel('Kata sandi').fill('password')
  await page.getByRole('button', { name: 'Masuk' }).click()
  await expect(page).toHaveURL(/\/dashboard$/, { timeout: 15_000 })
  await page.goto('/reporting-tables')
  const tableOneRow = page.getByRole('row').filter({ has: page.getByRole('cell', { name: '01', exact: true }) })
  await tableOneRow.getByRole('link', { name: 'Buka tabel' }).click()
  await expect(page).toHaveURL(/\/reporting-tables\/1(?:\?|$)/)
  const reopen = page.getByRole('button', { name: 'Perbaiki Input' })
  if (await reopen.isVisible()) {
    await reopen.click()
    await page.getByLabel('Alasan perubahan status').fill('Persiapan pengujian otomatis.')
    await page.getByRole('button', { name: 'Buka untuk diperbaiki' }).click()
  }

  const area = page.getByLabel('Nilai Luas Wilayah')
  await expect(area).toBeVisible({ timeout: 60_000 })
  await expect(area).toBeEnabled()
  await area.fill(String(Number(await area.inputValue()) + 0.1))
  await expect(page.getByText('Perubahan belum disimpan')).toBeVisible()
  await expect(page.getByRole('button', { name: 'Selesai Input' })).toBeDisabled()

  page.on('dialog', async (dialog) => {
    await dialog.dismiss().catch(() => undefined)
  })
  await page.getByRole('link', { name: 'Dashboard' }).click()
  await expect(page).toHaveURL(/\/reporting-tables\/1/)

  await page.goBack()
  await expect(page).toHaveURL(/\/reporting-tables\/1/)

  const saved = page.waitForResponse((response) => response.url().endsWith('/api/submissions/draft') && response.ok())
  await page.getByRole('button', { name: 'Simpan draft' }).click()
  await saved
  await expect(page.getByText('Perubahan belum disimpan')).toHaveCount(0, { timeout: 30_000 })
  await page.getByRole('link', { name: 'Dashboard' }).click()
  await expect(page).toHaveURL(/\/dashboard$/)
})
