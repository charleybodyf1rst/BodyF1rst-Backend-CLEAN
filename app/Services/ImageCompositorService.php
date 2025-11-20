<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Helpers\S3Helper;

/**
 * Image Compositor Service
 * Edit and enhance real coach photos with:
 * - Background removal
 * - Logo overlay addition
 * - Person removal (inpainting)
 * - Artistic background composition
 *
 * @author BodyF1rst Development Team
 * @version 1.0.0
 */
class ImageCompositorService
{
    private $removeBgService;
    private $algorithmicArtService;
    private $replicateService;

    public function __construct()
    {
        $this->removeBgService = new RemoveBgService();
        $this->algorithmicArtService = new AlgorithmicArtService();
        $this->replicateService = new ReplicateService();
    }

    /**
     * Complete workflow: Process real coach photo with all enhancements
     *
     * @param string $imageUrl URL or path to original coach photo
     * @param array $options Processing options
     * @return array ['success' => bool, 'final_image_url' => string, 'steps' => array]
     */
    public function processCoachPhoto(string $imageUrl, array $options = []): array
    {
        $steps = [];

        try {
            Log::info('ImageCompositor: Starting coach photo processing', [
                'image' => $imageUrl,
                'options' => $options
            ]);

            // Step 1: Remove unwanted person if specified
            if ($options['remove_person'] ?? false) {
                $personRemoved = $this->removePersonFromPhoto($imageUrl, $options['person_mask'] ?? null);
                if ($personRemoved['success']) {
                    $imageUrl = $personRemoved['image_url'];
                    $steps['person_removed'] = $personRemoved['image_url'];
                }
            }

            // Step 2: Remove background
            $noBgResult = $this->removeBgService->removeBackground($imageUrl, [
                'type' => 'person',
                'size' => 'full',
            ]);

            if (!$noBgResult['success']) {
                return [
                    'success' => false,
                    'error' => 'Failed to remove background: ' . ($noBgResult['error'] ?? 'Unknown error'),
                ];
            }

            $steps['no_background'] = $noBgResult['image_url'];

            // Step 3: Generate algorithmic art background
            $backgroundResult = $this->algorithmicArtService->generateFlowingLines([
                'width' => $options['width'] ?? 768,
                'height' => $options['height'] ?? 1344,
                'style' => $options['background_style'] ?? 'flowing',
            ]);

            if (!$backgroundResult['success']) {
                return [
                    'success' => false,
                    'error' => 'Failed to generate background',
                ];
            }

            $steps['background'] = $backgroundResult['image_url'];

            // Step 4: Add BodyF1rst logo to shirt
            $withLogoResult = $this->addLogoToShirt(
                $noBgResult['image_url'],
                $options['logo_position'] ?? 'left-chest',
                $options['logo_size'] ?? 150
            );

            if (!$withLogoResult['success']) {
                Log::warning('Failed to add logo, continuing without it');
                $withLogoResult = $noBgResult; // Use image without logo
            } else {
                $steps['with_logo'] = $withLogoResult['image_url'];
            }

            // Step 5: Composite coach photo with artistic background
            $finalResult = $this->compositeImages(
                $withLogoResult['image_url'],
                $backgroundResult['image_url'],
                $options
            );

            if (!$finalResult['success']) {
                return [
                    'success' => false,
                    'error' => 'Failed to composite final image',
                ];
            }

            $steps['final'] = $finalResult['image_url'];

            Log::info('ImageCompositor: Processing completed successfully', [
                'final_url' => $finalResult['image_url']
            ]);

            return [
                'success' => true,
                'final_image_url' => $finalResult['image_url'],
                'steps' => $steps,
            ];

        } catch (\Exception $e) {
            Log::error('ImageCompositor processing error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'steps' => $steps,
            ];
        }
    }

