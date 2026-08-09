<?php

namespace Modules\Tables\Interfaces\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class TableCollection extends ResourceCollection
{
    public $collects = TableResource::class;

    public function toArray(Request $request): array
    {
        // Agrupar mesas por área
        $grouped = $this->collection->groupBy('area_code')->map(function ($tables, $areaCode) {
            $firstTable = $tables->first();

            return [
                'area_code' => $areaCode,
                'area_name' => $firstTable->area_name,
                'tables' => TableResource::collection($tables->values()),
            ];
        });

        return $grouped->values()->toArray();
    }
}
