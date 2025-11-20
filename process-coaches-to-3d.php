<?php

/**
 * Process 3 Coach Photos to 3D Animated Avatars
 *
 * Photos provided:
 * 1. Charley (remove second person, remove bg, add logo, add algorithmic bg)
 * 2. Ken (gym photo, remove bg, add logo, add algorithmic bg)
 * 3. Annie (red shirt, remove bg, add logo, add algorithmic bg)
 *
 * Then convert to 3D GLB models with animations for mobile app
 */

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\Log;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Services
$compositorService = new App\Services\ImageCompositorService();
$removeBgService = new App\Services\RemoveBgService();
$artService = new App\Services\AlgorithmicArtService();

echo "====================================\n";
echo "3D Coach Avatar Generation Pipeline\n";
echo "====================================\n\n";

// Coach configuration
$coaches = [
    [
        'name' => 'Charley Blanchard',
        'image_path' => __DIR__ . '/storage/app/coaches/original/charley-original.jpg',
        'remove_person' => true, // Remove the second person (right side)
        'logo_position' => 'left-chest',
        'background_style' => 'flowing',
    ],
    [
        'name' => 'Ken Laney',
        'image_path' => __DIR__ . '/storage/app/coaches/original/ken-original.jpg',
        'remove_person' => false,
        'logo_position' => 'left-chest',
        'background_style' => 'waves',
    ],
    [
        'name' => 'Annie',
        'image_path' => __DIR__ . '/storage/app/coaches/original/annie-original.jpg',
        'remove_person' => false,
        'logo_position' => 'center-chest',
        'background_style' => 'energy',
    ],
];

$processedPhotos = [];

foreach ($coaches as $coach) {
    echo "\n📸 Processing: {$coach['name']}\n";
    echo str_repeat('-', 50) . "\n";

    // Check if image exists
    if (!file_exists($coach['image_path'])) {
        echo "❌ Image not found: {$coach['image_path']}\n";
        echo "   Please save the photo to this location first.\n";
        continue;
    }

    echo "✓ Image found: " . basename($coach['image_path']) . "\n";

    // Step 1: Upload to S3 for processing
    echo "📤 Uploading to S3...\n";
    $s3Path = 'coaches/original/' . strtolower(str_replace(' ', '-', $coach['name'])) . '.jpg';
    Storage::disk('s3')->put($s3Path, file_get_contents($coach['image_path']), 'public');
    $imageUrl = Storage::disk('s3')->url($s3Path);
    echo "✓ Uploaded: $imageUrl\n";

    // Step 2: Process photo (remove bg, remove person if needed, add logo, add background)
    echo "🎨 Processing photo with AI enhancements...\n";

    $result = $compositorService->processCoachPhoto($imageUrl, [
        'remove_person' => $coach['remove_person'],
        'logo_position' => $coach['logo_position'],
        'background_style' => $coach['background_style'],
        'width' => 768,
        'height' => 1344,
    ]);

    if (!$result['success']) {
        echo "❌ Processing failed: " . ($result['error'] ?? 'Unknown error') . "\n";
        continue;
    }

    echo "✓ Photo processed successfully!\n";
    echo "  - Background removed: " . ($result['steps']['no_background'] ?? 'N/A') . "\n";

    if ($coach['remove_person']) {
        echo "  - Person removed: " . ($result['steps']['person_removed'] ?? 'N/A') . "\n";
    }

    echo "  - Logo added: " . ($result['steps']['with_logo'] ?? 'N/A') . "\n";
    echo "  - Background: " . ($result['steps']['background'] ?? 'N/A') . "\n";
    echo "  - Final: " . ($result['final_image_url'] ?? 'N/A') . "\n";

    $processedPhotos[$coach['name']] = [
        'final_url' => $result['final_image_url'],
        'steps' => $result['steps'],
    ];
}

echo "\n\n====================================\n";
echo "Processed Photos Summary\n";
echo "====================================\n\n";

foreach ($processedPhotos as $name => $data) {
    echo "✅ $name\n";
    echo "   Final URL: {$data['final_url']}\n\n";
}

// Step 3: Convert to 3D GLB models
echo "\n====================================\n";
echo "Generating 3D GLB Models (Tripo3D)\n";
echo "====================================\n\n";

// Check if Tripo3D API key is configured
$tripoApiKey = env('TRIPO_API_KEY');
if (!$tripoApiKey) {
    echo "❌ TRIPO_API_KEY not found in .env file\n";
    echo "   Please add: TRIPO_API_KEY=your_api_key_here\n";
    exit(1);
}

$glbModels = [];

