import assert from 'node:assert/strict'
import { createRequire } from 'node:module'
import fs from 'node:fs/promises'

const require = createRequire(import.meta.url)
const { chromium } = require(process.env.PLAYWRIGHT_PACKAGE_PATH || 'playwright')
const browser = await chromium.launch({ headless: true, channel: 'chrome' })
const loans = [
  { id: 'annual', name: 'Annual test', balance: 500000, fee: 0, monthlyPayment: 15000, extraPayment: 0, apr: 18, aprType: 'annual', kind: 'カードローン', paymentHistory: [], fundedMonths: [] },
  { id: 'flat', name: 'Flat test', balance: 100000, fee: 2000, monthlyPayment: 10000, extraPayment: 0, apr: 30000, aprType: 'total', kind: 'ショッピング', paymentHistory: [], fundedMonths: [] },
]
await fs.mkdir('outputs', { recursive: true })
try {
  for (const viewport of [{ width: 412, height: 915 }, { width: 1440, height: 1000 }]) {
    const context = await browser.newContext({ viewport })
    await context.addInitScript((fixture) => localStorage.setItem('yutori-ledger-data-v1', JSON.stringify({ loans: fixture })), loans)
    const page = await context.newPage()
    const errors = []
    page.on('pageerror', (error) => errors.push(error.message))
    await page.goto('http://127.0.0.1:5176/life-revolution/')
    if (viewport.width < 800) await page.getByRole('button', { name: '固定', exact: true }).click()
    const panel = page.locator('.repayment-simulator')
    await panel.getByRole('heading', { name: '返済シミュレーション' }).waitFor()
    await panel.getByLabel('毎月の追加返済（円）', { exact: true }).fill('5000')
    await panel.getByLabel('開始時の一括返済（円）', { exact: true }).fill('100000')
    assert.match(await panel.locator('.repayment-difference').innerText(), /短縮/)
    await panel.locator('summary').click()
    await panel.getByRole('button', { name: '次の返済予定', exact: true }).click()
    assert.match(await panel.locator('.repayment-pagination').innerText(), /2 \/ /)
    await panel.getByRole('button', { name: '試算条件をすべてリセット' }).click()
    assert.equal(await panel.getByLabel('毎月の追加返済（円）', { exact: true }).inputValue(), '0')
    await panel.getByLabel('月返済（円）', { exact: true }).fill('1')
    assert.match(await panel.locator('.repayment-results').innerText(), /返済額が利息以下/)
    await panel.getByLabel('借金・ローン').selectOption('flat')
    assert.equal(await panel.getByLabel('残りの利子総額（円）').inputValue(), '30000')
    await panel.getByRole('button', { name: '試算条件をすべてリセット' }).click()
    await page.waitForTimeout(500)
    assert.deepEqual(await page.evaluate(() => JSON.parse(localStorage.getItem('yutori-ledger-data-v1')).loans), loans.map((loan) => ({ ...loan, totalPayments: 0 })))
    assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'page overflows horizontally')
    await panel.scrollIntoViewIfNeeded()
    await page.screenshot({ path: `outputs/repayment-${viewport.width}.png` })
    assert.deepEqual(errors, [])
    await context.close()
  }
  const context = await browser.newContext({ viewport: { width: 360, height: 800 } })
  const page = await context.newPage()
  await page.goto('http://127.0.0.1:5176/life-revolution/')
  await page.getByRole('button', { name: '固定', exact: true }).click()
  assert.match(await page.locator('.repayment-simulator').innerText(), /登録済みのローンはありません/)
  await context.close()
  console.log('UI checks passed: mobile/desktop, reset, warnings, schedule pagination, unchanged saved loans, empty state.')
} finally {
  await browser.close()
}
