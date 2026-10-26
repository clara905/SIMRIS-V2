<?php

return ['url' => env('ASET_API_URL'), 'token' => env('ASET_API_TOKEN'), 'unit_bawaan_kode' => env('ASET_UNIT_BAWAAN_KODE'), 'jadwal' => env('ASET_SINKRON_JADWAL', '02:00'), 'enabled' => (bool) env('ASET_SINKRON_AKTIF', false), 'lock_seconds' => 3600];
