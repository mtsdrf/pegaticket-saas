/**
 * Único lugar que decide se o vínculo do usuário com o tenant ativo é de
 * dono — usado pela sidebar (`AppLayout`) e pelo hub de Configurações
 * (`SettingsHubLayout`/`SettingsIndexPage`) pra aplicar o mesmo bypass de
 * permissão (`ownerBypassesAccess`). Extraído de `AppLayout.tsx` em
 * 2026-07-24 pra não duplicar a lógica entre os dois lugares.
 */
export function isOwnerRole(role?: string | null, roleSlug?: string | null): boolean {
  if (roleSlug === 'owner') {
    return true
  }

  const normalizedRole = role
    ?.normalize('NFD')
    .replace(/\p{Diacritic}/gu, '')
    .trim()
    .toLowerCase()

  return normalizedRole === 'owner' || normalizedRole === 'proprietario'
}