foreach ($processedPhotos as $name => $data) {
    echo "\n🎲 Creating 3D model for: $name\n";
    echo str_repeat('-', 50) . "\n";

    // Call Tripo3D API to convert image to 3D GLB
    $response = Http::withHeaders([
        'Authorization' => 'Bearer ' . $tripoApiKey,
    ])->post('https://api.tripo3d.ai/v2/openapi/task', [
        'type' => 'image_to_model',
        'file' => [
            'type' => 'url',
            'file_url' => $data['final_url'],
        ],
        'model_version' => 'default',
    ]);

    if ($response->successful()) {
        $taskData = $response->json();
        $taskId = $taskData['data']['task_id'] ?? null;

        if ($taskId) {
            echo "✓ Task created: $taskId\n";
            echo "⏳ Waiting for 3D model generation (30-60 seconds)...\n";

            // Poll for completion
            $maxAttempts = 30;
            $attempt = 0;

            while ($attempt < $maxAttempts) {
                sleep(10);
                $attempt++;

                $statusResponse = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $tripoApiKey,
                ])->get("https://api.tripo3d.ai/v2/openapi/task/{$taskId}");

                if ($statusResponse->successful()) {
                    $statusData = $statusResponse->json();
                    $status = $statusData['data']['status'] ?? 'unknown';

                    echo "  [$attempt/$maxAttempts] Status: $status\n";

                    if ($status === 'success') {
                        $output = $statusData['data']['output'] ?? [];
                        $modelUrl = $output['pbr_model'] ?? $output['model'] ?? null;

                        if ($modelUrl) {
                            echo "✅ 3D model generated!\n";
                            echo "   URL: $modelUrl\n";

                            // Download and save GLB file
                            $glbContent = file_get_contents($modelUrl);
                            $glbFilename = strtolower(str_replace(' ', '-', $name)) . '.glb';
                            $glbPath = 'coaches/3d-models/' . $glbFilename;

                            Storage::disk('s3')->put($glbPath, $glbContent, 'public');
                            $glbS3Url = Storage::disk('s3')->url($glbPath);

                            echo "✓ Saved to S3: $glbS3Url\n";

                            // Also save locally
                            file_put_contents(
                                __DIR__ . '/storage/app/coaches/3d-models/' . $glbFilename,
                                $glbContent
                            );

                            $glbModels[$name] = [
                                'url' => $glbS3Url,
                                'filename' => $glbFilename,
                            ];

                            break;
                        }
                    } elseif (in_array($status, ['failed', 'error'])) {
                        echo "❌ 3D generation failed: " . ($statusData['data']['error'] ?? 'Unknown error') . "\n";
                        break;
                    }
                }
            }
        }
    } else {
        echo "❌ Failed to create 3D task: " . $response->body() . "\n";
    }
}

echo "\n\n====================================\n";
echo "3D Models Generated\n";
echo "====================================\n\n";

foreach ($glbModels as $name => $model) {
    echo "✅ $name\n";
    echo "   GLB File: {$model['filename']}\n";
    echo "   S3 URL: {$model['url']}\n\n";
}

// Step 4: Create mobile app integration file
echo "\n====================================\n";
echo "Creating Mobile App Integration\n";
echo "====================================\n\n";

$mobileConfig = [
    'coaches' => [],
];

foreach ($glbModels as $name => $model) {
    $slug = strtolower(str_replace(' ', '-', $name));
    $mobileConfig['coaches'][] = [
        'id' => $slug,
        'name' => $name,
        'model_url' => $model['url'],
        'filename' => $model['filename'],
        'thumbnail' => $processedPhotos[$name]['final_url'] ?? null,
        'upgrade_system' => [
            'base_points' => 0,
            'max_points' => 10000,
            'animations' => [
                'idle' => true,
                'breathing' => true,
                'celebration' => true,
            ],
        ],
    ];
}

$configJson = json_encode($mobileConfig, JSON_PRETTY_PRINT);
file_put_contents(__DIR__ . '/storage/app/coaches/mobile-config.json', $configJson);

echo "✓ Mobile config created: mobile-config.json\n";
echo "\nConfig:\n";
echo $configJson . "\n";

echo "\n====================================\n";
echo "✨ ALL DONE! ✨\n";
echo "====================================\n\n";

echo "Next steps:\n";
echo "1. Copy GLB files to mobile app: storage/app/coaches/3d-models/\n";
echo "2. Integrate mobile-config.json into your React Native app\n";
echo "3. Use Three.js or React Three Fiber to display GLB models\n";
echo "4. Add idle animations (breathing, slight movement)\n";
echo "5. Implement body points upgrade system\n\n";
