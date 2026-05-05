<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Api\UserCategoryPermissionController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\BbpsBillAvenueController;
use App\Http\Controllers\BillerInformationController;
use App\Http\Controllers\BillFetchController;
use App\Http\Controllers\BillPaymentController;
use App\Http\Controllers\BillValidationController;
use App\Http\Controllers\Callback\PushRefundCallbackController;
use App\Http\Controllers\ComplaintRegisterTrackController;
use App\Http\Controllers\DepositController;
use App\Http\Controllers\PlanPullController;
use App\Http\Controllers\SchemeController;
use App\Http\Controllers\SelfMerchantOnboard\SelfMerchantOnboardController;
use App\Http\Controllers\TxnStatusController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VerifyOtp\VerifyMailMobileOtpController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/
Route::get('/hello', [BbpsBillAvenueController::class, 'helloFromBBPS']);

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
Route::prefix('user')->group(function () {
    Route::get('{userId}/categories', [UserCategoryPermissionController::class, 'getCategories']);
    Route::post('{userId}/categories', [UserCategoryPermissionController::class, 'updateCategories']);
    Route::post('{userId}/categories/remove', [UserCategoryPermissionController::class, 'removeCategory']);
});

// Email verification routes (protected, user must be logged in)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])->name('verification.send');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});

// Verify email (signed + throttled)
Route::get('/verify-email/{id}/{hash}', VerifyEmailController::class)
    ->middleware(['auth:sanctum', 'signed', 'throttle:6,1'])
    ->name('verification.verify');

Route::post('/register', [RegisteredUserController::class, 'store'])->name('register');
Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login');
Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');
Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.store');

// admin level apis
Route::middleware(['auth:sanctum'])->group(function () {
    // merchant
    Route::get('/get-merchants', [UserController::class, 'getMerchants']);
    Route::post('/onboard-merchant', [UserController::class, 'onboardMerchant']);
    Route::get('/show-merchant/{id}', [UserController::class, 'showMerchant']);
    Route::post('/update-merchant', [UserController::class, 'updateMerchant']);
    Route::post('/delete-merchant/{id}', [UserController::class, 'deleteMerchant']);
    Route::post('/update-user-statuses', [UserController::class, 'updateUserStatuses']);

    Route::get('/reports/user/{id}', [UserController::class, 'getUserSuccessfulReports']);
    Route::post('/add-money-to-merchant-wallet', [UserController::class, 'addMoneyToMerchantWallet']);
    // scheme
    Route::get('/get-schemes', [SchemeController::class, 'getSchemes']);
    Route::post('/create-scheme', [SchemeController::class, 'createScheme']);
    Route::get('/show-scheme/{id}', [SchemeController::class, 'editScheme']);
    Route::post('/update-scheme', [SchemeController::class, 'updateScheme']);
    Route::post('/delete-scheme/{id}', [SchemeController::class, 'deleteScheme']);
});