    /**
     * Remove unwanted person from photo using AI inpainting
     *
     * @param string $imageUrl Original photo URL
     * @param array|null $mask Mask coordinates for person to remove
     * @return array Result with new image URL
     */
    public function removePersonFromPhoto(string $imageUrl, $mask = null): array
    {
        try {
            Log::info('ImageCompositor: Removing person from photo', ['image' => $imageUrl]);

            // Use Replicate's inpainting model to remove person
            // Model: stability-ai/stable-diffusion-inpainting
            $result = $this->replicateService->run(
                'stability-ai/stable-diffusion-inpainting',
                [
                    'image' => $imageUrl,
                    'prompt' => 'empty space, plain background',
                    'mask' => $mask,
                    'num_inference_steps' => 50,
                ]
            );

            if ($result && isset($result['output'])) {
                // Download and save the result
                $inpaintedImageUrl = is_array($result['output']) ? $result['output'][0] : $result['output'];

                $imageData = file_get_contents($inpaintedImageUrl);
                $filename = 'coaches/inpainted/' . uniqid('coach-inpainted-') . '.png';

                $s3Result = S3Helper::uploadFile($imageData, $filename, 'image/png');

                if ($s3Result['success']) {
                    return [
                        'success' => true,
                        'image_url' => $s3Result['url'],
                        'filename' => $filename,
                    ];
                }
            }

            return [
                'success' => false,
                'error' => 'Failed to remove person from photo',
            ];

        } catch (\Exception $e) {
            Log::error('Person removal error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Add BodyF1rst logo overlay to shirt area
     *
     * @param string $imageUrl Coach photo URL (preferably with no background)
     * @param string $position Logo position ('left-chest', 'center-chest', 'right-chest')
     * @param int $logoSize Logo width in pixels
     * @return array Result with new image URL
     */
    public function addLogoToShirt(string $imageUrl, string $position = 'left-chest', int $logoSize = 150): array
    {
        try {
            // Check if GD is available
            if (!extension_loaded('gd')) {
                Log::warning('GD extension not loaded, skipping logo overlay');
                return [
                    'success' => false,
                    'error' => 'GD extension not available',
                ];
            }

            // Download coach photo
            $coachImageData = file_get_contents($imageUrl);
            $coachImage = imagecreatefromstring($coachImageData);

            if (!$coachImage) {
                return [
                    'success' => false,
                    'error' => 'Failed to load coach image',
                ];
            }

            // Load BodyF1rst logo
            $logoPath = public_path('assets/images/logo-orange-black.png');
            if (!file_exists($logoPath)) {
                // Try alternative logo paths
                $logoPath = public_path('assets/images/logo.png');
                if (!file_exists($logoPath)) {
                    Log::warning('Logo file not found, skipping overlay');
                    imagedestroy($coachImage);
                    return [
                        'success' => false,
                        'error' => 'Logo file not found',
                    ];
                }
            }

            $logo = imagecreatefrompng($logoPath);
            if (!$logo) {
                imagedestroy($coachImage);
                return [
                    'success' => false,
                    'error' => 'Failed to load logo',
                ];
            }

            // Resize logo
            $logoWidth = $logoSize;
            $logoHeight = (int)(imagesy($logo) * ($logoSize / imagesx($logo)));

            $logoResized = imagecreatetruecolor($logoWidth, $logoHeight);
            imagealphablending($logoResized, false);
            imagesavealpha($logoResized, true);
            imagecopyresampled($logoResized, $logo, 0, 0, 0, 0, $logoWidth, $logoHeight, imagesx($logo), imagesy($logo));

            // Calculate logo position
            $imageWidth = imagesx($coachImage);
            $imageHeight = imagesy($coachImage);

            switch ($position) {
                case 'left-chest':
                    $x = (int)($imageWidth * 0.35); // Left chest area
                    $y = (int)($imageHeight * 0.30); // Upper chest
                    break;
                case 'center-chest':
                    $x = (int)(($imageWidth - $logoWidth) / 2);
                    $y = (int)($imageHeight * 0.35);
                    break;
                case 'right-chest':
                    $x = (int)($imageWidth * 0.55);
                    $y = (int)($imageHeight * 0.30);
                    break;
                default:
                    $x = (int)($imageWidth * 0.35);
                    $y = (int)($imageHeight * 0.30);
            }

            // Merge logo onto coach image
            imagecopy($coachImage, $logoResized, $x, $y, 0, 0, $logoWidth, $logoHeight);

            // Save to temp file
            $tempPath = storage_path('app/temp/coach-with-logo-' . uniqid() . '.png');
            if (!file_exists(storage_path('app/temp'))) {
                mkdir(storage_path('app/temp'), 0755, true);
            }

            imagepng($coachImage, $tempPath);
            imagedestroy($coachImage);
            imagedestroy($logo);
            imagedestroy($logoResized);

            // Upload to S3
            $filename = 'coaches/with-logo/' . uniqid('coach-logo-') . '.png';
            $s3Result = S3Helper::uploadFile(file_get_contents($tempPath), $filename, 'image/png');

            unlink($tempPath);

            if ($s3Result['success']) {
                return [
                    'success' => true,
                    'image_url' => $s3Result['url'],
                    'filename' => $filename,
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to upload logo overlay to S3',
            ];

        } catch (\Exception $e) {
            Log::error('Logo overlay error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Composite coach photo with algorithmic art background
     *
     * @param string $coachImageUrl Coach photo (no background, with logo)
     * @param string $backgroundUrl Algorithmic art background
     * @param array $options Composition options
     * @return array Result with final image URL
     */
    public function compositeImages(string $coachImageUrl, string $backgroundUrl, array $options = []): array
    {
        try {
            if (!extension_loaded('gd')) {
                return [
                    'success' => false,
                    'error' => 'GD extension not available',
                ];
            }

            // Load background
            $backgroundData = file_get_contents($backgroundUrl);
            $background = imagecreatefromstring($backgroundData);

            if (!$background) {
                return [
                    'success' => false,
                    'error' => 'Failed to load background',
                ];
            }

            // Load coach image
            $coachData = file_get_contents($coachImageUrl);
            $coach = imagecreatefromstring($coachData);

            if (!$coach) {
                imagedestroy($background);
                return [
                    'success' => false,
                    'error' => 'Failed to load coach image',
                ];
            }

            // Get dimensions
            $bgWidth = imagesx($background);
            $bgHeight = imagesy($background);
            $coachWidth = imagesx($coach);
            $coachHeight = imagesy($coach);

            // Resize background to match coach if needed
            if ($bgWidth != $coachWidth || $bgHeight != $coachHeight) {
                $backgroundResized = imagecreatetruecolor($coachWidth, $coachHeight);
                imagecopyresampled($backgroundResized, $background, 0, 0, 0, 0, $coachWidth, $coachHeight, $bgWidth, $bgHeight);
                imagedestroy($background);
                $background = $backgroundResized;
            }

            // Composite: place coach on top of background (preserving alpha)
            imagealphablending($background, true);
            imagecopy($background, $coach, 0, 0, 0, 0, $coachWidth, $coachHeight);

            // Save composite
            $tempPath = storage_path('app/temp/coach-final-' . uniqid() . '.png');
            imagepng($background, $tempPath, 9); // Max compression

            imagedestroy($background);
            imagedestroy($coach);

            // Upload to S3
            $filename = 'coaches/final/' . uniqid('coach-enhanced-') . '.png';
            $s3Result = S3Helper::uploadFile(file_get_contents($tempPath), $filename, 'image/png');

            unlink($tempPath);

            if ($s3Result['success']) {
                Log::info('ImageCompositor: Final composite created', [
                    'url' => $s3Result['url']
                ]);

                return [
                    'success' => true,
                    'image_url' => $s3Result['url'],
                    'filename' => $filename,
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to upload final composite to S3',
            ];

        } catch (\Exception $e) {
            Log::error('Image composition error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Batch process multiple coach photos
     *
     * @param array $images Array of ['name' => string, 'url' => string, 'options' => array]
     * @return array Results for each coach
     */
    public function batchProcessCoaches(array $images): array
    {
        $results = [];

        foreach ($images as $image) {
            $name = $image['name'] ?? 'Unknown';
            $url = $image['url'] ?? '';
            $options = $image['options'] ?? [];

            Log::info('Batch processing coach: ' . $name);

            $result = $this->processCoachPhoto($url, $options);
            $results[$name] = $result;

            // Delay between processing to avoid rate limits
            sleep(2);
        }

        return [
            'success' => true,
            'total' => count($images),
            'results' => $results,
        ];
    }
}
