(function () {
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof bootstrap === 'undefined') {
            return;
        }

        const pageConfig = window.SE_MY_APPLICATION_PAGE || {};
        const payload = pageConfig.applicationModalPayload || {};
        const modalEl = document.getElementById('applicationDetailsModal');
        if (!modalEl) {
            return;
        }

        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        const applicationMetaEl = document.getElementById('modalApplicationMeta');
        const periodEl = document.getElementById('modalPeriod');
        const statusEl = document.getElementById('modalStatusBadge');
        const schoolEl = document.getElementById('modalSchool');
        const nextActionTitleEl = document.getElementById('modalNextActionTitle');
        const nextActionDetailEl = document.getElementById('modalNextActionDetail');
        const timelineEl = document.getElementById('modalTimeline');
        const interviewScheduleEl = document.getElementById('modalInterviewSchedule');
        const interviewScheduleRowEl = document.getElementById('modalInterviewScheduleRow');
        const interviewLocationEl = document.getElementById('modalInterviewLocation');
        const interviewLocationRowEl = document.getElementById('modalInterviewLocationRow');
        const reviewNotesEl = document.getElementById('modalReviewNotes');
        const reviewNotesRowEl = document.getElementById('modalReviewNotesRow');
        const soaDeadlineEl = document.getElementById('modalSoaDeadline');
        const soaDeadlineRowEl = document.getElementById('modalSoaDeadlineRow');
        const soaSubmittedEl = document.getElementById('modalSoaSubmitted');
        const soaSubmittedRowEl = document.getElementById('modalSoaSubmittedRow');
        const docsListEl = document.getElementById('modalDocumentsList');
        const docsEmptyEl = document.getElementById('modalDocumentsEmpty');
        const docsCountEl = document.getElementById('modalDocumentsCount');
        const printBtnEl = document.getElementById('modalPrintBtn');
        const docPreviewModalEl = document.getElementById('applicantDocPreviewModal');
        const docPreviewTitleEl = document.getElementById('applicantDocPreviewModalLabel');
        const docPreviewFrameEl = document.getElementById('applicantDocPreviewFrame');
        const docPreviewNewTabEl = document.getElementById('applicantDocPreviewNewTab');
        const docPreviewModal = docPreviewModalEl ? bootstrap.Modal.getOrCreateInstance(docPreviewModalEl) : null;

        const openDocumentPreview = function (title, path) {
            if (!docPreviewModal || !docPreviewFrameEl || !path) {
                return;
            }

            const previewUrl = 'preview-document.php?file=' + encodeURIComponent(String(path));
            if (docPreviewTitleEl) {
                docPreviewTitleEl.textContent = title || 'Document Preview';
            }
            docPreviewFrameEl.src = previewUrl;
            if (docPreviewNewTabEl) {
                docPreviewNewTabEl.href = previewUrl;
            }
            docPreviewModal.show();
        };

        const openModal = function (appId) {
            const item = payload[String(appId)];
            if (!item) {
                return;
            }

            if (applicationMetaEl) {
                const metaParts = [String(item.application_no || '').trim()];
                metaParts.push(Number(item.is_archived || 0) === 1 ? 'Archived Period' : 'Active Period');
                applicationMetaEl.textContent = metaParts.filter(Boolean).join(' | ') || '-';
            }
            periodEl.textContent = item.period || '-';
            statusEl.textContent = (item.status_label || '-').toUpperCase();
            statusEl.className = 'badge ' + (item.status_badge_class || 'text-bg-secondary');
            schoolEl.textContent = [item.school_name || '', item.school_type || ''].filter(Boolean).join(' | ') || '-';

            if (nextActionTitleEl) {
                nextActionTitleEl.textContent = item.next_action_title || '-';
            }
            if (nextActionDetailEl) {
                const nextActionDetail = String(item.next_action_detail || '').trim();
                nextActionDetailEl.textContent = nextActionDetail || 'No additional action details.';
                nextActionDetailEl.classList.toggle('d-none', nextActionDetail === '');
            }

            if (timelineEl) {
                timelineEl.innerHTML = '';
                const timeline = Array.isArray(item.timeline) ? item.timeline : [];
                timeline.forEach(function (step) {
                    const stepWrap = document.createElement('div');
                    stepWrap.className = 'application-step application-step-' + String(step.state || 'upcoming');

                    const stepDot = document.createElement('div');
                    stepDot.className = 'application-step-dot';
                    stepWrap.appendChild(stepDot);

                    const stepLabel = document.createElement('div');
                    stepLabel.className = 'application-step-label';
                    stepLabel.textContent = String(step.short_label || step.label || 'Step');
                    stepWrap.appendChild(stepLabel);

                    timelineEl.appendChild(stepWrap);
                });
            }

            const reviewNotes = String(item.review_notes || '').trim();
            reviewNotesRowEl.classList.toggle('d-none', reviewNotes === '');
            reviewNotesEl.textContent = reviewNotes || '-';

            const interviewSchedule = String(item.interview_schedule || '').trim();
            interviewScheduleRowEl.classList.toggle('d-none', interviewSchedule === '');
            interviewScheduleEl.textContent = interviewSchedule || '-';

            const interviewLocation = String(item.interview_location || '').trim();
            interviewLocationRowEl.classList.toggle('d-none', interviewLocation === '');
            interviewLocationEl.textContent = interviewLocation || '-';

            const soaDeadline = String(item.soa_deadline || '').trim();
            soaDeadlineRowEl.classList.toggle('d-none', soaDeadline === '');
            soaDeadlineEl.textContent = soaDeadline || '-';

            const soaSubmitted = String(item.soa_submitted || '').trim();
            soaSubmittedRowEl.classList.toggle('d-none', soaSubmitted === '');
            soaSubmittedEl.textContent = soaSubmitted || '-';

            docsListEl.innerHTML = '';
            const docs = Array.isArray(item.documents) ? item.documents : [];
            docsEmptyEl.classList.toggle('d-none', docs.length > 0);
            if (docsCountEl) {
                docsCountEl.textContent = docs.length + ' file' + (docs.length === 1 ? '' : 's');
            }

            docs.forEach(function (doc) {
                const li = document.createElement('li');
                li.className = 'list-group-item d-flex justify-content-between align-items-center gap-2 flex-wrap';

                const name = document.createElement('span');
                name.className = 'small';
                const verificationStatus = String(doc.verification_status || '').trim();
                const statusSuffix = verificationStatus !== '' ? ' [' + verificationStatus.toUpperCase() + ']' : '';
                name.textContent = String(doc.name || 'Requirement') + statusSuffix;
                li.appendChild(name);

                if (doc.previewable && doc.path) {
                    const actionsWrap = document.createElement('div');
                    actionsWrap.className = 'd-flex align-items-center gap-1';

                    const viewBtn = document.createElement('button');
                    viewBtn.type = 'button';
                    viewBtn.className = 'btn btn-outline-primary btn-sm';
                    viewBtn.textContent = 'View';
                    viewBtn.addEventListener('click', function () {
                        openDocumentPreview(String(doc.name || 'Document Preview'), String(doc.path || ''));
                    });
                    actionsWrap.appendChild(viewBtn);

                    const openBtn = document.createElement('a');
                    openBtn.className = 'btn btn-outline-secondary btn-sm';
                    openBtn.href = 'preview-document.php?file=' + encodeURIComponent(String(doc.path));
                    openBtn.target = '_blank';
                    openBtn.rel = 'noopener noreferrer';
                    openBtn.title = 'Open in new tab';
                    openBtn.setAttribute('aria-label', 'Open in new tab');
                    openBtn.innerHTML = '<i class="fa-solid fa-up-right-from-square"></i>';
                    actionsWrap.appendChild(openBtn);

                    li.appendChild(actionsWrap);
                }

                docsListEl.appendChild(li);
            });

            printBtnEl.setAttribute('href', String(item.print_url || '#'));
            modal.show();
        };

        document.querySelectorAll('.js-open-application-modal-row').forEach(function (row) {
            row.addEventListener('click', function () {
                openModal(row.getAttribute('data-app-id') || '');
            });
            row.addEventListener('keydown', function (event) {
                if (event.key !== 'Enter' && event.key !== ' ') {
                    return;
                }
                event.preventDefault();
                openModal(row.getAttribute('data-app-id') || '');
            });
        });

        document.querySelectorAll('.js-open-application-modal').forEach(function (button) {
            button.addEventListener('click', function () {
                openModal(button.getAttribute('data-app-id') || '');
            });
        });

        if (docPreviewModalEl) {
            docPreviewModalEl.addEventListener('hidden.bs.modal', function () {
                if (docPreviewFrameEl) {
                    docPreviewFrameEl.src = 'about:blank';
                }
                if (docPreviewNewTabEl) {
                    docPreviewNewTabEl.href = '#';
                }
            });
        }
    });
}());
