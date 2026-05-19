<?php

namespace App\Services\Gdpr\Exporters;

use App\Models\Member;

final class ProfileExporter implements MemberDataExporter
{
    public function key(): string
    {
        return 'profile';
    }

    public function export(Member $member): array
    {
        return [
            'id'                       => $member->id,
            'first_name'               => $member->first_name,
            'last_name'                => $member->last_name,
            'email'                    => $member->email,
            'phone'                    => $member->phone,
            'company_name'             => $member->company_name,
            'job_title'                => $member->job_title,
            'vat_number'               => $member->vat_number,
            'region'                   => $member->region,
            'timezone'                 => $member->timezone,
            'is_active'                => $member->is_active,
            'email_verified_at'        => $member->email_verified_at?->format('Y-m-d H:i:s'),
            'last_login_at'            => $member->last_login_at?->format('Y-m-d H:i:s'),
            'created_at'               => $member->created_at?->format('Y-m-d H:i:s'),
            'communication_preferences'=> $member->communication_preferences,
            'show_activity'            => $member->show_activity,
            'show_badges'              => $member->show_badges,
            'stripe_customer_id'       => $member->stripe_customer_id,
        ];
    }
}