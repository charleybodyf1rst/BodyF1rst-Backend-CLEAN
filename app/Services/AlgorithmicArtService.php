<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Helpers\S3Helper;

/**
 * Algorithmic Art Generation Service
 * Generates flowing particle system backgrounds for coach avatars
 *
 * Creates "Roaring Vectors" style backgrounds with:
 * - Flowing white lines on black background
 * - Particle system with motion trails
 * - Bezier curves for smooth organic movement
 * - High contrast minimalist aesthetic
 *
 * @author BodyF1rst Development Team
 * @version 1.0.0
 */
class AlgorithmicArtService
{
    private $width;
    private $height;
    private $particleCount;
    private $noiseScale;
    private $trailFade;
    private $maneIntensity;

    public function __construct()
    {
        // Default parameters (can be overridden)
        $this->width = 768;
        $this->height = 1344; // Full-body portrait orientation
        $this->particleCount = 150;
        $this->noiseScale = 150;
        $this->trailFade = 4;
        $this->maneIntensity = 3;
    }

    /**
     * Generate flowing white lines on black background
     * Main method for creating algorithmic art backgrounds
     *
     * @param array $options Customization options
     * @return array ['success' => bool, 'image_url' => string, 'filename' => string]
     */
    public function generateFlowingLines(array $options = []): array
    {
        try {
            // Override defaults with options
            $width = $options['width'] ?? $this->width;
            $height = $options['height'] ?? $this->height;
            $particleCount = $options['particle_count'] ?? $this->particleCount;
            $style = $options['style'] ?? 'flowing'; // 'flowing', 'waves', 'energy'

            Log::info('AlgorithmicArt: Generating background', [
                'width' => $width,
                'height' => $height,
                'particles' => $particleCount,
                'style' => $style,
            ]);

            // Create black background
            $image = imagecreatetruecolor($width, $height);
            $black = imagecolorallocate($image, 0, 0, 0);
            $white = imagecolorallocate($image, 255, 255, 255);
            $gray = imagecolorallocatealpha($image, 255, 255, 255, 90); // Semi-transparent white

            imagefill($image, 0, 0, $black);

            // Enable antialiasing for smooth lines
            imageantialias($image, true);

            // Generate art based on style
            switch ($style) {
                case 'flowing':
                    $this->generateFlowingParticles($image, $white, $gray, $width, $height, $particleCount);
                    break;
                case 'waves':
                    $this->generateWavePattern($image, $white, $width, $height);
                    break;
                case 'energy':
                    $this->generateEnergyBurst($image, $white, $gray, $width, $height, $particleCount);
                    break;
                default:
                    $this->generateFlowingParticles($image, $white, $gray, $width, $height, $particleCount);
            }

            // Save to temporary file
            $filename = 'coaches/backgrounds/algorithmic-' . uniqid() . '-' . $style . '.png';
            $tempPath = storage_path('app/temp/' . basename($filename));

            // Ensure temp directory exists
            if (!file_exists(storage_path('app/temp'))) {
                mkdir(storage_path('app/temp'), 0755, true);
            }

            imagepng($image, $tempPath, 9); // Max compression
            imagedestroy($image);

            // Upload to S3
            $s3Result = S3Helper::uploadFile(
                file_get_contents($tempPath),
                $filename,
                'image/png'
            );

            // Clean up temp file
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }

            if ($s3Result['success']) {
                Log::info('AlgorithmicArt: Background generated successfully', [
                    'filename' => $filename,
                    'url' => $s3Result['url'],
                ]);

                return [
                    'success' => true,
                    'image_url' => $s3Result['url'],
                    'filename' => $filename,
                    'style' => $style,
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to upload background to S3',
            ];
        } catch (\Exception $e) {
            Log::error('AlgorithmicArt generation error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Generate flowing particle trails (Roaring Vectors style)
     *
     * @param resource $image GD image resource
     * @param int $primaryColor Primary color resource
     * @param int $secondaryColor Secondary color resource
     * @param int $width Image width
     * @param int $height Image height
     * @param int $particleCount Number of particles
     */
    private function generateFlowingParticles($image, $primaryColor, $secondaryColor, $width, $height, $particleCount)
    {
        // Create multiple flow fields with different starting points
        for ($i = 0; $i < $particleCount; $i++) {
            $startX = rand(0, $width);
            $startY = rand(0, $height);

            // Random flow direction
            $angle = rand(0, 360) * (M_PI / 180);
            $flow = $this->generateFlowField($width, $height);

            // Draw flowing curve
            $this->drawFlowingCurve(
                $image,
                $primaryColor,
                $startX,
                $startY,
                $angle,
                $flow,
                $width,
                $height,
                rand(50, 200) // Length of flow
            );

            // Add smaller detail curves
            if (rand(0, 3) === 0) {
                $this->drawDetailCurve($image, $secondaryColor, $startX, $startY, $width, $height);
            }
        }
    }

    /**
     * Generate wave pattern
     *
     * @param resource $image GD image resource
     * @param int $color Color resource
     * @param int $width Image width
     * @param int $height Image height
     */
    private function generateWavePattern($image, $color, $width, $height)
    {
        $numWaves = 30;

        for ($wave = 0; $wave < $numWaves; $wave++) {
            $amplitude = rand(20, 100);
            $frequency = rand(1, 5) / 100;
            $phase = rand(0, 100);
            $yOffset = ($height / $numWaves) * $wave;

            $points = [];
            for ($x = 0; $x < $width; $x += 5) {
                $y = $yOffset + sin($x * $frequency + $phase) * $amplitude;
                $points[] = $x;
                $points[] = $y;
            }

            // Draw smooth curve through points
            if (count($points) >= 4) {
                imagesetthickness($image, rand(1, 3));
                $this->drawSmoothCurve($image, $color, $points);
            }
        }
    }

    /**
     * Generate energy burst pattern (radiating from center)
     *
     * @param resource $image GD image resource
     * @param int $primaryColor Primary color
     * @param int $secondaryColor Secondary color
     * @param int $width Image width
     * @param int $height Image height
     * @param int $particleCount Number of particles
     */
    private function generateEnergyBurst($image, $primaryColor, $secondaryColor, $width, $height, $particleCount)
    {
        $centerX = $width / 2;
        $centerY = $height / 2;

        for ($i = 0; $i < $particleCount; $i++) {
            $angle = ($i / $particleCount) * 2 * M_PI;
            $length = rand(100, max($width, $height) / 2);
            $curvature = rand(-50, 50);

            // Draw energy line from center outward
            $controlX = $centerX + cos($angle) * ($length / 2) + $curvature;
            $controlY = $centerY + sin($angle) * ($length / 2) + $curvature;
            $endX = $centerX + cos($angle) * $length;
            $endY = $centerY + sin($angle) * $length;

            $this->drawBezierCurve(
                $image,
                $primaryColor,
                $centerX,
                $centerY,
                $controlX,
                $controlY,
                $endX,
                $endY
            );
        }
    }

    /**
     * Draw a flowing curve following a flow field
     */
    private function drawFlowingCurve($image, $color, $startX, $startY, $angle, $flowField, $width, $height, $steps)
    {
        $x = $startX;
        $y = $startY;
        $prevX = $x;
        $prevY = $y;

        imagesetthickness($image, rand(1, 3));

        for ($step = 0; $step < $steps; $step++) {
            // Get flow direction from field
            $flowX = (int)($x / 10) % 50;
            $flowY = (int)($y / 10) % 50;
            $flowAngle = $flowField[$flowY][$flowX] ?? $angle;

            // Move particle
            $x += cos($flowAngle) * 3;
            $y += sin($flowAngle) * 3;

            // Wrap around edges
            if ($x < 0) $x = $width;
            if ($x > $width) $x = 0;
            if ($y < 0) $y = $height;
            if ($y > $height) $y = 0;

            // Draw line segment
            imageline($image, (int)$prevX, (int)$prevY, (int)$x, (int)$y, $color);

            $prevX = $x;
            $prevY = $y;
        }
    }

    /**
     * Generate a Perlin noise-like flow field
     */
    private function generateFlowField($width, $height): array
    {
        $field = [];
        $cols = 50;
        $rows = 50;

        for ($y = 0; $y < $rows; $y++) {
            $field[$y] = [];
            for ($x = 0; $x < $cols; $x++) {
                // Generate angle based on position (simplified Perlin noise)
                $angle = (sin($x * 0.1) + cos($y * 0.1)) * M_PI;
                $field[$y][$x] = $angle;
            }
        }

        return $field;
    }

    /**
     * Draw a Bezier curve
     */
    private function drawBezierCurve($image, $color, $x0, $y0, $x1, $y1, $x2, $y2)
    {
        $prevX = $x0;
        $prevY = $y0;

        for ($t = 0; $t <= 1; $t += 0.02) {
            $x = (1 - $t) * (1 - $t) * $x0 + 2 * (1 - $t) * $t * $x1 + $t * $t * $x2;
            $y = (1 - $t) * (1 - $t) * $y0 + 2 * (1 - $t) * $t * $y1 + $t * $t * $y2;

            imageline($image, (int)$prevX, (int)$prevY, (int)$x, (int)$y, $color);

            $prevX = $x;
            $prevY = $y;
        }
    }

    /**
     * Draw detail curves (smaller accents)
     */
    private function drawDetailCurve($image, $color, $startX, $startY, $width, $height)
    {
        $points = 10;
        $prevX = $startX;
        $prevY = $startY;

        imagesetthickness($image, 1);

        for ($i = 0; $i < $points; $i++) {
            $x = $prevX + rand(-20, 20);
            $y = $prevY + rand(-20, 20);

            imageline($image, (int)$prevX, (int)$prevY, (int)$x, (int)$y, $color);

            $prevX = $x;
            $prevY = $y;
        }
    }

    /**
     * Draw smooth curve through array of points
     */
    private function drawSmoothCurve($image, $color, $points)
    {
        $numPoints = count($points) / 2;

        for ($i = 0; $i < $numPoints - 1; $i++) {
            $x1 = $points[$i * 2];
            $y1 = $points[$i * 2 + 1];
            $x2 = $points[($i + 1) * 2];
            $y2 = $points[($i + 1) * 2 + 1];

            imageline($image, (int)$x1, (int)$y1, (int)$x2, (int)$y2, $color);
        }
    }

    /**
     * Generate background for specific coach (with unique seed)
     *
     * @param string $coachName Coach name for unique generation
     * @param array $options Additional options
     * @return array Result
     */
    public function generateCoachBackground(string $coachName, array $options = []): array
    {
        // Use coach name as seed for consistent generation
        $seed = crc32($coachName);
        mt_srand($seed);

        // Generate with consistent parameters for this coach
        return $this->generateFlowingLines(array_merge($options, [
            'style' => $options['style'] ?? 'flowing',
        ]));
    }

    /**
     * Generate multiple background variations
     *
     * @param int $count Number of variations to generate
     * @param array $options Options for generation
     * @return array Array of results
     */
    public function generateVariations(int $count = 5, array $options = []): array
    {
        $results = [];
        $styles = ['flowing', 'waves', 'energy'];

        for ($i = 0; $i < $count; $i++) {
            $style = $styles[$i % count($styles)];
            $result = $this->generateFlowingLines(array_merge($options, [
                'style' => $style,
            ]));

            $results[] = $result;

            // Small delay between generations
            usleep(500000); // 0.5 seconds
        }

        return [
            'success' => true,
            'count' => $count,
            'backgrounds' => $results,
        ];
    }
}
