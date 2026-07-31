import { createPortal } from 'react-dom'

interface ServerGridPrintExportProps {
  title: string
  generatedAt: string
  headers: string[]
  rows: string[][]
}

/**
 * Tabela "invisível" na tela, renderizada via portal direto em `document.body`
 * e só exibida pela folha `@media print` de `index.css` (`.pt-print-export`)
 * — o resto da UI some da impressão. `window.print()` é chamado por quem
 * monta este componente; o usuário escolhe "Salvar como PDF" no diálogo
 * nativo do navegador, sem lib nova.
 */
export function ServerGridPrintExport({ title, generatedAt, headers, rows }: ServerGridPrintExportProps) {
  return createPortal(
    <div className="pt-print-export">
      <h1>{title}</h1>
      <p>Exportado em {generatedAt}</p>
      <table>
        <thead>
          <tr>
            {headers.map((header, index) => (
              <th key={`${header}-${index}`}>{header}</th>
            ))}
          </tr>
        </thead>
        <tbody>
          {rows.map((row, rowIndex) => (
            <tr key={rowIndex}>
              {row.map((cell, cellIndex) => (
                <td key={cellIndex}>{cell}</td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>,
    document.body,
  )
}
