import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import './index.css'
import { App } from './app/App'

// Deploy novo troca o hash dos chunks (`AppRoutes.tsx` usa `lazy(() => import(...))`
// pra cada rota); uma aba aberta antes do deploy tenta carregar um chunk que não
// existe mais no servidor ao navegar pra uma rota ainda não carregada nessa sessão
// — o Vite dispara `vite:preloadError` nesse caso. Um reload busca o `index.html`
// novo (hashes atuais) e resolve na quase totalidade dos casos; o guard em
// sessionStorage evita loop infinito se o problema for outro (deploy quebrado de
// verdade), tentando o reload automático só uma vez por aba.
window.addEventListener('vite:preloadError', () => {
  const key = 'mk-chunk-reload-attempted'
  if (sessionStorage.getItem(key)) return
  sessionStorage.setItem(key, '1')
  window.location.reload()
})

// Gerenciamento proativo de deploy: `registerType: 'autoUpdate'` (vite.config.ts)
// já ativa skipWaiting+clientsClaim no Service Worker gerado, então assim que o
// navegador baixa e ativa a versão nova, ela assume o controle desta aba na hora
// — só falta recarregar. Sem isso, a aba continua rodando o JS antigo até o
// usuário fechar/reabrir, ou até bater num chunk 404 (rede de segurança acima).
if ('serviceWorker' in navigator) {
  // `controllerchange` também dispara no primeiro acesso de um visitante novo
  // (controller passa de null pro 1º SW instalado) — isso não é deploy novo, é
  // onboarding normal, não deve recarregar. Só reage quando JÁ havia um SW
  // controlando esta aba antes (usuário recorrente numa versão desatualizada).
  const hadControllerOnLoad = Boolean(navigator.serviceWorker.controller)
  navigator.serviceWorker.addEventListener('controllerchange', () => {
    if (hadControllerOnLoad) window.location.reload()
  })

  // O navegador só rechecka o SW por padrão em navegação ou a cada ~24h —
  // força a checagem (busca `sw.js`, que é `no-cache` via .htaccess) sempre que
  // a aba volta a ficar visível e periodicamente, pra reduzir a janela entre um
  // deploy novo e a aba detectar/ativar a versão atualizada.
  const forceUpdateCheck = () => navigator.serviceWorker.getRegistration().then((reg) => reg?.update())
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') forceUpdateCheck()
  })
  window.setInterval(forceUpdateCheck, 30 * 60 * 1000)
}

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <App />
  </StrictMode>,
)

// Bootstrap chegou até aqui sem `vite:preloadError` — libera o guard pra que
// um deploy novo mais tarde na mesma aba (sessão longa) também ganhe sua
// própria tentativa de auto-reload, em vez de ficar bloqueado pela primeira.
window.setTimeout(() => sessionStorage.removeItem('mk-chunk-reload-attempted'), 5000)
