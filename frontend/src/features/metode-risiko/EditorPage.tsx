import { methodIssues } from '../../lib/metode'
import { useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { Copy, LockKeyhole, Plus, RefreshCw, Info } from 'lucide-react'
import { toast } from 'sonner'
import { useMetode, useMetodeAction, useSaveMetode } from '../../api/metode'
import type { Metode } from '../../api/types'
import { errorMessage } from '../../api/client'
import { PageHeader } from '../../components/PageHeader'
import { TabBar } from '../../components/TabBar'
import { MatriksGrid } from '../../components/MatriksGrid'
import { SegelBeku } from '../../components/SegelBeku'
import { RiskLevelChip } from '../../components/RiskLevelChip'
import { StatusBadge } from '../../components/StatusBadge'
import { ConfirmDialog } from '../../components/ConfirmDialog'
import { Drawer } from '../../components/Drawer'
import { RecordForm } from '../../components/RecordForm'
import { ErrorState, Skeleton } from '../../components/States'
import s from './Editor.module.css'
import r from '../Records.module.css'
const tabs = [
  { value: 'kemungkinan', label: 'Skala kemungkinan' },
  { value: 'dampak', label: 'Skala dampak' },
  { value: 'kriteria', label: 'Area dan kriteria dampak' },
  { value: 'tingkat', label: 'Tingkat risiko' },
  { value: 'matriks', label: 'Matriks' },
]
export default function EditorPage() {
  const { id } = useParams()
  const query = useMetode()
  const method = query.data?.find((m) => m.id === Number(id))
  if (query.isPending) return <Skeleton rows={9} />
  if (query.isError)
    return <ErrorState message={errorMessage(query.error)} onRetry={() => query.refetch()} />
  if (!method)
    return (
      <>
        <h1>Versi tidak ditemukan</h1>
        <Link to="/metode-risiko">Kembali ke daftar versi</Link>
      </>
    )
  return <Editor key={method.id} method={method} />
}
function Editor({ method }: { method: Metode }) {
  const save = useSaveMetode()
  const action = useMetodeAction()
  const navigate = useNavigate()
  const [tab, setTab] = useState('matriks')
  const [selected, setSelected] = useState<string[]>([])
  const [level, setLevel] = useState('')
  const [dialog, setDialog] = useState<'freeze' | 'generate' | null>(null)
  const [issues, setIssues] = useState(false)
  const [duplicate, setDuplicate] = useState(false)
  const [edit, setEdit] = useState<number | 'new' | null>(null)
  const [stamped, setStamped] = useState(false)
  const validationIssues = methodIssues(method)
  const frozen = method.status === 'dibekukan'
  const empty = method.kemungkinan.flatMap((k) =>
    method.dampak
      .filter(
        (d) =>
          !method.matriks.some(
            (c) =>
              c.kemungkinan === k.nilai &&
              c.dampak === d.nilai &&
              method.tingkat.some((l) => l.id === c.tingkat_id),
          ),
      )
      .map((d) => `${k.nilai}-${d.nilai}`),
  )
  const update = async (patch: Partial<Metode>, message = 'Perubahan disimpan') => {
    await save.mutateAsync({ ...method, ...patch })
    toast.success(message)
  }
  const scale = tab === 'kemungkinan' || tab === 'dampak' ? method[tab] : []
  const currentScale = scale.find((v) => v.nilai === edit)
  const currentLevel = method.tingkat.find((v) => v.id === edit)
  const currentCriterion = method.kriteria.find((v) => v.id === edit)
  return (
    <>
      <Link to="/metode-risiko" className={r.muted}>
        Metode risiko / Daftar versi
      </Link>
      <PageHeader
        title={method.nama}
        description={method.deskripsi}
        actions={
          <>
            <button onClick={() => setDuplicate(true)}>
              <Copy size={16} />
              Duplikasi versi
            </button>
            {!frozen && (
              <button
                className="primary"
                onClick={() => (validationIssues.length ? setIssues(true) : setDialog('freeze'))}
              >
                <LockKeyhole size={16} />
                Bekukan versi
              </button>
            )}
          </>
        }
      />
      <div className={s.version}>
        <div>
          <StatusBadge status={method.status} />
          <span className={r.muted}>
            {' '}
            · {method.kemungkinan.length} skala kemungkinan · {method.dampak.length} skala dampak ·{' '}
            {method.tingkat.length} tingkat risiko
          </span>
        </div>
        {frozen && method.dibekukan_pada && (
          <SegelBeku date={method.dibekukan_pada} animated={stamped} />
        )}
      </div>
      {frozen && (
        <div className={r.notice}>
          <LockKeyhole size={17} />
          <p>
            Versi ini sudah dibekukan dan tidak bisa diubah. Duplikasi versi untuk membuat
            perubahan.
          </p>
        </div>
      )}
      <TabBar
        items={tabs}
        value={tab}
        onChange={(v) => {
          setTab(v)
          setEdit(null)
        }}
        label="Bagian metode risiko"
      >
        <section className={r.panel}>
          {tab === 'matriks' ? (
            <>
              <div className={s.toolbar}>
                <div>
                  <h2>Matriks risiko</h2>
                  <p>Skor dihitung dari nilai kemungkinan × nilai dampak.</p>
                </div>
                {!frozen && (
                  <button onClick={() => setDialog('generate')}>
                    <RefreshCw size={16} />
                    Generate matriks
                  </button>
                )}
              </div>
              <MatriksGrid
                kemungkinan={method.kemungkinan}
                dampak={method.dampak}
                tingkat={method.tingkat}
                cells={method.matriks}
                selected={selected}
                readOnly={frozen}
                onSelect={(key) =>
                  setSelected((values) =>
                    values.includes(key) ? values.filter((v) => v !== key) : [...values, key],
                  )
                }
              />
              <div className={s.legend}>
                {method.tingkat.map((l) => (
                  <RiskLevelChip key={l.id} name={l.nama} color={l.warna} />
                ))}
              </div>
              {!frozen && (
                <>
                  <div className={s.selection}>
                    <span aria-live="polite">{selected.length} sel dipilih</span>
                    <button
                      onClick={() =>
                        setSelected(
                          method.kemungkinan.flatMap((k) =>
                            method.dampak.map((d) => `${k.nilai}-${d.nilai}`),
                          ),
                        )
                      }
                    >
                      Pilih semua sel
                    </button>
                    {selected.length > 0 && (
                      <button onClick={() => setSelected([])}>Bersihkan pilihan</button>
                    )}
                    <select
                      aria-label="Tingkat risiko untuk sel terpilih"
                      value={level}
                      onChange={(e) => setLevel(e.target.value)}
                    >
                      <option value="">Pilih tingkat risiko</option>
                      {method.tingkat.map((l) => (
                        <option key={l.id} value={l.id}>
                          {l.nama}
                        </option>
                      ))}
                    </select>
                    <button
                      className="primary"
                      disabled={!selected.length || !level || save.isPending}
                      onClick={async () => {
                        const cells = method.kemungkinan.flatMap((k) =>
                          method.dampak.map((d) => {
                            const prev = method.matriks.find(
                              (c) => c.kemungkinan === k.nilai && c.dampak === d.nilai,
                            )
                            return {
                              kemungkinan: k.nilai,
                              dampak: d.nilai,
                              skor: k.nilai * d.nilai,
                              tingkat_id: selected.includes(`${k.nilai}-${d.nilai}`)
                                ? Number(level)
                                : prev?.tingkat_id || null,
                            }
                          }),
                        )
                        try {
                          await update({ matriks: cells }, 'Tingkat risiko diterapkan')
                          setSelected([])
                        } catch (err) {
                          toast.error(errorMessage(err))
                        }
                      }}
                    >
                      {save.isPending ? 'Menyimpan…' : 'Terapkan tingkat'}
                    </button>
                  </div>
                  {empty.length > 0 && (
                    <div className={r.notice}>
                      <Info size={16} />
                      <p>
                        {empty.length} sel belum memiliki tingkat risiko. Lengkapi sebelum
                        membekukan versi.
                      </p>
                    </div>
                  )}
                </>
              )}
            </>
          ) : (
            <>
              <div className={s.toolbar}>
                <div>
                  <h2>{tabs.find((t) => t.value === tab)?.label}</h2>
                  <p>
                    {frozen
                      ? 'Acuan tetap untuk penilaian risiko.'
                      : 'Tetapkan definisi yang jelas agar penilaian konsisten.'}
                  </p>
                </div>
                {!frozen && (
                  <button onClick={() => setEdit('new')}>
                    <Plus size={16} />
                    {tab === 'tingkat'
                      ? 'Tambah tingkat'
                      : tab === 'kriteria'
                        ? 'Tambah kriteria'
                        : 'Tambah skala'}
                  </button>
                )}
              </div>
              <div className={s.scroll}>
                <table className={s.table}>
                  <caption className="sr-only">{tabs.find((t) => t.value === tab)?.label}</caption>
                  <thead>
                    <tr>
                      {tab === 'tingkat' ? (
                        <>
                          <th scope="col">Urutan</th>
                          <th scope="col">Nama tingkat</th>
                          <th scope="col">Warna tingkat</th>
                        </>
                      ) : tab === 'kriteria' ? (
                        <>
                          <th scope="col">Skala</th>
                          <th scope="col">Area dampak</th>
                          <th scope="col">Kriteria</th>
                        </>
                      ) : (
                        <>
                          <th scope="col">Nilai</th>
                          <th scope="col">Nama skala</th>
                          <th scope="col">Kriteria</th>
                        </>
                      )}
                      {!frozen && <th scope="col">Tindakan</th>}
                    </tr>
                  </thead>
                  <tbody>
                    {scale.map((v) => (
                      <tr key={v.nilai}>
                        <td>{v.nilai}</td>
                        <td>{v.nama}</td>
                        <td>{v.kriteria}</td>
                        {!frozen && (
                          <td>
                            <button
                              className="link-button"
                              onClick={() => setEdit(v.nilai)}
                              aria-label={`Ubah ${v.nama}`}
                            >
                              Ubah skala
                            </button>
                          </td>
                        )}
                      </tr>
                    ))}
                    {tab === 'tingkat' &&
                      method.tingkat.map((v, i) => (
                        <tr key={v.id}>
                          <td>{i + 1}</td>
                          <td>
                            <RiskLevelChip name={v.nama} color={v.warna} />
                          </td>
                          <td>{v.warna}</td>
                          {!frozen && (
                            <td>
                              <button className="link-button" onClick={() => setEdit(v.id)}>
                                Ubah tingkat
                              </button>
                            </td>
                          )}
                        </tr>
                      ))}
                    {tab === 'kriteria' &&
                      method.kriteria.map((v) => (
                        <tr key={v.id}>
                          <td>{v.dampak}</td>
                          <td>{v.area}</td>
                          <td>{v.kriteria}</td>
                          {!frozen && (
                            <td>
                              <button className="link-button" onClick={() => setEdit(v.id)}>
                                Ubah kriteria
                              </button>
                            </td>
                          )}
                        </tr>
                      ))}
                  </tbody>
                </table>
              </div>
            </>
          )}
        </section>
      </TabBar>
      <Drawer
        open={issues}
        onClose={() => setIssues(false)}
        title="Lengkapi sebelum membekukan"
        description="Periksa masalah berikut dan buka bagian yang perlu dilengkapi."
      >
        <p role="alert">
          Matriks belum lengkap: {empty.length} sel belum punya tingkat risiko. Lengkapi sel yang
          ditandai, lalu bekukan lagi.
        </p>
        <ul className={r.list}>
          {empty.map((key) => (
            <li key={key}>
              <button
                className="link-button"
                onClick={() => {
                  setTab('matriks')
                  setSelected([key])
                  setIssues(false)
                  requestAnimationFrame(() => document.getElementById(`sel-${key}`)?.focus())
                }}
              >
                Lengkapi kemungkinan {key.split('-')[0]}, dampak {key.split('-')[1]}
              </button>
            </li>
          ))}
        </ul>
        {validationIssues
          .filter((issue) => issue.tab !== 'matriks')
          .map((issue) => (
            <p key={issue.message}>
              <button
                className="link-button"
                onClick={() => {
                  setTab(issue.tab)
                  setIssues(false)
                }}
              >
                {issue.message}
              </button>
            </p>
          ))}
        {!method.kriteria.length && (
          <button
            onClick={() => {
              setTab('kriteria')
              setIssues(false)
            }}
          >
            Lengkapi area dan kriteria dampak
          </button>
        )}
      </Drawer>
      <ConfirmDialog
        open={!!dialog}
        onClose={() => setDialog(null)}
        title={dialog === 'freeze' ? 'Bekukan versi' : 'Generate matriks'}
        confirmLabel={dialog === 'freeze' ? 'Bekukan versi' : 'Generate matriks'}
        confirmation={dialog === 'freeze' ? method.nama : undefined}
        description={
          dialog === 'freeze'
            ? [
                'Seluruh skala, kriteria, tingkat, dan matriks menjadi hanya-lihat.',
                'Versi ini tidak dapat dibuka kembali untuk perubahan.',
                'Duplikasi versi untuk menyusun metode berikutnya.',
              ]
            : [
                'Sel matriks dibuat ulang dari skala kemungkinan dan dampak.',
                'Penetapan tingkat risiko yang ada akan dikosongkan.',
              ]
        }
        pending={action.isPending || save.isPending}
        onConfirm={async () => {
          try {
            if (dialog === 'freeze') {
              await action.mutateAsync({
                id: method.id,
                action: 'bekukan',
                body: { nama: method.nama },
              })
              setStamped(true)
              setSelected([])
              toast.success('Versi dibekukan')
            } else {
              await update(
                {
                  matriks: method.kemungkinan.flatMap((k) =>
                    method.dampak.map((d) => ({
                      kemungkinan: k.nilai,
                      dampak: d.nilai,
                      skor: k.nilai * d.nilai,
                      tingkat_id: null,
                    })),
                  ),
                },
                'Matriks dibuat',
              )
              setSelected([])
            }
            setDialog(null)
          } catch (err) {
            toast.error(errorMessage(err))
          }
        }}
      />
      <Drawer open={duplicate} onClose={() => setDuplicate(false)} title="Duplikasi versi">
        <RecordForm
          fields={[{ name: 'nama', label: 'Nama versi baru' }]}
          values={{ nama: `${method.nama} (salinan)` }}
          submitLabel="Duplikasi versi"
          onSave={async (v) => {
            const result = await action.mutateAsync({ id: method.id, action: 'duplikasi', body: v })
            setDuplicate(false)
            toast.success('Versi diduplikasi')
            navigate(`/metode-risiko/${result.data.id}`)
          }}
        />
      </Drawer>
      <Drawer
        open={edit !== null && !frozen}
        onClose={() => setEdit(null)}
        title={edit === 'new' ? 'Tambah definisi' : 'Ubah definisi'}
      >
        {edit !== null && (tab === 'kemungkinan' || tab === 'dampak') && (
          <RecordForm
            fields={[
              { name: 'nama', label: 'Nama skala' },
              { name: 'kriteria', label: 'Kriteria', type: 'textarea' },
            ]}
            values={
              currentScale ? { nama: currentScale.nama, kriteria: currentScale.kriteria } : {}
            }
            onSave={async (v) => {
              const next =
                edit === 'new'
                  ? [
                      ...scale,
                      {
                        nilai: Math.max(0, ...scale.map((x) => x.nilai)) + 1,
                        nama: v.nama,
                        kriteria: v.kriteria,
                      },
                    ]
                  : scale.map((x) => (x.nilai === edit ? { ...x, ...v } : x))
              await update({ [tab]: next })
              setEdit(null)
            }}
          />
        )}
        {edit !== null && tab === 'tingkat' && (
          <RecordForm
            fields={[
              { name: 'nama', label: 'Nama tingkat risiko' },
              { name: 'warna', label: 'Warna', type: 'color', pattern: /^#[0-9a-f]{6}$/i },
            ]}
            values={
              currentLevel
                ? { nama: currentLevel.nama, warna: currentLevel.warna }
                : { warna: '#2e8b7a' }
            }
            onSave={async (v) => {
              await update({
                tingkat:
                  edit === 'new'
                    ? [...method.tingkat, { id: Date.now(), nama: v.nama, warna: v.warna }]
                    : method.tingkat.map((l) => (l.id === edit ? { ...l, ...v } : l)),
              })
              setEdit(null)
            }}
          />
        )}
        {edit !== null && tab === 'kriteria' && (
          <RecordForm
            fields={[
              { name: 'area', label: 'Area dampak' },
              {
                name: 'dampak',
                label: 'Skala dampak',
                type: 'select',
                options: method.dampak.map((d) => ({
                  value: String(d.nilai),
                  label: `${d.nilai} · ${d.nama}`,
                })),
              },
              { name: 'kriteria', label: 'Kriteria dampak', type: 'textarea' },
            ]}
            values={
              currentCriterion
                ? {
                    area: currentCriterion.area,
                    dampak: String(currentCriterion.dampak),
                    kriteria: currentCriterion.kriteria,
                  }
                : {}
            }
            onSave={async (v) => {
              const value = {
                id: edit === 'new' ? Date.now() : Number(edit),
                area: v.area,
                dampak: Number(v.dampak),
                kriteria: v.kriteria,
              }
              await update({
                kriteria:
                  edit === 'new'
                    ? [...method.kriteria, value]
                    : method.kriteria.map((c) => (c.id === edit ? value : c)),
              })
              setEdit(null)
            }}
          />
        )}
      </Drawer>
    </>
  )
}
