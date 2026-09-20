<?php

namespace Tests\Feature\Http;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Route;
use Tests\Fixtures\Http\Resources\ExampleResource;
use Tests\TestCase;

class ApiResourceConventionTest extends TestCase
{
    public function test_single_resource_uses_the_data_envelope(): void
    {
        Route::get('/api/testing/resource', fn (): ExampleResource => new ExampleResource([
            'id' => 7,
            'name' => 'Example',
        ]));

        $response = $this->getJson('/api/testing/resource');

        $response
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'id' => 7,
                    'name' => 'Example',
                ],
            ]);
    }

    public function test_paginated_resource_uses_laravel_data_links_and_meta_envelopes(): void
    {
        Route::get('/api/testing/resources', function (): mixed {
            $paginator = new LengthAwarePaginator(
                items: [
                    ['id' => 7, 'name' => 'Example'],
                ],
                total: 1,
                perPage: 15,
                currentPage: 1,
                options: ['path' => url('/api/testing/resources')],
            );

            return ExampleResource::collection($paginator);
        });

        $response = $this->getJson('/api/testing/resources');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.id', 7)
            ->assertJsonPath('data.0.name', 'Example')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonStructure([
                'data',
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'from', 'last_page', 'path', 'per_page', 'to', 'total'],
            ]);
    }
}
