export type FixedCostSchedule = {
  billingCycle?: 'monthly' | 'annual'
  dueMonth?: number
}

export function clampDueMonth(value: number) {
  return Math.min(Math.max(Number(value) || 1, 1), 12)
}

export function fixedCostOccursInMonth(cost: FixedCostSchedule, month: string) {
  if (cost.billingCycle !== 'annual') return true

  const selectedMonth = Number(month.slice(5, 7))
  return selectedMonth === clampDueMonth(cost.dueMonth ?? 1)
}
