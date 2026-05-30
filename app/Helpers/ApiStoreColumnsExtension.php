<?php

namespace App\Helpers;

use App\Http\Controllers\Api\ApiResourceController;
use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\RequestBodyObject;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\BooleanType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\NumberType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Dedoc\Scramble\Support\RouteInfo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use ReflectionClass;

final class ApiStoreColumnsExtension extends OperationExtension
{
    /**
     * @var array<string, array<string, array{name: string, sql: string, nullable: bool, required: bool}>>
     */
    private static array $tables = [];

    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        if (! $this->documentsResourcePayload($routeInfo)) {
            return;
        }

        $controllerClass = $routeInfo->className();

        if (! $controllerClass || ! is_subclass_of($controllerClass, ApiResourceController::class)) {
            return;
        }

        $table = $this->tableForController($controllerClass);

        if ($table === null) {
            return;
        }

        $columns = $this->documentableColumns($table);

        if ($columns === []) {
            return;
        }

        $schema = $this->requestBodySchema($operation);
        $type = $schema->type;

        if (! $type instanceof ObjectType) {
            return;
        }

        $requiredColumns = $type->required;
        $documentsCreatePayload = $routeInfo->methodName() === 'store';

        foreach ($columns as $column) {
            if (! $type->hasProperty($column['name'])) {
                $type->addProperty($column['name'], $this->typeFromSql($column['sql'], $column['nullable']));
            }

            if ($documentsCreatePayload && $column['required'] && ! in_array($column['name'], $requiredColumns, true)) {
                $requiredColumns[] = $column['name'];
            }
        }

        $type->setRequired($requiredColumns);
        $operation->requestBodyObject?->required($requiredColumns !== []);
    }

    private function documentsResourcePayload(RouteInfo $routeInfo): bool
    {
        return match ($routeInfo->methodName()) {
            'store' => strtolower($routeInfo->method) === 'post',
            'update' => in_array(strtolower($routeInfo->method), ['put', 'patch'], true),
            default => false,
        };
    }

    /**
     * @param  class-string  $controllerClass
     */
    private function tableForController(string $controllerClass): ?string
    {
        $reflectionClass = new ReflectionClass($controllerClass);

        if (! $reflectionClass->hasProperty('modelClass')) {
            return null;
        }

        $property = $reflectionClass->getProperty('modelClass');
        $property->setAccessible(true);

        $modelClass = $property->getValue($reflectionClass->newInstanceWithoutConstructor());

        if (! is_string($modelClass) || ! is_subclass_of($modelClass, Model::class)) {
            return null;
        }

        $modelReflection = new ReflectionClass($modelClass);
        $model = $modelReflection->newInstanceWithoutConstructor();

        if ($modelReflection->hasMethod('tableName')) {
            $method = $modelReflection->getMethod('tableName');
            $method->setAccessible(true);

            return $method->invoke($model);
        }

        return $model->getTable();
    }

    /**
     * @return array<int, array{name: string, sql: string, nullable: bool, required: bool}>
     */
    private function documentableColumns(string $table): array
    {
        return collect($this->tables()[$table] ?? [])
            ->reject(fn (array $column): bool => in_array($column['name'], ['id', 'created_at', 'updated_at', 'deleted_at'], true))
            ->values()
            ->all();
    }

    private function requestBodySchema(Operation $operation): Schema
    {
        $operation->requestBodyObject ??= RequestBodyObject::make();

        if (! isset($operation->requestBodyObject->content) || $operation->requestBodyObject->content === []) {
            $operation->requestBodyObject->setContent('application/json', Schema::fromType(new ObjectType));
        }

        $contentType = array_key_exists('application/json', $operation->requestBodyObject->content)
            ? 'application/json'
            : array_key_first($operation->requestBodyObject->content);

        $schema = $operation->requestBodyObject->content[$contentType];

        if (! $schema instanceof Schema || ! $schema->type instanceof ObjectType) {
            $schema = Schema::fromType(new ObjectType);
            $operation->requestBodyObject->content[$contentType] = $schema;
        }

        return $schema;
    }

    /**
     * @return array<string, array<string, array{name: string, sql: string, nullable: bool, required: bool}>>
     */
    private function tables(): array
    {
        if (self::$tables !== []) {
            return self::$tables;
        }

        $schemaPath = database_path('talaplus.sql');

        if (! is_file($schemaPath)) {
            return self::$tables = [];
        }

        preg_match_all(
            '/CREATE TABLE IF NOT EXISTS `(?P<table>[^`]+)` \((?P<body>.*?)\)\s*ENGINE/s',
            file_get_contents($schemaPath) ?: '',
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $match) {
            $columns = [];

            foreach (preg_split('/\R/', $match['body']) ?: [] as $line) {
                $line = trim($line);

                if (! preg_match('/^`(?P<name>[^`]+)` (?P<sql>.+?)(?:,\s*)?$/', $line, $columnMatch)) {
                    continue;
                }

                $sql = $columnMatch['sql'];

                $columns[$columnMatch['name']] = [
                    'name' => $columnMatch['name'],
                    'sql' => $sql,
                    'nullable' => ! Str::contains($sql, 'NOT NULL') && Str::contains($sql, ' NULL'),
                    'required' => Str::contains($sql, 'NOT NULL')
                        && ! Str::contains($sql, ['DEFAULT', 'AUTO_INCREMENT']),
                ];
            }

            self::$tables[$match['table']] = $columns;
        }

        return self::$tables;
    }

    private function typeFromSql(string $sql, bool $nullable): Type
    {
        $type = match (true) {
            Str::contains($sql, ['TINYINT']) => new BooleanType,
            Str::contains($sql, ['BIGINT', 'INT', 'SMALLINT']) => new IntegerType,
            Str::contains($sql, ['DECIMAL', 'FLOAT', 'DOUBLE']) => new NumberType,
            Str::contains($sql, ['JSON']) => new ObjectType,
            default => new StringType,
        };

        if (preg_match('/ENUM\((?P<values>.*?)\)/', $sql, $matches)) {
            $type->enum(str_getcsv($matches['values'], ',', "'"));
        }

        if (Str::contains($sql, ['DATE', 'TIMESTAMP', 'DATETIME'])) {
            $type->format(Str::contains($sql, 'DATE ') ? 'date' : 'date-time');
        }

        return $type->nullable($nullable);
    }
}
