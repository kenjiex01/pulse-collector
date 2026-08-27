<?php

namespace Tests\Unit;

use Tests\TestCase;

class DesktopInstallerUpdateConfigTest extends TestCase
{
    public function test_artifact_patterns_match_pulse_and_legacy_biometric_collector_filenames(): void
    {
        $patterns = config('desktop_updater.artifacts');

        $this->assertMatchesRegularExpression($patterns['win-x64'], 'Pulse-0.1.0-setup.exe');
        $this->assertMatchesRegularExpression($patterns['mac-arm64'], 'Pulse-0.1.0-arm64.dmg');
        $this->assertMatchesRegularExpression($patterns['mac-arm64-zip'], 'Pulse-0.1.0-arm64.zip');
        $this->assertMatchesRegularExpression($patterns['mac-x64'], 'Pulse-0.1.0-x64.dmg');

        $this->assertMatchesRegularExpression($patterns['win-x64'], 'Biometric Collector-0.1.0-setup.exe');
        $this->assertMatchesRegularExpression($patterns['mac-arm64'], 'Biometric Collector-0.1.0-arm64.dmg');
        $this->assertMatchesRegularExpression($patterns['mac-x64'], 'Biometric Collector-0.1.0-x64.dmg');

        $this->assertSame(1, preg_match($patterns['win-x64'], 'Pulse-0.1.0-setup.exe', $m));
        $this->assertSame('0.1.0', $m[1]);
    }

    public function test_installer_basename_is_pulse(): void
    {
        $this->assertSame('Pulse', config('desktop_updater.installer_basename'));
    }

    public function test_default_prefix_is_biometric_installer(): void
    {
        $this->assertSame('biometric_installer', config('desktop_updater.s3_prefix'));
    }
}
