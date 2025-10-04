<?php

namespace App\Controllers;

use App\Framework\Http\Request;
use App\Repositories\EventSignupRepository;
use App\Services\EmailService;
use App\Services\Url\UrlResolutionResult;

class EventController extends Controller
{
    public function __construct(
        private EventSignupRepository $eventSignupRepository,
        private EmailService          $emailService
    )
    {
        parent::__construct();
    }

    public function signup(Request $request)
    {
        try {
            // Combine first and last name if provided separately
            if (!$request->has('name') && ($request->has('first_name') || $request->has('last_name'))) {
                $data['name'] = trim(($request->get('first_name') ?? '') . ' ' . ($request->get('last_name') ?? ''));
            }

            $signup = $this->eventSignupRepository->createSignup($request->all());

            // Send confirmation email
            if ($request->has('event_title')) {
                $this->emailService->sendEventConfirmation($signup, $request->get('event_title') );;
            }

            return $this->jsonResponse([
                'success' => true,
                'message' => 'Event signup successful! Check your email for confirmation.',
                'signup_id' => $signup->id
            ]);

        } catch (\Exception $e) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to process signup. Please try again.'
            ], 400);
        }
    }
}