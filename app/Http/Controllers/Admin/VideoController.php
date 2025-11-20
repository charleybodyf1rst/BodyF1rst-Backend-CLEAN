<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\Helper;
use App\Helpers\Pagination;
use App\Http\Controllers\Controller;
use App\Models\Tag;
use App\Models\Video;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class VideoController extends Controller
{
    protected $role;
    protected $userId;

    public function addVideo(Request $request)
    {
        $role = $request->role;
        $userId = Auth::guard(strtolower($role))->id();
        $validator = Validator::make($request->all(), [
            'video_title' => 'string|max:255',
            'video_file' => 'required_without:video_url',
            'video_thumbnail' => 'required_with:video_file',
            'video_url' => 'required_without:video_file',
            'video_duration' => 'required_with:video_file',
            'video_format' => 'required_with:video_file',
            'tags' => 'array'
        ]);

        if ($validator->fails()) {
            $response = [
                "status" => 422,
                "message" => $validator->errors()->first(),
                "errors" => $validator->errors(),
            ];
            return response($response, $response["status"]);
        }
        $video = Video::create($request->toArray());
        if($request->filled('video_format'))
        {
            if($request->video_format == "file")
            {
                $video->video_url = null;
            }
            else if($request->video_format == "url"){
                $video->video_file = null;
                $video->video_duration = null;
            }
        }
        if ($request->hasFile('video_file') && $request->video_format == 'file') {
            $filename   = time() . rand(111, 699) . '.' . $request->video_file->getClientOriginalExtension();
            $file = Helper::uploadedImage("upload/videos/", $filename, $request->video_file);
            $video->video_file = $file;
        }
        if ($request->has('video_thumbnail')) {
            $videoThumbnail = $request->video_thumbnail;

            if (preg_match("/^data:image\/(\w+);base64,/", $videoThumbnail, $matches)) {
                $extension = $matches[1];
                $imageData = base64_decode(preg_replace("/^data:image\/\w+;base64,/", '', $videoThumbnail));

                $tempFile = tmpfile();
                $tempPath = stream_get_meta_data($tempFile)['uri'];
                file_put_contents($tempPath, $imageData);

                $fileObject = new \Illuminate\Http\File($tempPath);

                $filename = time() . rand(111, 699) . '.' . $extension;

                $file = Helper::uploadedImage("upload/videos/thumbnails/", $filename, $fileObject);

                $video->video_thumbnail = $file;

                fclose($tempFile);
            } else {
                if ($request->file('video_thumbnail')) {
                    $fileObject = $request->file('video_thumbnail');
                    $filename = time() . rand(111, 699) . '.' . $fileObject->getClientOriginalExtension();
                    $file = Helper::uploadedImage("upload/videos/thumbnails/", $filename, $fileObject);

                    $video->video_thumbnail = $file;
                } else {
                    $video->video_thumbnail = $request->video_thumbnail == '' ? null : $request->video_thumbnail;
                }
            }
        }


        if ($request->filled('tags')) {
            foreach ($request->tags as $tag) {
                // $normalizedTag = str_replace(' ', '', ucwords(strtolower($tag)));
                $normalizedTag = $tag;

                $existingTag = Tag::where('tag', $normalizedTag)->where('type', 'Video')->first();

                if (!$existingTag) {
                    Tag::create([
                        'tag' => $normalizedTag,
                        'type' => 'Video'
                    ]);
                }
            }
        }
        $video->uploaded_by = $userId;
        $video->uploader = $role;
        $video->type = $request->type ?? "Public";
        $video->is_active = 1;
        $video->save();
        $video->load('upload_by:id,first_name,last_name,profile_image');
        Helper::createActionLog($userId,$role,'videos','add',null,$video);
        $response = [
            "status" => 200,
            "message" => "Video Added Successfully",
            "video" => $video
        ];
        return response($response, $response["status"]);
    }

    public function cloneVideo(Request $request,$id = null)
    {
        $role = $request->role;
        $userId = Auth::guard(strtolower($role))->id();
        $video = Video::find($id);

        if (isset($video))
        {
            $existingClone = Video::where('parent_id',$video->id)->where('uploaded_by',$userId)->where('uploader',$role)->first();
            if(isset($existingClone))
            {
                $response = [
                    "status" => 422,
                    "message" => "Already Cloned!",
                ];
                return response($response, $response["status"]);
            }

            // if ($video->video_format == "file") {
            //     $videoFile = basename($video->video_file);
            //     $videoFilename = time() . rand(111, 699) . '.' . pathinfo($video->video_file, PATHINFO_EXTENSION);

            //     $originalVideoPath = public_path('upload/videos/' . $videoFile);
            //     $newVideoPath = public_path('upload/videos/' . $videoFilename);

            //     $clonedVideoFile = null;

            //     if (file_exists($originalVideoPath) && copy($originalVideoPath, $newVideoPath)) {
            //         $clonedVideoFile = $videoFilename;
            //     }
            // }

            // $thumbnailFile = basename($video->video_thumbnail);
            // $thumbnailFilename = time() . rand(111, 699) . '_thumb.' . pathinfo($video->video_thumbnail, PATHINFO_EXTENSION);

            // $originalThumbnailPath = public_path('upload/videos/thumbnails/' . $thumbnailFile);
            // $newThumbnailPath = public_path('upload/videos/thumbnails/' . $thumbnailFilename);

            // $clonedThumbnailFile = null;

            // if (file_exists($originalThumbnailPath) && copy($originalThumbnailPath, $newThumbnailPath)) {
            //     $clonedThumbnailFile = $thumbnailFilename;
            // }
            $copy = $video->replicate()->fill(
                [
                    'video_title' => $video->video_title . " - Copy"
                ]
            );
            // $copy->video_file = $clonedVideoFile ?? null;
            // $copy->video_thumbnail = $clonedThumbnailFile ?? null;
            $copy->parent_id = $id;
            $copy->uploaded_by = $userId;
            $copy->uploader = $role;
            $copy->save();
            $video->load('upload_by:id,first_name,last_name,profile_image');
            $copy->load('upload_by:id,first_name,last_name,profile_image');
            Helper::createActionLog($userId,$role,'videos','clone',$copy,$video);
            $response = [
                "status" => 200,
                "message" => "Video Cloned Successfully",
                "video" => $copy
            ];
        }
        else{
            $response = [
                "status" => 422,
                "message" => "Video Not Found",
            ];
        }
        return response($response, $response["status"]);
    }

    public function updateVideo(Request $request, $id)
    {
        $role = $request->role;
        $userId = Auth::guard(strtolower($role))->id();

        $validator = Validator::make($request->all(), [
            'video_title' => 'string|max:255',
            'tags' => 'array'
        ]);

        if ($validator->fails()) {
            $response = [
                "status" => 422,
                "message" => $validator->errors()->first(),
                "errors" => $validator->errors(),
            ];
            return response($response, $response["status"]);
        }

        $video = Video::find($id);
        if (isset($video)) {
            $video->load('upload_by:id,first_name,last_name,profile_image');
            $before_data = $video->replicate();
            $video->fill($request->toArray());
            if($request->filled('video_format'))
            {
                if($request->video_format == "file")
                {
                    $video->video_url = null;
                }
                else if($request->video_format == "url"){
                    $video->video_file = null;
                    $video->video_duration = null;
                }
            }
            $onlyIsActive = $request->only(['is_active']) == $request->all();
            $message = '';
            if($request->filled('is_active'))
            {
                $video->is_active = $request->is_active;
                $message = $request->is_active == 1 ? 'Video Active Successfully' : 'Video Blocked Successfully';
            }
            $beforeVideo = basename($video->video_file);
            $beforeThumbnail = basename($video->video_thumbnail);
            if ($request->hasFile('video_file') && $request->video_format == 'file') {
                $filename   = time() . rand(111, 699) . '.' . $request->video_file->getClientOriginalExtension();
                $file = Helper::uploadedImage("upload/videos/", $filename, $request->video_file, $beforeVideo);
                $video->video_file = $file;
            }
            if ($request->has('video_thumbnail')) {
                $videoThumbnail = $request->video_thumbnail;

                if (preg_match("/^data:image\/(\w+);base64,/", $videoThumbnail, $matches)) {
                    $extension = $matches[1];
                    $imageData = base64_decode(preg_replace("/^data:image\/\w+;base64,/", '', $videoThumbnail));

                    $tempFile = tmpfile();
                    $tempPath = stream_get_meta_data($tempFile)['uri'];
                    file_put_contents($tempPath, $imageData);

                    $fileObject = new \Illuminate\Http\File($tempPath);

                    $filename = time() . rand(111, 699) . '.' . $extension;

                    $file = Helper::uploadedImage("upload/videos/thumbnails/", $filename, $fileObject,$beforeThumbnail);

                    $video->video_thumbnail = $file;

                    fclose($tempFile);
                } else {
                    if ($request->file('video_thumbnail')) {
                        $fileObject = $request->file('video_thumbnail');
                        $filename = time() . rand(111, 699) . '.' . $fileObject->getClientOriginalExtension();
                        $file = Helper::uploadedImage("upload/videos/thumbnails/", $filename, $fileObject,$beforeThumbnail);

                        $video->video_thumbnail = $file;
                    } else {
                        $video->video_thumbnail = $request->video_thumbnail == '' ? null : $request->video_thumbnail;
                    }
                }
            }


            if ($request->filled('tags')) {
                foreach ($request->tags as $tag) {
                    // $normalizedTag = str_replace(' ', '', ucwords(strtolower($tag)));
                    $normalizedTag = $tag;

                    $existingTag = Tag::where('tag', $normalizedTag)->where('type', 'Video')->first();

                    if (!$existingTag) {
                        Tag::create([
                            'tag' => $normalizedTag,
                            'type' => 'Video'
                        ]);
                    }
                }
            }
            else
            {
                $video->tags = null;
            }
            $video->uploaded_by = $userId;
            $video->uploader = $role;
            $video->type = $request->type ?? "Public";

            $video->save();
            $video->load('upload_by:id,first_name,last_name,profile_image');

            if($onlyIsActive)
            {
                $response = [
                    "status" => 200,
                    "message" => $message,
                    "video" => $video
                ];
                return response($response, $response["status"]);
            }
            else
            {
                $response = [
                    "status" => 200,
                    "message" => "Video Updated Successfully",
                    "video" => $video,
                    "role" => $role,
                    "user" => $userId,
                ];
            }
            Helper::createActionLog($userId,$role,'videos','update',$before_data,$video);

        } else {
            $response = [
                "status" => 422,
                "message" => "Video Not Found!",
            ];
        }
        return response($response, $response["status"]);
    }

    public function getVideos(Request $request)
    {
        $role = $request->role;
        $userId = Auth::guard(strtolower($role))->id();
        $videos = Video::withCount('exercises')->with('upload_by:id,first_name,last_name,profile_image')
        ->when($role == "Coach",function($query) use ($request,$userId,$role){
            $query->where(function($subquery) use ($request,$userId,$role){
                $subquery->where('uploaded_by',$userId)
                ->where('uploader',$role)
                ->where(function($query) use ($request){
                    $query->where('type','Private')
                    ->orWhere('type','Public');
                });
            })->orWhere('type','Public');
        })->when($request->filled('search'), function ($query) use ($request) {
                $query->where(function($subquery) use ($request){
                    $subquery->where('video_title', 'LIKE', '%' . $request->search . '%')
                    ->orWhereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(tags, '$[*]'))) LIKE ?", ['%' . strtolower($request->search) . '%']);
                });
            })
            ->when($request->filled('status'),function($query) use ($request){
                if($request->query('status') == 'Active')
                {
                    $query->where('is_active',1);
                }
                else if($request->query('status') == 'Blocked')
                {
                    $query->where('is_active',0);
                }
            })
            ->when($request->filled('video_type'),function($query) use ($request){
                $query->where('type',$request->query('video_type'));
            })
            ->when($request->filled('uploaded_by'), function ($query) use ($request) {
                $query->where('uploader',$request->query('uploaded_by'));
            })
            ->when($request->filled('coach_id'), function ($query) use ($request) {
                $query->where('uploader','Coach')
                ->where('uploaded_by', $request->query('coach_id'));
            })
            ->latest();

        $response = Pagination::paginate($request, $videos, "videos");

        return response($response, $response["status"]);
    }
    public function getVideo(Request $request, $id)
    {
        $video = Video::find($id);
        if (isset($video)) {
            $video->load("upload_by:id,first_name,last_name,profile_image");
            $response = [
                "status" => 200,
                "message" => "Video Fetched Successfully",
                "video" => $video
            ];
        } else {
            $response = [
                "status" => 422,
                "message" => "Video Not Found!",
            ];
        }

        return response($response, $response["status"]);
    }
    public function deleteVideo(Request $request, $id)
    {
        $role = $request->role;
        $userId = Auth::guard(strtolower($role))->id();
        $video = Video::find($id);
        if (isset($video)) {
            if ($video->parent_id === null) {
                $hasClones = Video::where('parent_id', $id)->exists();
                if (!$hasClones) {
                    if (isset($video->video_file)) {
                        Helper::removeImage("upload/videos/", basename($video->video_file));
                        Helper::removeImage("upload/videos/thumbnails/", basename($video->video_thumbnail));
                    } else  {
                        if(isset($video->video_thumbnail)){
                            Helper::removeImage("upload/videos/thumbnails/", basename($video->video_thumbnail));
                        }
                    }
                }
            } else {
                $parentVideo = Video::find($video->parent_id);
                if (!$parentVideo) {
                    if (isset($video->video_file)) {
                        Helper::removeImage("upload/videos/", basename($video->video_file));
                        Helper::removeImage("upload/videos/thumbnails/", basename($video->video_thumbnail));
                    } else {
                        if(isset($video->video_thumbnail)){
                            Helper::removeImage("upload/videos/thumbnails/", basename($video->video_thumbnail));
                        }
                    }
                }
            }
            Helper::createActionLog($userId, $role, 'videos', 'delete', $video, null);
            $video->delete();
            $response = [
                "status" => 200,
                "message" => "Video Deleted Successfully",
            ];
        } else {
            $response = [
                "status" => 422,
                "message" => "Video Not Found!",
            ];
        }

        return response($response, $response["status"]);
    }

    public function getVideoTags(Request $request)
    {
        $limit = $request->query('limit',10);
        $type = $request->query('type','Video');
        $tags = Tag::when($request->filled('search'), function ($query) use ($request) {
            $query->where(function ($subquery) use ($request) {
                $subquery->where('tag', 'LIKE', '%' . $request->search . '%');
            });
        })->where('type',$type)->where('is_active',1)->limit($limit)->select('id','tag')->get();

        $response = [
            "status" => 200,
            "message" => "Tags Fetched Successfully",
            "tags" => $tags,
        ];

        return response($response, $response["status"]);
    }

    /**
     * Stream video with secure signed URLs
     * Provides secure video delivery with access control
     */
    public function streamVideo(Request $request, $id)
    {
        $video = Video::find($id);

        if (!isset($video)) {
            return response()->json([
                'status' => 404,
                'message' => 'Video not found'
            ], 404);
        }

        // Check if user has access to this video
        $user = auth()->user();
        $role = $request->role ?? ($user ? get_class($user) : null);
        $userId = $user ? $user->id : null;

        // Access control logic
        $hasAccess = false;

        // 1. Public videos - everyone can access
        if ($video->type === 'Public' && $video->is_active) {
            $hasAccess = true;
        }

        // 2. Private videos - only uploader can access
        if ($video->type === 'Private' && $userId) {
            if ($video->uploader === $role && $video->uploaded_by === $userId) {
                $hasAccess = true;
            }
        }

        // 3. Admin can access everything
        if ($role === 'Admin') {
            $hasAccess = true;
        }

        if (!$hasAccess) {
            return response()->json([
                'status' => 403,
                'message' => 'Unauthorized access to this video'
            ], 403);
        }

        // If it's a URL-based video, return the URL directly
        if ($video->video_url) {
            return response()->json([
                'success' => true,
                'stream_type' => 'url',
                'stream_url' => $video->video_url,
                'thumbnail_url' => $video->video_thumbnail,
                'duration' => $video->video_duration,
                'title' => $video->video_title
            ]);
        }

        // If it's a file-based video, generate signed URL for S3
        if ($video->video_file) {
            // Assume videos are stored in S3 or local storage
            $videoPath = $video->video_file;

            // Check if using S3 storage
            if (config('filesystems.default') === 's3' && \Storage::disk('s3')->exists($videoPath)) {
                // Generate signed URL (expires in 2 hours)
                $signedUrl = \Storage::disk('s3')->temporaryUrl(
                    $videoPath,
                    now()->addHours(2)
                );

                $thumbnailUrl = null;
                if ($video->video_thumbnail && \Storage::disk('s3')->exists($video->video_thumbnail)) {
                    $thumbnailUrl = \Storage::disk('s3')->temporaryUrl(
                        $video->video_thumbnail,
                        now()->addHours(2)
                    );
                }

                return response()->json([
                    'success' => true,
                    'stream_type' => 'file',
                    'stream_url' => $signedUrl,
                    'thumbnail_url' => $thumbnailUrl ?? $video->video_thumbnail,
                    'duration' => $video->video_duration,
                    'title' => $video->video_title,
                    'expires_at' => now()->addHours(2)->toISOString()
                ]);
            }

            // For local storage, return public URL
            else {
                $streamUrl = asset($video->video_file);
                $thumbnailUrl = $video->video_thumbnail ? asset($video->video_thumbnail) : null;

                return response()->json([
                    'success' => true,
                    'stream_type' => 'file',
                    'stream_url' => $streamUrl,
                    'thumbnail_url' => $thumbnailUrl,
                    'duration' => $video->video_duration,
                    'title' => $video->video_title
                ]);
            }
        }

        return response()->json([
            'status' => 404,
            'message' => 'Video file not found'
        ], 404);
    }

    /**
     * Get video thumbnail
     */
    public function getThumbnail(Request $request, $id)
    {
        $video = Video::find($id);

        if (!isset($video) || !$video->video_thumbnail) {
            return response()->json([
                'status' => 404,
                'message' => 'Thumbnail not found'
            ], 404);
        }

        // Generate thumbnail URL
        if (config('filesystems.default') === 's3' && \Storage::disk('s3')->exists($video->video_thumbnail)) {
            $thumbnailUrl = \Storage::disk('s3')->temporaryUrl(
                $video->video_thumbnail,
                now()->addHours(24)
            );
        } else {
            $thumbnailUrl = asset($video->video_thumbnail);
        }

        return response()->json([
            'success' => true,
            'thumbnail_url' => $thumbnailUrl,
            'video_id' => $video->id,
            'video_title' => $video->video_title
        ]);
    }
}
