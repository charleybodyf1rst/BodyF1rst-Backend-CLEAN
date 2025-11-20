<?php

namespace App\Imports;

use App\Models\User;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\BeforeImport;
use Maatwebsite\Excel\Events\AfterImport;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\EmployeesWithRemarksExport;
use App\Helpers\Helper;
use App\Jobs\SendUserCredentials;
use App\Models\Admin;
use App\Models\Coach;
use App\Models\Department;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

class EmployeesImport implements ToCollection, WithHeadingRow, WithChunkReading, WithEvents
{
    const ERR_MISSING_DATA = 'Missing required data';
    const ERR_EMAIL_EXISTS = 'Email already exists';
    const ERR_DUPLICATE_EMAIL = 'Duplicate email in the import file';
    const ERR_INVALID_EMAIL_FORMAT = 'Please register this email ID on Google or another platform before using it for registration.';
    const ADDED_SUCCESSFULLY = 'Added successfully';
    const POC_ADDED_SUCCESSFULLY = 'Ready To Go';

    private $organization_id;
    private $organization_name;
    private $type;
    private $role;
    private $userId;
    private $users = [];
    private $departments = [];
    private $errors = [];
    private $rowsWithRemarks = [];
    private $file;
    private $message;
    private $err_imp_count = 0;
    private $is_mailed_count = 0;

    public function __construct($organization_id,$type,$organization_name,$role,$userId)
    {
        $this->organization_id = $organization_id;
        $this->organization_name = $organization_name;
        $this->type = $type;
        $this->role = $role;
        $this->userId = $userId;
    }

    public function collection(Collection $rows)
    {
        $existingEmails = User::pluck('email')->toArray();
        $existingDepartments = Department::pluck('name')->toArray();
        $newDepartments = [];
        $emailsInRows = [];
        foreach ($rows as $row) {
            $password = Helper::generatePassword();
            // $password = 123456;
            $remark = '';

            if (empty($row['email']) || empty($row['first_name']) || empty($row['last_name'])) {
                $remark = self::ERR_MISSING_DATA;
                $this->err_imp_count+=1;
            }
            elseif (!preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z\d\-]{2,}\.[a-zA-Z]{2,}$/', $row['email'])) {
                $remark = self::ERR_INVALID_EMAIL_FORMAT;
                $this->err_imp_count += 1;
            }
             elseif (in_array($row['email'], $existingEmails)) {
                $remark = self::ERR_EMAIL_EXISTS;
                $this->err_imp_count+=1;
            }
            else if(in_array($row['email'],$emailsInRows))
            {
                $remark = self::ERR_DUPLICATE_EMAIL;
                $this->err_imp_count+=1;
            }
            else {
                // SECURITY: Password reset link should be sent instead of plaintext password
                $this->users[] = [
                    'first_name' => $row['first_name'],
                    'last_name' => $row['last_name'],
                    'email' => $row['email'],
                    'organization_id' => $this->organization_id,
                    'department' => $row['department'] ?? null,
                    'password' => bcrypt($password),
                    'uploader' => $this->role ?? "Admin",
                    'uploaded_by' => $this->userId ?? 1,
                    'created_at' => now(),
                    'updated_at' => now()
                ];

                if (!in_array($row['department'] ?? null, $existingDepartments) && !empty($row['department'])) {
                    $newDepartments[] = $row['department'];
                }
            }
            $emailsInRows[] = $row['email'];

            // Collect row data with remarks for the final export
            $this->rowsWithRemarks[] = [
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'email' => $row['email'],
                'department' => $row['department'] ?? null,
                'remark' => $remark ?: ($this->type == "POC" ? self::POC_ADDED_SUCCESSFULLY : self::ADDED_SUCCESSFULLY)
            ];

            if($newDepartments)
            {
                $this->departments = $newDepartments;
            }

            if ($remark) {
                $this->errors[] = 'Name: ' . $row['first_name'] . ' ' . $row['last_name'] . ' Email: ' . $row['email'] . ' - ' . $remark;
            }
        }

