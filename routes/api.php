<?php

use App\Http\Controllers\Api\User\WalletController;
use App\Http\Controllers\Api\Website\AdsController;
use App\Http\Controllers\Api\Website\ArticleCategoryController;
use App\Http\Controllers\Api\Website\ArticleCommentController;
use App\Http\Controllers\Api\Website\ArticleController;
use App\Http\Controllers\Api\Website\AuthController;
use App\Http\Controllers\Api\Website\BannerController;
use App\Http\Controllers\Api\Website\CartController;
use App\Http\Controllers\Api\Website\CategoryController;
use App\Http\Controllers\Api\Website\ContactUsController;
use App\Http\Controllers\Api\Website\CustomizationOptionsController;
use App\Http\Controllers\Api\Website\FaqController;
use App\Http\Controllers\Api\Website\FileController;
use App\Http\Controllers\Api\Website\FavoriteController;
use App\Http\Controllers\Api\Website\HomeController;
use App\Http\Controllers\Api\Website\InfoIpController;
use App\Http\Controllers\Api\Website\LanguageController;
use App\Http\Controllers\Api\Website\NowPaymentsIpnController;
use App\Http\Controllers\Api\Website\OrderController;
use App\Http\Controllers\Api\Website\ProductController;
use App\Http\Controllers\Api\Website\ReviewController;
use App\Http\Controllers\Api\Website\SettingController;
use App\Http\Controllers\Api\Website\SocialMediaController;
use App\Http\Controllers\Api\Website\StaticPagesController;
use App\Http\Controllers\Api\Website\UserAddressController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::middleware(['auth:sanctum', 'user.active'])->group(function () {
        Route::prefix('addresses')->group(function () {
            Route::get('/', [UserAddressController::class, 'index']);
            Route::get('/{id}', [UserAddressController::class, 'show']);
            Route::post('/', [UserAddressController::class, 'store']);
            Route::put('/{id}', [UserAddressController::class, 'update']);
            Route::delete('/{id}', [UserAddressController::class, 'destroy']);
        });
        Route::get('user/contact-us', [ContactUsController::class, 'index']);
        Route::get('user/contact-us/{id}', [ContactUsController::class, 'show']);
        Route::post('user/contact-us/{contact}/replies', [ContactUsController::class, 'reply']);

        Route::prefix('cart')->name('cart.')->group(function () {
            Route::get('/', [CartController::class, 'index'])->name('index');
            Route::post('/add', [CartController::class, 'add'])->name('add');
            Route::post('/upload-image', [CartController::class, 'uploadImage'])->middleware('throttle:file-upload')->name('upload-image');
            Route::put('/items/{cartItem}', [CartController::class, 'update'])->name('update');
            Route::delete('/items/{cartItem}', [CartController::class, 'remove'])->name('remove');
            Route::delete('/clear', [CartController::class, 'clear'])->name('clear');
        });
        // Backward-compatible upload alias used by older frontend builds.
        Route::post('/upload-image', [CartController::class, 'uploadImage'])->middleware('throttle:file-upload')->name('cart.upload-image.legacy');
        Route::post('/files/designs', [FileController::class, 'storeDesign'])->middleware('throttle:file-upload')->name('files.designs.store');
        Route::post('/file', [FileController::class, 'storeDesign'])->middleware('throttle:file-upload')->name('files.designs.store.legacy');
        Route::get('/files/{uploadedFile}/download', [FileController::class, 'download'])->name('files.download');

        Route::prefix('auth')->group(function () {
            Route::get('/profile', [AuthController::class, 'profile']);
            Route::post('/update-profile', [AuthController::class, 'updateProfile'])->name('auth.profile.update');
            Route::post('/profile/update', [AuthController::class, 'updateProfile'])->name('auth.profile.update.legacy');
            Route::post('/change-password', [AuthController::class, 'changePassword']);
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::post('/logout-all', [AuthController::class, 'logoutAll']);
            Route::post('/deactivate', [AuthController::class, 'deactivateAccount']);
        });

        Route::prefix('favorites')->group(function () {
            Route::get('/', [FavoriteController::class, 'index']);
            Route::post('/toggle', [FavoriteController::class, 'toggle']);
            Route::delete('/{productId}', [FavoriteController::class, 'destroy']);
        });

        Route::prefix('order')->name('order.')->group(function () {
            Route::get('/', [OrderController::class, 'index'])->name('index');
            Route::get('/payment-status', [OrderController::class, 'paymentStatus'])->name('payment-status');
            Route::post('/', [OrderController::class, 'store'])->name('store');
            Route::post('/cancel/{codeOrder}', [OrderController::class, 'cancelled'])->name('cancel');
            Route::get('/{orderID}', [OrderController::class, 'show'])->whereNumber('orderID')->name('show');
        });
        Route::get('trace/{codeOrder}', [OrderController::class, 'traceOrder'])->name('trace');

        Route::post('coupon/apply', [OrderController::class, 'applyCoupon']);

        // User wallet routes
        Route::prefix('user/wallet')->group(function () {
            Route::get('/', [WalletController::class, 'getBalance']);
            Route::get('/transactions', [WalletController::class, 'getTransactions']);
            Route::get('/banks', [WalletController::class, 'getBanks']);
            Route::post('/deposit', [WalletController::class, 'initiateDeposit'])->middleware('throttle:wallet-sensitive');
            Route::post('/withdraw', [WalletController::class, 'withdraw'])->middleware('throttle:wallet-sensitive');
            Route::post('/transfer', [WalletController::class, 'transfer'])->middleware('throttle:wallet-sensitive');
        });
    });

    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:user-login');
        Route::post('/social-login', [AuthController::class, 'socialLogin'])->middleware('throttle:social-login');
        Route::post('send-otp', [AuthController::class, 'sendOtp'])->middleware('throttle:otp-send');
        Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:otp-verify');
        Route::post('verify-otp', [AuthController::class, 'verifyOtp'])->middleware('throttle:otp-verify');
    });

    Route::get('home', [HomeController::class, 'index']);

    Route::get('social-media', [SocialMediaController::class, 'index']);

    Route::get('static-pages/{slug}', [StaticPagesController::class, 'index']);

    Route::get('faqs', [FaqController::class, 'index']);

    Route::get('ads', [AdsController::class, 'index']);

    Route::post('contact-us', [ContactUsController::class, 'store'])->middleware('throttle:contact-public')->name('contact.store');

    // تقييمات المنتجات
    Route::prefix('reviews')->group(function () {
        Route::get('/', [ReviewController::class, 'index']); // جميع التقييمات مع فلترة
        Route::get('/my-reviews', [ReviewController::class, 'myReviews'])->middleware(['auth:sanctum', 'user.active']);
        Route::get('/product/{productId}', [ReviewController::class, 'productReviews']);
        Route::get('/{id}', [ReviewController::class, 'show']);

        Route::middleware(['auth:sanctum', 'user.active'])->group(function () {
            Route::post('/', [ReviewController::class, 'store']);
            Route::put('/{id}', [ReviewController::class, 'update']);
            Route::delete('/{id}', [ReviewController::class, 'destroy']);
        });
    });

    Route::prefix('categories')->group(function () {
        Route::get('/', [CategoryController::class, 'index']);
        Route::get('/{id}', [CategoryController::class, 'show']);
    });

    Route::prefix('products')->group(function () {
        Route::get('/', [ProductController::class, 'index']);
        Route::get('/{id}', [ProductController::class, 'show']);
    });

    Route::get('/languages', [LanguageController::class, 'index']);
    Route::get('/banners', [BannerController::class, 'index']);
    Route::get('/payment-methods', [OrderController::class, 'paymentMethods']);

    Route::get('/customization-options', [CustomizationOptionsController::class, 'index'])->name('customization_options');


    Route::post('/paymob/webhook', [OrderController::class, 'webhook'])->middleware('throttle:payment-webhook')->name('paymob.webhook');
    Route::post('/nowpayments/ipn', NowPaymentsIpnController::class)->middleware('throttle:payment-webhook')->name('nowpayments.ipn');
    Route::get('/files/{uploadedFile}/preview', [FileController::class, 'preview'])->middleware('signed')->name('api.v1.files.preview');

    // مقالات
    Route::prefix('articles')->name('articles.')->group(function () {
        Route::get('/', [ArticleController::class, 'index'])->name('index');
        Route::get('/featured', [ArticleController::class, 'featured'])->name('featured');
        Route::get('/popular', [ArticleController::class, 'popular'])->name('popular');
        Route::post('/comments', [ArticleCommentController::class, 'store'])->middleware('throttle:contact-public')->name('comments.store');
        Route::get('/{article}/comments', [ArticleCommentController::class, 'index'])->whereNumber('article')->name('comments.index');
        Route::get('/{slug}/related', [ArticleController::class, 'related'])->where('slug', '[A-Za-z0-9\-_]+')->name('related');
        Route::get('/{slug}', [ArticleController::class, 'show'])->where('slug', '[A-Za-z0-9\-_]+')->name('show');
    });

    // أقسام المقالات
    Route::prefix('article-categories')->group(function () {
        Route::get('/', [ArticleCategoryController::class, 'index']);
        Route::get('/with-counts', [ArticleCategoryController::class, 'withCounts']);
        Route::get('/{slug}', [ArticleCategoryController::class, 'show']);
    });

    Route::get('/country-from-ip', [InfoIpController::class, 'getCountryAndCurrency']);
    Route::get('/setting', [SettingController::class, 'index']);
});
