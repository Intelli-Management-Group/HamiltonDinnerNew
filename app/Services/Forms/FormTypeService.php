<?php

namespace App\Services\Forms;

use App\Models\FormType;
use App\Repositories\Contracts\Forms\FormTypeRepositoryInterface;
use App\Services\BaseService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class FormTypeService extends BaseService
{
    public function __construct(
        private FormTypeRepositoryInterface $formTypes
    ) {}

    public function findById(int $id): array
    {
        $item = $this->formTypes->findById($id);

        if (!$item) {
            return $this->errorResponse(
                message: 'FormType not found',
                statusCode: 404,
                data: null
            );
        }

        return $this->successResponse(
            data: $item,
            message: 'Form type retrieved'
        );
    }

    public function list(array $params): array
    {
        $usePagination = isset($params['per_page']) || isset($params['page']);

        if ($usePagination) {
            $perPage = (int)($params['per_page'] ?? 10);
            $pageNumber = (int)($params['page'] ?? 1);

            /** @var LengthAwarePaginator $formTypes */
            $formTypes = $this->formTypes->paginate([], $perPage, $pageNumber);

            return $this->paginatedResponse(
                paginator: $formTypes,
                statusCode: 200,
                message: 'Form types retrieved',
                paginationKey: 'meta',
                paginationData: [
                    'current_page' => $formTypes->currentPage(),
                    'last_page'    => $formTypes->lastPage(),
                    'per_page'     => $formTypes->perPage(),
                    'total'        => $formTypes->total(),
                ]
            );
        }

        /** @var Collection $formTypes */
        $formTypes = $this->formTypes->getAll();

        return $this->collectionResponse(
            collection: $formTypes,
            statusCode: 200,
            message: 'Form types retrieved',
            includeCount: false
        );
    }

    public function store(array $data): array
    {
        /** @var FormType $formType */
        $formType = $this->formTypes->create($data);

        return $this->successResponse(
            data: $formType,
            message: 'Form type created',
            statusCode: 201
        );
    }

    public function update(FormType $formType, array $data): array
    {
        $formType->fill($data);
        $this->formTypes->save($formType);

        return $this->successResponse(
            data: $formType,
            message: 'Form type updated'
        );
    }

    public function destroy(FormType $formType): array
    {
        $this->formTypes->delete($formType);

        return $this->successResponse(
            message: 'Form type deleted',
            statusCode: 200,
            includeData: false
        );
    }

    public function bulkDestroy(array $ids): array
    {
        $deletedCount = $this->formTypes->bulkDeleteByIds($ids);

        return $this->successResponse(
            message: "{$deletedCount} form types deleted",
            statusCode: 200,
            includeData: false
        );
    }
}