        if ($this->type == "POC") {
            $this->message = "You have successfully uploaded <strong>" . count($this->users) . "</strong> users from your file. However, there were some issues, and <strong>" . $this->err_imp_count . "</strong> users couldn’t be uploaded. Please download the file to see which entries had problems. After fixing the issues, you can upload the file again.";
        } else {
            $this->message = "Successfully added " . count($this->users) . " out of " . $rows->count();
        }

        usort($this->rowsWithRemarks, function ($a, $b) {
            return $a['remark'] === self::ADDED_SUCCESSFULLY || $a['remark'] === self::POC_ADDED_SUCCESSFULLY ? 1 : -1;
        });
    }

    public function getErrors()
    {
        return $this->errors;
    }
    public function getFile()
    {
        return $this->file;
    }
    public function getMessage()
    {
        return $this->message;
    }
    public function checkMailedCount()
    {
        return $this->is_mailed_count;
    }
    public function getDepartments()
    {
        return $this->departments;
    }
    public function getUsersCount()
    {
        return count($this->users);
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    public function beforeImport(BeforeImport $event)
    {
        // Use the event to perform any setup before import if needed
    }

    public function afterImport(AfterImport $event)
    {
        $upload_by = null;
        if($this->role == "Admin")
        {
            $upload_by = Admin::find($this->userId);
        }
        else
        {
            $upload_by = Coach::find($this->userId);
        }
        if($this->type == "POC")
        {
            $this->exportRemarksCsv();
        }
        else
        {
            // Insert users in chunks
            $chunks = array_chunk($this->users, 1000);
            foreach ($chunks as $chunk) {
                User::insert($chunk);
                // dispatch(new SendUserCredentials($chunk, $this->organization_name,$this->role,$this->userId));

            }
            // $users = User::where('is_mailed', 0)->get();

            foreach ($this->users as $user) {
                $name = $user['first_name'] . ' ' . $user['last_name'];
                $coach = $upload_by ? $upload_by->first_name . ' '. $upload_by->last_name : null;
                try {
                    // SECURITY: Password reset link should be sent instead of plaintext password
                    Helper::sendCredential("User", $name, $user['email'], null,$this->organization_name,$coach,$this->role);
                    $this->updateUserMailedStatus($user['email'],1);
                } catch (\Exception $e) {
                    foreach($this->rowsWithRemarks as $index=>$row)
                    {
                        if($row['email'] == $user['email'])
                        {
                            $remark = "Added Successfully but User has not received their credential email.";
                            $this->rowsWithRemarks[$index] = [
                                'first_name' => $row['first_name'],
                                'last_name' => $row['last_name'],
                                'email' => $row['email'],
                                'department' => $row['department'],
                                'remark' => $remark,
                            ];
                        }
                    }
                    $this->is_mailed_count +=1;
                    $this->errors[] = 'Name: ' . $user['first_name'] . ' ' . $user['last_name'] . ' Email: ' . $user['email'] . ' - ' . 'Failed to send email: ' . $e->getMessage();
                }
            }

                // Artisan::call('sendcredentials');

            // Export the rows with remarks as a new CSV file
            $this->exportRemarksCsv();
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

    private function exportRemarksCsv()
    {
        $fileName = 'import_results_' . $this->organization_id . '_' . now()->format('Y_m_d_His') . '.xlsx';
        $path = '/upload/employee_remarks/' . $fileName;

        $download = Excel::download(new EmployeesWithRemarksExport($this->rowsWithRemarks), $fileName)->getFile();

        $download->move(public_path('upload/employee_remarks'), $fileName);

        $this->file = url("/") . $path;
    }




    public function registerEvents(): array
    {
        return [
            AfterImport::class => [$this, 'afterImport']
        ];
    }
}
