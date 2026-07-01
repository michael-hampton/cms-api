<?php
namespace App\Enums\Pages;

enum PageType: string
{
    case Page = 'page';
    case Article = 'article';
    case Content = 'content';
    case Review = 'review';
    case LandingPage = 'landing-page';
}