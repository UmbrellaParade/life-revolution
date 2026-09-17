import assert from 'node:assert/strict'
import test from 'node:test'
import { defaultRepaymentPlan, simulateRepayment } from '../src/repayment.ts'

const loan = { id: 'test', name: 'Test loan', balance: 100000, fee: 0, monthlyPayment: 10000, extraPayment: 0, apr: 0, aprType: 'annual' }

test('zero-rate repayment caps the final payment and conserves principal', () => {
  const result = simulateRepayment({ ...loan, balance: 100001 })
  assert.equal(result.status, 'paid')
  assert.equal(result.months, 11)
  assert.equal(result.totalPaid, 100001)
  assert.equal(result.rows.at(-1).payment, 1)
  assert.equal(result.rows.at(-1).balance, 0)
})

test('annual interest accrues monthly, with a reduced final payment', () => {
  const result = simulateRepayment({ ...loan, apr: 12 })
  assert.equal(result.rows[0].interest, 1000)
  assert.equal(result.rows[0].balance, 91000)
  assert.equal(result.months, 11)
  assert.equal(result.totalPaid, loan.balance + result.totalInterest)
})

test('extra and upfront repayment reduce payoff time and annual interest without mutation', () => {
  const original = { ...loan, apr: 18 }
  const before = JSON.stringify(original)
  const baseline = simulateRepayment(original)
  const trial = simulateRepayment(original, { ...defaultRepaymentPlan(original), extraPayment: 5000, lumpSum: 20000 })
  assert.equal(trial.rows[0].month, 0)
  assert.equal(trial.rows[0].balance, 80000)
  assert.ok(trial.months < baseline.months)
  assert.ok(trial.totalInterest < baseline.totalInterest)
  assert.equal(JSON.stringify(original), before)
  assert.equal(trial.totalPaid, original.balance + trial.totalInterest)
})

test('full upfront repayment does not overpay or accrue future interest', () => {
  const result = simulateRepayment(loan, { ...defaultRepaymentPlan(loan), lumpSum: 200000 })
  assert.equal(result.months, 0)
  assert.equal(result.totalPaid, 100000)
  assert.equal(result.totalInterest, 0)
})

test('fixed unpaid interest is added once, not interpreted as an annual rate', () => {
  const result = simulateRepayment({ ...loan, fee: 2000, apr: 30000, aprType: 'total' })
  assert.equal(result.months, 14)
  assert.equal(result.totalPaid, 132000)
  assert.equal(result.totalInterest, 30000)
  assert.ok(result.rows.every((row) => row.interest === 0))
})

test('no payment or payment at/below interest is flagged', () => {
  for (const monthlyPayment of [0, 999, 1000]) {
    assert.equal(simulateRepayment({ ...loan, apr: 12, monthlyPayment }).status, 'non-amortizing')
  }
})

test('zero balance is already paid even without payments', () => {
  assert.equal(simulateRepayment({ ...loan, balance: 0, monthlyPayment: 0 }).status, 'paid')
})

test('very slow payoff stops at the bounded horizon', () => {
  const result = simulateRepayment({ ...loan, monthlyPayment: 1 })
  assert.equal(result.status, 'limit')
  assert.equal(result.rows.length, 1200)
})

test('invalid and nonfinite amounts are rejected', () => {
  for (const lumpSum of [-1, NaN, Infinity, 1e13]) {
    assert.equal(simulateRepayment(loan, { ...defaultRepaymentPlan(loan), lumpSum }).status, 'invalid')
  }
})
