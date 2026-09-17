import { fv } from 'financial'

export type RepaymentLoan = {
  id: string
  name: string
  balance: number
  fee: number
  monthlyPayment: number
  extraPayment: number
  apr: number
  aprType: 'annual' | 'total'
}

export type RepaymentPlan = {
  monthlyPayment: number
  extraPayment: number
  lumpSum: number
  flatInterest: number
}

export type RepaymentRow = {
  month: number
  payment: number
  interest: number
  balance: number
}

export function defaultRepaymentPlan(loan: RepaymentLoan): RepaymentPlan {
  return {
    monthlyPayment: loan.monthlyPayment,
    extraPayment: loan.extraPayment,
    lumpSum: 0,
    flatInterest: loan.aprType === 'total' ? loan.apr : 0,
  }
}

export function simulateRepayment(loan: RepaymentLoan, plan = defaultRepaymentPlan(loan)) {
  const rows: RepaymentRow[] = []
  const values = [loan.balance, loan.fee, loan.apr, ...Object.values(plan)]
  if (values.some((value) => !Number.isFinite(value) || value < 0 || value > 1e12) ||
    (loan.aprType === 'annual' && loan.apr > 1000)) {
    return { status: 'invalid' as const, rows, months: 0, totalPaid: 0, totalInterest: 0 }
  }

  let balance = Math.round(loan.balance + loan.fee + plan.flatInterest)
  let totalInterest = Math.round(plan.flatInterest)
  let totalPaid = Math.min(balance, Math.round(plan.lumpSum))
  balance -= totalPaid
  if (totalPaid > 0) rows.push({ month: 0, payment: totalPaid, interest: 0, balance })
  const payment = Math.round(plan.monthlyPayment + plan.extraPayment)
  const rate = loan.aprType === 'annual' ? loan.apr / 1200 : 0

  for (let month = 1; month <= 1200 && balance > 0; month += 1) {
    // FV uses end-of-period cash flows. Round each month's interest to whole yen.
    const interest = Math.max(0, Math.round(fv(rate, 1, 0, -balance) - balance))
    if (payment <= interest) {
      return { status: 'non-amortizing' as const, rows, months: month - 1, totalPaid, totalInterest }
    }
    const actualPayment = Math.min(payment, balance + interest)
    balance = balance + interest - actualPayment
    totalPaid += actualPayment
    totalInterest += interest
    rows.push({ month, payment: actualPayment, interest, balance })
  }

  return {
    status: balance === 0 ? 'paid' as const : 'limit' as const,
    rows,
    months: rows.at(-1)?.month ?? 0,
    totalPaid,
    totalInterest,
  }
}
