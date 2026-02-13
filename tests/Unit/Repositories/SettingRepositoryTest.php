<?php

namespace Tests\Unit\Repositories;

use App\Models\Setting;
use App\Repositories\Eloquent\SettingRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private SettingRepository $settings;

    protected function setUp(): void
    {
        parent::setUp();
        $this->settings = new SettingRepository(new Setting());
    }

    /** @test */
    public function it_finds_setting_by_key_and_lists_key_values()
    {
        $this->settings->create([
            'key' => 'site.name',
            'display_name' => 'Site Name',
            'value' => 'Hamilton',
            'type' => 'string',
            'order' => 1,
            'group' => 'site',
        ]);

        $found = $this->settings->findByKey('site.name');
        $this->assertNotNull($found);

        $all = $this->settings->getAllKeyValues();
        $this->assertSame('Hamilton', $all['site.name']);
    }

    /** @test */
    public function it_filters_settings_by_group_and_type()
    {
        $this->settings->create([
            'key' => 'site.theme',
            'display_name' => 'Site Theme',
            'value' => 'dark',
            'type' => 'string',
            'order' => 1,
            'group' => 'site',
        ]);

        $this->settings->create([
            'key' => 'mail.host',
            'display_name' => 'Mail Host',
            'value' => 'smtp',
            'type' => 'string',
            'order' => 2,
            'group' => 'mail',
        ]);

        $results = $this->settings->getAllWithParameters([
            'group' => 'site',
            'type' => 'string',
        ]);

        $this->assertCount(1, $results);
        $this->assertSame('site.theme', $results->first()->key);
    }
}
