<?php

namespace App\Mail;

use App\Models\Announcement;

/**
 * F-195: System Announcement Email (N-020, BR-319)
 *
 * Email sent to all targeted users for a system announcement.
 *
 * BR-315: All three channels: push + DB + email
 * BR-319: Subject: "DancyMeals Announcement" with first line as preview
 * BR-321: All text uses __() localization
 */
class SystemAnnouncementMail extends BaseMailableNotification
{
    public function __construct(
        private Announcement $announcement,
    ) {
        $this->initializeMailable();
    }

    /**
     * Get the email subject line.
     *
     * BR-319: "DancyMeals Announcement"
     */
    protected function getSubjectLine(): string
    {
        return $this->trans('DancyMeals Announcement');
    }

    /**
     * Get the blade view name for the email content.
     */
    protected function getEmailView(): string
    {
        return 'emails.system-announcement';
    }

    /**
     * Get the data to pass to the email view.
     *
     * @return array<string, mixed>
     */
    protected function getEmailData(): array
    {
        return [
            'announcement' => $this->announcement,
            'content' => $this->announcement->content,
            'contentPreview' => $this->announcement->getContentPreview(150),
            'sentAt' => $this->announcement->sent_at?->format('M d, Y H:i') ?? now()->format('M d, Y H:i'),
        ];
    }

    /**
     * Get the email type identifier for queue routing.
     */
    protected function getEmailType(): string
    {
        return 'general';
    }
}
