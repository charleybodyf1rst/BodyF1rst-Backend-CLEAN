<?php

use App\Http\Controllers\MessagingController;
use App\Http\Controllers\Admin\MessageModerationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Messaging API Routes
|--------------------------------------------------------------------------
|
| Enterprise-grade messaging system routes for BodyF1RST
| Includes: Private messaging, group chats, organization-wide messaging,
| content moderation, encryption, and admin controls
|
*/

// ============================================================================
// MESSAGING ROUTES (Authenticated Users, Coaches, Admins)
// ============================================================================

Route::middleware(['auth:sanctum'])->group(function () {

    // Conversation Management
    Route::prefix('messaging')->group(function () {

        // Get or create conversation
        Route::post('/conversations', [MessagingController::class, 'getOrCreateConversation']);

        // Get conversations list
        Route::get('/conversations', [MessagingController::class, 'getConversations']);

        // Get messages for a conversation
        Route::get('/conversations/{conversationId}/messages', [MessagingController::class, 'getMessages']);

        // Send message
        Route::post('/messages', [MessagingController::class, 'sendMessage']);

        // Edit message
        Route::put('/messages/{messageId}', [MessagingController::class, 'editMessage']);

        // Delete message (soft delete)
        Route::delete('/messages/{messageId}', [MessagingController::class, 'deleteMessage']);

        // Pin/Unpin message
        Route::post('/messages/{messageId}/toggle-pin', [MessagingController::class, 'togglePinMessage']);

        // Message Reactions
        Route::post('/messages/{messageId}/reactions', [MessagingController::class, 'addReaction']);
        Route::delete('/messages/{messageId}/reactions', [MessagingController::class, 'removeReaction']);

        // Message Search
        Route::get('/messages/search', [MessagingController::class, 'searchMessages']);

        // Typing Indicators
        Route::post('/typing', [MessagingController::class, 'updateTypingStatus']);

        // User Blocking
        Route::post('/block-user', [MessagingController::class, 'blockUser']);
        Route::delete('/blocked-users/{blockedUserId}', [MessagingController::class, 'unblockUser']);

        // Report Message
        Route::post('/messages/{messageId}/report', [MessagingController::class, 'reportMessage']);
    });
});

// ============================================================================
// ADMIN MODERATION ROUTES
// ============================================================================

Route::prefix('admin')->middleware(['auth:admin'])->group(function () {

    Route::prefix('moderation')->group(function () {

        // Get flagged messages
        Route::get('/flagged-messages', [MessageModerationController::class, 'getFlaggedMessages']);

        // Get single flagged message details
        Route::get('/flagged-messages/{flagId}', [MessageModerationController::class, 'getFlaggedMessageDetails']);

        // Review flagged message (dismiss, warn, delete, ban)
        Route::post('/flagged-messages/{flagId}/review', [MessageModerationController::class, 'reviewFlaggedMessage']);

        // Bulk review flags
        Route::post('/flagged-messages/bulk-review', [MessageModerationController::class, 'bulkReviewFlags']);

        // Get moderation statistics
        Route::get('/stats', [MessageModerationController::class, 'getModerationStats']);

        // Get reported users
        Route::get('/reported-users', [MessageModerationController::class, 'getReportedUsers']);

        // Ban/Unban users
        Route::post('/ban-user', [MessageModerationController::class, 'banUser']);
        Route::post('/unban-user', [MessageModerationController::class, 'unbanUser']);

        // Get moderation logs
        Route::get('/logs', [MessageModerationController::class, 'getModerationLogs']);
    });
});

// ============================================================================
// BACKWARDS COMPATIBILITY ROUTES (Existing Chat System)
// ============================================================================

// Keep existing chat routes for backwards compatibility
// These will be deprecated in favor of the new messaging system

Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/chat/send-message', [\App\Http\Controllers\ChatController::class, 'sendMessage']);
    Route::get('/chat/inbox', [\App\Http\Controllers\ChatController::class, 'getMyInboxChats']);
    Route::get('/chat/conversation', [\App\Http\Controllers\ChatController::class, 'getInboxChat']);
    Route::get('/chat/conversation/{id}', [\App\Http\Controllers\ChatController::class, 'getInboxChatbyID']);
});
