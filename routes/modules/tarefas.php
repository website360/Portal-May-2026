<?php

use App\Http\Controllers\Tasks\TaskController;
use Illuminate\Support\Facades\Route;

Route::get('tarefas', [TaskController::class, 'index'])->name('tarefas.index');
Route::post('tarefas', [TaskController::class, 'store'])->name('tarefas.store');
Route::put('tarefas/{tarefa}', [TaskController::class, 'update'])->name('tarefas.update');
Route::delete('tarefas/{tarefa}', [TaskController::class, 'destroy'])->name('tarefas.destroy');

// Troca rápida de situação, usada pelo seletor inline das listagens.
Route::patch('tarefas/{tarefa}/situacao', [TaskController::class, 'updateStatus'])->name('tarefas.status');
