<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SendOrganizationAssignmentToCoach extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public $organization;
    public $coach;
    public function __construct($organization,$coach)
    {
        $this->organization = $organization;
        $this->coach = $coach;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Organization Assigned!')
        ->view('emails.organizationAssigned')
        ->with(['organization'=>$this->organization,'coach'=>$this->coach]);
    }
}
