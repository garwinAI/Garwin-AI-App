<?php
/**
 * "Get Started FREE" button that opens a choice between signing up and logging in.
 *
 * @var string $getStartedSize  Extra button class, e.g. 'button-small'.
 * @var string $getStartedClass Menu placement: 'menu-end', 'menu-start', add 'menu-up' to open upward.
 */
$getStartedSize ??= '';
$getStartedClass ??= 'menu-end';
?>
<details class="get-started <?= e($getStartedClass) ?>" data-get-started>
    <summary class="button <?= e($getStartedSize) ?>">Get Started FREE <span class="get-started-caret" aria-hidden="true"></span></summary>
    <div class="get-started-menu">
        <a class="get-started-option" href="/register">
            <span class="get-started-option-icon" aria-hidden="true">✦</span>
            <span><strong>Create a free account</strong><small>New to Garwin AI? Sign up in a minute.</small></span>
        </a>
        <a class="get-started-option" href="/login">
            <span class="get-started-option-icon" aria-hidden="true">→</span>
            <span><strong>Log in</strong><small>Already a member? Go to your dashboard.</small></span>
        </a>
    </div>
</details>
