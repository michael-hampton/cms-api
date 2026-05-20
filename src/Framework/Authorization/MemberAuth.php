<?php

namespace App\Framework\Authorization;

use App\Framework\Session\Session;
use App\Framework\Support\Event;
use App\Framework\Support\SiteContext;
use App\Models\Member;

class MemberAuth
{
    public static ?AuthenticatedMember $member = null;

    public static function attempt(array $credentials, ?int $siteId = null): bool
    {
        $email = $credentials['email'] ?? null;
        $password = $credentials['password'] ?? null;
        $siteId = $siteId ?? SiteContext::getId();

        if (!$email || !$password) {
            return false;
        }

        $member = Member::findByEmail($email, null);

        if (!$member) {
            return false;
        }

        if (!$member->isActive()) {
            return false;
        }

//        if (!$member->isEmailVerified()) {
//            return false;
//        }

        if (!$member->verifyPassword($password)) {
            return false;
        }

        self::login($member);
        return true;
    }

    public static function login(Member $member): void
    {
        self::$member = self::buildAuthenticatedMember($member);

        // Store in session
        Session::put('member_id', $member->id);
        Session::put('member_email', $member->email);
        Session::put('member_first_name', $member->first_name);
        Session::put('member_last_name', $member->last_name);
        Session::put('member_display_name', $member->display_name);
        Session::put('member_roles', self::$member->roles);
        Session::put('member_authenticated', true);

        // Regenerate session ID for security
        Session::regenerate();

        $member = Member::where('id', $member->id)->first();

        // Update last login
        $member->update([
            'last_login_at' => date('Y-m-d H:i:s')
        ]);

        Event::fire('member.login', $member->toArray());
    }

    public static function authenticateApi(Member $member): void
    {
        self::$member = self::buildAuthenticatedMember($member);
    }

    public static function logout(): void
    {
        $member = self::$member;
        self::$member = null;

        Session::forgetMultiple([
            'member_id',
            'member_email',
            'member_first_name',
            'member_last_name',
            'member_display_name',
            'member_roles',
            'member_authenticated'
        ]);

        Session::regenerate();

        if ($member) {
            // Event::fire('member.logout');
        }
    }

    public static function check(): bool
    {
        return self::member() !== null;
    }

    public static function guest(): bool
    {
        return !self::check();
    }

    public static function getMember(): ?Member
    {
        if (self::$member !== null) {
            return Member::find(self::$member->id);
        }

        $memberId = Session::get('member_id');

        if (!$memberId) {
            return null;
        }

        return Member::find($memberId);
    }

    public static function member(): ?AuthenticatedMember
    {
        if (self::$member !== null) {
            return self::$member;
        }

        if (Session::get('member_authenticated') === true && Session::has('member_id')) {

            $member = self::getMember();

            if (!$member || !$member->isActive()) {
                self::logout();
                return null;
            }

            $roles = $member->roles()?->get();

            $rolesSlugs = $roles->pluck('slug')->toArray();

            self::$member = self::buildAuthenticatedMember($member, $rolesSlugs);

            return self::$member;
        }

        return null;
    }

    public static function id(): ?int
    {
        $member = self::member();
        return $member ? $member->id : null;
    }

    public static function setMember(?AuthenticatedMember $member): void
    {
        self::$member = $member;
    }

    private static function buildAuthenticatedMember(Member $member, ?array $rolesSlugs = null): AuthenticatedMember
    {
        if ($rolesSlugs === null) {
            $member->roles(true);
            $rolesSlugs = $member->roles(true)->get()->pluck('slug')->toArray();
        }

        $authenticatedMember = new AuthenticatedMember(
            $member->id,
            $member->email,
            $member->first_name,
            $member->last_name,
            $member->display_name,
            $rolesSlugs
        );
        $authenticatedMember->exists = true;

        return $authenticatedMember;
    }
}
