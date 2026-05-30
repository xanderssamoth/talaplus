<?php

namespace Tests\Unit;

use App\Helpers\ApiStoreColumnsExtension;
use App\Http\Controllers\Api\RoleController;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Routing\Route;
use ReflectionClass;
use Tests\TestCase;

class ApiStoreColumnsExtensionTest extends TestCase
{
    public function test_it_documents_store_columns_from_the_sql_schema(): void
    {
        $route = new Route(['POST'], 'api/v1/role', [
            'uses' => RoleController::class.'@store',
        ]);

        $operation = Operation::make('post');

        (new ReflectionClass(ApiStoreColumnsExtension::class))->newInstanceWithoutConstructor()->handle(
            $operation,
            new RouteInfo($route, 'post')
        );

        $requestBody = $operation->toArray()['requestBody'];
        $schema = $requestBody['content']['application/json']['schema'];

        $this->assertArrayHasKey('role_name', $schema['properties']);
        $this->assertArrayHasKey('role_description', $schema['properties']);
        $this->assertSame('object', $schema['properties']['role_name']['type']);
        $this->assertContains('role_name', $schema['required']);
    }

    public function test_it_documents_update_columns_without_requiring_a_full_payload(): void
    {
        $route = new Route(['PUT'], 'api/v1/role/{role}', [
            'uses' => RoleController::class.'@update',
        ]);

        $operation = Operation::make('put');

        (new ReflectionClass(ApiStoreColumnsExtension::class))->newInstanceWithoutConstructor()->handle(
            $operation,
            new RouteInfo($route, 'put')
        );

        $requestBody = $operation->toArray()['requestBody'];
        $schema = $requestBody['content']['application/json']['schema'];

        $this->assertArrayHasKey('role_name', $schema['properties']);
        $this->assertArrayHasKey('role_description', $schema['properties']);
        $this->assertArrayNotHasKey('required', $schema);
    }
}
