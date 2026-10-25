import { api } from '../../api/client'

export type Row = Record<string, unknown> & { id: number }
export type Page = {
  data: Row[]
  meta: {
    current_page: number
    last_page: number
    total: number
    from: number | null
    to: number | null
  }
}
export type Option = { id: number; label: string }

/** Mengambil satu halaman dari endpoint daftar berpaginasi milik backend. */
export async function getPage(
  endpoint: string,
  params: Record<string, unknown> = {},
  signal?: AbortSignal,
): Promise<Page> {
  const response = await api.get<Page>(endpoint, { params: { per_page: 10, ...params }, signal })
  if (!Array.isArray(response.data.data) || !response.data.meta)
    throw new Error('Respons API tidak memuat struktur paginasi yang sesuai.')
  return response.data
}

/** Jumlah total data dari endpoint daftar berpaginasi (meta.total), tanpa mengunduh isinya. */
export async function countOf(
  endpoint: string,
  filter?: Record<string, unknown>,
  signal?: AbortSignal,
): Promise<number> {
  const page = await getPage(endpoint, { per_page: 1, ...(filter ? { filter } : {}) }, signal)
  return page.meta.total
}

/** Daftar pilihan (maksimal 100 data) untuk kolom select. */
export async function getOptions(
  endpoint: string,
  toLabel: (row: Row) => string,
  signal?: AbortSignal,
): Promise<Option[]> {
  const page = await getPage(endpoint, { per_page: 100 }, signal)
  return page.data.map((row) => ({ id: row.id, label: toLabel(row) }))
}

const text = (value: unknown) => (value == null || value === '' ? '' : String(value))

export const byKodeNama = (row: Row) =>
  [text(row.kode), text(row.nama)].filter(Boolean).join(' - ') || '#' + row.id

export const byPegawai = (row: Row) =>
  `${text(row.nama) || '#' + row.id}${row.nip ? ' (' + text(row.nip) + ')' : ''}`

/** Tanggal dari API bisa "2026-10-22" atau ISO; input tanggal HTML butuh YYYY-MM-DD. */
export const dateOnly = (value: unknown) => text(value).slice(0, 10)