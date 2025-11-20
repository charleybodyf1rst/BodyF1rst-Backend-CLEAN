<?php

namespace App\Http\Controllers\Coach;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MessageController extends Controller
{
    public function getMessages(Request $request)
    {
        try {
            $coachId = auth()->id();

            $messages = DB::table('coach_messages')
                ->where('coach_id', $coachId)
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get();

            return response()->json(['success' => true, 'data' => $messages]);
        } catch (\Exception $e) {
            return response()->json(['success' => true, 'data' => []]);
        }
    }

    public function sendMessage(Request $request, $clientId)
    {
        $validator = Validator::make($request->all(), [
            'message' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        try {
            $coachId = auth()->id();

            $messageId = DB::table('coach_messages')->insertGetId([
                'coach_id' => $coachId,
                'client_id' => $clientId,
                'message' => $request->message,
                'sent_by' => 'coach',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return response()->json(['success' => true, 'message' => 'Message sent successfully', 'data' => ['id' => $messageId]]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error sending message'], 500);
        }
    }
}
