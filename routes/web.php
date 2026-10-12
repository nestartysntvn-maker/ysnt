<?php

use App\Http\Controllers\AdminFileController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\MarketplaceAuthController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\RecruitmentController;
use App\Http\Controllers\WorkplaceController;
use App\Http\Middleware\WorkplaceGate;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\WebhookController;
use App\Http\Middleware\ShareLayout;
use Illuminate\Support\Facades\Route;

Route::middleware(ShareLayout::class)->group(function () {
    Route::get('/', [SiteController::class, 'home']);
    Route::get('/san-pham', [SiteController::class, 'listing']);
    Route::get('/danh-muc/{slug}', [SiteController::class, 'listing']);
    Route::get('/tim-kiem', [SiteController::class, 'listing'])->name('search');
    Route::get('/san-pham/{slug}', [SiteController::class, 'product']);
    Route::get('/gio-hang', [SiteController::class, 'cart']);
    Route::get('/thanh-toan', [SiteController::class, 'checkout']);
    Route::get('/dat-hang-thanh-cong', [SiteController::class, 'success']);
    Route::get('/tra-cuu-don', [SiteController::class, 'track'])->middleware('throttle:track');
    Route::get('/tham-quan', [SiteController::class, 'visit']);
    Route::get('/gioi-thieu', [SiteController::class, 'about']);
    Route::get('/lien-he', [SiteController::class, 'contact']);
    Route::get('/chinh-sach/{slug}', [SiteController::class, 'policy']);
    Route::get('/tuyen-dung', [RecruitmentController::class, 'index']);
    Route::get('/ho-so-nhan-vien', [RecruitmentController::class, 'onboardForm']);
});
Route::get('/sitemap.xml', [SiteController::class, 'sitemap']);
Route::get('/api/variants', [SiteController::class, 'variants'])->middleware('throttle:variants');
Route::get('/api/ref/{code}', [ShopController::class, 'refInfo'])->middleware('throttle:ref');
Route::post('/api/orders', [OrderController::class, 'store'])->middleware('throttle:orders');

// web chat (AI advisor / staff)
Route::get('/api/chat', [ChatController::class, 'poll'])->middleware('throttle:chat-poll');
Route::post('/api/chat', [ChatController::class, 'send'])->middleware('throttle:chat-send');
Route::post('/api/chat/lien-he', [ChatController::class, 'contact'])->middleware('throttle:chat-contact');

// inbound messages; CSRF is skipped for webhooks/* in bootstrap/app.php, signatures are checked instead
Route::post('/webhooks/zalo', [WebhookController::class, 'zalo'])->middleware('throttle:webhooks');
Route::get('/webhooks/meta', [WebhookController::class, 'metaVerify']);
Route::post('/webhooks/meta', [WebhookController::class, 'meta'])->middleware('throttle:webhooks');

// marketplace OAuth ("Cấp quyền gian hàng") and private admin files
Route::get('/ket-noi-san/{channel}/cap-quyen', [MarketplaceAuthController::class, 'start'])->whereNumber('channel');
Route::get('/ket-noi-san/callback/{platform}', [MarketplaceAuthController::class, 'callback'])->whereIn('platform', ['tiktok', 'shopee', 'lazada']);
Route::get('/quan-tri/tai-file/{key}', [AdminFileController::class, 'show']);

Route::middleware('web')->get('/pos-bill/{order}', function (\App\Models\Order $order) {
    $u = auth()->user();
    if (! $u) return redirect('/admin/login');
    abort_unless($u->canAccessPanel(\Filament\Facades\Filament::getPanel('admin')), 403);
    $order->load('items');
    return view('pos.bill', ['o' => $order, 'staff' => \App\Models\User::find($order->created_by)?->name ?? '—']);
})->name('pos.bill');

// recruitment (public) + new-hire profile portal
Route::post('/tuyen-dung', [RecruitmentController::class, 'apply'])->middleware('throttle:apply');
Route::post('/ho-so-nhan-vien', [RecruitmentController::class, 'onboard'])->middleware('throttle:onboard');

// staff Workplace: own "staff" guard, never /admin
Route::prefix('noi-bo')->group(function () {
    Route::get('/dang-nhap', [WorkplaceController::class, 'loginForm']);
    Route::post('/dang-nhap', [WorkplaceController::class, 'login'])->middleware('throttle:wp-login');
    Route::post('/otp/gui', [WorkplaceController::class, 'otpSend'])->middleware('throttle:wp-otp-send');
    Route::post('/otp/xac-nhan', [WorkplaceController::class, 'otpVerify'])->middleware('throttle:wp-otp-verify');
    Route::middleware(WorkplaceGate::class)->group(function () {
        Route::post('/dang-xuat', [WorkplaceController::class, 'logout']);
        Route::get('/', [WorkplaceController::class, 'home']);
        Route::post('/thong-bao/{recipient}/da-doc', [WorkplaceController::class, 'markRead']);
        Route::get('/quy-trinh', [WorkplaceController::class, 'docs']);
        Route::get('/quy-trinh/{doc}', [WorkplaceController::class, 'docShow'])->whereNumber('doc');
        Route::get('/quy-trinh/{doc}/tep', [WorkplaceController::class, 'docFile'])->whereNumber('doc');
        Route::get('/hoi-ai', [WorkplaceController::class, 'ask']);
        Route::post('/hoi-ai', [WorkplaceController::class, 'askPost']);
        Route::post('/hoi-ai/{question}/danh-gia', [WorkplaceController::class, 'rate']);
        Route::get('/danh-ba', [WorkplaceController::class, 'directory']);
        Route::get('/viec', [WorkplaceController::class, 'tasks']);
        Route::post('/viec/giao', [WorkplaceController::class, 'taskGive'])->middleware('throttle:wp-refer');
        Route::get('/viec/{task}', [WorkplaceController::class, 'taskShow'])->whereNumber('task');
        Route::post('/viec/{task}/trang-thai', [WorkplaceController::class, 'taskStatus'])->whereNumber('task');
        Route::post('/viec/{task}/binh-luan', [WorkplaceController::class, 'taskComment'])->whereNumber('task');
        Route::get('/tuyen-dung', [WorkplaceController::class, 'jobs']);
        Route::post('/tuyen-dung', [WorkplaceController::class, 'refer'])->middleware('throttle:wp-refer');
        Route::get('/toi', [WorkplaceController::class, 'profile']);
        Route::post('/toi', [WorkplaceController::class, 'profileSave']);
        Route::post('/doi-mat-khau', [WorkplaceController::class, 'password'])->middleware('throttle:wp-pass');
    });
});

// private HR files (CV, ID card, degrees) for admin roles
Route::get('/quan-tri/cv/{application}', [AdminFileController::class, 'cv'])->whereNumber('application');
Route::get('/quan-tri/ho-so/{employee}/{kind}/{i?}', [AdminFileController::class, 'hrFile'])->whereNumber('employee')->whereIn('kind', ['id_front', 'id_back', 'degrees', 'health']);
