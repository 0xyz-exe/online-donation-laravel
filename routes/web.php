<?php

use App\Http\Controllers\DonationController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DonationController::class, 'index'])->name('home');
Route::redirect('/dashboard', '/campaigns')->middleware('auth')->name('dashboard');
Route::get('/campaigns', [DonationController::class, 'index'])->name('campaigns.index');
Route::get('/campaigns/create', [DonationController::class, 'createCampaign'])->middleware('auth')->name('campaigns.create');
Route::get('/campaigns/mine', [DonationController::class, 'mine'])->middleware('auth')->name('campaigns.mine');
Route::get('/campaigns/{campaign:slug}', [DonationController::class, 'show'])->name('campaigns.show');
Route::middleware('auth')->group(function () {
    Route::post('/campaigns', [DonationController::class, 'saveCampaign'])->middleware('throttle:10,1')->name('campaigns.store');

    Route::get('/campaigns/{campaign}/edit', [DonationController::class, 'editCampaign'])->name('campaigns.edit');
    Route::put('/campaigns/{campaign}', [DonationController::class, 'saveCampaign'])->name('campaigns.update');
    Route::post('/campaigns/{campaign:slug}/donations', [DonationController::class, 'donate'])->middleware('throttle:10,1')->name('donations.store');
    Route::get('/donations/history', [DonationController::class, 'history'])->name('donations.history');
    Route::get('/donations/{donation}/receipt', [DonationController::class, 'receipt'])->name('donations.receipt');
    Route::get('/admin/donations', [DonationController::class, 'admin'])->name('admin.dashboard');
    Route::patch('/admin/campaigns/{campaign}', [DonationController::class, 'moderateCampaign'])->name('admin.campaigns.update');
    Route::patch('/admin/donations/{donation}', [DonationController::class, 'reviewDonation'])->name('admin.donations.update');
    Route::get('/admin/reports', [DonationController::class, 'report'])->name('admin.reports');
    Route::get('/admin/donations.csv', [DonationController::class, 'export'])->name('admin.donations.csv');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});
require __DIR__.'/auth.php';
