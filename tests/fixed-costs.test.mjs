import assert from 'node:assert/strict'
import test from 'node:test'
import { clampDueMonth, fixedCostOccursInMonth } from '../src/fixedCosts.ts'

test('existing fixed costs remain monthly when no billing cycle is stored', () => {
  assert.equal(fixedCostOccursInMonth({}, '2026-01'), true)
  assert.equal(fixedCostOccursInMonth({}, '2026-12'), true)
})

test('annual fixed costs occur only in their configured month', () => {
  const annual = { billingCycle: 'annual', dueMonth: 7 }
  assert.equal(fixedCostOccursInMonth(annual, '2026-07'), true)
  assert.equal(fixedCostOccursInMonth(annual, '2026-06'), false)
  assert.equal(fixedCostOccursInMonth(annual, '2027-07'), true)
})

test('annual due months are clamped to a valid calendar month', () => {
  assert.equal(clampDueMonth(0), 1)
  assert.equal(clampDueMonth(13), 12)
  assert.equal(fixedCostOccursInMonth({ billingCycle: 'annual', dueMonth: 13 }, '2026-12'), true)
})
