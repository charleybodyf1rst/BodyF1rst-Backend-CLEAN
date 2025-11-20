<?php

namespace App\Jobs;

use App\Mail\UserCredentialsMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\EmployeesWithRemarksExport;
use App\Helpers\Helper;
use App\Models\Admin;
use App\Models\Coach;
use App\Models\User;
use Carbon\Carbon;

class SendUserCredentials implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $users;
    protected $organizationName;
    protected $role;
    protected $userId;
    protected $failedUsers = [];

    public function __construct(array $users, $organizationName,$role,$userId)
    {
        $this->users = $users;
        $this->organizationName = $organizationName;
        $this->role = $role;
        $this->userId = $userId;
    }

    public function handle()
    {
        if($this->role == "Admin")
        {
            $upload_by = Admin::find($this->userId);
        }
        else
        {
            $upload_by = Coach::find($this->userId);
        }
        foreach ($this->users as $user) {
            try {
                $coach = $upload_by ?  $upload_by->first_name . " " . $upload_by->last_name : null;
                $name = $user->first_name .' '.$user->last_name;
                $name = $user['first_name'] . ' ' . $user['last_name'];
                // SECURITY: Password reset link should be sent instead of plaintext password
                Helper::sendCredential("User", $name, $user['email'], null,$this->organizationName,$coach,$this->role);
                $this->updateUserMailedStatus($user['email'],1);

            } catch (\Exception $e) {
                $this->failedUsers[] = [
                    'first_name' => $user['first_name'],
                    'last_name' => $user['last_name'],
                    'email' => $user['email'],
                    'remark' => 'Mail not send to the User.',
                ];
            }
        }

        // Export the failed users to an Excel file if any failures occurred
        if (!empty($this->failedUsers)) {
            $this->exportFailedUsers();
        }
    }
    private function updateUserMailedStatus($email, $isMailed)
    {
        $user = User::where('email',$email)->first();
        if(isset($user))
        {
            $user->is_mailed = $isMailed ? 1 : 0;
            $user->last_reminded_at = Carbon::now();
            $user->save();
        }
    }
    private function exportFailedUsers()
    {
        $fileName = 'failed_emails_' . now()->format('Y_m_d_His') . '.xlsx';
        $path = public_path('upload/failed_emails/' . $fileName);

        Excel::store(new EmployeesWithRemarksExport($this->failedUsers), $path, 'public');

        info('Failed email export generated: ' . $fileName);
    }
}
