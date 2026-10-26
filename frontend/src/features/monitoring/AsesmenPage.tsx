import { Link, useParams } from 'react-router-dom'
import { useMonitoring } from '../../api/monitoring'
import { errorMessage } from '../../api/client'
import { PageHeader } from '../../components/PageHeader'
import { ErrorState, Skeleton } from '../../components/States'
import { StatusBadge } from '../../components/StatusBadge'
import { ReadOnlyBanner } from './MonitoringPage'
import { tanggal, waktu } from '../../lib/format'
import s from './Asesmen.module.css'
import r from '../Records.module.css'
export default function AsesmenPage() {
  const { id } = useParams()
  const query = useMonitoring()
  const record = query.data?.find((a) => a.id === Number(id) && a.jenis === 'asesmen')
  if (query.isPending) return <Skeleton rows={8} />
  if (query.isError)
    return <ErrorState message={errorMessage(query.error)} onRetry={() => query.refetch()} />
  if (!record)
    return (
      <>
        <h1>Asesmen tidak ditemukan</h1>
        <Link to="/monitoring">Kembali ke monitoring</Link>
      </>
    )
  return (
    <>
      <Link to="/monitoring">Monitoring / Asesmen</Link>
      <PageHeader title="Lembar asesmen" description="Dokumen penilaian dan perjalanan versinya." />
      <ReadOnlyBanner />
      <article className={s.document}>
        <header className={s.header}>
          <div>
            <p className={r.muted}>{record.kode}</p>
            <h2>{record.nama}</h2>
            <p>{record.unit}</p>
          </div>
          <StatusBadge status={record.status} />
        </header>
        <dl className={r.meta}>
          <dt>Periode penilaian</dt>
          <dd>{record.periode}</dd>
          <dt>Metode acuan</dt>
          <dd>
            <Link to="/metode-risiko/1">{record.metode}</Link>
          </dd>
          <dt>Pembaruan terakhir</dt>
          <dd>
            {tanggal(record.diperbarui)}, {waktu(record.diperbarui)}
          </dd>
        </dl>
        <section className={r.section}>
          <h3>Tahap penilaian</h3>
          <p>
            Catatan berikut memperlihatkan perkembangan dokumen yang diterima dari unit pemilik
            risiko.
          </p>
          <ol className={r.timeline}>
            {record.riwayat.map((v) => (
              <li key={v.versi}>
                <strong>{v.tahap}</strong>
                <p>
                  Versi {v.versi} dicatat oleh {v.pelaku}.
                </p>
                <small>
                  {tanggal(v.tanggal)}, {waktu(v.tanggal)}
                </small>
              </li>
            ))}
          </ol>
        </section>
        <section className={r.section}>
          <h3>Riwayat versi dokumen</h3>
          <div className={s.scroll}>
            <table>
              <caption className="sr-only">Versi asesmen</caption>
              <thead>
                <tr>
                  <th scope="col">Versi</th>
                  <th scope="col">Tahap</th>
                  <th scope="col">Pencatat</th>
                  <th scope="col">Tanggal</th>
                </tr>
              </thead>
              <tbody>
                {record.riwayat.map((v) => (
                  <tr key={v.versi}>
                    <td>{v.versi}</td>
                    <td>{v.tahap}</td>
                    <td>{v.pelaku}</td>
                    <td>{tanggal(v.tanggal)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>
        <footer className={s.foot}>
          Dokumen monitoring SIMRIS · Seluruh perubahan dilakukan oleh pemilik transaksi sesuai
          kewenangannya.
        </footer>
      </article>
    </>
  )
}
