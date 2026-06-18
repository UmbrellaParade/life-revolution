import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import './index.css'
import App from './App.tsx'

const mountedRoots = new WeakSet<Element>()

function renderApp(target: Element) {
  if (mountedRoots.has(target)) return

  target.classList.add('yutori-ledger-root')
  createRoot(target).render(
    <StrictMode>
      <App />
    </StrictMode>,
  )
  mountedRoots.add(target)
}

function mountApp() {
  const pluginRoots = document.querySelectorAll('[data-yutori-ledger-root]')

  if (pluginRoots.length > 0) {
    pluginRoots.forEach(renderApp)
    return
  }

  const root = document.getElementById('root')
  if (root) renderApp(root)
}

if (document.readyState === 'loading') {
  window.addEventListener('DOMContentLoaded', mountApp)
} else {
  mountApp()
}

const shouldRegisterServiceWorker =
  window.YutoriLedgerConfig?.enableServiceWorker ?? !window.YutoriLedgerConfig

if (shouldRegisterServiceWorker && 'serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    const base = window.YutoriLedgerConfig?.assetsUrl ?? import.meta.env.BASE_URL
    const normalizedBase = base.endsWith('/') ? base : `${base}/`
    navigator.serviceWorker.register(`${normalizedBase}sw.js`)
  })
}
