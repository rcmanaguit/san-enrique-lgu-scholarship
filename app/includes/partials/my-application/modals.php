<?php
declare(strict_types=1);
?>
<div class="modal fade" id="applicationDetailsModal" tabindex="-1" aria-labelledby="applicationDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h2 class="modal-title h6 m-0" id="applicationDetailsModalLabel">Application Details</h2>
                    <div class="small text-muted mt-1" id="modalApplicationMeta">-</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="applicant-next-action mb-3">
                    <div class="small text-muted text-uppercase mb-1">Next Action</div>
                    <div class="fw-semibold" id="modalNextActionTitle">-</div>
                    <div class="small text-muted mt-1" id="modalNextActionDetail">-</div>
                </div>
                <div class="application-stepper application-stepper-compact mb-3" id="modalTimeline"></div>
                <div class="review-kv">
                    <div class="review-kv-row">
                        <div class="review-kv-label">Period</div>
                        <div class="review-kv-value" id="modalPeriod">-</div>
                    </div>
                    <div class="review-kv-row">
                        <div class="review-kv-label">Status</div>
                        <div class="review-kv-value"><span class="badge" id="modalStatusBadge">-</span></div>
                    </div>
                    <div class="review-kv-row">
                        <div class="review-kv-label">School</div>
                        <div class="review-kv-value" id="modalSchool">-</div>
                    </div>
                    <div class="review-kv-row" id="modalInterviewScheduleRow">
                        <div class="review-kv-label">Interview Schedule</div>
                        <div class="review-kv-value" id="modalInterviewSchedule">-</div>
                    </div>
                    <div class="review-kv-row" id="modalInterviewLocationRow">
                        <div class="review-kv-label">Interview Location</div>
                        <div class="review-kv-value" id="modalInterviewLocation">-</div>
                    </div>
                    <div class="review-kv-row" id="modalReviewNotesRow">
                        <div class="review-kv-label">Review Notes</div>
                        <div class="review-kv-value" id="modalReviewNotes">-</div>
                    </div>
                    <div class="review-kv-row" id="modalSoaDeadlineRow">
                        <div class="review-kv-label">SOA Deadline</div>
                        <div class="review-kv-value" id="modalSoaDeadline">-</div>
                    </div>
                    <div class="review-kv-row" id="modalSoaSubmittedRow">
                        <div class="review-kv-label">SOA Submitted</div>
                        <div class="review-kv-value" id="modalSoaSubmitted">-</div>
                    </div>
                </div>

                <details class="workflow-panel workflow-panel-collapsible mt-3" open>
                    <summary>
                        <span>Uploaded Documents</span>
                        <small id="modalDocumentsCount">0 file(s)</small>
                    </summary>
                    <div class="workflow-panel-body">
                        <div id="modalDocumentsEmpty" class="small text-muted d-none">No uploaded documents found.</div>
                        <ul class="list-group" id="modalDocumentsList"></ul>
                    </div>
                </details>
            </div>
            <div class="modal-footer justify-content-between flex-wrap gap-2">
                <a href="#" class="btn btn-outline-primary btn-sm" id="modalPrintBtn">
                    <i class="fa-solid fa-print me-1"></i>Print
                </a>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="applicantDocPreviewModal" tabindex="-1" aria-labelledby="applicantDocPreviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h6 m-0" id="applicantDocPreviewModalLabel">Document Preview</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <iframe
                    id="applicantDocPreviewFrame"
                    src="about:blank"
                    title="Document Preview"
                    style="border:0;width:100%;height:100%;background:#fff;"
                ></iframe>
            </div>
            <div class="modal-footer justify-content-between">
                <a href="#" id="applicantDocPreviewNewTab" class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener noreferrer">
                    <i class="fa-solid fa-up-right-from-square me-1"></i>Open in New Tab
                </a>
                <button type="button" class="btn btn-primary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
