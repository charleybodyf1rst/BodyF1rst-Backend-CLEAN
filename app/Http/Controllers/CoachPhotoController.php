<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\ImageCompositorService;
use App\Services\RemoveBgService;
use App\Services\AlgorithmicArtService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Coach Photo Processing Controller
 * Handles uploading and processing real coach photos with AI enhancements
 *
 * @author BodyF1rst Development Team
 * @version 1.0.0
 */
class CoachPhotoController extends Controller
{
    private $compositorService;
    private $removeBgService;
    private $artService;

    public function __construct()
    {
        $this->compositorService = new ImageCompositorService();
        $this->removeBgService = new RemoveBgService();
        $this->artService = new AlgorithmicArtService();
    }

    /**
     * Process a single coach photo with all enhancements
     *
     * POST /api/coach-photos/process
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function processPhoto(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'image' => 'required|string', // URL or base64
            'coach_name' => 'required|string',
            'remove_person' => 'boolean',
            'logo_position' => 'string|in:left-chest,center-chest,right-chest',
            'background_style' => 'string|in:flowing,waves,energy',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 400);
        }

        try {
            $imageUrl = $request->input('image');
            $coachName = $request->input('coach_name');

            Log::info('Processing coach photo', ['coach' => $coachName]);

            $options = [
                'remove_person' => $request->boolean('remove_person'),
                'logo_position' => $request->input('logo_position', 'left-chest'),
                'logo_size' => $request->input('logo_size', 150),
                'background_style' => $request->input('background_style', 'flowing'),
                'width' => $request->input('width', 768),
                'height' => $request->input('height', 1344),
            ];

            $result = $this->compositorService->processCoachPhoto($imageUrl, $options);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Coach photo processed successfully',
                    'coach_name' => $coachName,
                    'final_image_url' => $result['final_image_url'],
                    'processing_steps' => $result['steps'],
                ]);
            }

            return response()->json([
                'success' => false,
                'error' => $result['error'] ?? 'Processing failed',
            ], 500);

        } catch (\Exception $e) {
            Log::error('Coach photo processing error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Process all 5 coach photos in batch
     *
     * POST /api/coach-photos/process-batch
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function processBatch(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'coaches' => 'required|array|min:1',
            'coaches.*.name' => 'required|string',
            'coaches.*.image_url' => 'required|string',
            'coaches.*.remove_person' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 400);
        }

        try {
            $coaches = $request->input('coaches');

            // Format for batch processing
            $images = [];
            foreach ($coaches as $coach) {
                $images[] = [
                    'name' => $coach['name'],
                    'url' => $coach['image_url'],
                    'options' => [
                        'remove_person' => $coach['remove_person'] ?? false,
                        'logo_position' => $coach['logo_position'] ?? 'left-chest',
                        'background_style' => $coach['background_style'] ?? 'flowing',
                    ],
                ];
            }

            $result = $this->compositorService->batchProcessCoaches($images);

            return response()->json([
                'success' => true,
                'message' => 'Batch processing completed',
                'total_processed' => $result['total'],
                'results' => $result['results'],
            ]);

        } catch (\Exception $e) {
            Log::error('Batch processing error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upload and process coach photo
     *
     * POST /api/coach-photos/upload-and-process
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function uploadAndProcess(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'photo' => 'required|image|max:10240', // Max 10MB
            'coach_name' => 'required|string',
            'remove_person' => 'boolean',
            'logo_position' => 'string',
            'background_style' => 'string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 400);
        }

        try {
            // Upload original photo to S3
            $file = $request->file('photo');
            $filename = 'coaches/original/' . uniqid('coach-') . '.' . $file->getClientOriginalExtension();

            $uploadResult = \App\Helpers\S3Helper::uploadImage($file, $filename);

            if (!$uploadResult['success']) {
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to upload photo',
                ], 500);
            }

            $imageUrl = $uploadResult['url'];

            // Process the uploaded photo
            $options = [
                'remove_person' => $request->boolean('remove_person'),
                'logo_position' => $request->input('logo_position', 'left-chest'),
                'background_style' => $request->input('background_style', 'flowing'),
            ];

            $result = $this->compositorService->processCoachPhoto($imageUrl, $options);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Photo uploaded and processed successfully',
                    'coach_name' => $request->input('coach_name'),
                    'original_url' => $imageUrl,
                    'final_image_url' => $result['final_image_url'],
                    'processing_steps' => $result['steps'],
                ]);
            }

            return response()->json([
                'success' => false,
                'error' => $result['error'] ?? 'Processing failed',
            ], 500);

        } catch (\Exception $e) {
            Log::error('Upload and process error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove background only
     *
     * POST /api/coach-photos/remove-background
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function removeBackground(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'image_url' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 400);
        }

        try {
            $result = $this->removeBgService->removeBackground($request->input('image_url'));

            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate algorithmic art background only
     *
     * POST /api/coach-photos/generate-background
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function generateBackground(Request $request)
    {
        try {
            $options = [
                'width' => $request->input('width', 768),
                'height' => $request->input('height', 1344),
                'style' => $request->input('style', 'flowing'),
                'particle_count' => $request->input('particle_count', 150),
            ];

            $result = $this->artService->generateFlowingLines($options);

            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Add logo overlay only
     *
     * POST /api/coach-photos/add-logo
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function addLogo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'image_url' => 'required|string',
            'position' => 'string|in:left-chest,center-chest,right-chest',
            'size' => 'integer|min:50|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 400);
        }

        try {
            $result = $this->compositorService->addLogoToShirt(
                $request->input('image_url'),
                $request->input('position', 'left-chest'),
                $request->input('size', 150)
            );

            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Composite two images (coach + background)
     *
     * POST /api/coach-photos/composite
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function composite(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'coach_image_url' => 'required|string',
            'background_url' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 400);
        }

        try {
            $result = $this->compositorService->compositeImages(
                $request->input('coach_image_url'),
                $request->input('background_url')
            );

            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get credits remaining for Remove.bg API
     *
     * GET /api/coach-photos/credits
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCredits()
    {
        try {
            $result = $this->removeBgService->getAccountInfo();

            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
