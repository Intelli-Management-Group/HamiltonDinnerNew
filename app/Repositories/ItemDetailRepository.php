<?php

namespace App\Repositories;

use App\Models\ItemDetail;

class ItemDetailRepository
{
    protected $model;

    public function __construct(ItemDetail $model)
    {
        $this->model = $model;
    }

    // -- CREATE METHODS --
    public function create(array $data)
    {
        return $this->model->create($data);
    }

    // -- READ METHODS --
    public function find($id)
    {
        return $this->model->find($id);
    }

    public function getByIds(array $ids)
    {
        return $this->model
            ->select('id', 'item_name', 'cat_id')
            ->whereIn('id', $ids)
            ->orderBy('cat_id')
            ->get();
    }

    // -- UPDATE METHODS --
    public function update($id, array $data)
    {
        $itemDetail = $this->model->find($id);
        if ($itemDetail) {
            $itemDetail->update($data);
            return $itemDetail;
        }
        return null;
    }

    // -- DELETE METHODS --
    public function delete($id)
    {
        $itemDetail = $this->model->find($id);
        if ($itemDetail) {
            return $itemDetail->delete();
        }
        return false;
    }
}