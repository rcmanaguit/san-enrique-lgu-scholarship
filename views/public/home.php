<?php require __DIR__ . '/../layouts/header.php'; ?>
<?php $publicAnnouncements = \App\Models\Announcement::latestForAudience('Public', 3); ?>
<?php
$applicationSettings = \App\Models\ApplicationPeriod::getCurrent();
$isApplicationOpen = \App\Models\ApplicationPeriod::isAcceptingApplications($applicationSettings);
$activePeriodLabel = \App\Models\ApplicationPeriod::activePeriodLabel($applicationSettings);
?>

<main class="public-site">
    <nav class="public-navbar navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-3" href="<?php echo htmlspecialchars(base_url()); ?>">
                <img src="<?php echo htmlspecialchars(asset_url('images/lgu-logo.png')); ?>" alt="San Enrique LGU Logo" class="public-navbar-logo">
                <span class="public-navbar-brand-copy">
                    <span class="d-block fw-bold">San Enrique LGU Scholarship</span>
                    <span class="public-navbar-subtitle">Municipality of San Enrique, Negros Occidental</span>
                </span>
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#publicNavbar"
                aria-controls="publicNavbar" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="publicNavbar">
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link" href="#procedure">Application Guide</a></li>
                    <li class="nav-item"><a class="nav-link" href="#announcements">Announcements</a></li>
                </ul>
                <div class="d-flex ms-lg-3 public-navbar-login">
                    <a href="<?php echo htmlspecialchars(base_url('login')); ?>" class="btn btn-warning fw-bold">Login</a>
                </div>
            </div>
        </div>
    </nav>

    <section class="public-hero">
        <div class="public-hero-overlay"></div>
        <div class="container public-hero-content">
            <div class="row">
                <div class="col-lg-8">
                    <p class="public-hero-kicker mb-2">Official Scholarship Portal</p>
                    <h1 class="public-hero-title">San Enrique LGU Scholarship Program</h1>
                    <p class="public-hero-text">
                        Online scholarship application and monitoring system for qualified college students of the
                        Municipality of San Enrique, Negros Occidental.
                    </p>
                    <div class="mt-4 public-hero-status">
                        <?php if ($isApplicationOpen): ?>
                            <div class="alert alert-warning d-inline-flex align-items-center gap-2 mb-0 px-3 py-2">
                                <i class="fa-solid fa-circle-check"></i>
                                <span><strong>Applications are open.</strong> <?php echo htmlspecialchars($activePeriodLabel); ?></span>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-light d-inline-flex align-items-center gap-2 mb-0 px-3 py-2">
                                <i class="fa-solid fa-circle-info"></i>
                                <span><strong>Applications are currently closed.</strong> <?php echo htmlspecialchars($activePeriodLabel); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="public-hero-actions mt-4">
                        <?php if ($isApplicationOpen): ?>
                            <a href="<?php echo htmlspecialchars(base_url('register')); ?>" class="btn btn-warning btn-lg fw-bold px-4">Apply Now</a>
                        <?php else: ?>
                            <button type="button" class="btn btn-secondary btn-lg fw-bold px-4" disabled>Applications Closed</button>
                        <?php endif; ?>
                        <a href="<?php echo htmlspecialchars(base_url('login')); ?>" class="btn btn-outline-light btn-lg fw-bold px-4">Portal Login</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="public-section public-guide-section" id="eligibility">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <article class="public-guide-document" id="procedure">
                        <header class="public-guide-document-header">
                            <span class="public-guide-kicker">Guide</span>
                            <h2 class="public-section-title mb-2">Scholarship Application Guide</h2>
                            <p class="public-guide-intro mb-0">Official guide for scholarship applicants of the Municipality of San Enrique, Negros Occidental.</p>
                        </header>

                        <div class="public-guide-jump-nav">
                            <a href="#guide-eligibility" class="public-guide-jump-link">Eligibility</a>
                            <a href="#guide-requirements" class="public-guide-jump-link">Requirements</a>
                            <a href="#guide-procedure" class="public-guide-jump-link">Procedure</a>
                            <a href="#guide-reminders" class="public-guide-jump-link">Reminders</a>
                        </div>

                        <section class="public-guide-entry" id="guide-eligibility">
                            <h3 class="public-guide-entry-title">Eligibility</h3>
                            <p class="public-guide-intro mb-0">The San Enrique LGU Scholarship Program is open to all college students who are bona fide residents of the Municipality of San Enrique, Negros Occidental.</p>
                        </section>

                        <section class="public-guide-entry" id="guide-requirements">
                            <h3 class="public-guide-entry-title">Initial Requirements</h3>
                            <p class="public-guide-intro">Applicants are advised to prepare the following initial requirements before filing their online scholarship application:</p>
                            <ul class="public-guide-list">
                                <li>Clear copy of the latest Report Card or Copy of Grades</li>
                                <li>Certificate of Barangay Residency</li>
                                <li>2x2 ID Picture</li>
                                <li>Digital Signature</li>
                                <li>Statement of Account (SOA) issued by the school, to be submitted only after passing the interview</li>
                            </ul>
                        </section>

                        <section class="public-guide-entry" id="guide-procedure">
                            <h3 class="public-guide-entry-title">Scholarship Application Procedure</h3>
                            <p class="public-guide-intro">Applicants may follow the steps below to submit their scholarship application through the online portal:</p>
                            <ol class="public-guide-steps public-guide-steps-document">
                                <li>
                                    <h3>Create an account</h3>
                                    <p>Create an account in the scholarship portal using your mobile number.</p>
                                </li>
                                <li>
                                    <h3>Verify your account</h3>
                                    <p>Verify your account through the one-time password (OTP) sent to your registered mobile number.</p>
                                </li>
                                <li>
                                    <h3>Complete the online application form</h3>
                                    <p>Log in to the portal and complete the online scholarship application form.</p>
                                </li>
                                <li>
                                    <h3>Upload the documentary requirements</h3>
                                    <p>Upload the required documentary requirements and provide your 2x2 photo and digital signature.</p>
                                </li>
                                <li>
                                    <h3>Review and submit your application</h3>
                                    <p>Review your application, confirm the certification and data privacy consent, then submit your application.</p>
                                </li>
                                <li>
                                    <h3>Wait for evaluation and updates</h3>
                                    <p>Wait for the evaluation of your application and monitor your account for updates on document review, interview schedule, and final results.</p>
                                </li>
                                <li>
                                    <h3>Attend the interview when scheduled</h3>
                                    <p>If your application passes document review, wait for your interview schedule and appear on the assigned date, time, and venue.</p>
                                </li>
                                <li>
                                    <h3>Submit your Statement of Account when required</h3>
                                    <p>If you pass the interview, upload the Statement of Account (SOA) issued by your school within the deadline set by the scholarship office.</p>
                                </li>
                                <li>
                                    <h3>Wait for final approval and payout schedule</h3>
                                    <p>After the final review of your records, monitor your account for the final approval result and the release of your payout schedule.</p>
                                </li>
                            </ol>
                            <div class="public-guide-actions">
                                <?php if ($isApplicationOpen): ?>
                                    <a href="<?php echo htmlspecialchars(base_url('register')); ?>" class="btn btn-primary btn-lg px-4">Start Application</a>
                                <?php else: ?>
                                    <button type="button" class="btn btn-secondary btn-lg px-4" disabled>Application Period Closed</button>
                                <?php endif; ?>
                            </div>
                        </section>

                        <section class="public-guide-entry" id="guide-reminders">
                            <h3 class="public-guide-entry-title">Important Reminders</h3>
                            <p class="public-guide-intro">Applicants are reminded to observe the following when preparing and submitting their scholarship requirements:</p>
                            <ul class="public-guide-list">
                                <li>Upload clear, readable, and complete copies of all required documents.</li>
                                <li>Make sure that documents requiring signatures have complete and proper signatories.</li>
                                <li>Blurry, incomplete, cropped, or unreadable files may be rejected and returned for correction.</li>
                                <li>Use only updated and valid documents issued by the proper barangay, school, or authorized office.</li>
                                <li>Keep your registered mobile number active to receive OTP, interview schedule, and payout updates.</li>
                                <li>Monitor your scholarship account regularly for announcements, remarks, and status changes.</li>
                                <li>Bring the original physical Statement of Account during payout, if required by the scholarship office.</li>
                            </ul>
                        </section>
                    </article>
                </div>
            </div>
        </div>
    </section>

    <section class="public-section public-section-alt" id="announcements">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <h2 class="public-section-title text-center mb-4">Latest Announcements</h2>
                    <div class="row g-3">
                        <?php if ($publicAnnouncements === []): ?>
                            <div class="col-12">
                                <div class="public-panel-simple text-center">
                                    <p class="mb-0">No public announcements yet.</p>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($publicAnnouncements as $announcement): ?>
                                <div class="col-md-4">
                                    <div class="public-card h-100">
                                        <h3 class="public-card-title"><?php echo htmlspecialchars((string) $announcement['title']); ?></h3>
                                        <p><?php echo nl2br(htmlspecialchars((string) $announcement['body'])); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <footer class="public-footer" id="footer">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <small>Automated Scholarship Records Management System</small>
                <small class="d-flex align-items-center gap-2 flex-wrap">
                    <span>Municipality of San Enrique, Negros Occidental</span>
                    <a href="https://www.facebook.com/groups/438677743308925" target="_blank" rel="noopener noreferrer"
                        class="public-footer-link text-decoration-none">
                        <i class="fa-brands fa-facebook me-1"></i>LGU-San Enrique Scholars
                    </a>
                </small>
            </div>
        </div>
    </footer>
</main>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
