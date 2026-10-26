import { writeFile } from 'node:fs/promises'
const str = { type: 'string' }
const num = { type: 'integer' }
const bool = { type: 'boolean' }
const ref = (name) => ({ $ref: `#/components/schemas/${name}` })
const array = (items) => ({ type: 'array', items })
const object = (properties) => ({ type: 'object', required: Object.keys(properties), properties })
const schemas = {
  SessionUser: object({ id: num, nama: str, email: str, wajib_ganti_password: bool, role: str }),
  RoleScope: object({
    id: num,
    role: str,
    unit_id: num,
    unit_nama: str,
    mulai: str,
    berakhir: { type: ['string', 'null'] },
  }),
  Penempatan: object({
    id: num,
    unit_id: num,
    unit_nama: str,
    jabatan: str,
    mulai: str,
    berakhir: { type: ['string', 'null'] },
  }),
  Pengguna: object({
    id: num,
    nama: str,
    email: str,
    status: str,
    terakhir_masuk: str,
    roles: array(ref('RoleScope')),
  }),
  Unit: object({
    id: num,
    nama: str,
    kode: str,
    jenis: str,
    induk_id: { type: ['integer', 'null'] },
    status: str,
    pimpinan: str,
    pegawai_aktif: num,
  }),
  Pegawai: object({
    id: num,
    nama: str,
    nip: str,
    email: str,
    status: str,
    penempatan: array(ref('Penempatan')),
  }),
  Skala: object({ nilai: num, nama: str, kriteria: str }),
  TingkatRisiko: object({ id: num, nama: str, warna: str }),
  SelMatriks: object({
    kemungkinan: num,
    dampak: num,
    skor: num,
    tingkat_id: { type: ['integer', 'null'] },
  }),
  KriteriaDampak: object({ id: num, area: str, dampak: num, kriteria: str }),
  Metode: object({
    id: num,
    nama: str,
    deskripsi: str,
    status: str,
    dibekukan_pada: { type: ['string', 'null'] },
    kemungkinan: array(ref('Skala')),
    dampak: array(ref('Skala')),
    tingkat: array(ref('TingkatRisiko')),
    matriks: array(ref('SelMatriks')),
    kriteria: array(ref('KriteriaDampak')),
  }),
  Master: object({
    id: num,
    kode: str,
    nama: str,
    deskripsi: str,
    jenis: str,
    status: str,
    induk_id: { type: ['integer', 'null'] },
    digunakan: bool,
    mulai: str,
    selesai: str,
    sumber: str,
    sinkron_pada: str,
  }),
  Asesmen: object({
    id: num,
    kode: str,
    nama: str,
    unit: str,
    status: str,
    periode: str,
    metode: str,
    diperbarui: str,
    jenis: str,
    lampiran_status: str,
    riwayat: array(object({ versi: num, tahap: str, tanggal: str, pelaku: str })),
  }),
  Audit: object({
    id: num,
    waktu: str,
    pelaku: str,
    aksi: str,
    entitas: str,
    nama: str,
    data_lama: { type: 'object', additionalProperties: true },
    data_baru: { type: 'object', additionalProperties: true },
  }),
  ValidationError: object({
    message: str,
    errors: { type: 'object', additionalProperties: array(str) },
  }),
}
const paths = {}
for (const [endpoint, name] of Object.entries({
  pengguna: 'Pengguna',
  unit: 'Unit',
  pegawai: 'Pegawai',
  metode: 'Metode',
  master: 'Master',
  monitoring: 'Asesmen',
  audit: 'Audit',
})) {
  paths[`/api/${endpoint}`] = {
    get: {
      responses: {
        200: {
          description: 'Daftar',
          content: { 'application/json': { schema: object({ data: array(ref(name)) }) } },
        },
      },
    },
  }
}
paths['/api/me'] = {
  get: {
    responses: {
      200: {
        description: 'Sesi',
        content: { 'application/json': { schema: object({ data: ref('SessionUser') }) } },
      },
    },
  },
}
const response = (schema, description = 'Berhasil') => ({
  description,
  content: { 'application/json': { schema } },
})
const errors = {
  401: response(object({ message: str }), 'Sesi tidak valid'),
  403: response(object({ message: str }), 'Tanpa akses'),
  422: response(ref('ValidationError'), 'Galat validasi'),
}
const body = (schema) => ({ required: true, content: { 'application/json': { schema } } })
const idParam = { name: 'id', in: 'path', required: true, schema: num }
for (const [endpoint, name] of Object.entries({
  pengguna: 'Pengguna',
  unit: 'Unit',
  pegawai: 'Pegawai',
  metode: 'Metode',
  master: 'Master',
})) {
  const input = { ...schemas[name], required: [], properties: { ...schemas[name].properties } }
  delete input.properties.id
  schemas[`${name}Input`] = input
  paths[`/api/${endpoint}`].post = {
    requestBody: body(ref(`${name}Input`)),
    responses: { 201: response(object({ data: ref(name) })), ...errors },
  }
  paths[`/api/${endpoint}/{id}`] = {
    parameters: [idParam],
    put: {
      requestBody: body(ref(`${name}Input`)),
      responses: {
        200: response(object({ data: ref(name) })),
        409: response(object({ message: str }), 'Versi beku'),
        ...errors,
      },
    },
  }
}
paths['/sanctum/csrf-cookie'] = {
  get: { responses: { 204: { description: 'Cookie CSRF disiapkan' } } },
}
paths['/login'] = {
  post: {
    requestBody: body(object({ email: str, password: str })),
    responses: { 204: { description: 'Sesi dibuat' }, ...errors },
  },
}
paths['/logout'] = { post: { responses: { 204: { description: 'Sesi diakhiri' }, ...errors } } }
paths['/api/password'] = {
  post: {
    requestBody: body(object({ password: str, password_confirmation: str })),
    responses: { 204: { description: 'Kata sandi diperbarui' }, ...errors },
  },
}
paths['/api/pengguna/{id}/reset-password'] = {
  parameters: [idParam],
  post: { responses: { 200: response(object({ message: str })), ...errors } },
}
for (const action of ['bekukan', 'duplikasi'])
  paths[`/api/metode/{id}/${action}`] = {
    parameters: [idParam],
    post: {
      requestBody: body(object({ nama: str })),
      responses: { 200: response(object({ data: ref('Metode') })), ...errors },
    },
  }
paths['/api/master/{id}/impor'] = {
  parameters: [idParam],
  post: {
    requestBody: body(object({ jenis: str, rows: array(ref('MasterInput')) })),
    responses: { 200: response(object({ message: str })), ...errors },
  },
}
paths['/api/master/{id}/sinkron'] = {
  parameters: [idParam],
  post: { responses: { 200: response(object({ message: str })), ...errors } },
}
await writeFile(
  'src/api/mock.openapi.json',
  JSON.stringify(
    {
      openapi: '3.1.0',
      info: {
        title: 'SIMRIS — kontrak sementara frontend, belum diverifikasi backend',
        version: '0.1.0',
      },
      paths,
      components: { schemas },
    },
    null,
    2,
  ),
)
