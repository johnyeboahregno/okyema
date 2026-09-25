<?php

declare(strict_types=1);

use App\Http\Controllers\Api\ActionController;
use App\Http\Controllers\Api\AgendaController;
use App\Http\Controllers\Api\ApprovalController;
use App\Http\Controllers\Api\AssistantController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AutomationController;
use App\Http\Controllers\Api\BriefingController;
use App\Http\Controllers\Api\ConnectorController;
use App\Http\Controllers\Api\ContextController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\InboxController;
use App\Http\Controllers\Api\MeetingController;
use App\Http\Controllers\Api\PeopleController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ReceiptController;
use App\Http\Controllers\Api\RecordingController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\TripController;
use App\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Okyema
|--------------------------------------------------------------------------
*/

// Public auth
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Public inbound webhook: AssemblyAI pushes the finished transcript here.
// Authenticated by a shared secret header, verified in the controller.
Route::post('/webhooks/assemblyai', [WebhookController::class, 'assemblyai']);

// Authenticated (every resource is strictly scoped to the signed-in user)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::patch('/profile', [ProfileController::class, 'update']);

    Route::post('/assistant', [AssistantController::class, 'ask']);

    Route::get('/dashboard', [DashboardController::class, 'index']);

    Route::get('/contexts', [ContextController::class, 'index']);
    Route::post('/contexts', [ContextController::class, 'store']);
    // Declared before the binding so "all" is never treated as an id.
    Route::post('/contexts/all/activate', [ContextController::class, 'activateAll']);
    Route::patch('/contexts/{context}', [ContextController::class, 'update']);
    Route::delete('/contexts/{context}', [ContextController::class, 'destroy']);
    Route::post('/contexts/{context}/activate', [ContextController::class, 'activate']);

    Route::get('/agenda', [AgendaController::class, 'agenda']);
    Route::get('/timeline', [AgendaController::class, 'timeline']);

    Route::get('/connectors', [ConnectorController::class, 'index']);
    Route::delete('/connectors/{account}', [ConnectorController::class, 'destroy']);

    Route::get('/meetings', [MeetingController::class, 'index']);
    Route::post('/meetings', [MeetingController::class, 'store']);
    Route::get('/meetings/{meeting}', [MeetingController::class, 'show']);
    Route::patch('/meetings/{meeting}', [MeetingController::class, 'update']);
    Route::delete('/meetings/{meeting}', [MeetingController::class, 'destroy']);
    Route::post('/meetings/{meeting}/notes', [MeetingController::class, 'addNote']);
    Route::post('/meetings/{meeting}/participants', [MeetingController::class, 'addParticipant']);
    Route::post('/meetings/{meeting}/generate', [MeetingController::class, 'generate']);

    Route::get('/actions', [ActionController::class, 'index']);
    Route::post('/actions', [ActionController::class, 'store']);
    Route::patch('/actions/{action}', [ActionController::class, 'update']);
    Route::delete('/actions/{action}', [ActionController::class, 'destroy']);
    Route::post('/actions/{action}/transition', [ActionController::class, 'transition']);
    Route::post('/decisions/{decision}/convert', [ActionController::class, 'convertDecision']);

    Route::get('/receipts', [ReceiptController::class, 'index']);
    Route::post('/receipts', [ReceiptController::class, 'store']);
    Route::post('/receipts/{receipt}/confirm', [ReceiptController::class, 'confirm']);

    Route::get('/expenses', [ExpenseController::class, 'index']);
    Route::get('/expenses/export', [ExpenseController::class, 'export']);

    Route::get('/inbox', [InboxController::class, 'index']);
    Route::get('/inbox/{conversation}', [InboxController::class, 'show']);
    Route::post('/inbox/{conversation}/draft', [InboxController::class, 'draft']);

    Route::get('/people', [PeopleController::class, 'index']);

    Route::post('/approvals/{approval}/approve', [ApprovalController::class, 'approve']);
    Route::post('/approvals/{approval}/reject', [ApprovalController::class, 'reject']);

    Route::post('/recordings', [RecordingController::class, 'store']);
    Route::get('/transcripts', [RecordingController::class, 'index']);
    Route::post('/transcripts', [RecordingController::class, 'storeTranscript']);
    Route::delete('/transcripts/{meeting}', [RecordingController::class, 'destroy']);

    Route::get('/trips', [TripController::class, 'index']);
    Route::post('/trips', [TripController::class, 'store']);
    Route::post('/trips/extract', [TripController::class, 'extract']);
    Route::post('/trips/itinerary', [TripController::class, 'saveItinerary']);

    Route::get('/documents', [DocumentController::class, 'index']);
    Route::post('/documents', [DocumentController::class, 'store']);

    Route::get('/search', [SearchController::class, 'search']);

    Route::get('/automations', [AutomationController::class, 'index']);
    Route::post('/automations', [AutomationController::class, 'store']);
    Route::post('/automations/{rule}/run', [AutomationController::class, 'run']);

    Route::get('/briefing/morning', [BriefingController::class, 'morning']);
    Route::get('/briefing/weekly', [BriefingController::class, 'weekly']);
});
