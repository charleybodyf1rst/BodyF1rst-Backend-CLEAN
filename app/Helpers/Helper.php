<?php

namespace App\Helpers;

use App\Mail\CoachAssigned;
use App\Mail\CoachAssignment;
use App\Mail\OrganizationSubmissionForm;
use App\Mail\PlanAssignment;
use App\Mail\SendAssignmentToCoach;
use App\Mail\SendCredential;
use App\Mail\SendOrganizationAssignmentToCoach;
use App\Mail\SendOrganizationSubmissionToAdmin;
use App\Mail\SendNewOnBoarding;
use App\Mail\SendReminder;
use App\Models\ActivityLog;
use App\Models\BodyPoint;
use App\Models\ReportContent;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class Helper
{

        private static $url = "https://bodyf1rst-5672e.web.app/submit-organization/";
    // public static function uploadedImage($path, $filename,$file,$before = null)
    // {

    //     if($before != null)
    //     {
    //         self::removeImage($path,$before);
    //     }
    //     $file->move($path, $filename);

    //     return $filename;
    // }

    // public static function uploadedData($path, $filename, $file, $before = null)
    // {
    //     if ($before != null) {
    //         self::removeImage($path, $before);
    //     }

    //     $file->move($path, $filename);

    //     $fileExtension = pathinfo($filename, PATHINFO_EXTENSION);
    //     $videoTypes = ['mp4', 'mkv'];

    //     if (in_array(strtolower($fileExtension), $videoTypes)) {
    //         $inputFile = $path . '' . $filename;

    //         $outputFile = $path . 'compressed_' . $filename;

    //         $command = "HandBrakeCLI -i {$inputFile} -o {$outputFile} --preset=\"Very Fast 720p30\"";
    //         exec($command, $output, $status);

    //         if ($status === 0) {
    //             unlink($inputFile);
    //             return $filename;
    //         } else {
    //             error_log('HandBrakeCLI compression failed: ' . implode("\n", $output));
    //         }
    //     }

    //     return null;
    // }


    public static function uploadedImage($path, $filename, $file, $before = null)
    {
        if ($before != null) {
            self::removeImage($path, $before);
        }

        $src_path = $file->getRealPath();
        $info = getimagesize($src_path);

        if ($info !== false) {
            switch ($info['mime']) {
                case 'image/jpeg':
                    $src_image = imagecreatefromjpeg($src_path);
                    break;
                case 'image/png':
                    $src_image = imagecreatefrompng($src_path);
                    break;
                case 'image/gif':
                    $src_image = imagecreatefromgif($src_path);
                    break;
                default:
                    throw new Exception('Unsupported image type');
            }

            $width = imagesx($src_image);
            $height = imagesy($src_image);

            $dst_image = imagecreatetruecolor($width, $height);

            if ($info['mime'] == 'image/png' || $info['mime'] == 'image/gif') {
                imagealphablending($dst_image, false);
                imagesavealpha($dst_image, true);
                $transparent = imagecolorallocatealpha($dst_image, 0, 0, 0, 127);
                imagefilledrectangle($dst_image, 0, 0, $width, $height, $transparent);
            }

            imagecopyresampled($dst_image, $src_image, 0, 0, 0, 0, $width, $height, $width, $height);

            $dest_path = $path . '/' . $filename;

            switch ($info['mime']) {
                case 'image/jpeg':
                    imagejpeg($dst_image, $dest_path, 10);
                    break;
                case 'image/png':
                    imagepng($dst_image, $dest_path, 1);
                    break;
                case 'image/gif':
                    imagegif($dst_image, $dest_path);
                    break;
            }

            imagedestroy($src_image);
            imagedestroy($dst_image);

        } else {
            $file->move($path, $filename);
        }

        return $filename;
    }

    public static function generateThumbnail($path, $filename, $file, $targetWidth, $targetHeight, $quality = 90, $before=null)
    {
        if ($before != null) {
            self::removeImage($path, $before);
        }

        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        }

        $src_path = $file->getRealPath();
        $info = getimagesize($src_path);

        if ($info !== false) {
            switch ($info['mime']) {
                case 'image/jpeg':
                    $src_image = imagecreatefromjpeg($src_path);
                    break;
                case 'image/png':
                    $src_image = imagecreatefrompng($src_path);
                    break;
                case 'image/gif':
                    $src_image = imagecreatefromgif($src_path);
                    break;
                default:
                    throw new Exception('Unsupported image type');
            }

            $width = imagesx($src_image);
            $height = imagesy($src_image);

            // Maintain aspect ratio for thumbnail
            $aspectRatio = $width / $height;

            if ($targetWidth / $targetHeight > $aspectRatio) {
                $targetWidth = $targetHeight * $aspectRatio;
            } else {
                $targetHeight = $targetWidth / $aspectRatio;
            }

            $dst_image = imagecreatetruecolor($targetWidth, $targetHeight);

            if ($info['mime'] == 'image/png' || $info['mime'] == 'image/gif') {
                imagealphablending($dst_image, false);
                imagesavealpha($dst_image, true);
                $transparent = imagecolorallocatealpha($dst_image, 0, 0, 0, 127);
                imagefilledrectangle($dst_image, 0, 0, $targetWidth, $targetHeight, $transparent);
            }

            imagecopyresampled($dst_image, $src_image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

            $dest_path = $path . '/' . $filename;

            switch ($info['mime']) {
                case 'image/jpeg':
                    imagejpeg($dst_image, $dest_path, $quality);
                    break;
                case 'image/png':
                    imagepng($dst_image, $dest_path, 4);
                    break;
                case 'image/gif':
                    imagegif($dst_image, $dest_path);
                    break;
            }

            imagedestroy($src_image);
            imagedestroy($dst_image);
        } else {
            throw new Exception('Invalid image file');
        }

        return $filename;
    }


    public static function uploadImage($file)
    {
        $filename   = time() . rand(111, 699) . '.' . $file->getClientOriginalExtension();
        $path = public_path('upload/attachments/');
        self::chatCompressImage($file,$path,$filename);
        return url("/") . "/upload/attachments/{$filename}";
    }

    public static function removeImage($path,$filename)
    {
        $fullPath = public_path($path . $filename);
        if (file_exists($fullPath)) {
            unlink($fullPath);
            return "File deleted successfully";
        } else {
            return "File not found";
        }
    }

    public static function generateToken($length = 60)
    {
        return bin2hex(random_bytes($length / 2));
    }

    public static function compressImage($path, $filename, $before = null)
    {
        if($before != null)
        {
            self::removeImage($path,$before);
        }
        $src_path = $path . '/' . $filename;
        $info = getimagesize($src_path);

        switch ($info['mime']) {
            case 'image/jpeg':
                $src_image = imagecreatefromjpeg($src_path);
                break;
            case 'image/png':
                $src_image = imagecreatefrompng($src_path);
                break;
            case 'image/gif':
                $src_image = imagecreatefromgif($src_path);
                break;
            default:
                throw new Exception('Unsupported image type');
        }
        $width = imagesx($src_image);
        $height = imagesy($src_image);

        $dst_image = imagecreatetruecolor($width, $height);
        if ($info['mime'] == 'image/png' || $info['mime'] == 'image/gif') {
            imagealphablending($dst_image, false);
            imagesavealpha($dst_image, true);
            $transparent = imagecolorallocatealpha($dst_image, 0, 0, 0, 127);
            imagefilledrectangle($dst_image, 0, 0, $width, $height, $transparent);
        }
        imagecopyresampled($dst_image, $src_image, 0, 0, 0, 0, $width, $height, imagesx($src_image), imagesy($src_image));

        $compressed_filename = pathinfo($filename, PATHINFO_FILENAME) . '_thumbnail.' . pathinfo($filename, PATHINFO_EXTENSION);
        $compressed_path = $path . '/' . $compressed_filename;

        switch ($info['mime']) {
            case 'image/jpeg':
                imagejpeg($dst_image, $compressed_path, 10);
                break;
            case 'image/png':
                imagepng($dst_image, $compressed_path, 1);
                break;
            case 'image/gif':
                imagegif($dst_image, $compressed_path);
                break;
        }

        return $compressed_filename;
    }
    public static function chatCompressImage($file, $path, $filename)
    {
        // Ensure the directory exists
        if (!file_exists($path)) {
            mkdir($path, 0755, true);
        }

        $src_path = $file->getRealPath(); // Get the real path of the uploaded file
        $info = getimagesize($src_path);

        switch ($info['mime']) {
            case 'image/jpeg':
                $src_image = imagecreatefromjpeg($src_path);
                break;
            case 'image/png':
                $src_image = imagecreatefrompng($src_path);
                break;
            case 'image/gif':
                $src_image = imagecreatefromgif($src_path);
                break;
            default:
                throw new Exception('Unsupported image type');
        }

        $width = imagesx($src_image);
        $height = imagesy($src_image);

        $dst_image = imagecreatetruecolor($width, $height);
        if ($info['mime'] == 'image/png' || $info['mime'] == 'image/gif') {
            imagealphablending($dst_image, false);
            imagesavealpha($dst_image, true);
            $transparent = imagecolorallocatealpha($dst_image, 0, 0, 0, 127);
            imagefilledrectangle($dst_image, 0, 0, $width, $height, $transparent);
        }

        imagecopyresampled($dst_image, $src_image, 0, 0, 0, 0, $width, $height, imagesx($src_image), imagesy($src_image));

        switch ($info['mime']) {
            case 'image/jpeg':
                imagejpeg($dst_image, $path . $filename, 65);
                break;
            case 'image/png':
                imagepng($dst_image, $path . $filename, 6);
                break;
            case 'image/gif':
                imagegif($dst_image, $path . $filename);
                break;
        }

        imagedestroy($src_image);
        imagedestroy($dst_image);

        return $path . $filename;
    }


    public static function createActionLog($user_id,$type, $model_type, $action, $before_data = null, $after_data = null)
    {
        try {
            if ($before_data != null && isset($before_data->id)) {
                $item_id = $before_data->id;
            } else if ($after_data != null) {
                $item_id = $after_data->id;
            } else {
                $item_id = null;
            }
            ActivityLog::create([
                "action_by" => $user_id,
                "action_type" => $type,
                "model_type" => $model_type,
                "model_id" => $item_id,
                "action" => $action,
                "before" => $before_data,
                "after" => $after_data,
            ]);
        } catch (\Exception $e) {
        }
    }

    public static function sendCredential($type,$name,$email,$password,$organization,$coach,$role)
    {
        $credential = [
            'type' => $type,
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'organization' => $organization,
            'coach' => $coach,
            'role' => $role,
        ];
        Mail::to($email)->send(new SendCredential($credential));
    }

    public static function sendReminder($user)
    {
        Mail::to($user->email)->send(new SendReminder($user));
    }
    public static function sendOrganizationSubmissionForm($organization)
    {
        $organization['url'] = self::$url;
        Mail::to($organization->poc_email)->send(new OrganizationSubmissionForm($organization));
    }
    public static function sendOrganizationSubmitted($user,$organization)
    {
        Mail::to($user->email)->send(new SendOrganizationSubmissionToAdmin($organization,$user));
    }
    public static function sendOrganizationAssignedToCoach($coaches,$organization)
    {
        foreach($coaches as $coach)
        {
            Mail::to($coach->email)->send(new SendOrganizationAssignmentToCoach($organization,$coach));
        }
    }
    public static function sendAssignmentToCoach($coach,$organizations,$users)
    {
        $coach['url'] = self::$url;
        Mail::to($coach->email)->send(new SendAssignmentToCoach($coach,$organizations,$users));
    }
    public static function sendAssignedToCoach($coach,$user)
    {
        $coach['url'] = self::$url;
        Mail::to($coach->email)->send(new CoachAssigned($coach,$user));
    }
    public static function sendAssignmentToUsers($coach,$users)
    {
        foreach($users as $user)
        {
            Mail::to($user->email)->send(new CoachAssignment($coach,$user));
        }
    }
    public static function sendNewOnBoarding($coaches,$organization)
    {
        foreach($coaches as $coach)
        {
            Mail::to($coach->email)->send(new SendNewOnBoarding($organization,$coach));
        }
    }
    public static function sendPlanAssignment($users,$plan)
    {
        foreach($users as $user)
        {
            $user = User::find($user);
            if(isset($user))
            {
                Mail::to($user->email)->send(new PlanAssignment($plan,$user));
            }
        }
    }


    public static function sendPush($title, $description, $user_id = null, $notification_id = null,$type=null,$model_id=null,$users=[])
    {
        $salt = "";
        // if (env('APP_DEBUG') == true) {
        //     // return "Notification not sent in staging";
        //     $salt = "-test";

        //     if ($user_id == null) {
        //         return "Notification not sent in staging";
        //     }
        // }

        $content = ["en" => $description];
        $head      = ["en" => $title];
        $fields = array(
            'app_id' => env("ONE_SIGNAL_APP_ID"),
            "channel_for_external_user_ids" => "push",
            'data' => array("notification_id" => $notification_id, "type" => $type, "model_id" => $model_id),
            'contents' => $content,
            'headings' => $head
        );
        if(is_array($users) && !empty($users))
        {
            $users = array_map('strval',$users);

            if (!empty($users)) {
                $fields["include_external_user_ids"] = $users;
            }
        }
        else
        {
            if ($user_id == null) {
                $fields["included_segments"] = array('Total Subscriptions');
            } else {
                $fields["include_external_user_ids"] = [$user_id . $salt];
            }
        }




        $fields = json_encode($fields);

        Log::info($fields);

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, "https://onesignal.com/api/v1/notifications");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Authorization: Basic ' . env("ONE_SIGNAL_SECRET")
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

        $response = curl_exec($ch);
        curl_close($ch);



        return $response;
    }

    public static function getBodyPoints($user)
    {
        $points = BodyPoint::select('meta_key', 'meta_value')->where('meta_key','points')->first();

        $body_data = $points['meta_value'];

        if (isset($body_data['workout_and_exercise'])) {
            $workout_data = $body_data['workout_and_exercise'];
            $user_accountability = strtolower($user->accountability);

            $accountability_key = 'acccountability_' . $user_accountability;

            if (isset($workout_data[$accountability_key])) {
                $body_points = $workout_data[$accountability_key];
            } else {
                $body_points = 0;
            }
        } else {
            $body_points = 0;
        }

        return $body_points;
    }

    public static function getCurrentPhase($currentWeek, $phases)
    {
        $cumulativeWeeks = 0;
        foreach ($phases as $index => $phase) {
            $cumulativeWeeks += count($phase['weeks']);
            if ($currentWeek <= $cumulativeWeeks) {
                return $index + 1; // Phase index starts from 0, so add 1
            }
        }
        return count($phases); // Default to last phase if none match
    }

    public static function getCurrentWeekInPhase($currentPhaseDetails, $currentWeek)
    {
        $cumulativeWeeks = 0;
        foreach ($currentPhaseDetails['weeks'] as $index => $week) {
            $cumulativeWeeks++;
            if ($currentWeek <= $cumulativeWeeks) {
                return $index + 1; // Week index starts from 0, so add 1
            }
        }
        return count($currentPhaseDetails['weeks']); // Default to last week if none match
    }

    public static function generatePassword()
    {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*()_-+=<>?';

        $password = substr(str_shuffle($characters), 0, 8);

        return $password;
    }


    public static function reportedContents($user,$type)
    {
        $reports = ReportContent::where('user_id',$user->id)->where('model_type',$type)->pluck('model_id')->toArray();
        return $reports;
    }
}
