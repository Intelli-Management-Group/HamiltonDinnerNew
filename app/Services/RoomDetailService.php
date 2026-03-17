<?php

namespace App\Services;

use App\Models\RoomDetail;
use App\Repositories\Contracts\RoomDetailRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class RoomDetailService extends BaseService
{
    public function __construct(
        private RoomDetailRepositoryInterface $roomDetails
    ) {}

    public function findRoomById(int $id): array
    {
        $room = $this->roomDetails->findById($id);

        if (!$room) {
            return $this->errorResponse(
                message: 'RoomDetail not found.',
                statusCode: 404,
                data: null
            );
        }

        return $this->successResponse($room);
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

            return $this->paginatedResponse($rooms);
        }

        /** @var Collection $rooms */
        $rooms = $this->roomDetails->getAll(
            filters: $filters
        );

        return $this->collectionResponse($rooms);
    }

    public function store(array $data): array
    {
        /** @var RoomDetail $room */
        $room = $this->roomDetails->create($data);

        return $this->successResponse(
            data: $room,
            message: 'RoomDetail created successfully.',
            statusCode: 201
        );
    }

    public function update(RoomDetail $room, array $data): array
    {
        $room->fill($data);
        $this->roomDetails->save($room);

        return $this->successResponse(
            data: $room,
            message: 'RoomDetail updated successfully.'
        );
    }

    public function destroy(RoomDetail $room): array
    {
        $this->roomDetails->delete($room);

        return $this->successResponse(
            message: 'RoomDetail deleted successfully.',
            statusCode: 200,
            includeData: false
        );
    }

    public function bulkDestroy(array $ids): array
    {
        $deletedCount = $this->roomDetails->bulkDeleteByIds($ids);

        return $this->successResponse(
            message: "{$deletedCount} RoomDetails deleted successfully.",
            statusCode: 200,
            includeData: false
        );
    }
}