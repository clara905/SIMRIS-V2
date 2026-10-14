<?php

return [
    'DRAFT' => ['AJUKAN' => ['pelaku' => 'PENYUSUN', 'ke' => 'MENUNGGU_KASUB']],
    'MENUNGGU_KASUB' => ['SETUJU' => ['pelaku' => 'KASUB', 'ke' => 'MENUNGGU_KABID', 'tahap' => 'KASUB_AWAL'], 'TOLAK' => ['pelaku' => 'KASUB', 'ke' => 'DITOLAK', 'tahap' => 'KASUB_AWAL']],
    'MENUNGGU_KABID' => ['SETUJU' => ['pelaku' => 'KABID', 'ke' => 'AWAL_SELESAI', 'tahap' => 'KABID_AWAL'], 'TOLAK' => ['pelaku' => 'KABID', 'ke' => 'DITOLAK', 'tahap' => 'KABID_AWAL']],
    'PENANGANAN' => ['SELESAI_PENANGANAN' => ['pelaku' => 'PIC_PENYUSUN', 'ke' => 'RESIDUAL_DRAFT', 'syarat' => 'penanganan']],
    'RESIDUAL_DRAFT' => ['AJUKAN_RESIDUAL' => ['pelaku' => 'PENYUSUN', 'ke' => 'VERIFIKASI_KASUB', 'syarat' => 'residual']],
    'VERIFIKASI_KASUB' => ['SETUJU' => ['pelaku' => 'KASUB', 'ke' => 'VERIFIKASI_KABID', 'tahap' => 'KASUB_RESIDUAL'], 'TOLAK' => ['pelaku' => 'KASUB', 'ke' => 'KEMBALI', 'tahap' => 'KASUB_RESIDUAL']],
    'VERIFIKASI_KABID' => ['SETUJU' => ['pelaku' => 'KABID', 'ke' => 'MENUNGGU_KASATKER', 'tahap' => 'KABID_RESIDUAL'], 'TOLAK' => ['pelaku' => 'KABID', 'ke' => 'KEMBALI', 'tahap' => 'KABID_RESIDUAL']],
    'MENUNGGU_KASATKER' => ['SETUJU' => ['pelaku' => 'KASATKER', 'ke' => 'DISAHKAN', 'tahap' => 'KASATKER_FINAL'], 'TOLAK' => ['pelaku' => 'KASATKER', 'ke' => 'KEMBALI', 'tahap' => 'KASATKER_FINAL']],
];
