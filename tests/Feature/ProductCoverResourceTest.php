<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\Product;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductCoverResourceTest extends TestCase
{
    protected function tearDown(): void
    {
        RefreshDatabaseState::$migrated = false;

        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::disableForeignKeyConstraints();

        foreach (['files', 'reactions', 'specifications', 'products'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('product_name')->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('files', function (Blueprint $table): void {
            $table->id();
            $table->string('file_name')->nullable();
            $table->text('file_url');
            $table->string('file_type')->default('document');
            $table->string('mime_type')->nullable();
            $table->foreignId('product_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('reactions', function (Blueprint $table): void {
            $table->id();
            $table->string('type');
            $table->smallInteger('number_of_stars')->nullable();
            $table->foreignId('product_id')->nullable();
            $table->foreignId('user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('specifications', function (Blueprint $table): void {
            $table->id();
            $table->text('spec_content')->nullable();
            $table->foreignId('product_id');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::enableForeignKeyConstraints();
    }

    public function test_product_json_uses_the_default_cover_when_it_has_no_image_file(): void
    {
        $product = Product::create(['product_name' => 'Book', 'price' => 10]);

        $this->getJson("/api/v1/product/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.cover_url', asset('assets/img/cover-product.png'));
    }

    public function test_product_json_uses_its_first_image_file_as_cover(): void
    {
        $product = Product::create(['product_name' => 'Book', 'price' => 10]);
        File::create([
            'file_name' => 'cover.jpg',
            'file_url' => 'https://example.test/cover.jpg',
            'file_type' => 'photo',
            'mime_type' => 'image/jpeg',
            'product_id' => $product->id,
        ]);

        $this->getJson("/api/v1/product/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.cover_url', 'https://example.test/cover.jpg');
    }
}
