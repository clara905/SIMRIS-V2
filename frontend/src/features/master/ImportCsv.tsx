import { useState } from 'react'
import { toast } from 'sonner'
import { Drawer } from '../../components/Drawer'
import { DataTable } from '../../components/DataTable'
import { parseCsv } from '../../lib/csv'
import { useMasterAction } from '../../api/master'
import { errorMessage } from '../../api/client'
import type { Master } from '../../api/types'
import s from '../Records.module.css'
interface Row {
  id: number
  kode: string
  nama: string
  deskripsi: string
  status: string
}
export function ImportCsv({
  open,
  onClose,
  jenis,
  existing,
  parentId = null,
}: {
  open: boolean
  onClose: () => void
  jenis: string
  existing: Master[]
  parentId?: number | null
}) {
  const [rows, setRows] = useState<Row[]>([])
  const [error, setError] = useState('')
  const action = useMasterAction()
  return (
    <Drawer
      open={open}
      onClose={onClose}
      title="Impor CSV"
      description="Periksa pratinjau sebelum menambahkan data ke register."
    >
      <p>
        Gunakan kolom <strong>kode,nama,deskripsi</strong> dengan pemisah koma. Maksimal 1 MB dan
        1.000 baris.
      </p>
      {jenis === 'kontrol' && (
        <p>
          Kontrol akan ditempatkan di bawah{' '}
          <strong>{existing.find((m) => m.id === parentId)?.nama || 'standar yang dipilih'}</strong>
          .
        </p>
      )}
      <label htmlFor="csv-file">Berkas CSV</label>
      <input
        id="csv-file"
        type="file"
        accept=".csv,text/csv"
        onChange={async (e) => {
          setRows([])
          setError('')
          const file = e.target.files?.[0]
          if (!file) return
          try {
            if (file.size > 1_000_000)
              throw new Error('Berkas melebihi 1 MB. Bagi menjadi beberapa berkas.')
            const parsed = parseCsv(await file.text())
            const header = parsed.shift()?.map((v) => v.trim().toLowerCase())
            if (header?.join(',') !== 'kode,nama,deskripsi')
              throw new Error('Header harus kode,nama,deskripsi. Perbaiki urutan kolom.')
            if (parsed.length > 1000) throw new Error('Maksimal 1.000 baris per impor.')
            if (!parsed.length)
              throw new Error('Berkas belum berisi data. Tambahkan baris setelah header.')
            const used = new Set(existing.filter((m) => m.jenis === jenis).map((m) => m.kode))
            setRows(
              parsed.map((r, index) => {
                const duplicate = used.has(r[0]?.trim())
                used.add(r[0]?.trim())
                return {
                  id: index + 1,
                  kode: r[0]?.trim() || '',
                  nama: r[1]?.trim() || '',
                  deskripsi: r[2]?.trim() || '',
                  status:
                    r.length !== 3
                      ? 'Jumlah kolom harus 3'
                      : !r[0]?.trim() || !r[1]?.trim()
                        ? 'Kode dan nama wajib diisi'
                        : duplicate
                          ? 'Kode duplikat'
                          : 'Siap diimpor',
                }
              }),
            )
          } catch (err) {
            setError(errorMessage(err))
          }
        }}
      />
      {error && (
        <p role="alert" className="field-error">
          {error}
        </p>
      )}
      {!!rows.length && (
        <>
          <div className={s.notice}>
            {rows.length} baris · {rows.filter((r) => r.status !== 'Siap diimpor').length} baris
            perlu diperbaiki. Seluruh impor dibatalkan jika satu baris tidak valid.
          </div>
          <DataTable
            caption="Pratinjau impor"
            data={rows}
            columns={[
              { key: 'kode', label: 'Kode' },
              { key: 'nama', label: 'Nama' },
              { key: 'status', label: 'Pemeriksaan' },
            ]}
          />
          <div className={s.actions}>
            <button
              className="primary"
              disabled={action.isPending || rows.some((r) => r.status !== 'Siap diimpor')}
              onClick={async () => {
                try {
                  const result = await action.mutateAsync({
                    id: 0,
                    action: 'impor',
                    body: {
                      jenis,
                      rows: rows.map(({ kode, nama, deskripsi }) => ({
                        kode,
                        nama,
                        deskripsi,
                        induk_id: parentId,
                      })),
                    },
                  })
                  toast.success(result.message)
                  onClose()
                } catch (err) {
                  setError(errorMessage(err))
                }
              }}
            >
              {action.isPending ? 'Mengimpor…' : `Impor ${rows.length} data`}
            </button>
          </div>
        </>
      )}
    </Drawer>
  )
}
