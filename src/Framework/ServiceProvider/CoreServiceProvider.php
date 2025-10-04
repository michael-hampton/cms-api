<?php

namespace App\Framework\ServiceProvider;

/**
 * Core Service Provider - Mail, Views, Validation, etc.
 */
class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Mail
        $this->container->singleton(\App\Framework\Mail\SMTPMailer::class);
        $this->container->afterResolving(\App\Framework\Mail\SMTPMailer::class, function ($mailer) {
            \App\Framework\Support\Mail::setMailer($mailer);
        });

        // View Engine
        $this->container->singleton(\App\Framework\View\SimpleTemplateEngine::class, function () {
            return new \App\Framework\View\SimpleTemplateEngine('views');
        });

        $this->container->singleton(\App\Framework\View\ViewRenderer::class, function () {
            return new \App\Framework\View\ViewRenderer(
                $this->container->resolve(\App\Framework\View\SimpleTemplateEngine::class)
            );
        });

        // Validator
        $this->container->singleton(\App\Framework\Validation\Validator::class, function () {
            return new \App\Framework\Validation\Validator(
                $this->container->resolve(\App\Framework\Database\Database::class)
            );
        });
    }
}