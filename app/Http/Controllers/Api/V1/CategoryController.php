<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Categories\CategoryService;
use App\Domain\Shared\Exceptions\DomainErrorCode;
use App\Domain\Shared\Exceptions\DomainException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreCategoryRequest;
use App\Http\Requests\Api\V1\UpdateCategoryRequest;
use App\Http\Resources\Api\V1\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $categories = Category::query()->ownedBy($request->user())->orderBy('kind')->orderBy('name')->get();

        return $this->success($request, ['categories' => CategoryResource::collection($categories)->resolve($request)]);
    }

    public function store(StoreCategoryRequest $request, CategoryService $categories): JsonResponse
    {
        /** @var array{name: string, kind: string, parent_id?: string|null, budget_enabled?: bool, base_limit_minor_units?: int|null, rollover_enabled?: bool, overspend_carry_enabled?: bool, borrowing_enabled?: bool} $attributes */
        $attributes = $request->validated();
        $category = $categories->create($request->user(), $attributes);

        return $this->success($request, (new CategoryResource($category))->resolve($request), JsonResponse::HTTP_CREATED);
    }

    public function show(Request $request, Category $category): JsonResponse
    {
        if ($request->user()->cannot('view', $category)) {
            return $this->notFound($request);
        }

        return $this->success($request, (new CategoryResource($category))->resolve($request));
    }

    public function update(UpdateCategoryRequest $request, Category $category, CategoryService $categories): JsonResponse
    {
        if ($request->user()->cannot('update', $category)) {
            return $this->notFound($request);
        }
        $updated = $categories->update($request->user(), $category, $this->expectedVersion($request), $request->validated());

        return $this->success($request, (new CategoryResource($updated))->resolve($request));
    }

    public function archive(Request $request, Category $category, CategoryService $categories): JsonResponse
    {
        if ($request->user()->cannot('archive', $category)) {
            return $this->notFound($request);
        }
        $updated = $categories->archive($request->user(), $category, $this->expectedVersion($request));

        return $this->success($request, (new CategoryResource($updated))->resolve($request));
    }

    public function restore(Request $request, Category $category, CategoryService $categories): JsonResponse
    {
        if ($request->user()->cannot('restore', $category)) {
            return $this->notFound($request);
        }
        $updated = $categories->restore($request->user(), $category, $this->expectedVersion($request));

        return $this->success($request, (new CategoryResource($updated))->resolve($request));
    }

    private function expectedVersion(Request $request): int
    {
        $version = $request->header('If-Match');
        if (! is_string($version) || ! ctype_digit($version)) {
            throw DomainException::for(DomainErrorCode::ConcurrencyConflict, 'A current If-Match version is required.');
        }

        return (int) $version;
    }

    private function notFound(Request $request): JsonResponse
    {
        return $this->error($request, 'RESOURCE_NOT_FOUND', 'The requested resource was not found.', JsonResponse::HTTP_NOT_FOUND);
    }
}
