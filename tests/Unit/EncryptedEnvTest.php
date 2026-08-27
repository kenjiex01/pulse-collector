<?php

namespace Tests\Unit;

use App\Support\EncryptedEnv;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EncryptedEnvTest extends TestCase
{
    #[Test]
    public function reveal_returns_plaintext_unchanged(): void
    {
        $this->assertSame('plain-key', EncryptedEnv::reveal('plain-key'));
        $this->assertSame('', EncryptedEnv::reveal(null));
    }

    #[Test]
    public function seal_and_reveal_round_trip(): void
    {
        $sealed = EncryptedEnv::seal('collector-s3-secret');

        $this->assertTrue(EncryptedEnv::isEncrypted($sealed));
        $this->assertSame('collector-s3-secret', EncryptedEnv::reveal($sealed));
    }

    #[Test]
    public function reveal_configured_secrets_decrypts_s3_key(): void
    {
        config(['filesystems.disks.backup-s3.key' => EncryptedEnv::seal('AKIACOLL')]);

        EncryptedEnv::revealConfiguredSecrets();

        $this->assertSame('AKIACOLL', config('filesystems.disks.backup-s3.key'));
    }
}
