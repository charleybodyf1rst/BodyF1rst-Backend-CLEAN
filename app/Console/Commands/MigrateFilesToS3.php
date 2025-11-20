<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Services\S3FileService;
use App\Models\User;
use App\Models\Video;
use App\Models\IntroVideo;
use App\Models\Organization;

class MigrateFilesToS3 extends Command
{
    protected $signature = 'files:migrate-to-s3 {--dry-run : Show what would be migrated without actually doing it} {--batch-size=50 : Number of records to process at once}';
    protected $description = 'Migrate existing local files to S3 storage';

    protected $s3Service;
    protected $migratedCount = 0;
    protected $errorCount = 0;
    protected $skippedCount = 0;

    public function handle()
    {
        $this->s3Service = new S3FileService();
        $isDryRun = $this->option('dry-run');
        $batchSize = (int) $this->option('batch-size');

        $this->info('Starting file migration to S3...');
        
        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No files will actually be migrated');
        }

        // Migrate user profile images
        $this->migrateUserProfiles($isDryRun, $batchSize);

        // Migrate organization logos
        $this->migrateOrganizationLogos($isDryRun, $batchSize);

        // Migrate videos
        $this->migrateVideos($isDryRun, $batchSize);

        // Migrate intro videos
        $this->migrateIntroVideos($isDryRun, $batchSize);

        // Summary
        $this->info("\n=== Migration Summary ===");
        $this->info("Migrated: {$this->migratedCount}");
        $this->info("Errors: {$this->errorCount}");
        $this->info("Skipped: {$this->skippedCount}");

        if ($this->errorCount > 0) {
            $this->warn("Some files failed to migrate. Check the logs for details.");
        }

        if ($isDryRun) {
            $this->info("This was a dry run. Run without --dry-run to actually migrate files.");
        }

        return 0;
    }

    protected function migrateUserProfiles($isDryRun, $batchSize)
    {
        $this->info("\nMigrating user profile images...");
        
        User::whereNotNull('profile_image')
            ->where('profile_image', '!=', '')
            ->chunk($batchSize, function ($users) use ($isDryRun) {
                foreach ($users as $user) {
                    $this->migrateFile(
                        $user,
                        'profile_image',
                        'upload/user_profiles/',
                        'uploads/images/profiles/',
                        $isDryRun
                    );
                }
            });
    }

    protected function migrateOrganizationLogos($isDryRun, $batchSize)
    {
        $this->info("\nMigrating organization logos...");
        
        Organization::whereNotNull('logo')
            ->where('logo', '!=', '')
            ->chunk($batchSize, function ($organizations) use ($isDryRun) {
                foreach ($organizations as $organization) {
                    $this->migrateFile(
                        $organization,
                        'logo',
                        'upload/organization_profiles/',
                        'uploads/images/organizations/',
                        $isDryRun
                    );
                }
            });
    }

    protected function migrateVideos($isDryRun, $batchSize)
    {
        $this->info("\nMigrating videos and thumbnails...");
        
        Video::where(function($query) {
                $query->whereNotNull('video_file')
                      ->where('video_file', '!=', '')
                      ->orWhere(function($q) {
                          $q->whereNotNull('video_thumbnail')
                            ->where('video_thumbnail', '!=', '');
                      });
            })
            ->chunk($batchSize, function ($videos) use ($isDryRun) {
                foreach ($videos as $video) {
                    // Migrate video file
                    if ($video->video_file) {
                        $this->migrateFile(
                            $video,
                            'video_file',
                            'upload/videos/',
                            'uploads/videos/',
                            $isDryRun
                        );
                    }

                    // Migrate video thumbnail
                    if ($video->video_thumbnail) {
                        $this->migrateFile(
                            $video,
                            'video_thumbnail',
                            'upload/videos/thumbnails/',
                            'uploads/images/thumbnails/',
                            $isDryRun
                        );
                    }
                }
            });
    }

    protected function migrateIntroVideos($isDryRun, $batchSize)
    {
        $this->info("\nMigrating intro videos...");
        
        IntroVideo::where(function($query) {
                $query->whereNotNull('video')
                      ->where('video', '!=', '')
                      ->orWhere(function($q) {
                          $q->whereNotNull('video_thumbnail')
                            ->where('video_thumbnail', '!=', '');
                      });
            })
            ->chunk($batchSize, function ($videos) use ($isDryRun) {
                foreach ($videos as $video) {
                    // Migrate video file
                    if ($video->video) {
                        $this->migrateFile(
                            $video,
                            'video',
                            'upload/intro_videos/',
                            'uploads/videos/intro/',
                            $isDryRun
                        );
                    }

                    // Migrate video thumbnail
                    if ($video->video_thumbnail) {
                        $this->migrateFile(
                            $video,
                            'video_thumbnail',
                            'upload/intro_videos/thumbnails/',
                            'uploads/images/thumbnails/intro/',
                            $isDryRun
                        );
                    }
                }
            });
    }

    protected function migrateFile($model, $field, $localPath, $s3Path, $isDryRun)
    {
        $filename = $model->$field;
        
        // Skip if already an S3 URL
        if ($this->isS3Url($filename)) {
            $this->skippedCount++;
            return;
        }

        // Skip if already a full URL
        if (filter_var($filename, FILTER_VALIDATE_URL)) {
            $this->skippedCount++;
            return;
        }

        $localFilePath = public_path($localPath . $filename);
        
        // Check if local file exists
        if (!file_exists($localFilePath)) {
            $this->warn("Local file not found: {$localFilePath}");
            $this->errorCount++;
            return;
        }

        if ($isDryRun) {
            $this->line("Would migrate: {$localFilePath} -> {$s3Path}{$filename}");
            $this->migratedCount++;
            return;
        }

        try {
            // Read local file
            $fileContent = file_get_contents($localFilePath);
            
            // Upload to S3
            $s3Url = $this->s3Service->uploadContent($fileContent, $s3Path . $filename);
            
            if ($s3Url) {
                // Update database record
                $model->$field = $s3Url;
                $model->save();
                
                $this->line("✅ Migrated: {$filename} -> {$s3Url}");
                $this->migratedCount++;
                
                // Optionally delete local file (commented out for safety)
                // unlink($localFilePath);
                
            } else {
                $this->error("Failed to upload to S3: {$filename}");
                $this->errorCount++;
            }
            
        } catch (\Exception $e) {
            $this->error("Error migrating {$filename}: " . $e->getMessage());
            $this->errorCount++;
        }
    }

    protected function isS3Url($url)
    {
        return strpos($url, 's3.amazonaws.com') !== false || 
               strpos($url, '.s3.') !== false ||
               strpos($url, config('filesystems.disks.s3.url', '')) === 0;
    }
}
