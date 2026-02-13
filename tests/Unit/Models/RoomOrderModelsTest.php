<?php

namespace Tests\Unit\Models;

use App\Models\DateWiseOccupancy;
use App\Models\MoveInSummaryValues;
use App\Models\OrderDetail;
use App\Models\RoomDetail;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomOrderModelsTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function room_detail_scope_is_active_filters_rooms()
    {
        RoomDetail::create([
            'room_name' => '101',
            'is_active' => 1,
        ]);

        RoomDetail::create([
            'room_name' => '102',
            'is_active' => 0,
        ]);

        $results = RoomDetail::isActive(1)->get();

        $this->assertCount(1, $results);
        $this->assertSame('101', $results->first()->room_name);
    }

    /** @test */
    public function order_detail_relations_are_configured()
    {
        $order = new OrderDetail();

        $this->assertInstanceOf(HasOne::class, $order->roomData());
        $this->assertInstanceOf(HasOne::class, $order->itemData());
    }

    /** @test */
    public function date_wise_occupancy_can_be_created()
    {
        $record = DateWiseOccupancy::create([
            'room_id' => 1,
            'date' => now()->toDateString(),
            'occupancy' => 2,
        ]);

        $this->assertDatabaseHas('date_wise_occupancies', ['id' => $record->id]);
    }

    /** @test */
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

    /** @test */
    public function setting_scopes_filter_records()
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

        $results = Setting::group('site')->get();

        $this->assertCount(1, $results);
        $this->assertSame('site.app_name', $results->first()->key);
    }

    /** @test */
    public function move_in_summary_values_guards_id()
    {
        $model = new MoveInSummaryValues();

        $this->assertContains('id', $model->getGuarded());
    }
}
