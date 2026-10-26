<?php

namespace App\Contracts;

interface AsetSyncDriver
{
    /** @return list<array<string,mixed>> */
    public function fetch(): array;
}
