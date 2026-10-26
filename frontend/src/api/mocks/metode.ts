import { methodIssues } from '../../lib/metode'
import { http, HttpResponse } from 'msw'
import type { Metode } from '../types'
import { audit, authorized, readDb, validation, writeDb } from './data'
const scaleNames = ['Sangat jarang', 'Jarang', 'Kadang terjadi', 'Sering', 'Hampir pasti']
const impactNames = ['Tidak signifikan', 'Minor', 'Moderat', 'Signifikan', 'Katastropik']
const levelNames = ['Sangat rendah', 'Rendah', 'Sedang', 'Tinggi', 'Sangat tinggi']
export function newMetode(id: number, nama: string): Metode {
  return {
    id,
    nama,
    deskripsi: 'Pedoman penilaian risiko operasional dan keamanan informasi instansi.',
    status: 'draf',
    dibekukan_pada: null,
    kemungkinan: scaleNames.map((nama, i) => ({
      nilai: i + 1,
      nama,
      kriteria: [
        'Kurang dari 1 kejadian dalam 5 tahun.',
        '1 kejadian dalam 2–5 tahun.',
        '1 kejadian dalam setahun.',
        'Beberapa kejadian dalam setahun.',
        'Kejadian berulang setiap bulan.',
      ][i],
    })),
    dampak: impactNames.map((nama, i) => ({
      nilai: i + 1,
      nama,
      kriteria: [
        'Layanan tidak terhenti.',
        'Gangguan layanan kurang dari 4 jam.',
        'Gangguan layanan hingga 1 hari.',
        'Gangguan layanan 1–3 hari.',
        'Layanan kritis terhenti lebih dari 3 hari.',
      ][i],
    })),
    tingkat: levelNames.map((nama, i) => ({
      id: i + 1,
      nama,
      warna: ['#2E8B7A', '#8FB339', '#E3B23C', '#E07B39', '#C23B3B'][i],
    })),
    matriks: [],
    kriteria: impactNames.map((nama, i) => ({
      id: i + 1,
      area: 'Operasional',
      dampak: i + 1,
      kriteria: `Dampak ${nama.toLowerCase()} terhadap layanan instansi.`,
    })),
  }
}
export function generateCells(method: Metode, filled = false) {
  return method.kemungkinan.flatMap((k) =>
    method.dampak.map((d) => {
      const skor = k.nilai * d.nilai
      return {
        kemungkinan: k.nilai,
        dampak: d.nilai,
        skor,
        tingkat_id: filled
          ? skor <= 3
            ? 1
            : skor <= 6
              ? 2
              : skor <= 12
                ? 3
                : skor <= 19
                  ? 4
                  : 5
          : null,
      }
    }),
  )
}
function read(): Metode[] {
  const raw = localStorage.getItem('simris-metode-v1')
  if (raw) return JSON.parse(raw)
  const frozen = newMetode(1, 'Metode risiko 2026')
  frozen.status = 'dibekukan'
  frozen.dibekukan_pada = '2026-10-01T03:00:00Z'
  frozen.matriks = generateCells(frozen, true)
  const draft = newMetode(2, 'Metode risiko 2027')
  draft.matriks = generateCells(draft, true).map((cell, i) =>
    i > 21 ? { ...cell, tingkat_id: null } : cell,
  )
  return [draft, frozen]
}
function persist(values: Metode[]) {
  localStorage.setItem('simris-metode-v1', JSON.stringify(values))
}
export const metodeHandlers = [
  http.get('/api/metode', () => authorized() || HttpResponse.json({ data: read() })),
  http.post('/api/metode', async ({ request }) => {
    const denied = authorized()
    if (denied) return denied
    const body = (await request.json()) as Partial<Metode>
    if (!body.nama?.trim()) return validation('nama', 'Nama versi wajib diisi.')
    const methods = read()
    if (methods.some((m) => m.nama === body.nama))
      return validation('nama', 'Nama versi sudah digunakan.')
    const value = { ...newMetode(Date.now(), body.nama), deskripsi: body.deskripsi || '' }
    persist([value, ...methods])
    return HttpResponse.json({ data: value }, { status: 201 })
  }),
  http.put('/api/metode/:id', async ({ request, params }) => {
    const denied = authorized()
    if (denied) return denied
    const methods = read()
    const previous = methods.find((m) => m.id === Number(params.id))
    if (!previous) return new HttpResponse(null, { status: 404 })
    if (previous.status === 'dibekukan')
      return HttpResponse.json(
        { message: 'Versi sudah dibekukan dan tidak dapat diubah.' },
        { status: 409 },
      )
    const body = (await request.json()) as Partial<Metode>
    const value = { ...previous, ...body, id: previous.id, status: 'draf', dibekukan_pada: null }
    persist(methods.map((m) => (m.id === value.id ? value : m)))
    const db = readDb()
    audit(db, 'Metode risiko', value.nama, previous, value)
    writeDb(db)
    return HttpResponse.json({ data: value })
  }),
  http.post('/api/metode/:id/:action', async ({ request, params }) => {
    const denied = authorized()
    if (denied) return denied
    const methods = read()
    const method = methods.find((m) => m.id === Number(params.id))
    if (!method) return new HttpResponse(null, { status: 404 })
    const body = (await request.json()) as { nama?: string }
    if (params.action === 'duplikasi') {
      const copy = {
        ...structuredClone(method),
        id: Date.now(),
        nama: body.nama || `${method.nama} (salinan)`,
        status: 'draf',
        dibekukan_pada: null,
      }
      if (methods.some((m) => m.nama === copy.nama))
        return validation('nama', 'Nama versi sudah digunakan.')
      persist([copy, ...methods])
      return HttpResponse.json({ data: copy })
    }
    if (params.action === 'bekukan') {
      if (method.status === 'dibekukan')
        return HttpResponse.json({ message: 'Versi sudah dibekukan.' }, { status: 409 })
      if (body.nama !== method.nama) return validation('nama', 'Nama versi tidak cocok.')
      if (methodIssues(method).length)
        return validation(
          'matriks',
          'Matriks atau kriteria belum lengkap. Lengkapi sebelum membekukan versi.',
        )
      const value = { ...method, status: 'dibekukan', dibekukan_pada: new Date().toISOString() }
      persist(methods.map((m) => (m.id === method.id ? value : m)))
      const db = readDb()
      audit(
        db,
        'Metode risiko',
        value.nama,
        { status: 'draf' },
        { status: 'dibekukan' },
        'Membekukan',
      )
      writeDb(db)
      return HttpResponse.json({ data: value })
    }
    return new HttpResponse(null, { status: 404 })
  }),
]
