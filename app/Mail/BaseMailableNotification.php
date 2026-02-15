<?php

namespace App\Mail;

use App\Models\Tenant;
use App\Models\User;
use App\Services\EmailNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Base mailable notification for DancyMeals.
 *
 * All platform emails extend this class. It provides:
 * - Consistent DancyMeals branding via the base email layout
 * - Tenant branding support for tenant-context emails
 * - Locale-aware content based on recipient preferences (BR-118)
 * - Queued delivery with exponential backoff retries (BR-114, BR-120)
 * - Queue priority routing for critical emails (BR-121)
 *
 * Subclasses must implement getSubjectLine(), getEmailView(),
 * and getEmailData() to customize their content.
 */
abstract class BaseMailableNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * The number of times the queued job may be attempted (BR-120).
     */
    public int $tries = 3;

    /**
     * The backoff strategy in seconds (exponential: 10s, 30s, 90s).
     *
     * @var array<int, int>
     */
    public array $backoff = [10, 30, 90];

    /**
     * The recipient user (for locale resolution).
     */
    protected ?User $recipient = null;

    /**
     * The tenant context for branded emails (BR-116).
     */
    protected ?Tenant $tenant = null;

    /**
     * The resolved locale for the email content.
     */
    protected string $emailLocale = 'en';

    /**
     * Initialize the base mailable with afterCommit and queue routing.
     * Subclasses should call parent::initializeMailable() if they have constructors.
     */
    protected function initializeMailable(): void
    {
        $this->afterCommit();
        $this->applyQueueRouting();
    }

    /**
     * Set the recipient user for locale-aware emails.
     */
    public function forRecipient(User $user): static
    {
        $this->recipient = $user;
        $this->resolveEmailLocale();
        $this->applyQueueRouting();

        return $this;
    }

    /**
     * Set the tenant context for branded emails.
     */
    public function forTenant(?Tenant $tenant): static
    {
        $this->tenant = $tenant;

        return $this;
    }

    /**
     * Build the mailable, applying queue routing and afterCommit.
     * Called automatically by Laravel before sending.
     */
    public function build(): static
    {
        $this->initializeMailable();

        return $this;
    }

    /**
     * Get the email subject line.
     * Subclasses must implement this with localized content.
     */
    abstract protected function getSubjectLine(): string;

    /**
     * Get the blade view name for the email content.
     * This view will be rendered inside the base email layout.
     */
    abstract protected function getEmailView(): string;

    /**
     * Get the data to pass to the email view.
     * Merged with common template data from EmailNotificationService.
     *
     * @return array<string, mixed>
     */
    abstract protected function getEmailData(): array;

    /**
     * Get the email type identifier for queue routing.
     * Override in subclasses for critical emails (e.g., 'password_reset').
     */
    protected function getEmailType(): string
    {
        return 'general';
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->getSubjectLine(),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $emailService = app(EmailNotificationService::class);
        $commonData = $emailService->getCommonTemplateData($this->tenant, $this->recipient);

        return new Content(
            view: $this->getEmailView(),
            with: array_merge($commonData, $this->getEmailData(), [
                'emailLocale' => $this->emailLocale,
            ]),
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }

    /**
     * Determine the queue name for this mailable based on email type.
     */
    public function determineQueueName(): string
    {
        $emailService = app(EmailNotificationService::class);

        return $emailService->getQueueName($this->getEmailType());
    }

    /**
     * Apply queue routing based on the email type.
     * Sets the queue name on the Queueable trait's $queue property.
     */
    protected function applyQueueRouting(): void
    {
        $this->onQueue($this->determineQueueName());
    }

    /**
     * Handle a failed email delivery.
     * Logs the failure for admin review (BR-120).
     */
    public function failed(Throwable $exception): void
    {
        report($exception);
    }

    /**
     * Resolve the email locale from the recipient's preference.
     */
    protected function resolveEmailLocale(): void
    {
        $emailService = app(EmailNotificationService::class);
        $this->emailLocale = $emailService->resolveLocale($this->recipient);
    }

    /**
     * Translate a string using the email's resolved locale.
     * Used by subclasses to localize email content.
     */
    protected function trans(string $key, array $replace = []): string
    {
        return __($key, $replace, $this->emailLocale);
    }
}
