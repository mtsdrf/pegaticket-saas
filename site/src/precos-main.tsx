import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import './index.css'
import { App } from './App'
import { PrecosPage } from './pages/PrecosPage'

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <App>
      <PrecosPage />
    </App>
  </StrictMode>,
)
