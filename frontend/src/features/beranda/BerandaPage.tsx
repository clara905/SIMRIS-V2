import { useState } from 'react'
import { Link } from 'react-router-dom'
import { CalendarDays, ChevronRight } from 'lucide-react'
import { useBeranda } from '../../api/beranda'
import { PageHeader } from '../../components/PageHeader'
import { Skeleton, ErrorState } from '../../components/States'
import { tanggal, waktu } from '../../lib/format'
import { isActive } from '../../lib/records'
import { errorMessage } from '../../api/client'
import s from './Beranda.module.css'
export default function BerandaPage() {
  const { users, employees, units, periods, monitoring, audit } = useBeranda()
  const [now] = useState(() => new Date())
  const [priority, setPriority] = useState('semua')
  const queries = [users, employees, units, periods, monitoring, audit]
  const failed = queries.find((q) => q.isError)
  if (failed)
    return (
      <ErrorState
        message={errorMessage(failed.error)}
        onRetry={() => {
          queries.forEach((q) => {
            void q.refetch()
          })
        }}
      />
    )
  const tasks = [
    {
      label: 'Pegawai tanpa penempatan aktif',
      description: 'Lengkapi hubungan pegawai dengan unit organisasi.',
      count:
        employees.data?.filter(
          (p) => p.status === 'aktif' && !p.penempatan.some((v) => isActive(v.berakhir, v.mulai)),
        ).length || 0,
      path: '/pegawai?filter=tanpa-penempatan',
      action: 'Atur penempatan',
    },
    {
      label: 'Pengguna tanpa role aktif',
      description: 'Tinjau kewenangan agar akun memiliki akses yang tepat.',
      count:
        users.data?.filter(
          (u) => u.status === 'aktif' && !u.roles.some((v) => isActive(v.berakhir, v.mulai)),
        ).length || 0,
      path: '/pengguna?filter=tanpa-role',
      action: 'Atur role scope',
    },
    {
      label: 'Unit belum memiliki pimpinan',
      description: 'Tetapkan Kasatker atau Kabid pada unit terkait.',
      count:
        units.data?.filter(
          (u) => u.status === 'aktif' && !u.pimpinan && ['Satker', 'Bidang'].includes(u.jenis),
        ).length || 0,
      path: '/unit?filter=tanpa-pimpinan',
      action: 'Tinjau unit',
    },
    {
      label: 'Periode berakhir dalam 30 hari',
      description: 'Periksa jadwal penilaian dan rencana kerja berikutnya.',
      count:
        periods.data?.filter(
          (p) =>
            p.jenis === 'periode' &&
            p.status === 'aktif' &&
            new Date(p.selesai).getTime() - now.getTime() <= 30 * 86400000 &&
            new Date(p.selesai).getTime() >= now.getTime(),
        ).length || 0,
      path: '/periode?filter=berakhir',
      action: 'Tinjau periode',
    },
    {
      label: 'Lampiran perlu diperiksa',
      description: 'Ada berkas yang belum lolos pemeriksaan integritas.',
      count:
        monitoring.data?.filter((m) => m.jenis === 'lampiran' && m.lampiran_status === 'bermasalah')
          .length || 0,
      path: '/monitoring?tab=lampiran&filter=bermasalah',
      action: 'Lihat lampiran',
    },
  ]
  const assessments = monitoring.data?.filter((m) => m.jenis === 'asesmen') || []
  const statuses = [
    { value: 'selesai', label: 'Selesai' },
    { value: 'dalam_penilaian', label: 'Dalam penilaian' },
    { value: 'ditinjau', label: 'Ditinjau' },
    { value: 'draf', label: 'Draf' },
  ]
  return (
    <>
      <PageHeader
        title="Beranda"
        description="Jaga kelengkapan data. Pastikan tata kelola risiko tetap berjalan."
        actions={
          <span className={s.date}>
            <CalendarDays size={16} />
            {tanggal(now)} · WIB
          </span>
        }
      />
      {queries.some((q) => q.isPending) ? (
        <Skeleton rows={10} />
      ) : (
        <>
          <section className={s.section}>
            <header className={s.heading}>
              <div>
                <h2>Perlu ditindaklanjuti</h2>
                <p>Hal yang memerlukan perhatian Anda hari ini.</p>
              </div>
              <span className={s.count}>
                {tasks.reduce((sum, task) => sum + task.count, 0)} temuan
              </span>
            </header>
            <div className={s.priority} role="group" aria-label="Filter tindak lanjut">
              {[
                ['semua', 'Semua prioritas'],
                ['organisasi', 'Pengguna dan organisasi'],
                ['pemantauan', 'Pemantauan'],
              ].map(([value, label]) => (
                <button
                  key={value}
                  aria-pressed={priority === value}
                  onClick={() => setPriority(value)}
                >
                  {label}
                </button>
              ))}
            </div>
            {tasks
              .filter(
                (t, index) =>
                  t.count > 0 &&
                  (priority === 'semua' || (priority === 'organisasi' ? index < 3 : index >= 3)),
              )
              .map((task) => (
                <Link className={s.followup} key={task.path} to={task.path}>
                  <span className={s.number}>{task.count}</span>
                  <span>
                    <strong>{task.label}</strong>
                    <small>{task.description}</small>
                  </span>
                  <span className={s.action}>
                    {task.action}
                    <ChevronRight size={16} aria-hidden="true" />
                  </span>
                </Link>
              ))}
            {tasks
              .filter(
                (_, index) =>
                  priority === 'semua' || (priority === 'organisasi' ? index < 3 : index >= 3),
              )
              .every((t) => !t.count) && (
              <div className={s.summary} role="status">
                Tidak ada temuan pada kelompok ini. Pilih kelompok lain atau lanjutkan pemantauan
                berkala.
              </div>
            )}
          </section>
          <section className={s.section}>
            <header className={s.heading}>
              <div>
                <h2>Status asesmen</h2>
                <p>{assessments.length} asesmen terdaftar di seluruh unit organisasi.</p>
              </div>
              <Link to="/monitoring">Lihat asesmen</Link>
            </header>
            <div className={s.summary}>
              <div className={s.bar} aria-label="Ringkasan status asesmen">
                {statuses.map((status) => {
                  const count = assessments.filter((a) => a.status === status.value).length
                  return count ? (
                    <Link
                      key={status.value}
                      to={`/monitoring?status=${status.value}`}
                      style={{ flex: count }}
                    >
                      <strong>{count}</strong>
                      {status.label}
                    </Link>
                  ) : null
                })}
              </div>
              <small>Status proses penilaian; bukan tingkat risiko.</small>
            </div>
          </section>
          <section className={s.section}>
            <header className={s.heading}>
              <div>
                <h2>Aktivitas terakhir</h2>
                <p>Jejak perubahan data dan keputusan administrasi.</p>
              </div>
              <Link to="/audit">Buka log audit</Link>
            </header>
            {audit.data?.slice(0, 5).map((item) => (
              <div className={s.activity} key={item.id}>
                <time dateTime={item.waktu}>
                  {tanggal(item.waktu)}
                  <br />
                  {waktu(item.waktu)}
                </time>
                <div>
                  <strong>{item.pelaku}</strong>
                  <p>
                    {item.aksi} {item.entitas.toLowerCase()} · {item.nama}
                  </p>
                </div>
                <Link to={`/audit?id=${item.id}`} aria-label={`Rincian aktivitas ${item.nama}`}>
                  Lihat rincian
                </Link>
              </div>
            ))}
          </section>
        </>
      )}
    </>
  )
}
