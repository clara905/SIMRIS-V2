import s from './DiffViewer.module.css'
export function DiffViewer({
  before,
  after,
}: {
  before: Record<string, unknown>
  after: Record<string, unknown>
}) {
  const keys = [...new Set([...Object.keys(before), ...Object.keys(after)])]
  return (
    <div className={s.scroll}>
      <table className={s.table}>
        <caption className="sr-only">Perbandingan data lama dan data baru</caption>
        <thead>
          <tr>
            <th scope="col">Data lama</th>
            <th scope="col">Data baru</th>
          </tr>
        </thead>
        <tbody>
          {keys.map((key) => {
            const changed = JSON.stringify(before[key]) !== JSON.stringify(after[key])
            const marker = !(key in before)
              ? '+ Ditambahkan'
              : !(key in after)
                ? '− Dihapus'
                : changed
                  ? '~ Diubah'
                  : '= Tidak berubah'
            return (
              <tr key={key}>
                {[before, after].map((data, i) => (
                  <td key={i} className={changed ? s.changed : ''}>
                    <span className={s.marker}>{marker}</span>
                    <strong>{key}</strong>
                    <pre>{key in data ? JSON.stringify(data[key], null, 2) : '—'}</pre>
                  </td>
                ))}
              </tr>
            )
          })}
        </tbody>
      </table>
    </div>
  )
}
