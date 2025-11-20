<?php

namespace App\Http\Controllers;

use App\Services\NutritionAdvisorService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class NutritionAdvisorController extends Controller
{
    private readonly NutritionAdvisorService $advisorService;

    public function __construct(NutritionAdvisorService $advisorService)
    {
        $this->advisorService = $advisorService;
    }

    /**
     * Create a new nutrition advisor thread
     */
    public function createThread(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'plainText' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $plainText = $request->input('plainText', false);
            $threadData = $this->advisorService->createThread($plainText);

            return response()->json([
                'success' => true,
                'data' => $threadData
            ]);

        } catch (\Exception $e) {
            Log::error('Error creating nutrition advisor thread', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create nutrition advisor thread'
            ], 500);
        }
    }

    /**
     * Add message to nutrition advisor thread
     */
    public function addMessage(Request $request, string $threadId): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'message' => 'required|string|max:5000',
                'inputSensors' => 'array',
                'inputSensors.*' => 'string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $message = $request->input('message');
            $inputSensors = $request->input('inputSensors', []);

            $responseData = $this->advisorService->addMessage($threadId, $message, $inputSensors);

            return response()->json([
                'success' => true,
                'data' => $responseData
            ]);

        } catch (\Exception $e) {
            Log::error('Error adding message to nutrition advisor thread', [
                'thread_id' => $threadId,
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to add message to thread'
            ], 500);
        }
    }

    /**
     * Execute vision tool for food recognition
     */
    public function executeVisionTool(Request $request, string $threadId, string $toolName): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'image' => 'required|file|mimes:jpeg,jpg,png,gif|max:10240', // 10MB max
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $imageFile = $request->file('image');
            $responseData = $this->advisorService->executeVisionTool($threadId, $toolName, $imageFile);

            return response()->json([
                'success' => true,
                'data' => $responseData
            ]);

        } catch (\Exception $e) {
            Log::error('Error executing vision tool', [
                'thread_id' => $threadId,
                'tool_name' => $toolName,
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to execute vision tool'
            ], 500);
        }
    }

    /**
     * Execute target tool on a message
     */
    public function executeTargetTool(Request $request, string $threadId, string $toolName): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'messageId' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $messageId = $request->input('messageId');
            $responseData = $this->advisorService->executeTargetTool($threadId, $toolName, $messageId);

            return response()->json([
                'success' => true,
                'data' => $responseData
            ]);

        } catch (\Exception $e) {
            Log::error('Error executing target tool', [
                'thread_id' => $threadId,
                'tool_name' => $toolName,
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to execute target tool'
            ], 500);
        }
    }

    /**
     * Fulfill a data request from the advisor
     */
    public function fulfillDataRequest(Request $request, string $threadId, string $messageId): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'data' => 'required',
                'runId' => 'required|string',
                'toolCallId' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $data = $request->input('data');
            $runId = $request->input('runId');
            $toolCallId = $request->input('toolCallId');

            $responseData = $this->advisorService->fulfillDataRequest($threadId, $messageId, $runId, $toolCallId, $data);

            return response()->json([
                'success' => true,
                'data' => $responseData
            ]);

        } catch (\Exception $e) {
            Log::error('Error fulfilling data request', [
                'thread_id' => $threadId,
                'message_id' => $messageId,
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fulfill data request'
            ], 500);
        }
    }

    /**
     * Get available nutrition advisor tools
     */
    public function getAvailableTools(): JsonResponse
    {
        try {
            $tools = $this->advisorService->getAvailableTools();

            return response()->json([
                'success' => true,
                'data' => $tools
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting available tools', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get available tools'
            ], 500);
        }
    }

    /**
     * Generate intelligence profile for personalized nutrition advice
     */
    public function generateIntelligenceProfile(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'content' => 'required|string|max:5000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $content = $request->input('content');
            $profileData = $this->advisorService->generateIntelligenceProfile($content);

            return response()->json([
                'success' => true,
                'data' => $profileData
            ]);

        } catch (\Exception $e) {
            Log::error('Error generating intelligence profile', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate intelligence profile'
            ], 500);
        }
    }
}
