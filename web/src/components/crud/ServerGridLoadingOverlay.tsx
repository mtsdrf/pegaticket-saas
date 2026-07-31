import { Box, Skeleton, Stack } from '@mui/material'

const SKELETON_ROW_COUNT = 8
/** Larguras variadas por "célula" pra parecer linhas de tabela reais, não uma barra sólida repetida. */
const CELL_WIDTHS = ['32%', '20%', '16%', '10%']

/**
 * Overlay de carregamento do ag-Grid (aparece sempre que uma página nova é
 * buscada — primeira carga, troca de página, ordenação, filtro — não só na
 * montagem inicial). Antes era um `CircularProgress` genérico; virou um
 * esqueleto de linhas pra ficar consistente com o resto do app (`Skeleton`
 * do MUI em toda listagem/formulário). Sem acesso às colunas reais da tela
 * (esse componente é compartilhado por ~18 grids diferentes) — usa larguras
 * fixas variadas só pra sugerir "linha de tabela", não tenta espelhar as
 * colunas de cada tela.
 */
export function ServerGridLoadingOverlay() {
  return (
    <Box sx={{ width: '100%', px: 2, py: 1.5 }}>
      <Stack spacing={1.75}>
        {Array.from({ length: SKELETON_ROW_COUNT }).map((_, rowIndex) => (
          <Stack key={rowIndex} direction="row" spacing={3} sx={{ alignItems: 'center' }}>
            {CELL_WIDTHS.map((width, cellIndex) => (
              <Skeleton
                key={cellIndex}
                variant="rounded"
                height={14}
                sx={{ width, borderRadius: 'var(--mk-radius-sm)' }}
              />
            ))}
          </Stack>
        ))}
      </Stack>
    </Box>
  )
}
