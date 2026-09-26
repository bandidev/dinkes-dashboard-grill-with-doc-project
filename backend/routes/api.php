<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SubmissionController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/dashboard', DashboardController::class);

    Route::get('/regions', [CatalogController::class, 'regions']);
    Route::get('/reporting-years', [CatalogController::class, 'years']);
    Route::get('/reporting-years/{reportingYear}/tables', [CatalogController::class, 'tables']);
    Route::get('/reporting-tables/{reportingTable}', [CatalogController::class, 'table']);
    Route::get('/reporting-tables/{reportingTable}/worksheet', [SubmissionController::class, 'worksheet']);

    Route::get('/submissions', [SubmissionController::class, 'index']);
    Route::get('/submissions/{submission}', [SubmissionController::class, 'show']);

    Route::middleware('role:operator')->group(function () {
        Route::post('/submissions/draft', [SubmissionController::class, 'saveDraft']);
        Route::post('/submissions/{submission}/complete', [SubmissionController::class, 'complete']);
        Route::post('/submissions/{submission}/reopen', [SubmissionController::class, 'reopen']);
    });

    Route::middleware('role:administrator')->group(function () {
        Route::get('/reporting-tables/{reportingTable}/province', [SubmissionController::class, 'province']);
        Route::put('/reporting-tables/{reportingTable}/province-denominators', [SubmissionController::class, 'updateTableFiveProvinceDenominators']);
        Route::apiResource('users', UserController::class);
        Route::get('/reporting-years/{reportingYear}/catalog-import', [CatalogController::class, 'importReport']);
        Route::post('/regions', [CatalogController::class, 'storeRegion']);
        Route::put('/regions/{region}', [CatalogController::class, 'updateRegion']);
        Route::delete('/regions/{region}', [CatalogController::class, 'destroyRegion']);
        Route::post('/reporting-years', [CatalogController::class, 'storeYear']);
        Route::put('/reporting-years/{reportingYear}', [CatalogController::class, 'updateYear']);
        Route::delete('/reporting-years/{reportingYear}', [CatalogController::class, 'destroyYear']);
        Route::post('/reporting-tables', [CatalogController::class, 'storeTable']);
        Route::put('/reporting-tables/{reportingTable}/indicator-mapping', [CatalogController::class, 'mapIndicators']);
        Route::put('/reporting-tables/{reportingTable}', [CatalogController::class, 'updateTable']);
        Route::delete('/reporting-tables/{reportingTable}', [CatalogController::class, 'destroyTable']);
        Route::post('/indicators', [CatalogController::class, 'storeIndicator']);
        Route::put('/indicators/{indicator}', [CatalogController::class, 'updateIndicator']);
        Route::delete('/indicators/{indicator}', [CatalogController::class, 'destroyIndicator']);
        Route::post('/submissions/{submission}/verify', [SubmissionController::class, 'verify']);
        Route::post('/submissions/{submission}/unverify', [SubmissionController::class, 'unverify']);
    });
});
