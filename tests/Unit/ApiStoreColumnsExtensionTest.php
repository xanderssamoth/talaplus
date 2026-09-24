<?php

namespace Tests\Unit;

use App\Helpers\ApiStoreColumnsExtension;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\UserController;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\RequestBodyObject;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
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

    public function test_it_documents_only_the_supported_user_store_fields(): void
    {
        $route = new Route(['POST'], 'api/v1/user', [
            'uses' => UserController::class.'@store',
        ]);

        $operation = $this->userOperation([
            'firstname', 'lastname', 'surname', 'partner_name', 'about_me', 'gender', 'birthdate', 'country', 'city',
            'address_1', 'address_2', 'p_o_box', 'currency', 'email', 'phone', 'username', 'christian_preference',
            'belongs_to', 'child_lock_code', 'password', 'password_confirmation', 'avatar_base64', 'cover_base64',
        ], ['username']);
        $routeInfo = new RouteInfo($route, 'post');

        (new ReflectionClass(ApiStoreColumnsExtension::class))->newInstanceWithoutConstructor()->handle($operation, $routeInfo);

        $schema = $operation->toArray()['requestBody']['content']['application/json']['schema'];

        $this->assertArrayHasKey('avatar_base64', $schema['properties']);
        $this->assertArrayHasKey('cover_base64', $schema['properties']);
        $this->assertArrayHasKey('password_confirmation', $schema['properties']);
        $this->assertArrayHasKey('partner_name', $schema['properties']);
        $this->assertArrayHasKey('currency', $schema['properties']);
        $this->assertArrayHasKey('child_lock_code', $schema['properties']);
        $this->assertContains('username', $schema['required']);
        $this->assertArrayNotHasKey('confirm_passord', $schema['properties']);
        $this->assertArrayNotHasKey('api_token', $schema['properties']);
        $this->assertArrayNotHasKey('status', $schema['properties']);
        $this->assertArrayNotHasKey('type', $schema['properties']);
        $this->assertArrayNotHasKey('email_verified_at', $schema['properties']);
        $this->assertArrayNotHasKey('two_factor_secret', $schema['properties']);
    }

    public function test_it_documents_only_the_supported_user_update_fields(): void
    {
        $route = new Route(['PUT'], 'api/v1/user/{user}', [
            'uses' => UserController::class.'@update',
        ]);

        $operation = $this->userOperation([
            'firstname', 'lastname', 'surname', 'partner_name', 'about_me', 'gender', 'birthdate', 'country', 'city',
            'address_1', 'address_2', 'p_o_box', 'currency', 'email', 'phone', 'username', 'christian_preference',
            'belongs_to', 'child_lock_code', 'password', 'password_confirmation', 'avatar_base64', 'cover_base64',
            'api_key', 'promo_code', 'two_factor_secret', 'two_factor_recovery_codes',
            'two_factor_email_confirmed_at', 'two_factor_phone_confirmed_at', 'tips_at_every_login', 'is_online',
            'status', 'type',
        ]);
        $routeInfo = new RouteInfo($route, 'put');

        (new ReflectionClass(ApiStoreColumnsExtension::class))->newInstanceWithoutConstructor()->handle($operation, $routeInfo);

        $schema = $operation->toArray()['requestBody']['content']['application/json']['schema'];

        $this->assertArrayHasKey('avatar_base64', $schema['properties']);
        $this->assertArrayHasKey('cover_base64', $schema['properties']);
        $this->assertArrayHasKey('api_key', $schema['properties']);
        $this->assertArrayHasKey('two_factor_secret', $schema['properties']);
        $this->assertArrayHasKey('status', $schema['properties']);
        $this->assertArrayHasKey('type', $schema['properties']);
        $this->assertArrayNotHasKey('api_token', $schema['properties']);
        $this->assertArrayNotHasKey('avatar_url', $schema['properties']);
        $this->assertArrayNotHasKey('cover_url', $schema['properties']);
        $this->assertArrayNotHasKey('confirm_passord', $schema['properties']);
        $this->assertArrayNotHasKey('email_verified_at', $schema['properties']);
        $this->assertArrayNotHasKey('required', $schema);
    }

    /**
     * @param  list<string>  $fields
     * @param  list<string>  $requiredFields
     */
    private function userOperation(array $fields, array $requiredFields = []): Operation
    {
        $type = new ObjectType;

        foreach ($fields as $field) {
            $type->addProperty($field, new StringType);
        }

        $type->setRequired($requiredFields);

        return Operation::make('post')->addRequestBodyObject(
            RequestBodyObject::make()->setContent('application/json', Schema::fromType($type))
        );
    }
}
