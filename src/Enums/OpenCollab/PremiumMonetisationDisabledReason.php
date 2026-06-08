<?php

namespace App\Enums\OpenCollab;

enum PremiumMonetisationDisabledReason: string
{
    case PolicyBreach     = 'policy_breach';
    case CopyrightIssue   = 'copyright_issue';
    case QualityIssue     = 'quality_issue';
    case ContributorIssue = 'contributor_issue';
    case EmergencyTakedown = 'emergency_takedown';
    case AdminDecision    = 'admin_decision';
    case Other            = 'other';

    public function label(): string
    {
        return match ($this) {
            self::PolicyBreach      => 'Policy breach',
            self::CopyrightIssue    => 'Copyright issue',
            self::QualityIssue      => 'Quality issue',
            self::ContributorIssue  => 'Contributor issue',
            self::EmergencyTakedown => 'Emergency takedown',
            self::AdminDecision     => 'Admin decision',
            self::Other             => 'Other',
        };
    }
}