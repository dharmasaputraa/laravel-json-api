<?php

namespace App\Services\Base;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\QueryBuilder;

abstract class BaseService
{
    protected string $model;
    protected string $resource;
    protected array $allowedIncludes = [];
    protected array $allowedSorts = ['created_at'];
    protected array $allowedFilters = [];

    // Spatie QueryBuilder config
    protected array $spatieFilters = [];
    protected array $allowedFields = [];
    protected string $defaultSort = '-created_at';

    /**
     * Build a paginated/queryable list.
     */
    public function query(Request $request)
    {
        $query = $this->getModelInstance()->newQuery();

        // Eager load requested relationships
        if ($includes = $request->query('include')) {
            $relations = array_intersect(
                array_map('trim', explode(',', $includes)),
                $this->allowedIncludes
            );
            $query->with($relations);
        }

        // Apply filters
        $this->applyFilters($query, $request);

        // Apply sorts
        if ($sort = $request->query('sort')) {
            $sortFields = array_map('trim', explode(',', $sort));
            foreach ($sortFields as $field) {
                $direction = 'asc';
                if (str_starts_with($field, '-')) {
                    $direction = 'desc';
                    $field = substr($field, 1);
                }
                if (in_array($field, $this->allowedSorts)) {
                    $query->orderBy($field, $direction);
                }
            }
        } else {
            $query->latest();
        }

        $perPage = (int) ($request->query('per_page', 15));

        return $query->paginate($perPage);
    }

    /**
     * Find a single record by UUID with optional includes.
     */
    public function find(string $id, Request $request): Model
    {
        $query = $this->getModelInstance()->newQuery();

        if ($includes = $request->query('include')) {
            $relations = array_intersect(
                array_map('trim', explode(',', $includes)),
                $this->allowedIncludes
            );
            $query->with($relations);
        }

        return $query->findOrFail($id);
    }

    /**
     * Create a new record.
     */
    public function create(array $data, ?string $userId = null): Model
    {
        return $this->getModelInstance()->create($data);
    }

    /**
     * Update an existing record.
     */
    public function update(Model $model, array $data, ?string $userId = null): Model
    {
        $model->update($data);
        return $model;
    }

    /**
     * Delete a record.
     */
    public function delete(Model $model, ?string $userId = null): void
    {
        $model->delete();
    }

    protected function getModelInstance(): Model
    {
        return new $this->model;
    }

    protected function applyFilters(Builder $query, Request $request): void
    {
        $filterInput = $request->query('filter', []);

        if (!is_array($filterInput)) return;

        foreach ($this->allowedFilters as $field) {
            $value = $filterInput[$field] ?? null;

            if ($value === null || $value === '') continue;

            $query->where($field, 'LIKE', "%{$value}%");
        }
    }

    /**
     * Build a paginated list using Spatie QueryBuilder.
     *
     * Uses declarative filter/sort/include/field config from service properties.
     * Call this instead of query() for Spatie-powered filtering.
     */
    public function querySpatieBuilder(Request $request)
    {
        $builder = QueryBuilder::for($this->model)
            ->allowedSorts(...$this->allowedSorts)
            ->defaultSort($this->defaultSort);

        // Only add these if non-empty (Spatie throws on empty arrays)
        if (!empty($this->spatieFilters)) {
            $builder = $builder->allowedFilters(...$this->spatieFilters);
        }
        if (!empty($this->allowedIncludes)) {
            $builder = $builder->allowedIncludes(...$this->allowedIncludes);
        }
        if (!empty($this->allowedFields)) {
            $builder = $builder->allowedFields(...$this->allowedFields);
        }

        // input() supports dot notation (e.g. page.size), query() does not
        return $builder->paginate((int) $request->input('page.size', 15));
    }
}
