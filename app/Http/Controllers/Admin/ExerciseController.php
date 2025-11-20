<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\Helper;
use App\Helpers\Pagination;
use App\Http\Controllers\Controller;
use App\Models\Exercise;
use App\Models\ExerciseVideo;
use App\Models\WorkoutExercise;
use App\Models\Tag;
use App\Models\UserCompletedWorkout;
use App\Models\Video;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ExerciseController extends Controller
{
    public function addExercise(Request $request)
    {
        $role = $request->role;
        $userId = Auth::guard(strtolower($role))->id();

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
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
        $exercise = Exercise::create($request->toArray());
        if ($request->filled('tags')) {
            foreach ($request->tags as $tag) {
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
        $exercise->uploaded_by = $userId;
        $exercise->uploader = $role;
        $exercise->is_active = 1;
        $exercise->visibility_type = $request->visibility_type ?? "Public";
        $exercise->save();
        if (isset($request->video_format)) {
            $video = Video::create($request->toArray());
            if ($request->filled('video_format')) {
                if ($request->video_format == "file") {
                    $video->video_url = null;
                } else if ($request->video_format == "url") {
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
                    $normalizedTag = $tag;

                    $existingTag = Tag::where('tag', $normalizedTag)->where('type', 'Video')->first();

                    if (!$existingTag) {
                        Tag::create([
                            'tag' => $normalizedTag,
                            'type' => 'Video'
                        ]);
                    }
                }
            } else {
                $video->tags = null;
            }
            $video->video_title = $request->title;
            $video->uploaded_by = $userId;
            $video->uploader = $role;
            $video->type = $request->type ?? "Public";
            $video->is_active = 1;
            $video->save();
            $exercise->videos_pivot()->attach([$video->id]);
            Helper::createActionLog($userId, $role, 'videos', 'add', null, $video);
        }
        $exercise->load('upload_by:id,first_name,last_name,profile_image', 'video', 'videos');

        Helper::createActionLog($userId, $role, 'exercises', 'add', null, $exercise);

        $response = [
            "status" => 200,
            "message" => "Exercise Added Successfully",
            "exercise" => $exercise
        ];
        return response($response, $response["status"]);
    }
    public function updateExercise(Request $request, $id)
    {
        $role = $request->role;
        $userId = Auth::guard(strtolower($role))->id();
        $validator = Validator::make($request->all(), [
            'title' => 'string|max:255',
            'tags' => 'array',
        ]);

        if ($validator->fails()) {
            $response = [
                "status" => 422,
                "message" => $validator->errors()->first(),
                "errors" => $validator->errors(),
            ];
            return response($response, $response["status"]);
        }

        $exercise = Exercise::with('upload_by:id,first_name,last_name,profile_image', 'videos', 'video')->find($id);
        if (isset($exercise)) {
            $before_data = $exercise->replicate();
            $exercise->fill($request->toArray());
            $onlyIsActive = $request->only(['is_active']) == $request->all();
            $message = '';
            if ($request->filled('is_active')) {
                $exercise->is_active = $request->is_active;
                $message = $request->is_active == 1 ? 'Exercise Active Successfully' : 'Exercise Blocked Successfully';
            }


            if ($request->filled('tags')) {
                foreach ($request->tags as $tag) {
                    $normalizedTag = $tag;

                    $existingTag = Tag::where('tag', $normalizedTag)->where('type', 'Video')->first();

                    if (!$existingTag) {
                        Tag::create([
                            'tag' => $normalizedTag,
                            'type' => 'Video'
                        ]);
                    }
                }
            } else {
                if (!$onlyIsActive) {
                    $exercise->tags = null;
                }
            }
            $exercise->uploaded_by = $userId;
            $exercise->uploader = $role;
            $exercise->visibility_type = $request->visibility_type ?? "Public";
            $exercise->save();
            $video = $exercise->video;
            if (isset($video)) {
                $video->load('upload_by:id,first_name,last_name,profile_image');
                $before_data = $video->replicate();
                $video->fill($request->toArray());
                if ($request->filled('video_format')) {
                    if ($request->video_format == "file") {
                        $video->video_url = null;
                    } else if ($request->video_format == "url") {
                        $video->video_file = null;
                        $video->video_duration = null;
                    }
                }
                $message = '';
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

                        $file = Helper::uploadedImage("upload/videos/thumbnails/", $filename, $fileObject, $beforeThumbnail);

                        $video->video_thumbnail = $file;

                        fclose($tempFile);
                    } else {
                        if ($request->file('video_thumbnail')) {
                            $fileObject = $request->file('video_thumbnail');
                            $filename = time() . rand(111, 699) . '.' . $fileObject->getClientOriginalExtension();
                            $file = Helper::uploadedImage("upload/videos/thumbnails/", $filename, $fileObject, $beforeThumbnail);

                            $video->video_thumbnail = $file;
                        } else {
                            $video->video_thumbnail = $request->video_thumbnail == '' ? null : $request->video_thumbnail;
                        }
                    }
                }


                if ($request->filled('tags')) {
                    foreach ($request->tags as $tag) {
                        $normalizedTag = $tag;

                        $existingTag = Tag::where('tag', $normalizedTag)->where('type', 'Video')->first();

                        if (!$existingTag) {
                            Tag::create([
                                'tag' => $normalizedTag,
                                'type' => 'Video'
                            ]);
                        }
                    }
                } else {
                    $video->tags = null;
                }
                $video->video_title = $request->title;
                $video->uploaded_by = $userId;
                $video->uploader = $role;
                $video->type = $request->type ?? "Public";
                $video->save();
                $exercise->videos_pivot()->sync([$video->id]);
            }
            $exercise->load('upload_by:id,first_name,last_name,profile_image', 'video', 'videos');

            if ($onlyIsActive) {
                $response = [
                    "status" => 200,
                    "message" => $message,
                    "exercise" => $exercise
                ];
                return response($response, $response["status"]);
            } else {
                $response = [
                    "status" => 200,
                    "message" => "Exercise Updated Successfully",
                    "exercise" => $exercise
                ];
            }

            Helper::createActionLog($userId, $role, 'exercises', 'update', $before_data, $exercise);
        } else {
            $response = [
                "status" => 422,
                "message" => "Exercise Not Found!",
            ];
        }
        return response($response, $response["status"]);
    }

    public function cloneExercise(Request $request, $id = null)
    {
        $role = $request->role;
        $userId = Auth::guard(strtolower($role))->id();
        $exercise = Exercise::with('video', 'videos')->find($id);

        if (isset($exercise)) {
            DB::beginTransaction();

            $copy = $exercise->replicate()->fill(
                [
                    'title' => $exercise->title
                ]
            );
            $copy->parent_id = $id;
            $copy->uploaded_by = $userId;
            $copy->uploader = $role;
            $copy->save();

            $videoIds = [];

            $video = $exercise->video;
            if ($video) {







                $clonedVideo = $video->replicate()->fill(
                    [
                        'video_title' => $video->video_title
                    ]
                );
                unset($clonedVideo->laravel_through_key);
                $clonedVideo->uploaded_by = $userId;
                $clonedVideo->uploader = $role;
                $clonedVideo->parent_id = $video->id;
                $clonedVideo->save();
                $videoIds[] = $clonedVideo->id;







            }
            $copy->videos_pivot()->sync($videoIds);

            $exercise->load('upload_by:id,first_name,last_name,profile_image', 'videos');
            $copy->load('upload_by:id,first_name,last_name,profile_image', 'videos');
            Helper::createActionLog($userId, $role, 'exercises', 'clone', $copy, $exercise);
            DB::commit();
            $response = [
                "status" => 200,
                "message" => "Exercise Cloned Successfully",
                "exercise" => $copy
            ];
        } else {
            $response = [
                "status" => 422,
                "message" => "Exercise Not Found",
            ];
        }
        return response($response, $response["status"]);
    }

    public function getExercises(Request $request)
    {
        $role = $request->role;
        $userId = Auth::guard(strtolower($role))->id();
        $exercises = Exercise::with('upload_by:id,first_name,last_name,profile_image', 'videos', 'video')
            ->when($role == "Coach", function ($query) use ($request, $userId, $role) {
                $query->where(function($sq) use ($request, $userId, $role){
                        $sq->where(function ($subquery) use ($request, $userId, $role) {
                        $subquery->where('uploaded_by', $userId)
                            ->where('uploader', $role);
                    })->orWhere('visibility_type', 'Public');
                });
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                $query->where(function ($subquery) use ($request) {
                    $subquery->where('title', 'LIKE', '%' . $request->search . '%')
                        ->orWhereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(tags, '$[*]'))) LIKE ?", ['%' . strtolower($request->search) . '%']);
                });
            })
            ->when($request->filled('visibility_type'), function ($query) use ($request) {
                $query->where('visibility_type', $request->query('visibility_type'));
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                if ($request->query('status') == 'Active') {
                    $query->where('is_active', 1);
                } else if ($request->query('status') == 'Blocked') {
                    $query->where('is_active', 0);
                }
            })
            ->when($request->filled('tag'), function ($query) use ($request) {
                $query->where(function ($subquery) use ($request) {
                    $subquery->whereJsonContains('tags', $request->tag);
                });
            })
            ->when($request->filled('uploaded_by'), function ($query) use ($request) {
                $query->where('uploader', $request->query('uploaded_by'));
            })
            ->when($request->filled('coach_id'), function ($query) use ($request) {
                $query->where('uploader', 'Coach')
                    ->where('uploaded_by', $request->query('coach_id'));
            })
            ->whereNull('parent_id')
            ->latest();

        $response = Pagination::paginate($request, $exercises, "exercises");

        return response($response, $response["status"]);
    }
    public function getExercise(Request $request, $id)
    {
        $exercise = Exercise::find($id);
        if (isset($exercise)) {
            $exercise->load("upload_by:id,first_name,last_name,profile_image", 'videos', 'video');
            $response = [
                "status" => 200,
                "message" => "Exercise Fetched Successfully",
                "exercise" => $exercise
            ];
        } else {
            $response = [
                "status" => 422,
                "message" => "Exercise Not Found!",
            ];
        }

        return response($response, $response["status"]);
    }
    public function deleteExercise(Request $request, $id)
    {
        $role = $request->role;
        $userId = Auth::guard(strtolower($role))->id();
        $exercise = Exercise::find($id);
        if (isset($exercise)) {
            Helper::createActionLog($userId, $role, 'exercises', 'delete', $exercise, null);
            UserCompletedWorkout::where('exercise_id', $exercise->id)->delete();
            WorkoutExercise::where('exercise_id', $exercise->id)->delete();
            $exercise->delete();
            $response = [
                "status" => 200,
                "message" => "Exercise Deleted Successfully",
            ];
        } else {
            $response = [
                "status" => 422,
                "message" => "Exercise Not Found!",
            ];
        }

        return response($response, $response["status"]);
    }






























    public function createExercisesFromUnlinkedVideos()
    {
        $videos = Video::doesntHave('exercises')->get();

        foreach ($videos as $video) {
            DB::transaction(function () use ($video) {
                $exercise = Exercise::create([
                    'title' => $video->video_title,
                    'tags' => $video->tags,
                ]);
                $exercise->uploaded_by = $video->uploaded_by;
                $exercise->uploader = $video->uploader;
                $exercise->is_active = $video->is_active;
                $exercise->visibility_type = $video->type;
                $exercise->save();

                $exercise->videos_pivot()->attach([$video->id]);
            });
        }
    }

    /**
     * Get muscle groups dropdown
     */
    public function getMuscleGroupsDropdown(Request $request)
    {
        $muscleGroups = [
            ['id' => 1, 'name' => 'Chest'],
            ['id' => 2, 'name' => 'Back'],
            ['id' => 3, 'name' => 'Shoulders'],
            ['id' => 4, 'name' => 'Arms'],
            ['id' => 5, 'name' => 'Legs'],
            ['id' => 6, 'name' => 'Core'],
            ['id' => 7, 'name' => 'Glutes'],
            ['id' => 8, 'name' => 'Biceps'],
            ['id' => 9, 'name' => 'Triceps'],
            ['id' => 10, 'name' => 'Quads'],
            ['id' => 11, 'name' => 'Hamstrings'],
            ['id' => 12, 'name' => 'Calves'],
            ['id' => 13, 'name' => 'Full Body'],
        ];

        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => 'Muscle Groups Retrieved Successfully',
            'data' => $muscleGroups
        ]);
    }
}
