<?php

namespace Modules\Tables\Interfaces\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveFloorPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $branchId = $this->user()->branch_id;

        return [
            'plan_version' => 'nullable|integer|min:1',
            'plan' => 'nullable|array',
            'plan.width' => 'nullable|integer|min:500|max:10000',
            'plan.height' => 'nullable|integer|min:500|max:10000',
            'plan.grid_size' => 'nullable|integer|in:20,40,80,100',

            'zones' => 'nullable|array',
            'zones.*.uuid' => 'required|string|uuid',
            'zones.*.code' => 'required|string|max:50',
            'zones.*.name_translations' => 'nullable|array',
            'zones.*.type' => ['nullable', Rule::in(['dining_room', 'terrace', 'bar', 'private_room', 'waiting_area', 'other'])],
            'zones.*.position_x' => 'required|integer',
            'zones.*.position_y' => 'required|integer',
            'zones.*.width' => 'required|integer|min:50',
            'zones.*.height' => 'required|integer|min:50',
            'zones.*.rotation' => 'nullable|integer|in:0,90,180,270',
            'zones.*.properties' => 'nullable|array',

            // NUEVO CONTRATO
            'objects' => 'nullable|array',
            'objects.*.uuid' => 'required|string|uuid',
            'objects.*.object_type' => [
                'required', 
                Rule::in(['table', 'chair', 'wall', 'door', 'window', 'column', 'plant', 'counter', 'booth', 'bench', 'sofa', 'service_station', 'partition', 'other'])
            ],
            'objects.*.table_uuid' => [
                'nullable', 'string', 'uuid',
                Rule::exists('restaurant_tables', 'uuid')
                    ->where('branch_id', $branchId)
                    ->whereNull('deleted_at')
            ],
            'objects.*.zone_uuid' => 'nullable|string|uuid|exists:dining_zones,uuid',
            'objects.*.position_x' => 'required|integer',
            'objects.*.position_y' => 'required|integer',
            'objects.*.width' => 'required|integer|min:10',
            'objects.*.height' => 'required|integer|min:10',
            'objects.*.rotation' => 'nullable|integer',
            'objects.*.z_index' => 'nullable|integer',
            'objects.*.properties' => 'nullable|array',

            // COMPATIBILIDAD CON PRUEBAS LEGACY
            'tables' => 'nullable|array',
            'tables.*.uuid' => [
                'required', 'string', 'uuid',
                Rule::exists('restaurant_tables', 'uuid')
                    ->where('branch_id', $branchId)
                    ->whereNull('deleted_at')
            ],
            'tables.*.position_x' => 'required|integer',
            'tables.*.position_y' => 'required|integer',
            'tables.*.rotation' => 'nullable|integer|in:0,90,180,270',
            'tables.*.shape' => 'nullable|string|in:square,round,rectangle',
            'tables.*.width' => 'nullable|integer',
            'tables.*.height' => 'nullable|integer',
        ];
    }

    public function messages(): array
    {
        return [
            'objects.*.table_uuid.exists' => 'La mesa operativa especificada no existe o no pertenece a esta sucursal.',
            'tables.*.uuid.exists' => 'La mesa especificada no existe o no pertenece a esta sucursal.',
            'objects.*.object_type.in' => 'El tipo de objeto gráfico no está permitido en el catálogo.',
            'tables.*.shape.in' => 'La forma de la mesa no es válida.',
        ];
    }
}
