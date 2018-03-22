<?php

namespace App\Jobs;

use App\Booking;
use App\Events\NotifictionsBeforeBookingEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class NotificationsBeforeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $booking, $user;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Booking $booking, $user)
    {
        $this->booking = $booking;
        $this->user    = $user;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if($this->booking->is_accepted == true){
            event(new NotifictionsBeforeBookingEvent($this->booking, $this->user));
        }
    }
}
