<?php

namespace App\Models\Concerns;

use Carbon\Carbon;
use DateTimeInterface;

trait SerializesLocalDates
{
    protected function serializeDate(DateTimeInterface $date): string
    {
        return Carbon::instance($date)->setTimezone('Asia/Jakarta')->toIso8601String();
    }
}
