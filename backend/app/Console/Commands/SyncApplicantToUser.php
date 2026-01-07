<?php

namespace App\Console\Commands;

use App\Models\Applicant;
use Illuminate\Console\Command;

class SyncApplicantToUser extends Command
{
    protected $signature = 'applicants:sync-to-users';
    protected $description = 'Sync applicant personal data to corresponding users table';

    public function handle(): int
    {
        $applicants = Applicant::with('user')
            ->whereNotNull('first_name')
            ->get();

        $synced = 0;

        foreach ($applicants as $applicant) {
            $user = $applicant->user;
            if (!$user) {
                continue;
            }

            $userUpdates = [];

            if ($applicant->first_name) {
                $userUpdates['first_name'] = $applicant->first_name;
            }
            if ($applicant->last_name_1 || $applicant->last_name_2) {
                $userUpdates['last_name'] = trim("{$applicant->last_name_1} {$applicant->last_name_2}");
            }
            if ($applicant->full_name) {
                $userUpdates['name'] = $applicant->full_name;
            }
            if ($applicant->email && $applicant->email !== $user->email) {
                $userUpdates['email'] = $applicant->email;
            }

            if (!empty($userUpdates)) {
                $user->update($userUpdates);
                $synced++;
                $this->info("Synced user {$user->id}: {$applicant->full_name}");
            }
        }

        $this->info("Synced {$synced} users from applicant data.");

        return Command::SUCCESS;
    }
}
