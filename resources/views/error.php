<section class="error-card">
    <span class="metric-icon neutral-icon">!</span>
    <h1><?= e($title ?? 'Something went wrong') ?></h1>
    <p><?= e($message ?? 'The request could not be completed.') ?></p>
    <a class="button" href="/">Return home</a>
</section>
