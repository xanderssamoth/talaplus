<?php

namespace Tests\Unit\Models;

use App\Models\AboutContent;
use App\Models\AboutDash;
use App\Models\AboutSubject;
use App\Models\AboutTitle;
use App\Models\AdminNotification;
use App\Models\AI\AiConversation;
use App\Models\BankCard;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Comment;
use App\Models\CustomerOrder;
use App\Models\File;
use App\Models\Group;
use App\Models\History;
use App\Models\Media;
use App\Models\Message;
use App\Models\MoneyTransfer;
use App\Models\Pricing;
use App\Models\PricingDescription;
use App\Models\Product;
use App\Models\Reaction;
use App\Models\Reason;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\SoftDeletes;
use Tests\TestCase;

class ApiModelsTest extends TestCase
{
    public function test_models_that_use_deleted_at_are_soft_deletable(): void
    {
        $models = [
            User::class,
            Role::class,
            Category::class,
            Reason::class,
            Pricing::class,
            PricingDescription::class,
            Media::class,
            Message::class,
            AdminNotification::class,
            AiConversation::class,
            AboutSubject::class,
            AboutTitle::class,
            AboutContent::class,
            File::class,
            Product::class,
            Group::class,
            Comment::class,
            MoneyTransfer::class,
            History::class,
            Reaction::class,
            BankCard::class,
            AboutDash::class,
            Cart::class,
            CustomerOrder::class,
        ];

        foreach ($models as $model) {
            $this->assertContains(SoftDeletes::class, class_uses_recursive($model));
        }
    }

    public function test_sensitive_attributes_are_hidden_on_serialization(): void
    {
        $user = (new User)->forceFill([
            'password' => 'secret',
            'api_token' => 'token',
            'firstname' => 'Tala',
        ]);

        $bankCard = (new BankCard)->forceFill([
            'card_name' => 'Main card',
            'card_number' => '4111111111111111',
            'cvv_code' => '123',
            'provider' => 'Visa',
        ]);

        $userArray = $user->toArray();
        $cardArray = $bankCard->toArray();

        $this->assertArrayNotHasKey('password', $userArray);
        $this->assertArrayNotHasKey('api_token', $userArray);
        $this->assertArrayNotHasKey('card_number', $cardArray);
        $this->assertArrayNotHasKey('cvv_code', $cardArray);
    }
}
