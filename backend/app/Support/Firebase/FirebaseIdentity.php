<?php

declare(strict_types=1);

namespace App\Support\Firebase;

final readonly class FirebaseIdentity
{
    public function __construct(
        public string $uid,
        public string $email,
        public bool $emailVerified,
    ) {
    }
}