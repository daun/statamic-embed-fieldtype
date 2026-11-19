<?php

use Daun\StatamicEmbed\Http\Controllers\ProvidersController;
use Illuminate\Support\Facades\Route;

Route::get('providers', ProvidersController::class)->name('embed.providers');
