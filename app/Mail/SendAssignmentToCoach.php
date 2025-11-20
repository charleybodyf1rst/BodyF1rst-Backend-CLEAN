<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SendAssignmentToCoach extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public $coach;
    public $organizations;
    public $users;

    public function __construct($coach,$organizations,$users)
    {
        $this->coach = $coach;
        $this->organizations = $organizations;
        $this->users = $users;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Organizations/Users Assigned!')
        ->view('emails.coachAssignment')
        ->with(['organizations'=>$this->organizations,'users'=>$this->users,'coach'=>$this->coach]);
    }
}
