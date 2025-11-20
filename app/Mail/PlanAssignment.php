<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PlanAssignment extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public $plan;
    public $user;
    public function __construct($plan,$user)
    {
        $this->plan = $plan;
        $this->user = $user;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Plan Assigned!')
        ->view('emails.planAssigned')
        ->with(['plan'=>$this->plan,'user'=>$this->user]);
    }
}
