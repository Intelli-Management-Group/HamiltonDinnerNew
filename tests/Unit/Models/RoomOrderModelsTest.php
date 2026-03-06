<?php

namespace Tests\Unit\Models;

use App\Models\DateWiseOccupancy;
use App\Models\MoveInSummaryValues;
use App\Models\OrderDetail;
use App\Models\RoomDetail;
use App\Models\Setting;
use App\Repositories\Eloquent\RoomDetailRepository;
use App\Repositories\Eloquent\SettingRepository;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class RoomOrderModelsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function room_detail_filters_by_active_with_repository()
    {
        RoomDetail::create([
            'room_name' => '101',
            'is_active' => 1,
        ]);

        RoomDetail::create([
            'room_name' => '102',
            'is_active' => 0,
        ]);

        $repository = new RoomDetailRepository(new RoomDetail());
        $results = $repository->getAll(['is_active' => 1]);

        $this->assertCount(1, $results);
        $this->assertSame('101', $results->first()->room_name);
    }

    #[Test]
    public function order_detail_relations_are_configured()
    {
        $order = new OrderDetail();

        $this->assertInstanceOf(HasOne::class, $order->roomData());
        $this->assertInstanceOf(HasOne::class, $order->itemData());
    }

    #[Test]
    public function date_wise_occupancy_can_be_created()
    {
        $record = DateWiseOccupancy::create([
            'room_id' => 1,
            'date' => now()->toDateString(),
            'occupancy' => 2,
        ]);

        $this->assertDatabaseHas('date_wise_occupancies', ['id' => $record->id]);
    }

    #[Test]
    public function setting_json_value_is_encoded_and_decoded()
    {
        $setting = new Setting([
            'key' => 'example',
            'type' => 'json',
        ]);

        $setting->value = ['foo' => 'bar'];

        $this->assertSame('{"foo":"bar"}', $setting->getAttributes()['value']);

        $setting->setRawAttributes([
            'key' => 'example',
            'type' => 'json',
            'value' => '{"hello":"world"}',
        ]);

        $this->assertSame(['hello' => 'world'], $setting->value);
    }

    #[Test]
    public function setting_filters_by_group_with_repository()
    {
        Setting::create([
            'key' => 'site.app_name',
            'display_name' => 'App Name',
            'value' => 'Hamilton',
            'type' => 'string',
            'order' => 1,
            'group' => 'site',
        ]);

        Setting::create([
            'key' => 'mail.host',
            'display_name' => 'Mail Host',
            'value' => 'smtp',
            'type' => 'string',
            'order' => 2,
            'group' => 'mail',
        ]);

        $repository = new SettingRepository(new Setting());
        $results = $repository->getAll(['group' => 'site']);

        $this->assertCount(1, $results);
        $this->assertSame('site.app_name', $results->first()->key);
    }

    #[Test]
    public function move_in_summary_values_guards_id()
    {
        $model = new MoveInSummaryValues();

        $this->assertContains('id', $model->getGuarded());
    }
}
