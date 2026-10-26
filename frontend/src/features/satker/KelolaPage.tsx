import { KelolaAset } from './KelolaAset'
import { KelolaKepatuhan } from './KelolaKepatuhan'
import { KelolaDokumen } from './KelolaDokumen'
import { KelolaPegawai } from './KelolaPegawai'
import { KelolaPengguna } from './KelolaPengguna'
import { KelolaUnit } from './KelolaUnit'

type Props = {
  item: { path: string; label: string; group: string; description: string }
}

/** Halaman pengelolaan khusus Admin Satker. Setiap modul mengisi path-nya sendiri. */
export function KelolaPage({ item }: Props) {
  if (item.path === '/kelola/aset') return <KelolaAset />
  if (item.path === '/kelola/dokumen') return <KelolaDokumen />
  if (item.path === '/kelola/kepatuhan') return <KelolaKepatuhan />
  if (item.path === '/kelola/unit') return <KelolaUnit />
  if (item.path === '/kelola/pegawai') return <KelolaPegawai />
  if (item.path === '/kelola/pengguna') return <KelolaPengguna />

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
