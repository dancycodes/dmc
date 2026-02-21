<?php

namespace App\Mail;

use App\Models\Complaint;

/**
 * F-193 BR-292/BR-293/BR-294: Complaint Resolved Email (N-012)
 *
 * Email sent to the client when their complaint is resolved.
 *
 * BR-293: Email is sent ONLY on resolution (not for other complaint events).
 * BR-294: Email includes: order ID, complaint category, resolution type,
 *         refund amount (if applicable), admin notes.
 */
class ComplaintResolvedMail extends BaseMailableNotification
{
    public function __construct(
        private Complaint $complaint
    ) {
        $this->forTenant($complaint->tenant);
        $this->initializeMailable();
    }

    /**
     * Get the email subject line.
     */
    protected function getSubjectLine(): string
    {
        return $this->trans('Complaint Resolved - Order :number', [
            'number' => $this->complaint->order?->order_number ?? '#',
        ]);
    }

    /**
     * Get the blade view name for the email content.
     */
    protected function getEmailView(): string
    {
        return 'emails.complaint-resolved';
    }

    /**
     * Get the data to pass to the email view.
     *
     * BR-294: Must include order ID, category, resolution type, refund amount, admin notes.
     *
     * @return array<string, mixed>
     */
    protected function getEmailData(): array
    {
        $resolutionType = $this->complaint->resolution_type ?? 'dismiss';
        $refundAmount = $this->complaint->refund_amount;
        $formattedRefundAmount = $refundAmount
            ? number_format((float) $refundAmount, 0, '.', ',').' XAF'
            : null;

        // BR-295: "View Complaint" button links to the client complaint tracking page
        $viewComplaintUrl = url('/my-orders/'.$this->complaint->order_id.'/complaint/'.$this->complaint->id);

        return [
            'complaint' => $this->complaint,
            'order' => $this->complaint->order,
            'complaintId' => $this->complaint->id,
            'orderNumber' => $this->complaint->order?->order_number ?? __('N/A'),
            'category' => $this->complaint->category,
            'categoryLabel' => $this->complaint->categoryLabel(),
            'resolutionType' => $resolutionType,
            'resolutionTypeLabel' => $this->complaint->resolutionTypeLabel(),
            'resolutionNotes' => $this->complaint->resolution_notes,
            'refundAmount' => $formattedRefundAmount,
            'viewComplaintUrl' => $viewComplaintUrl,
            'emailLocale' => $this->emailLocale,
        ];
    }

    /**
     * Resolution emails use the general queue.
     */
    protected function getEmailType(): string
    {
        return 'general';
    }
}
