<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CategoryApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('categories');

        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->json('category_name');
            $table->json('category_description')->nullable();
            $table->string('for_type');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function test_index_returns_only_active_categories(): void
    {
        Category::create([
            'category_name' => ['fr' => 'Culture', 'en' => 'Culture'],
            'category_description' => ['fr' => 'Categorie active', 'en' => 'Active category'],
            'for_type' => 'education',
        ]);

        $deletedCategory = Category::create([
            'category_name' => ['fr' => 'Supprimee', 'en' => 'Deleted'],
            'category_description' => ['fr' => 'Categorie supprimee', 'en' => 'Deleted category'],
            'for_type' => 'education',
        ]);

        $deletedCategory->delete();

        $response = $this->getJson('/api/v1/category');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.category_name.fr', 'Culture');
    }
}
