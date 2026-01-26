<?php

namespace App\Services;

use App\Models\FormType;
use App\Repositories\Contracts\FormTypeRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class FormTypeService
{
    public function __construct(
        private FormTypeRepositoryInterface $formTypes
    ) {}

    public function findById(int $id): array
    {
        $item = $this->formTypes->findById($id);

        if (!$item) {
            return [
                'statusCode' => 404,
                'payload'    => [
                    'success' => false,
                    'message' => 'FormType not found',
                    'data' => null
                ]
            ];
        }

        return [
            'statusCode' => 200,
            'payload'    => [
                'success' => true,
                'data'    => $item,
                'message' => 'Form type retrieved'
            ]
        ];
    }

    public function list(array $params): array
    {
        $usePagination = isset($params['per_page']) || isset($params['page']);

        if ($usePagination) {
            $perPage = (int)($params['per_page'] ?? 10);
            $pageNumber = (int)($params['page'] ?? 1);

            /** @var LengthAwarePaginator $formTypes */
            $formTypes = $this->formTypes->paginate([], $perPage, $pageNumber);

            return [
                'statusCode' => 200,
                'payload'    => [
                    'success' => true,
                    'data'    => $formTypes->items(),
                    'meta'    => [
                        'current_page' => $formTypes->currentPage(),
                        'last_page'    => $formTypes->lastPage(),
                        'per_page'     => $formTypes->perPage(),
                        'total'        => $formTypes->total(),
                    ],
                    'message' => 'Form types retrieved'
                ],
            ];
        }

        /** @var Collection $formTypes */
        $formTypes = $this->formTypes->getAll();

        return [
            'statusCode' => 200,
            'payload'    => [
                'success' => true,
                'data'    => $formTypes,
                'message' => 'Form types retrieved'
            ]
        ];
    }

    public function store(array $data): array
    {
        /** @var FormType $formType */
        $formType = $this->formTypes->create($data);

        return [
            'statusCode' => 201,
            'payload'    => [
                'success' => true,
                'data'    => $formType,
                'message' => 'Form type created'
            ]
        ];
    }

    public function update(FormType $formType, array $data): array
    {
        $formType->fill($data);
        $this->formTypes->save($formType);

        return [
            'statusCode' => 200,
            'payload'    => [
                'success' => true,
                'data'    => $formType,
                'message' => 'Form type updated'
            ]
        ];
    }

    public function destroy(FormType $formType): array
    {
        $this->formTypes->delete($formType);

        return [
            'statusCode' => 200,
            'payload'    => [
                'success' => true,
                'message' => 'Form type deleted'
            ]
        ];
    }

    public function bulkDestroy(array $ids): array
    {
        $deletedCount = $this->formTypes->bulkDeleteByIds($ids);

        return [
            'statusCode' => 200,
            'payload'    => [
                'success' => true,
                'message' => "{$deletedCount} form types deleted"
            ]
        ];
    }
}