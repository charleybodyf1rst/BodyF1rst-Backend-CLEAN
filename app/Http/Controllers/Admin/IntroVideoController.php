<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\Helper;
use App\Helpers\Pagination;
use App\Http\Controllers\Controller;
use App\Models\IntroVideo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class IntroVideoController extends Controller
{
    public function addIntroVideo(Request $request)
    {
        $role = $request->role;
        $userId = Auth::guard(strtolower($role))->id();
        $validator = Validator::make($request->all(), [
            'video_title' => 'string|max:255',
            'video' => 'required',
            'video_thumbnail' => 'required_with:video',
            'type' => 'required|in:General,Nutrition'
        ]);

        if ($validator->fails()) {
            $response = [
                "status" => 422,
                "message" => $validator->errors()->first(),
                "errors" => $validator->errors(),
            ];
            return response($response, $response["status"]);
        }
        if ($request->type == "General") {
            IntroVideo::where('type', 'General')->update(['is_active' => 0]);
        }
        $video = IntroVideo::create($request->toArray());
        if ($request->hasFile('video')) {
            $filename   = time() . rand(111, 699) . '.' . $request->video->getClientOriginalExtension();
            $file = Helper::uploadedImage("upload/intro_videos/", $filename, $request->video);
            $video->video = $file;
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

                $file = Helper::uploadedImage("upload/intro_videos/thumbnails/", $filename, $fileObject);

                $video->video_thumbnail = $file;

                fclose($tempFile);
            } else {
                if ($request->file('video_thumbnail')) {
                    $fileObject = $request->file('video_thumbnail');
                    $filename = time() . rand(111, 699) . '.' . $fileObject->getClientOriginalExtension();
                    $file = Helper::uploadedImage("upload/intro_videos/thumbnails/", $filename, $fileObject);

                    $video->video_thumbnail = $file;
                } else {
                    $video->video_thumbnail = $request->video_thumbnail == '' ? null : $request->video_thumbnail;
                }
            }
        }
        $video->is_active = 1;
        $video->save();
        Helper::createActionLog($userId, "Admin", 'intro_videos', 'add', null, $video);
        $response = [
            "status" => 200,
            "message" => "Video Added Successfully",
            "video" => $video
        ];
        return response($response, $response["status"]);
    }

    public function updateIntroVideo(Request $request, $id)
    {
        $role = $request->role;
        $userId = Auth::guard(strtolower($role))->id();

        $validator = Validator::make($request->all(), [
            'video_title' => 'string|max:255',
            'type' => 'in:General,Nutrition'
        ]);

        if ($validator->fails()) {
            $response = [
                "status" => 422,
                "message" => $validator->errors()->first(),
                "errors" => $validator->errors(),
            ];
            return response($response, $response["status"]);
        }

        $video = IntroVideo::find($id);
        if (isset($video)) {
            $before_data = $video->replicate();
            $video->fill($request->toArray());
            $onlyIsActive = $request->only(['is_active']) == $request->all();
            $message = '';
            if ($request->filled('is_active')) {
                if ($request->is_active == 1) {
                    IntroVideo::where('type','General')->whereNotIn('id', [$id])->update(['is_active' => 0]);
                }
                else
                {
                    $existingVideo = IntroVideo::where('type','General')->whereNotIn('id', [$id])->latest()->first();
                    if(isset($existingVideo))
                    {
                        $existingVideo->is_active = 1;
                        $existingVideo->save();
                    }
                }
                $video->is_active = $request->is_active;
                $message = $request->is_active == 1 ? 'Video Active Successfully' : 'Video Blocked Successfully';
                $video->save();
                $response = [
                    "status" => 200,
                    "message" => $message,
                    "video" => $video
                ];
                return response($response, $response["status"]);
            }
            $beforeVideo = basename($video->video);
            $beforeThumbnail = basename($video->video_thumbnail);
            if ($request->hasFile('video')) {
                $filename   = time() . rand(111, 699) . '.' . $request->video->getClientOriginalExtension();
                $file = Helper::uploadedImage("upload/intro_videos/", $filename, $request->video, $beforeVideo);
                $video->video = $file;
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

                    $file = Helper::uploadedImage("upload/intro_videos/thumbnails/", $filename, $fileObject, $beforeThumbnail);

                    $video->video_thumbnail = $file;

                    fclose($tempFile);
                } else {
                    if ($request->file('video_thumbnail')) {
                        $fileObject = $request->file('video_thumbnail');
                        $filename = time() . rand(111, 699) . '.' . $fileObject->getClientOriginalExtension();
                        $file = Helper::uploadedImage("upload/intro_videos/thumbnails/", $filename, $fileObject, $beforeThumbnail);

                        $video->video_thumbnail = $file;
                    } else {
                        $video->video_thumbnail = $request->video_thumbnail == '' ? null : $request->video_thumbnail;
                    }
                }
            }
            $video->save();
            if ($onlyIsActive) {
                $response = [
                    "status" => 200,
                    "message" => $message,
                    "video" => $video
                ];
                return response($response, $response["status"]);
            } else {
                $response = [
                    "status" => 200,
                    "message" => "Video Updated Successfully",
                    "video" => $video,
                ];
            }
            Helper::createActionLog($userId, $role, 'intro_videos', 'update', $before_data, $video);
        } else {
            $response = [
                "status" => 422,
                "message" => "Video Not Found!",
            ];
        }
        return response($response, $response["status"]);
    }

    public function getIntroVideos(Request $request)
    {
        $role = $request->role;
        $userId = Auth::guard(strtolower($role))->id();
        $videos = IntroVideo::when($request->filled('search'), function ($query) use ($request) {
                $query->where(function ($subquery) use ($request) {
                    $subquery->where('video_title', 'LIKE', '%' . $request->search . '%');
                });
            })
            ->when($request->filled('type'),function($query) use ($request){
                $query->where('type',$request->query('type'));
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                if ($request->query('status') == 'Active') {
                    $query->where('is_active', 1);
                } else if ($request->query('status') == 'Blocked') {
                    $query->where('is_active', 0);
                }
            })
            ->latest();

        $response = Pagination::paginate($request, $videos, "videos");

        return response($response, $response["status"]);
    }
    public function getIntroVideo(Request $request, $id)
    {
        $video = IntroVideo::find($id);
        if (isset($video)) {
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
    public function deleteIntroVideo(Request $request, $id)
    {
        $role = $request->role;
        $userId = Auth::guard(strtolower($role))->id();
        $video = IntroVideo::find($id);
        if (isset($video)) {
            Helper::createActionLog($userId, $role, 'intro_videos', 'delete', $video, null);
            Helper::removeImage("upload/intro_videos/", basename($video->video));
            Helper::removeImage("upload/intro_videos/thumbnails/", basename($video->video_thumbnail));
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
}
