<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function getMessages() {
        return response()->json(['status' => 200, 'data' => []]);
    }

    public function getMessageThread($id) {
        return response()->json(['status' => 200, 'data' => ['id' => $id]]);
    }

    public function sendMessage(Request $request) {
        return response()->json(['status' => 200, 'data' => ['id' => 1]]);
    }

    public function deleteMessage($id) {
        return response()->json(['status' => 200, 'message' => 'Message deleted']);
    }

    public function getUnreadMessages() {
        return response()->json(['status' => 200, 'data' => []]);
    }

    public function markAsRead($id) {
        return response()->json(['status' => 200, 'message' => 'Marked as read']);
    }
}