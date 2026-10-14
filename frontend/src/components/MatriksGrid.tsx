import { Tooltip } from 'radix-ui'
import type { SelMatriks, Skala, TingkatRisiko } from '../api/types'
import { contrastText } from '../lib/contrast'
import s from './MatriksGrid.module.css'
export function MatriksGrid({
  kemungkinan,
  dampak,
  tingkat,
  cells,
  selected = [],
  onSelect,
  readOnly = false,
}: {
  kemungkinan: Skala[]
  dampak: Skala[]
  tingkat: TingkatRisiko[]
  cells: SelMatriks[]
  selected?: string[]
  onSelect?: (key: string) => void
  readOnly?: boolean
}) {
  return (
    <Tooltip.Provider delayDuration={250}>
      <div className={s.scroll} tabIndex={0} role="region" aria-label="Matriks risiko">
        <table className={s.table}>
          <caption>
            Kemungkinan × dampak ·{' '}
            {readOnly
              ? 'Versi hanya-lihat'
              : 'Pilih satu atau beberapa sel untuk menetapkan tingkat risiko'}
          </caption>
          <thead>
            <tr>
              <th scope="col">Kemungkinan</th>
              {dampak.map((d) => (
                <th scope="col" key={d.nilai}>
                  <strong>{d.nilai}</strong>
                  {d.nama}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {[...kemungkinan].reverse().map((k) => (
              <tr key={k.nilai}>
                <th scope="row">
                  <strong>{k.nilai}</strong>
                  {k.nama}
                </th>
                {dampak.map((d) => {
                  const key = `${k.nilai}-${d.nilai}`
                  const cell = cells.find((c) => c.kemungkinan === k.nilai && c.dampak === d.nilai)
                  const level = tingkat.find((l) => l.id === cell?.tingkat_id)
                  const content = (
                    <>
                      <strong>{cell?.skor ?? k.nilai * d.nilai}</strong>
                      <span>{level?.nama || 'Belum ditetapkan'}</span>
                    </>
                  )
                  return (
                    <td key={key}>
                      <Tooltip.Root>
                        <Tooltip.Trigger asChild>
                          {readOnly ? (
                            <div
                              tabIndex={0}
                              className={`${s.cell} ${!level ? s.empty : ''}`}
                              style={
                                level
                                  ? { background: level.warna, color: contrastText(level.warna) }
                                  : undefined
                              }
                              aria-label={`Kemungkinan ${k.nilai}, dampak ${d.nilai}: ${level?.nama || 'Belum ditetapkan'}`}
                            >
                              {content}
                            </div>
                          ) : (
                            <button
                              id={`sel-${key}`}
                              type="button"
                              aria-pressed={selected.includes(key)}
                              aria-label={`Kemungkinan ${k.nilai}, dampak ${d.nilai}: ${level?.nama || 'Belum ditetapkan'}`}
                              className={`${s.cell} ${!level ? s.empty : ''}`}
                              style={
                                level
                                  ? { background: level.warna, color: contrastText(level.warna) }
                                  : undefined
                              }
                              onClick={() => onSelect?.(key)}
                            >
                              {content}
                            </button>
                          )}
                        </Tooltip.Trigger>
                        <Tooltip.Portal>
                          <Tooltip.Content className={s.tooltip} sideOffset={6}>
                            <strong>{k.nama}</strong>
                            <p>{k.kriteria}</p>
                            <strong>{d.nama}</strong>
                            <p>{d.kriteria}</p>
                          </Tooltip.Content>
                        </Tooltip.Portal>
                      </Tooltip.Root>
                    </td>
                  )
                })}
              </tr>
            ))}
          </tbody>
        </table>
        <div className={s.axis}>Dampak</div>
      </div>
    </Tooltip.Provider>
  )
}
