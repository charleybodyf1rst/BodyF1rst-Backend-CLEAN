<?php

namespace App\Services;

use App\Mail\AppointmentConfirmation;
use App\Mail\AppointmentReminder;
use App\Mail\AppointmentCancelled;
use App\Mail\AppointmentRescheduled;
use App\Models\Appointment;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Service for handling automated email notifications for appointments
 */
class AppointmentNotificationService
{
    /**
     * Send appointment confirmation email
     *
     * @param Appointment $appointment
     * @return bool
     */
    public function sendConfirmation(Appointment $appointment)
    {
        try {
            $client = $appointment->client;
            $coach = $appointment->coach;

            if (!$client || !$client->email) {
                Log::warning("No client email found for appointment {$appointment->id}");
                return false;
            }

            $data = [
                'client_name' => $client->first_name . ' ' . $client->last_name,
                'coach_name' => $coach->first_name . ' ' . $coach->last_name,
                'appointment_type' => $appointment->type,
                'appointment_date' => Carbon::parse($appointment->scheduled_at)->format('l, F j, Y'),
                'appointment_time' => Carbon::parse($appointment->scheduled_at)->format('g:i A'),
                'duration' => $appointment->duration ?? 60,
                'location' => $appointment->location ?? 'Studio',
                'notes' => $appointment->notes
            ];

            Mail::to($client->email)->send(new AppointmentConfirmation($data));

            Log::info("Confirmation email sent for appointment {$appointment->id}");
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to send confirmation email for appointment {$appointment->id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send appointment reminder email (24 hours before)
     *
     * @param Appointment $appointment
     * @return bool
     */
    public function sendReminder(Appointment $appointment)
    {
        try {
            $client = $appointment->client;
            $coach = $appointment->coach;

            if (!$client || !$client->email) {
                Log::warning("No client email found for appointment {$appointment->id}");
                return false;
            }

            $startTime = Carbon::parse($appointment->scheduled_at);
            $hoursUntil = Carbon::now()->diffInHours($startTime);

            $data = [
                'client_name' => $client->first_name . ' ' . $client->last_name,
                'coach_name' => $coach->first_name . ' ' . $coach->last_name,
                'appointment_type' => $appointment->type,
                'appointment_date' => $startTime->format('l, F j, Y'),
                'appointment_time' => $startTime->format('g:i A'),
                'duration' => $appointment->duration ?? 60,
                'location' => $appointment->location ?? 'Studio',
                'hours_until' => $hoursUntil,
                'notes' => $appointment->notes
            ];

            Mail::to($client->email)->send(new AppointmentReminder($data));

            // Mark that reminder was sent
            $appointment->reminder_sent = true;
            $appointment->save();

            Log::info("Reminder email sent for appointment {$appointment->id}");
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to send reminder email for appointment {$appointment->id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send appointment cancellation email
     *
     * @param Appointment $appointment
     * @param string|null $reason
     * @return bool
     */
    public function sendCancellation(Appointment $appointment, $reason = null)
    {
        try {
            $client = $appointment->client;
            $coach = $appointment->coach;

            if (!$client || !$client->email) {
                Log::warning("No client email found for appointment {$appointment->id}");
                return false;
            }

            $data = [
                'client_name' => $client->first_name . ' ' . $client->last_name,
                'coach_name' => $coach->first_name . ' ' . $coach->last_name,
                'appointment_type' => $appointment->type,
                'appointment_date' => Carbon::parse($appointment->scheduled_at)->format('l, F j, Y'),
                'appointment_time' => Carbon::parse($appointment->scheduled_at)->format('g:i A'),
                'cancellation_reason' => $reason
            ];

            Mail::to($client->email)->send(new AppointmentCancelled($data));

            Log::info("Cancellation email sent for appointment {$appointment->id}");
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to send cancellation email for appointment {$appointment->id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send appointment rescheduled email
     *
     * @param Appointment $oldAppointment
     * @param Appointment $newAppointment
     * @return bool
     */
    public function sendRescheduled(Appointment $oldAppointment, Appointment $newAppointment)
    {
        try {
            $client = $newAppointment->client;
            $coach = $newAppointment->coach;

            if (!$client || !$client->email) {
                Log::warning("No client email found for appointment {$newAppointment->id}");
                return false;
            }

            $data = [
                'client_name' => $client->first_name . ' ' . $client->last_name,
                'coach_name' => $coach->first_name . ' ' . $coach->last_name,
                'appointment_type' => $newAppointment->type,
                'old_date' => Carbon::parse($oldAppointment->scheduled_at)->format('l, F j, Y'),
                'old_time' => Carbon::parse($oldAppointment->scheduled_at)->format('g:i A'),
                'new_date' => Carbon::parse($newAppointment->scheduled_at)->format('l, F j, Y'),
                'new_time' => Carbon::parse($newAppointment->scheduled_at)->format('g:i A'),
                'duration' => $newAppointment->duration ?? 60,
                'location' => $newAppointment->location ?? 'Studio',
                'notes' => $newAppointment->notes
            ];

            Mail::to($client->email)->send(new AppointmentRescheduled($data));

            Log::info("Rescheduled email sent for appointment {$newAppointment->id}");
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to send rescheduled email for appointment {$newAppointment->id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send reminders for all appointments happening in 24 hours
     * This method should be called by a scheduled task
     *
     * @return int Number of reminders sent
     */
    public function sendUpcomingReminders()
    {
        try {
            $tomorrow = Carbon::now()->addDay();
            $remindersSent = 0;

            // Get appointments happening in next 24 hours that haven't been reminded
            $appointments = Appointment::where('scheduled_at', '>=', Carbon::now())
                ->where('scheduled_at', '<=', $tomorrow)
                ->where('status', 'confirmed')
                ->where(function($query) {
                    $query->where('reminder_sent', false)
                          ->orWhereNull('reminder_sent');
                })
                ->with(['client', 'coach'])
                ->get();

            foreach ($appointments as $appointment) {
                if ($this->sendReminder($appointment)) {
                    $remindersSent++;
                }
            }

            Log::info("Sent {$remindersSent} appointment reminders");
            return $remindersSent;
        } catch (\Exception $e) {
            Log::error("Failed to send upcoming reminders: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Send no-show follow-up email
     *
     * @param Appointment $appointment
     * @return bool
     */
    public function sendNoShowFollowUp(Appointment $appointment)
    {
        try {
            $client = $appointment->client;
            $coach = $appointment->coach;

            if (!$client || !$client->email) {
                Log::warning("No client email found for appointment {$appointment->id}");
                return false;
            }

            $data = [
                'client_name' => $client->first_name . ' ' . $client->last_name,
                'coach_name' => $coach->first_name . ' ' . $coach->last_name,
                'appointment_type' => $appointment->type,
                'appointment_date' => Carbon::parse($appointment->scheduled_at)->format('l, F j, Y'),
                'appointment_time' => Carbon::parse($appointment->scheduled_at)->format('g:i A')
            ];

            // Use existing reminder template for now, can create specific no-show template later
            Mail::to($client->email)->send(new AppointmentReminder($data));

            Log::info("No-show follow-up email sent for appointment {$appointment->id}");
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to send no-show email for appointment {$appointment->id}: " . $e->getMessage());
            return false;
        }
    }
}
