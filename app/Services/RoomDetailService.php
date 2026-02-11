<?php

namespace App\Services;

use App\Models\RoomDetail;
use App\Repositories\Contracts\RoomDetailRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class RoomDetailService
{
    public function __construct(
        private RoomDetailRepositoryInterface $roomDetails
    ) {}

    public function findRoomById(int $id): array
    {
        $room = $this->roomDetails->findById($id);

        if (!$room) {
            return [
                'statusCode' => 404,
                'payload'    => [
                    'success' => false,
                    'message' => 'RoomDetail not found.',
                    'data' => null
                ]
            ];
        }

        return [
            'statusCode' => 200,
            'payload'    => [
                'success' => true,
                'data'    => $room
            ]
        ];
    }

    public function list(array $params): array
    {
        $filters = [
            'is_active' => $params['is_active'] ?? null,
        ];

        $usePagination = isset($params['pagesize']) || isset($params['pagenumber']);

        if ($usePagination) {
            $pageSize = (int)($params['pagesize'] ?? 15);
            $pageNumber = (int)($params['pagenumber'] ?? 1);

            /** @var LengthAwarePaginator $rooms */
            $rooms = $this->roomDetails->paginate(
                filters: $filters,
                perPage: $pageSize,
                pageNumber: $pageNumber
            );

            return [
                'statusCode' => 200,
                'payload'    => [
                    'success' => true,
                    'data'    => $rooms->items(),
                    'pagination' => [
                        'total' => $rooms->total(),
                        'per_page' => $rooms->perPage(),
                        'current_page' => $rooms->currentPage(),
                        'last_page' => $rooms->lastPage(),
                        'from' => $rooms->firstItem(),
                        'to' => $rooms->lastItem(),
                    ]
                ]
            ];
        }

        /** @var Collection $rooms */
        $rooms = $this->roomDetails->getAll(
            filters: $filters
        );

        return [
            'statusCode' => 200,
            'payload'    => [
                'success' => true,
                'data'    => $rooms,
                'count'   => $rooms->count()
            ]
        ];
    }

    public function store(array $data): array
    {
        /** @var RoomDetail $room */
        $room = $this->roomDetails->create($data);

        return [
            'statusCode' => 201,
            'payload'    => [
                'success' => true,
                'message' => 'RoomDetail created successfully.',
                'data'    => $room
            ]
        ];
    }

    public function update(RoomDetail $room, array $data): array
    {
        $room->fill($data);
        $this->roomDetails->save($room);

        return [
            'statusCode' => 200,
            'payload'    => [
                'success' => true,
                'message' => 'RoomDetail updated successfully.',
                'data'    => $room
            ]
        ];
    }

    public function destroy(RoomDetail $room): array
    {
        $this->roomDetails->delete($room);

        return [
            'statusCode' => 200,
            'payload'    => [
                'success' => true,
                'message' => 'RoomDetail deleted successfully.'
            ]
        ];
    }

    public function bulkDestroy(array $ids): array
    {
        $deletedCount = $this->roomDetails->bulkDeleteByIds($ids);

        return [
            'statusCode' => 200,
            'payload'    => [
                'success' => true,
                'message' => "{$deletedCount} RoomDetails deleted successfully."
            ]
        ];
    }
}