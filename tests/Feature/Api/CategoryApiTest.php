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

    public function test_find_by_for_type_returns_matching_active_categories(): void
    {
        Category::create([
            'category_name' => ['fr' => 'Cours', 'en' => 'Courses'],
            'category_description' => ['fr' => 'Categorie education', 'en' => 'Education category'],
            'for_type' => 'education',
        ]);

        Category::create([
            'category_name' => ['fr' => 'Musique', 'en' => 'Music'],
            'category_description' => ['fr' => 'Categorie musique', 'en' => 'Music category'],
            'for_type' => 'music',
        ]);

        $deletedCategory = Category::create([
            'category_name' => ['fr' => 'Archive', 'en' => 'Archive'],
            'category_description' => ['fr' => 'Categorie archivee', 'en' => 'Archived category'],
            'for_type' => 'education',
        ]);

        $deletedCategory->delete();

        $response = $this->getJson('/api/v1/category/for-type/education');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.category_name.fr', 'Cours');
        $response->assertJsonPath('data.0.for_type', 'education');
        $response->assertJsonPath('count', 1);
    }

    public function test_show_missing_category_returns_uniform_api_error(): void
    {
        $response = $this->getJson('/api/v1/category/999?lang=en');

        $response->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('data', null)
            ->assertJsonPath('message', 'Category not found.');
    }
}
