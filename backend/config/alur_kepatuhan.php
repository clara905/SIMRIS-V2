<?php

return [
    'DRAFT' => ['AJUKAN' => ['pelaku' => 'ADMIN_SATKER', 'ke' => 'MENUNGGU_KASUB']],
    'MENUNGGU_KASUB' => ['SETUJU' => ['pelaku' => 'KASUB', 'ke' => 'MENUNGGU_KABID'], 'TOLAK' => ['pelaku' => 'KASUB', 'ke' => 'DITOLAK']],
    'MENUNGGU_KABID' => ['SETUJU' => ['pelaku' => 'KABID', 'ke' => 'MENUNGGU_KASATKER'], 'TOLAK' => ['pelaku' => 'KABID', 'ke' => 'DITOLAK']],
    'MENUNGGU_KASATKER' => ['SETUJU' => ['pelaku' => 'KASATKER', 'ke' => 'DISAHKAN'], 'TOLAK' => ['pelaku' => 'KASATKER', 'ke' => 'DITOLAK']],
];
