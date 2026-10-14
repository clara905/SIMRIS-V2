<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\UnitOrganisasi;
use Illuminate\Support\Facades\DB;

class HierarkiService
{
    public function validateUnit(array $data, ?UnitOrganisasi $unit = null): void
    {
        $values = [...($unit?->getAttributes() ?? []), ...$data];
        $parent = isset($values['parent_id']) ? UnitOrganisasi::findOrFail($values['parent_id']) : null;
        $seen = [];
        $cursor = $parent;
        while ($cursor) {
            if ($cursor->id === $unit?->id || isset($seen[$cursor->id])) {
                throw new BusinessRuleException('siklus_hierarki', 'Induk tersebut membentuk siklus organisasi.');
            }
            $seen[$cursor->id] = true;
            $cursor = $cursor->parent_id ? UnitOrganisasi::find($cursor->parent_id) : null;
        }
        if (($values['jenis'] === 'SATKER' && $parent) || ($values['jenis'] !== 'SATKER' && ! $parent) || ($parent && ! in_array($values['jenis'], config('aturan_unit.'.$parent->jenis, []), true))) {
            throw new BusinessRuleException('level_unit_tidak_valid', 'Jenis unit tidak sesuai dengan tingkat induknya.');
        }
        if ($parent && ! $parent->aktif) {
            throw new BusinessRuleException('induk_tidak_aktif', 'Induk unit harus aktif.');
        }
        if ($unit && UnitOrganisasi::where('parent_id', $unit->id)->whereNotIn('jenis', config('aturan_unit.'.$values['jenis'], []))->exists()) {
            throw new BusinessRuleException('level_anak_tidak_valid', 'Perubahan jenis tidak sesuai dengan anak unit yang ada.');
        }
        if ($unit && ! ($values['aktif'] ?? true)) {
            $hasStaff = DB::table('PENEMPATAN_PEGAWAI as p')->join('PEGAWAI as e', 'e.id', '=', 'p.pegawai_id')->where('p.unit_id', $unit->id)->where('e.aktif', true)->where('p.mulai', '<=', today())->where(fn ($q) => $q->whereNull('p.selesai')->orWhere('p.selesai', '>=', today()))->exists();
            if (UnitOrganisasi::where('parent_id', $unit->id)->exists() || $hasStaff) {
                throw new BusinessRuleException('unit_masih_digunakan', 'Unit dengan anak atau pegawai aktif tidak dapat dinonaktifkan.');
            }
        }
    }

    public function tree(array $rows): array
    {
        $ids = array_column($rows, 'id');
        $build = function (?int $parent, array $visited = []) use (&$build, $rows, $ids): array {
            $result = [];
            foreach ($rows as $row) {
                $root = $row['parent_id'] === null || ! in_array($row['parent_id'], $ids, true);
                if (($parent === null ? $root : $row['parent_id'] === $parent) && ! in_array($row['id'], $visited, true)) {
                    $row['children'] = $build($row['id'], [...$visited, $row['id']]);
                    $result[] = $row;
                }
            }

            return $result;
        };

        return $build(null);
    }
}
