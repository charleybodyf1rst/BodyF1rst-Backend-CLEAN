<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CoachAssignment extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public $user;
    public $coach;
    public function __construct($coach,$user)
    {
        $this->user = $user;
        $this->coach = $coach;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Coach Assigned!')
        ->view('emails.userAssignment')
        ->with(['user'=>$this->user,'coach'=>$this->coach]);
    }
}
