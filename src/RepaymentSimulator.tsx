import { Calculator, ChevronLeft, ChevronRight, RotateCcw } from 'lucide-react'
import { useState } from 'react'
import { defaultRepaymentPlan, simulateRepayment } from './repayment'
import type { RepaymentLoan, RepaymentPlan } from './repayment'
import './RepaymentSimulator.css'

const money = new Intl.NumberFormat('ja-JP', { style: 'currency', currency: 'JPY', maximumFractionDigits: 0 })
const yen = (value: number) => money.format(value)

function paymentMonth(startMonth: string, offset: number) {
  const [year, month] = startMonth.split('-').map(Number)
  const date = new Date(year, month - 1 + offset, 1)
  return `${date.getFullYear()}年${date.getMonth() + 1}月`
}

export default function RepaymentSimulator({ loans, startMonth }: { loans: RepaymentLoan[], startMonth: string }) {
  const [selectedId, setSelectedId] = useState('')
  const [plans, setPlans] = useState<Record<string, Partial<RepaymentPlan>>>({})
  const [page, setPage] = useState(0)
  const selected = loans.find((loan) => loan.id === selectedId) ?? loans[0]
  const results = loans.map((loan) => ({
    loan,
    current: simulateRepayment(loan),
    trial: simulateRepayment(loan, { ...defaultRepaymentPlan(loan), ...plans[loan.id] }),
  }))
  const aggregate = (key: 'current' | 'trial') => ({
    paid: results.every((result) => result[key].status === 'paid'),
    months: Math.max(0, ...results.map((result) => result[key].months)),
    interest: results.reduce((sum, result) => sum + result[key].totalInterest, 0),
    total: results.reduce((sum, result) => sum + result[key].totalPaid, 0),
  })
  const current = aggregate('current')
  const trial = aggregate('trial')
  const selectedPlan = selected ? { ...defaultRepaymentPlan(selected), ...plans[selected.id] } : null
  const selectedResult = results.find((result) => result.loan.id === selected?.id)?.trial
  const pageCount = Math.max(1, Math.ceil((selectedResult?.rows.length ?? 0) / 12))
  const visiblePage = Math.min(page, pageCount - 1)
  function updatePlan(key: keyof RepaymentPlan, value: string) {
    if (!selected) return
    setPlans((previous) => ({ ...previous, [selected.id]: { ...previous[selected.id], [key]: Number(value) } }))
    setPage(0)
  }
  const warnings = { invalid: '入力値を確認してください', 'non-amortizing': '返済額が利息以下のため完済できません', limit: '100年以内に完済できません', paid: '' }

  return (
    <div className="repayment-simulator">
      <div className="panel-heading">
        <h3>返済シミュレーション</h3>
        <Calculator size={22} />
      </div>
      {!selected || !selectedPlan ? <p className="muted-text">登録済みのローンはありません</p> : <>
        <div className="repayment-controls">
          <label>
            <span>借金・ローン</span>
            <select value={selected.id} onChange={(event) => { setSelectedId(event.target.value); setPage(0) }}>
              {loans.map((loan) => <option key={loan.id} value={loan.id}>{loan.name}</option>)}
            </select>
          </label>
          <button className="icon-button subtle" type="button" aria-label="試算条件をすべてリセット" title="試算条件をすべてリセット" onClick={() => { setPlans({}); setPage(0) }}><RotateCcw size={18} /></button>
        </div>
        <p className="repayment-principal">現在残高・手数料 <strong>{yen(selected.balance + selected.fee)}</strong></p>
        <div className="repayment-inputs">
          {([
            ['monthlyPayment', '月返済（円）'],
            ['extraPayment', '毎月の追加返済（円）'],
            ['lumpSum', '開始時の一括返済（円）'],
            ...(selected.aprType === 'total' ? [['flatInterest', '残りの利子総額（円）']] : []),
          ] as [keyof RepaymentPlan, string][]).map(([key, label]) => <label key={key}>
            <span>{label}</span>
            <input type="number" min="0" max="1000000000000" step="1" value={selectedPlan[key]} onChange={(event) => updatePlan(key, event.target.value)} />
          </label>)}
        </div>
        <p className="repayment-assumptions">概算・{paymentMonth(startMonth, 0)}開始。月末返済、年率÷12、利息は円単位で四捨五入。日割り利息・繰上返済手数料・契約変更は含みません。利子総額方式は未払利子を残高に加算します。各ローンの返済額は完済まで固定です。</p>
        <div className="repayment-table-scroll">
          <table className="repayment-table">
            <caption>全ローンの比較（{loans.length}件）</caption>
            <thead><tr><th scope="col">項目</th><th scope="col">現在の計画</th><th scope="col">試算</th></tr></thead>
            <tbody>
              <tr><th scope="row">完済時期</th>{[current, trial].map((result, i) => <td key={i}>{result.paid ? (result.months === 0 ? '開始時に完済' : paymentMonth(startMonth, result.months - 1)) : '完済不可'}<small>{result.paid ? `${result.months}ヶ月` : '条件を要確認'}</small></td>)}</tr>
              <tr><th scope="row">利息合計</th><td>{current.paid ? yen(current.interest) : '—'}</td><td>{trial.paid ? yen(trial.interest) : '—'}</td></tr>
              <tr><th scope="row">総返済額</th><td>{current.paid ? yen(current.total) : '—'}</td><td>{trial.paid ? yen(trial.total) : '—'}</td></tr>
            </tbody>
          </table>
        </div>
        {current.paid && trial.paid && <div className="repayment-difference">
          <span>完済までの期間差 <strong>{Math.abs(current.months - trial.months)}ヶ月{trial.months <= current.months ? '短縮' : '延長'}</strong></span>
          <span>利息差 <strong>{yen(Math.abs(current.interest - trial.interest))}{trial.interest <= current.interest ? '減' : '増'}</strong></span>
        </div>}
        <ul className="repayment-results">
          {results.map(({ loan, current: baseline, trial: result }) => <li key={loan.id}>
            <strong>{loan.name}</strong>
            <span>{result.status === 'paid' ? `試算 ${result.months}ヶ月 / ${yen(result.totalPaid)}` : warnings[result.status]}</span>
            {baseline.status !== 'paid' && <small className="danger-text">現在の計画：{warnings[baseline.status]}</small>}
          </li>)}
        </ul>
        <details className="repayment-schedule">
          <summary>{selected.name}の返済予定</summary>
          <div className="repayment-table-scroll">
            <table className="repayment-table repayment-schedule-table">
              <thead><tr><th scope="col">返済月</th><th scope="col">返済額</th><th scope="col">利息</th><th scope="col">残高</th></tr></thead>
              <tbody>{selectedResult?.rows.slice(visiblePage * 12, visiblePage * 12 + 12).map((row) => <tr key={row.month}><th scope="row">{row.month === 0 ? '開始時' : paymentMonth(startMonth, row.month - 1)}</th><td>{yen(row.payment)}</td><td>{yen(row.interest)}</td><td>{yen(row.balance)}</td></tr>)}</tbody>
            </table>
          </div>
          {!selectedResult?.rows.length && <p>{selectedResult?.status === 'paid' ? '残高なし' : warnings[selectedResult?.status ?? 'invalid']}</p>}
          <div className="repayment-pagination">
            <button className="icon-button subtle" type="button" aria-label="前の返済予定" title="前の返済予定" disabled={visiblePage === 0} onClick={() => setPage(visiblePage - 1)}><ChevronLeft size={18} /></button>
            <span>{visiblePage + 1} / {pageCount}</span>
            <button className="icon-button subtle" type="button" aria-label="次の返済予定" title="次の返済予定" disabled={visiblePage === pageCount - 1} onClick={() => setPage(visiblePage + 1)}><ChevronRight size={18} /></button>
          </div>
        </details>
      </>}
    </div>
  )
}
