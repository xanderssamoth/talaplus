<?php

use App\Http\Controllers\Api\AboutContentController;
use App\Http\Controllers\Api\AboutDashController;
use App\Http\Controllers\Api\AboutSubjectController;
use App\Http\Controllers\Api\AboutTitleController;
use App\Http\Controllers\Api\AI\AIController;
use App\Http\Controllers\Api\AI\AiConversationController;
use App\Http\Controllers\Api\AI\AiMessageController;
use App\Http\Controllers\Api\AI\AiMessageFileController;
use App\Http\Controllers\Api\AI\AiToolCallController;
use App\Http\Controllers\Api\BankCardController;
use App\Http\Controllers\Api\BlockedUserController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\CustomerOrderController;
use App\Http\Controllers\Api\FileController;
use App\Http\Controllers\Api\GroupController;
use App\Http\Controllers\Api\HashtagController;
use App\Http\Controllers\Api\HistoryController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\MediaProgressController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\MoneyTransferController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PricingController;
use App\Http\Controllers\Api\PricingDescriptionController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\PromoCodeController;
use App\Http\Controllers\Api\ReactionController;
use App\Http\Controllers\Api\ReasonController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::middleware('auth:sanctum')->post('ai/chat', [AIController::class, 'chat']);

    Route::middleware('auth:sanctum')->post('chat/token', [AIController::class, 'chat']);

    Route::post('user/login', [UserController::class, 'login']);
    Route::post('user/entrepreneurs', [UserController::class, 'entrepreneurs']);
    Route::get('user/username/{username}', [UserController::class, 'findByUsername']);
    Route::get('user/search/by-word', [UserController::class, 'search']);
    Route::get('user/{user}/belongs-to', [UserController::class, 'hasBelongsTo']);
    Route::patch('user/{user}/child-lock-code', [UserController::class, 'switchChildLockCode']);
    Route::get('user/{user}/watchlist', [UserController::class, 'userWatchlist']);
    Route::post('user/{user}/watchlist/{media}', [UserController::class, 'addToWatchlist']);
    Route::delete('user/{user}/watchlist/{media}', [UserController::class, 'removeFromWatchlist']);
    Route::patch('user/{user}/status', [UserController::class, 'updateStatus']);
    Route::patch('user/{user}/password', [UserController::class, 'updatePassword']);
    Route::patch('user/{user}/type', [UserController::class, 'updateType']);
    Route::patch('user/{user}/role', [UserController::class, 'updateRole']);
    Route::patch('user/{user}/avatar', [UserController::class, 'updateAvatar']);
    Route::post('user/{user}/file', [UserController::class, 'storeFiles']);

    Route::post('password-reset/find-user', [PasswordResetController::class, 'findUser']);
    Route::post('password-reset/check-token', [PasswordResetController::class, 'checkToken']);

    Route::get('media/popular/list', [MediaController::class, 'popularMedias']);
    Route::get('media/filter/list', [MediaController::class, 'filterMedias']);
    Route::get('media/belongs-to/{belongsTo}', [MediaController::class, 'findByBelongsTo']);
    Route::post('media/progress', [MediaController::class, 'mediaProgress']);
    Route::patch('media/{media}/publish', [MediaController::class, 'publishMedia']);
    Route::post('media/{media}/share', [MediaController::class, 'share']);
    Route::get('media/{media}/view', [MediaController::class, 'mediaViews']);
    Route::get('media/{media}/play', [MediaController::class, 'mediaPlays']);
    Route::get('media/{media}/like', [MediaController::class, 'mediaLikes']);
    Route::get('media/{media}/gift', [MediaController::class, 'mediaGifts']);
    Route::post('media/{media}/like', [MediaController::class, 'like']);
    Route::post('media/{media}/gift', [MediaController::class, 'gift']);
    Route::post('media/{media}/report/{user}', [MediaController::class, 'report']);

    Route::get('product/popular/list', [ProductController::class, 'popularProducts']);
    Route::get('product/promoted/list', [ProductController::class, 'promotedProducts']);
    Route::get('product/filter/list', [ProductController::class, 'filterProducts']);
    Route::patch('product/{product}/publish', [ProductController::class, 'publishProduct']);
    Route::post('product/{product}/share', [ProductController::class, 'share']);
    Route::get('product/{product}/view', [ProductController::class, 'productViews']);
    Route::get('product/{product}/star', [ProductController::class, 'productStars']);
    Route::post('product/{product}/rate', [ProductController::class, 'rate']);
    Route::post('product/{product}/report/{user}', [ProductController::class, 'report']);

    Route::post('cart/add', [CartController::class, 'addToCart']);
    Route::delete('cart/remove', [CartController::class, 'removeFromCart']);
    Route::get('cart/is-in-cart', [CartController::class, 'isInCart']);

    Route::get('comment/news-feed', [CommentController::class, 'newsFeed']);
    Route::post('comment/{comment}/share', [CommentController::class, 'share']);
    Route::get('comment/{comment}/like', [CommentController::class, 'commentLikes']);
    Route::post('comment/{comment}/like', [CommentController::class, 'like']);

    Route::get('message/search/by-word', [MessageController::class, 'search']);
    Route::get('message/conversation', [MessageController::class, 'conversations']);
    Route::get('message/conversation/users', [MessageController::class, 'userConversation']);
    Route::get('message/conversation/group', [MessageController::class, 'groupConversation']);

    Route::get('category/for-type/{forType}', [CategoryController::class, 'findByForType']);
    Route::get('hashtag/{hashtag}/entities', [HashtagController::class, 'entities']);
    Route::get('notification/user/{user}', [NotificationController::class, 'userNotifications']);
    Route::get('notification/user/{user}/unread', [NotificationController::class, 'unreadUserNotifications']);
    Route::patch('notification/{notification}/read', [NotificationController::class, 'markAsRead']);
    Route::patch('notification/user/{user}/read', [NotificationController::class, 'markAllAsRead']);
    Route::get('subscription/is-follower', [SubscriptionController::class, 'isFollower']);
    Route::delete('subscription/unfollow', [SubscriptionController::class, 'unfollow']);
    Route::get('subscription/user/{user}/subscriptions', [SubscriptionController::class, 'userSubscriptions']);
    Route::get('subscription/user/{user}/followers', [SubscriptionController::class, 'userFollowers']);
    Route::get('subscription/user/{user}/connections', [SubscriptionController::class, 'userConnections']);

    Route::apiResource('user', UserController::class);
    Route::apiResource('role', RoleController::class);
    Route::apiResource('category', CategoryController::class);
    Route::apiResource('reason', ReasonController::class);
    Route::apiResource('pricing', PricingController::class);
    Route::apiResource('pricing-description', PricingDescriptionController::class);
    Route::apiResource('media', MediaController::class);
    Route::apiResource('media-progress', MediaProgressController::class);
    Route::apiResource('product', ProductController::class);
    Route::apiResource('group', GroupController::class);
    Route::apiResource('message', MessageController::class);
    Route::apiResource('ai-conversation', AiConversationController::class);
    Route::apiResource('ai-message', AiMessageController::class);
    Route::apiResource('ai-message-file', AiMessageFileController::class);
    Route::apiResource('ai-tool-call', AiToolCallController::class);
    Route::apiResource('file', FileController::class);
    Route::apiResource('notification', NotificationController::class);
    Route::apiResource('promo-code', PromoCodeController::class);
    Route::apiResource('about-subject', AboutSubjectController::class);
    Route::apiResource('about-title', AboutTitleController::class);
    Route::apiResource('about-content', AboutContentController::class);
    Route::apiResource('about-dash', AboutDashController::class);
    Route::apiResource('blocked-user', BlockedUserController::class);
    Route::apiResource('money-transfer', MoneyTransferController::class);
    Route::apiResource('history', HistoryController::class);
    Route::apiResource('subscription', SubscriptionController::class);
    Route::apiResource('report', ReportController::class);
    Route::apiResource('hashtag', HashtagController::class);
    Route::apiResource('reaction', ReactionController::class);
    Route::apiResource('bank-card', BankCardController::class);
    Route::apiResource('cart', CartController::class);
    Route::apiResource('customer-order', CustomerOrderController::class);
    Route::apiResource('comment', CommentController::class);
    Route::apiResource('payment', PaymentController::class);
});
