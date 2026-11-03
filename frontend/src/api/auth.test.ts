import { describe, expect, it } from 'vitest'
import { normalizeSession } from './auth'

describe('normalizeSession', () => {
  it('keeps global super admin role when Laravel returns a null unit scope', () => {
    const session = normalizeSession({
      id: 1,
      username: 'superadmin',
      email: 'admin@simris.go.id',
      nama: 'Admin SIMRIS',
      aktif: true,
      peran: [{ kode: 'SUPER_ADMIN', nama: 'Super Admin', unit: null }],
    })

    expect(session.roleCodes).toEqual(['SUPER_ADMIN'])
    expect(session.unitIds).toEqual([])
  })

  it('normalizes role and unit from Laravel session response', () => {
    const session = normalizeSession({
      id: 2,
      username: 'staff',
      email: 'staff@simris.go.id',
      nama: 'Staf SIMRIS',
      aktif: true,
      peran: [{ kode: 'STAFF', nama: 'Staf', unit: { id: 8 } }],
    })

    expect(session.roleCodes).toEqual(['STAFF'])
    expect(session.unitIds).toEqual([8])
  })
})
