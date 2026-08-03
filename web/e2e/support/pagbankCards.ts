import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

export interface PagBankSandboxCard {
  brand: string
  number: string
  cvv: string
  expiration: string
  encrypted: string
}

export function readPagBankSandboxCards(): PagBankSandboxCard[] {
  const fixturePath = resolve(process.cwd(), '..', 'cartoes_teste_pagbank.txt')
  const contents = readFileSync(fixturePath, 'utf8').trim()

  if (!contents) {
    throw new Error(`Arquivo de cartões do PagBank está vazio: ${fixturePath}`)
  }

  return contents
    .split(/\n\s*\n/)
    .map((block) => {
      const entries = Object.fromEntries(
        block
          .split('\n')
          .map((line) => line.split(/:\s*/, 2))
          .filter(([key, value]) => key && value)
          .map(([key, value]) => [key.trim(), value.trim()]),
      )

      return {
        brand: entries.Bandeira,
        number: entries.Número,
        cvv: entries.CVV,
        expiration: entries.Expiração,
        encrypted: entries.Criptografia,
      }
    })
    .filter((card): card is PagBankSandboxCard => Boolean(card.brand && card.number && card.cvv && card.expiration && card.encrypted))
}
