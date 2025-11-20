<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\AIMLAnimationService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

/**
 * Avatar Animation Controller
 * Handles animating coach avatars using AIML/ByteDance OmniHuman API
 *
 * @author BodyF1rst Development Team
 * @version 1.0.0
 */
class AvatarAnimationController extends Controller
{
    private $aimlService;

    public function __construct()
    {
        $this->aimlService = new AIMLAnimationService();
    }

    /**
     * Animate a single avatar using OmniHuman 1.5
     *
     * POST /api/avatar-animation/animate
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function animateAvatar(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'image_url' => 'required|string|url',
            'audio_url' => 'required|string|url',
            'name' => 'string',
            'upload_to_s3' => 'boolean',
            's3_path' => 'string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 400);
        }

        try {
            $imageUrl = $request->input('image_url');
            $audioUrl = $request->input('audio_url');
            $name = $request->input('name', 'Avatar');

            Log::info('Animating avatar via AIML OmniHuman', [
                'name' => $name,
                'image_url' => $imageUrl,
            ]);

            $options = [
                'upload_to_s3' => $request->boolean('upload_to_s3', false),
                's3_path' => $request->input('s3_path', 'avatars/animated'),
            ];

            $result = $this->aimlService->animateAvatar($imageUrl, $audioUrl, $options);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Avatar animated successfully',
                    'name' => $name,
                    'video_url' => $result['video']['url'] ?? null,
                    's3_url' => $result['s3_url'] ?? null,
                    'duration' => $result['video']['duration'] ?? null,
                    'generation_id' => $result['generation_id'] ?? null,
                    'meta' => $result['meta'] ?? null,
                ]);
            }

            return response()->json([
                'success' => false,
                'error' => $result['error'] ?? 'Animation failed',
            ], 500);
        } catch (\Exception $e) {
            Log::error('Avatar animation error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upload and animate from file upload
     *
     * POST /api/avatar-animation/upload-and-animate
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function uploadAndAnimate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'image' => 'required|file|image|max:10240', // Max 10MB
            'audio' => 'required|file|mimes:mp3,wav,m4a|max:10240',
            'name' => 'string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 400);
        }

        try {
            $name = $request->input('name', 'Avatar');

            // Upload image to S3
            $imagePath = $request->file('image')->store('avatars/original', 's3');
            $imageUrl = Storage::disk('s3')->url($imagePath);

            // Upload audio to S3
            $audioPath = $request->file('audio')->store('avatars/audio', 's3');
            $audioUrl = Storage::disk('s3')->url($audioPath);

            Log::info('Uploaded files for animation', [
                'name' => $name,
                'image_url' => $imageUrl,
                'audio_url' => $audioUrl,
            ]);

            // Animate avatar
            $result = $this->aimlService->animateAvatar($imageUrl, $audioUrl, [
                'upload_to_s3' => true,
                's3_path' => 'avatars/animated',
            ]);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Avatar uploaded and animated successfully',
                    'name' => $name,
                    'image_url' => $imageUrl,
                    'audio_url' => $audioUrl,
                    'video_url' => $result['video']['url'] ?? null,
                    's3_url' => $result['s3_url'] ?? null,
                    'duration' => $result['video']['duration'] ?? null,
                ]);
            }

            return response()->json([
                'success' => false,
                'error' => $result['error'] ?? 'Animation failed',
            ], 500);
        } catch (\Exception $e) {
            Log::error('Upload and animate error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Batch animate multiple avatars
     *
     * POST /api/avatar-animation/batch
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function batchAnimate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'avatars' => 'required|array|min:1',
            'avatars.*.name' => 'required|string',
            'avatars.*.image_url' => 'required|string|url',
            'avatars.*.audio_url' => 'required|string|url',
            'upload_to_s3' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 400);
        }

        try {
            $avatars = $request->input('avatars');
            $uploadToS3 = $request->boolean('upload_to_s3', false);

            Log::info('Batch animating avatars', ['count' => count($avatars)]);

            $result = $this->aimlService->batchAnimateAvatars($avatars, [
                'upload_to_s3' => $uploadToS3,
                's3_path' => 'avatars/animated',
            ]);

            return response()->json([
                'success' => $result['success'],
                'message' => 'Batch animation completed',
                'total' => $result['total'],
                'successful' => $result['successful'],
                'failed' => $result['failed'],
                'results' => $result['results'],
            ]);
        } catch (\Exception $e) {
            Log::error('Batch animation error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Animate with Seedance (Image-to-Video)
     *
     * POST /api/avatar-animation/seedance
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function animateWithSeedance(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'image_url' => 'required|string|url',
            'prompt' => 'required|string',
            'resolution' => 'string|in:480p,720p',
            'duration' => 'integer|in:5,10',
            'upload_to_s3' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 400);
        }

        try {
            $imageUrl = $request->input('image_url');
            $prompt = $request->input('prompt');

            $options = [
                'resolution' => $request->input('resolution', '720p'),
                'duration' => $request->input('duration', 5),
                'upload_to_s3' => $request->boolean('upload_to_s3', false),
                's3_path' => 'avatars/animated',
            ];

            if ($request->has('seed')) {
                $options['seed'] = $request->input('seed');
            }

            if ($request->has('camera_fixed')) {
                $options['camera_fixed'] = $request->boolean('camera_fixed');
            }

            Log::info('Animating with Seedance', [
                'image_url' => $imageUrl,
                'prompt' => $prompt,
            ]);

            $result = $this->aimlService->animateWithSeedance($imageUrl, $prompt, $options);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Avatar animated with Seedance successfully',
                    'video_url' => $result['video']['url'] ?? null,
                    's3_url' => $result['s3_url'] ?? null,
                    'duration' => $result['video']['duration'] ?? null,
                    'meta' => $result['meta'] ?? null,
                ]);
            }

            return response()->json([
                'success' => false,
                'error' => $result['error'] ?? 'Seedance animation failed',
            ], 500);
        } catch (\Exception $e) {
            Log::error('Seedance animation error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check generation status
     *
     * GET /api/avatar-animation/status/{generationId}
     *
     * @param string $generationId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStatus($generationId)
    {
        try {
            $result = $this->aimlService->getGenerationStatus($generationId);

            return response()->json([
                'success' => $result['success'],
                'status' => $result['status'] ?? 'unknown',
                'video' => $result['video'] ?? null,
                'error' => $result['error'] ?? null,
                'meta' => $result['meta'] ?? null,
            ]);
        } catch (\Exception $e) {
            Log::error('Status check error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get AIML API credits/usage
     *
     * GET /api/avatar-animation/credits
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCredits()
    {
        try {
            $result = $this->aimlService->getCredits();

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List animation presets for common use cases
     *
     * GET /api/avatar-animation/presets
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPresets()
    {
        return response()->json([
            'success' => true,
            'presets' => [
                [
                    'id' => 'welcome',
                    'name' => 'Welcome Message',
                    'description' => 'Coach welcoming new members',
                    'suggested_audio' => 'Audio script: "Welcome to BodyF1rst! I\'m excited to help you on your fitness journey."',
                ],
                [
                    'id' => 'workout_demo',
                    'name' => 'Workout Demonstration',
                    'description' => 'Coach demonstrating exercise form',
                    'suggested_audio' => 'Audio script: Exercise instructions with form cues',
                ],
                [
                    'id' => 'motivation',
                    'name' => 'Motivational Message',
                    'description' => 'Coach providing encouragement',
                    'suggested_audio' => 'Audio script: "You\'ve got this! Remember why you started."',
                ],
                [
                    'id' => 'nutrition_tip',
                    'name' => 'Nutrition Tip',
                    'description' => 'Coach sharing nutrition advice',
                    'suggested_audio' => 'Audio script: Nutrition guidance and tips',
                ],
                [
                    'id' => 'check_in',
                    'name' => 'Weekly Check-in',
                    'description' => 'Coach checking progress',
                    'suggested_audio' => 'Audio script: "How has your week been? Let\'s review your progress."',
                ],
            ],
        ]);
    }
}
