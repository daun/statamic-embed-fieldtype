<?php

use Daun\StatamicEmbed\Http\Controllers\EmbedController;
use Illuminate\Support\Facades\Route;

Route::post('embed/info', [EmbedController::class, 'info'])->name('embed.info');
