<?php

namespace Tests\Unit;

use Carbon\Carbon;
use Tests\TestCase;

class HelperDateFormattingTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_admin_date_time_displays_elapsed_time_for_today(): void
    {
        config(['app.timezone' => 'Africa/Kinshasa']);
        Carbon::setTestNow(Carbon::parse('2026-05-27 10:00:00', 'Africa/Kinshasa'));

        $this->assertSame('Il y a 1 heure', formatAdminDateTime('2026-05-27 08:15:00'));
        $this->assertSame('Il y a 12 minutes', formatAdminDateTime('2026-05-27 09:48:00'));
        $this->assertSame('Il y a 35 secondes', formatAdminDateTime('2026-05-27 09:59:25'));
    }

    public function test_admin_date_time_displays_yesterday_with_time(): void
    {
        config(['app.timezone' => 'Africa/Kinshasa']);
        Carbon::setTestNow(Carbon::parse('2026-05-27 10:00:00', 'Africa/Kinshasa'));

        $this->assertSame('Hier a 18:45', formatAdminDateTime('2026-05-26 18:45:00'));
    }

    public function test_admin_date_time_displays_full_date_for_older_values(): void
    {
        config(['app.timezone' => 'Africa/Kinshasa']);
        Carbon::setTestNow(Carbon::parse('2026-05-27 10:00:00', 'Africa/Kinshasa'));

        $this->assertSame('Le 25/05/2026 a 07:30', formatAdminDateTime('2026-05-25 07:30:00'));
    }

    public function test_social_count_format_uses_network_style_abbreviations(): void
    {
        $this->assertSame('0', formatSocialCount(0));
        $this->assertSame('999', formatSocialCount(999));
        $this->assertSame('1k', formatSocialCount(1000));
        $this->assertSame('1k+', formatSocialCount(1500));
        $this->assertSame('10k', formatSocialCount(10000));
        $this->assertSame('10k+', formatSocialCount(10999));
        $this->assertSame('1M', formatSocialCount(1000000));
        $this->assertSame('1M+', formatSocialCount(1200000));
        $this->assertSame('1B', formatSocialCount(1000000000));
        $this->assertSame('1B+', formatSocialCount(1500000000));
    }
}
