<?php

use Illuminate\Support\Facades\Route;

// 관리자 전용 시스템 - 루트 접속은 관리 화면으로
Route::redirect('/', '/admin');
