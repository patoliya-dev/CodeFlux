<?php

use App\Http\Controllers\AttendanceController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;

Route::get('/', function () {
    return redirect()->route('users.create');
});

Route::get('/register', [UserController::class, 'create'])->name('users.create');
Route::post('/register', [UserController::class, 'register'])->name('users.register');

Route::get('/attandance', [AttendanceController::class, 'index'])->name('attendance');
Route::get('/attandance/multiple', [AttendanceController::class, 'multipleAttendance'])->name('attendance.multiple');
Route::post('/attandance/recognize', [AttendanceController::class, 'recognize'])->name('attendance.recognize');
Route::post('/attandance/multiple/recognize', [AttendanceController::class, 'multipleRecognize'])->name('attendance.multiple-recognize');
Route::match(['get', 'post'], '/attandance/report', [AttendanceController::class, 'report'])->name('attendance.report');
