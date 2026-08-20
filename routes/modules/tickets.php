<?php

use App\Http\Controllers\Tickets\TicketController;
use Illuminate\Support\Facades\Route;

Route::get('tickets', [TicketController::class, 'index'])->name('tickets.index');
Route::post('tickets', [TicketController::class, 'store'])->name('tickets.store');
// Antes de tickets/{ticket}: senão "quadro" casaria como um id de ticket.
Route::get('tickets/quadro', [TicketController::class, 'board'])->name('tickets.board');
Route::get('tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
Route::post('tickets/{ticket}/resposta', [TicketController::class, 'reply'])->name('tickets.reply');
Route::put('tickets/{ticket}', [TicketController::class, 'update'])->name('tickets.update');
Route::delete('tickets/{ticket}', [TicketController::class, 'destroy'])->name('tickets.destroy');