// self mechant onboard process
Route::post('/self-merchant-onboard-process', [SelfMerchantOnboardController::class, 'selfMerchantOnboardProcess']);
Route::post('/register-new-merchant', [SelfMerchantOnboardController::class, 'registerNewMerchant']);
Route::post('/onboard-user', [SelfMerchantOnboardController::class, 'onboardUser']);
//
Route::post('/send-mobile-otp', [VerifyMailMobileOtpController::class, 'sendMobileOtp']);
Route::post('/verify-mobile-otp', [VerifyMailMobileOtpController::class, 'verifyMobileOtp']);
Route::post('/send-mail-otp', [VerifyMailMobileOtpController::class, 'sendMailOtp']);
Route::post('/verify-mail-otp', [VerifyMailMobileOtpController::class, 'verifyMailOtp']);
Route::post('/flutter-test', [VerifyMailMobileOtpController::class, 'flutterTest']);
// bbps json apis
Route::middleware(['auth:sanctum'])->group(function () {
    // Testing
    Route::get('/get-billers-test/{category}', [BillerInformationController::class, 'getBillers']);
    // Production
    Route::get('/get-billers/{category}', [BillerInformationController::class, 'getBillersProd']);
    // Testing
    Route::get('/get-biller-name-and-category', [BillerInformationController::class, 'getBillerNameAndCategory']);
    // Testing
    Route::post('/bbps/sync-biller-info-test/json', [BillerInformationController::class, 'syncBillerInfo']);
    // Testing
    Route::post('/bbps/biller-info-test/json', [BillerInformationController::class, 'billerInfo']);
    // Production
    Route::post('/bbps/sync-biller-info/json', [BillerInformationController::class, 'syncBillerInfoProd']);
    Route::post('/bbps/biller-info/json', [BillerInformationController::class, 'billerInfoProd']);
    // Route::post('/bbps/bill-fetch/json', [BillFetchController::class, 'billFetch']);
    // Route::post('/bbps/bill-validation/json', [BillValidationController::class, 'billValidationApi']);
    // Testing
    Route::post('/bbps/plan-pull-test/json', [PlanPullController::class, 'planPull']);
    // Production
    Route::post('/bbps/plan-pull/json', [PlanPullController::class, 'planPullProd']);
    // Testing
    Route::post('/bbps/bill-process-test/json', [BillValidationController::class, 'billProcess']);
    // Production
    Route::post('/bbps/bill-process/json', [BillValidationController::class, 'billProcessProd']);
    // Testing
    Route::post('/bbps/bill-payment-test/json', [BillPaymentController::class, 'billPayment']);
    // Production
    Route::post('/bbps/bill-payment/json', [BillPaymentController::class, 'billPaymentProd']);
    // Testing
    Route::post('/bbps/get-txn-status-test', [TxnStatusController::class, 'transactionStatusTest']);
    // production
    Route::post('/bbps/get-txn-status', [TxnStatusController::class, 'transactionStatusProd']);
    // Testing
    Route::post('/bbps/complaint-register-test/json', [ComplaintRegisterTrackController::class, 'complaintRegistertest']);
    // production
    Route::post('/bbps/complaint-register/json', [ComplaintRegisterTrackController::class, 'complaintRegisterprod']);
    // Testing
    Route::post('/bbps/complaint-status-test/json', [ComplaintRegisterTrackController::class, 'complaintStatusTest']);
    // production
    Route::post('/bbps/complaint-status/json', [ComplaintRegisterTrackController::class, 'complaintStatusProd']);
    // Testing
    Route::post('/bbps/all-complaints-test/json', [ComplaintRegisterTrackController::class, 'allComplaintsdata']);
    // production
    Route::post('/bbps/all-complaints/json', [ComplaintRegisterTrackController::class, 'allComplaintsdataProd']);
    // Testing
    Route::post('/bbps/deposit-enquiry-test/json', [DepositController::class, 'depositEnquiryTest']);
    // production
    Route::post('/bbps/deposit-enquiry/json', [DepositController::class, 'depositEnquiryProd']);
    // Testing
    Route::any('/bbps/all-bill-payments-test/json', [BillPaymentController::class, 'allPaymentdata']);
    // production
    Route::any('/bbps/all-bill-payments/json', [BillPaymentController::class, 'allPaymentdataProd']);

    // Testing user
    Route::any('/bbps/user-bill-payments-test/json/{id}', [BillPaymentController::class, 'userPaymentdata']);
    // production user
    Route::any('/bbps/user-bill-payments/json/{id}', [BillPaymentController::class, 'userPaymentdataProd']);

    // push-refund-callback production user
    Route::post('/push-refund-notification', [PushRefundCallbackController::class, 'pushRefundNotification']);

});

// xml apis
// Route::post('/bbps/fetch-biller-info/xml', [BillerInformationController::class, 'fetchBillerInfo']);
Route::post('/bbps/fetch-biller-info/xml', [BbpsBillAvenueController::class, 'fetchBillerInfo']);
Route::post('/bbps/fetch-bill-detail/xml', [BillFetchController::class, 'fetchBillDetails']);
Route::post('/bbps/process-bill-payment/xml', [BillPaymentController::class, 'processBillPayment']);
Route::post('/bbps/bill-validation/xml', [BillValidationController::class, 'billValidation'])->name('bbps.billValidation.xml');
Route::post('/fetch-bill-details/xml', [BbpsBillAvenueController::class, 'fetchBillDetails'])->name('bill_details');

Route::get('/admin/dashboard-data', [AdminController::class, 'getDashboardData']);
