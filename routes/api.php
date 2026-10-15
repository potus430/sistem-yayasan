<?php

use App\Http\Controllers\SyncIngestController;
use Illuminate\Support\Facades\Route;

Route::post('/sync/{entity}', SyncIngestController::class)
    ->whereIn('entity', [
        'branch_incentives',
        'annual_incentive_assignments',
        'branch_positions',
        'employee_position_assignments',
        'students',
        'salaries',
        'attendances',
        'teacher_substitutions',
    ])
    ->middleware('throttle:60,1');
