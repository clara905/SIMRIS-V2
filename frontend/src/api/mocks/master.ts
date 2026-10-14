import { http, HttpResponse } from 'msw'
import type { Master } from '../types'
import { authorized, validation, audit, readDb, writeDb } from './data'
const groups: Record<string, string[]> = {
  ancaman: [
    'Akses tidak sah ke sistem',
    'Gangguan layanan jaringan',
    'Kehilangan perangkat',
    'Kegagalan perangkat keras',
    'Kesalahan operasional',
    'Kebocoran informasi',
  ],
  kerentanan: [
    'Kata sandi tidak diperbarui',
    'Cadangan data tidak teruji',
    'Hak akses berlebih',
    'Pembaruan sistem tertunda',
  ],
  standar: ['ISO/IEC 27001:2022', 'Standar pengamanan SPBE'],
  kontrol: [
    'Kebijakan keamanan informasi',
    'Pengelolaan hak akses',
    'Peninjauan akses berkala',
    'Pencadangan informasi',
  ],
  regulasi: ['Peraturan pengelolaan risiko instansi', 'Pedoman keamanan informasi'],
  sumber: ['Register aset internal', 'Inventaris perangkat TI'],
  kategori: ['Teknologi informasi', 'Perangkat keras', 'Perangkat lunak', 'Informasi dan data'],
  klasifikasi: ['Terbuka', 'Internal', 'Terbatas', 'Rahasia'],
  periode: ['Penilaian semester II 2026', 'Penilaian tahunan 2027'],
  rencana: ['Evaluasi kontrol akses', 'Pemutakhiran register risiko'],
}
export function readMaster(): Master[] {
  const raw = localStorage.getItem('simris-master-v1')
  if (raw) return JSON.parse(raw)
  let id = 100
  const result: Master[] = []
  for (const [jenis, names] of Object.entries(groups))
    names.forEach((nama, i) =>
      result.push({
        id: id++,
        jenis,
        nama,
        kode: `${jenis.slice(0, 3).toUpperCase()}-${String(i + 1).padStart(3, '0')}`,
        deskripsi: `Referensi ${jenis} untuk pengelolaan risiko dan tata kelola instansi.`,
        status: 'aktif',
        induk_id: null,
        digunakan: i === 0,
        mulai: jenis === 'periode' && i === 1 ? '2027-01-01' : '2026-07-01',
        selesai: jenis === 'periode' && i === 0 ? '2026-10-31' : '2027-12-31',
        sumber: 'Register internal',
        sinkron_pada: '2026-10-12T01:00:00Z',
      }),
    )
  const standard = result.find((m) => m.jenis === 'standar')!
  const controls = result.filter((m) => m.jenis === 'kontrol')
  controls.forEach((c) => (c.induk_id = standard.id))
  controls[2].induk_id = controls[1].id
  const categories = result.filter((m) => m.jenis === 'kategori')
  categories[1].induk_id = categories[0].id
  categories[2].induk_id = categories[0].id
  return result
}
function persist(values: Master[]) {
  localStorage.setItem('simris-master-v1', JSON.stringify(values))
}
function validate(value: Partial<Master>, data: Master[], id?: number) {
  if (!value.nama?.trim()) return validation('nama', 'Nama wajib diisi.')
  if (!value.kode?.trim()) return validation('kode', 'Kode wajib diisi.')
  if (
    value.jenis === 'kontrol' &&
    !data.some((m) => m.id === value.induk_id && ['standar', 'kontrol'].includes(m.jenis))
  )
    return validation('induk_id', 'Pilih standar atau kontrol induk yang valid.')
  if (data.some((m) => m.jenis === value.jenis && m.kode === value.kode && m.id !== id))
    return validation('kode', 'Kode sudah digunakan pada register ini.')
  if (
    ['periode', 'rencana'].includes(value.jenis || '') &&
    (!value.mulai || !value.selesai || value.mulai > value.selesai)
  )
    return validation('selesai', 'Tanggal selesai harus sama atau setelah tanggal mulai.')
  if (value.status === 'nonaktif' && data.some((m) => m.induk_id === id && m.status === 'aktif'))
    return validation(
      'status',
      'Data memiliki anak aktif. Pindahkan atau nonaktifkan anak lebih dahulu.',
    )
  let parent = value.induk_id
  const seen = new Set<number>()
  while (parent) {
    if (parent === id || seen.has(parent))
      return validation('induk_id', 'Induk tidak boleh membentuk siklus.')
    seen.add(parent)
    parent = data.find((m) => m.id === parent)?.induk_id
  }
}
const complete = (value: Partial<Master>, id: number): Master => ({
  kode: '',
  nama: '',
  deskripsi: '',
  jenis: '',
  status: 'aktif',
  induk_id: null,
  digunakan: false,
  mulai: '',
  selesai: '',
  sumber: 'Register internal',
  sinkron_pada: '',
  ...value,
  id,
})
export const masterHandlers = [
  http.get('/api/master', () => authorized() || HttpResponse.json({ data: readMaster() })),
  http.post('/api/master', async ({ request }) => {
    const denied = authorized()
    if (denied) return denied
    const body = (await request.json()) as Partial<Master>
    const data = readMaster()
    const err = validate(body, data)
    if (err) return err
    const value = complete(body, Date.now())
    persist([...data, value])
    const db = readDb()
    audit(db, value.jenis, value.nama, {}, value, 'Menambahkan')
    writeDb(db)
    return HttpResponse.json({ data: value }, { status: 201 })
  }),
  http.put('/api/master/:id', async ({ request, params }) => {
    const denied = authorized()
    if (denied) return denied
    const data = readMaster()
    const previous = data.find((m) => m.id === Number(params.id))
    if (!previous) return new HttpResponse(null, { status: 404 })
    const value = {
      ...previous,
      ...((await request.json()) as Partial<Master>),
      id: previous.id,
      digunakan: previous.digunakan,
    }
    const err = validate(value, data, previous.id)
    if (err) return err
    persist(data.map((m) => (m.id === value.id ? value : m)))
    const db = readDb()
    audit(db, value.jenis, value.nama, previous, value)
    writeDb(db)
    return HttpResponse.json({ data: value })
  }),
  http.post('/api/master/:id/impor', async ({ request }) => {
    const denied = authorized()
    if (denied) return denied
    const body = (await request.json()) as { jenis: string; rows: Partial<Master>[] }
    const data = readMaster()
    const incoming: Master[] = []
    for (const [i, row] of body.rows.entries()) {
      const value = complete({ ...row, jenis: body.jenis }, Date.now() + i)
      const err = validate(value, [...data, ...incoming])
      if (err) return err
      incoming.push(value)
    }
    persist([...data, ...incoming])
    const db = readDb()
    audit(db, body.jenis, `${incoming.length} data`, {}, { jumlah: incoming.length }, 'Mengimpor')
    writeDb(db)
    return HttpResponse.json({ message: `${incoming.length} data diimpor` })
  }),
  http.post('/api/master/:id/sinkron', ({ params }) => {
    const denied = authorized()
    if (denied) return denied
    const data = readMaster()
    const row = data.find((m) => m.id === Number(params.id))
    if (!row || row.jenis !== 'sumber') return new HttpResponse(null, { status: 404 })
    row.sinkron_pada = new Date().toISOString()
    persist(data)
    return HttpResponse.json({ message: 'Status sinkronisasi diperbarui (simulasi).' })
  }),
]
