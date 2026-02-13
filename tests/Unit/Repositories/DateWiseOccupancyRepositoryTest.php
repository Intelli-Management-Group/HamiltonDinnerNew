<?php

namespace Tests\Unit\Repositories;

use App\Models\DateWiseOccupancy;
use App\Repositories\Eloquent\DateWiseOccupancyRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DateWiseOccupancyRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private DateWiseOccupancyRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new DateWiseOccupancyRepository(new DateWiseOccupancy());
    }

    /** @test */
    public function it_creates_and_finds_records()
    {
        $record = $this->repository->create([
            'room_id' => 1,
            'date' => now()->toDateString(),
            'occupancy' => 2,
        ]);

        $found = $this->repository->findById($record->id);

        $this->assertNotNull($found);
        $this->assertSame(2, $found->occupancy);
    }

    /** @test */
    public function it_filters_by_date_and_room_id()
    {
        $date = now()->toDateString();

        $this->repository->create([
            'room_id' => 1,
            'date' => $date,
            'occupancy' => 1,
        ]);

        $this->repository->create([
            'room_id' => 2,
            'date' => $date,
            'occupancy' => 3,
        ]);

        $results = $this->repository->getAll([
            'date' => $date,
            'room_id' => 1,
        ]);

        $this->assertCount(1, $results);
        $this->assertSame(1, $results->first()->occupancy);
    }

    /** @test */
    public function it_upserts_records_by_filters()
    {
        $date = now()->toDateString();

        $first = $this->repository->upsertByFilters([
            'room_id' => 5,
            'date' => $date,
        ], [
            'occupancy' => 2,
        ]);

        $second = $this->repository->upsertByFilters([
            'room_id' => 5,
            'date' => $date,
        ], [
            'occupancy' => 4,
        ]);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(4, $second->occupancy);
    }
}
