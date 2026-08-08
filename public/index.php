<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/header.php';

$googleFormUrl = setting('google_form_url', '#');
$eventDates = setting('event_dates', 'Dates to be announced');
$contactEmail = setting('contact_email', 'expo@must.ac.ug');
$contactPhone = setting('contact_phone', '+256 700 000000');
?>
<?php require __DIR__ . '/../includes/public-nav.php'; ?>

<main>
    <section class="hero">
        <div class="hero-grid">
            <div>
                <span class="eyebrow">MUST Freshers Expo 2026</span>
                <h1 class="hero-title">Manage your bazaar stall application from one official portal.</h1>
                <p>Welcome to the Freshers Expo 2026 Stall Management Portal. This portal is for applicants who have already submitted the stall registration Google Form. Use it to create your portal account, track your application, upload proof of payment, receive official communication, and view your stall allocation.</p>
                <div class="hero-actions">
                    <a class="button button-primary" href="<?php echo h(app_url('public/create-account.php')); ?>">Create Portal Account</a>
                    <a class="button button-secondary" href="<?php echo h(app_url('public/login.php')); ?>">Login</a>
                    <a class="button button-ghost" href="<?php echo h($googleFormUrl); ?>" target="_blank" rel="noopener">Complete Google Form</a>
                </div>
            </div>
            <aside class="hero-card" aria-label="Portal steps">
                <span class="eyebrow">How it works</span>
                <div class="steps">
                    <div class="step"><span>1</span><div><strong>Fill the Google Form</strong><br><small>Submit your stall application details first.</small></div></div>
                    <div class="step"><span>2</span><div><strong>Create a portal account</strong><br><small>Use the same email or phone number from the form.</small></div></div>
                    <div class="step"><span>3</span><div><strong>Track committee updates</strong><br><small>View status, payment verification, messages, and stall allocation.</small></div></div>
                </div>
            </aside>
        </div>
    </section>

    <section class="section" id="marketplace">
        <div class="section-header">
            <span class="eyebrow">Applicant tools</span>
            <h2>Built for students, vendors, and the organizing committee.</h2>
            <p class="lead">The portal keeps the registration process lightweight while giving applicants clear visibility into what the committee needs next.</p>
        </div>
        <div class="feature-grid">
            <article class="content-card feature-card">
                <h3>Application status</h3>
                <p>Track whether your stall application is pending review, needs correction, approved, or rejected.</p>
            </article>
            <article class="content-card feature-card">
                <h3>Payment proof</h3>
                <p>Upload or replace proof of payment and follow verification feedback from the committee.</p>
            </article>
            <article class="content-card feature-card">
                <h3>Stall allocation</h3>
                <p>View your assigned stall number and location once your application is approved and allocated.</p>
            </article>
        </div>
    </section>

    <section class="section" id="guidelines">
        <div class="panel">
            <span class="eyebrow">Guidelines</span>
            <h2>Participation rules summary</h2>
            <p><?php echo h(setting('rules_text', 'By participating in the event, all stall holders agree to operate only within their allocated space, keep their stall clean and safe, avoid selling illegal or unauthorized items, follow hygiene requirements where applicable, respect university property, follow security and electrical safety instructions, avoid excessive noise, and comply with all guidance from the organizing committee. Final stall allocation is subject to approval and signing of the compliance document.')); ?></p>
            <div class="meta-row">
                <span><strong>Event dates:</strong> <?php echo h($eventDates); ?></span>
                <span><strong>Official contact:</strong> <?php echo h($contactPhone); ?></span>
            </div>
        </div>
    </section>

    <section class="section" id="support">
        <div class="section-header">
            <span class="eyebrow">Support</span>
            <h2>Need help finding your application?</h2>
            <p class="lead">Use the same email address or phone number submitted in the Google Form. Uganda phone numbers are normalized automatically, for example 0771234567 and +256771234567 are treated as the same number.</p>
            <div class="section-actions">
                <a class="button button-primary" href="mailto:<?php echo h($contactEmail); ?>">Email Support</a>
                <a class="button button-ghost" href="tel:<?php echo h(preg_replace('/\s+/', '', $contactPhone)); ?>">Call Committee</a>
            </div>
        </div>
    </section>
</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>
