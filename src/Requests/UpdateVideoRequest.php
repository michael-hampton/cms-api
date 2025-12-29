<?php

namespace App\Requests;

use App\Framework\Http\FormRequest;
use App\Models\Video;
use App\Policies\VideoPolicy;
use App\Repositories\VideoRepository;

class UpdateVideoRequest extends FormRequest
{
    protected static string $model = Video::class;
    private VideoRepository $videoRepository;

    public function __construct()
    {
        parent::__construct();
        $this->videoRepository = new VideoRepository();
    }

    protected static function model(): string
    {
        return Video::class;
    }

    public function rules(): array
    {
        return [
            'title' => 'string|max:255',
            'description' => 'string'
        ];
    }

    protected function getPolicyClass(): ?string
    {
        return VideoPolicy::class;
    }
}