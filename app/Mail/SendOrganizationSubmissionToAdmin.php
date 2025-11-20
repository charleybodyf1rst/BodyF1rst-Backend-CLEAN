<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SendOrganizationSubmissionToAdmin extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public $organization;
    public $admin;
    public function __construct($organization,$admin)
    {
        $this->organization = $organization;
        $this->admin = $admin;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Organization Submitted File. Please Check it Out!')
        ->view('emails.organizationSubmitted')
        ->with(['organization'=>$this->organization,'admin'=>$this->admin]);
    }
}
