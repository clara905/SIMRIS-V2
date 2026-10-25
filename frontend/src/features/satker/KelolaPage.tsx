import { KelolaAset } from './KelolaAset'

type Props = {
  item: { path: string; label: string; group: string; description: string }
}

/** Halaman pengelolaan khusus Admin Satker. Setiap modul mengisi path-nya sendiri. */
export function KelolaPage({ item }: Props) {
  if (item.path === '/kelola/aset') return <KelolaAset />

  return (
    <>
      <div className="ws-page-heading">
        <div>
          <span className="ws-eyebrow">{item.group}</span>
          <h1>{item.label}</h1>
          <p>{item.description}</p>
        </div>
      </div>
      <section className="ws-panel ws-empty">
        <strong>Formulir sedang disiapkan</strong>
        <p>Halaman ini hanya tampil untuk akun Admin Satker.</p>
      </section>
    </>
  )
}