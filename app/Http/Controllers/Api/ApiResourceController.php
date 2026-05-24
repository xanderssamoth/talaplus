<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseController;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

abstract class ApiResourceController extends BaseController
{
    /**
     * @var class-string<Model>
     */
    protected string $modelClass;

    /**
     * @var class-string<JsonResource>
     */
    protected string $resourceClass;

    public function index(Request $request): JsonResponse
    {
        $records = ($this->modelClass)::query()->latest('id')->paginate(20)->withQueryString();

        return $this->handleResponse(
            ($this->resourceClass)::collection($records),
            __('api.retrieved'),
            $records->lastPage(),
            $records->total()
        );
    }

    public function show(int $id): JsonResponse
    {
        return $this->handleResponse(
            ($this->resourceClass)::make(($this->modelClass)::query()->findOrFail($id)),
            __('api.retrieved')
        );
    }

    public function store(Request $request): JsonResponse
    {
        $record = new ($this->modelClass)();
        $record->fill($this->payload($request));
        $record->save();

        return $this->handleResponse(
            ($this->resourceClass)::make($record->refresh()),
            __('api.created')
        );
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $record = ($this->modelClass)::query()->findOrFail($id);
        $record->fill($this->payload($request));
        $record->save();

        return $this->handleResponse(
            ($this->resourceClass)::make($record->refresh()),
            __('api.updated')
        );
    }

    public function destroy(int $id): JsonResponse
    {
        ($this->modelClass)::query()->findOrFail($id)->delete();

        return $this->handleResponse(null, __('api.deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(Request $request): array
    {
        $model = new ($this->modelClass)();

        return Arr::only($request->all(), $model->getFillable());
    }
}
