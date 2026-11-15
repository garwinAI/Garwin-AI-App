<?php $errors ??= []; $old ??= []; ?>
<section class="auth-wrap auth-wrap-login">
    <div class="auth-card">
        <div class="auth-heading">
            <h1>Login</h1>
        </div>
        <?php if (isset($errors['form'])): ?><div class="alert" role="alert"><?= e($errors['form']) ?></div><?php endif; ?>
        <form method="post" action="/login" class="form-stack">
            <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
            <label>Email address
                <input type="email" name="email" autocomplete="email" required maxlength="255" value="<?= e($old['email'] ?? '') ?>" aria-invalid="<?= isset($errors['email']) ? 'true' : 'false' ?>">
                <?php if (isset($errors['email'])): ?><small class="field-error"><?= e($errors['email']) ?></small><?php endif; ?>
            </label>
            <label>Password
                <input type="password" name="password" autocomplete="current-password" required maxlength="1024">
                <?php if (isset($errors['password'])): ?><small class="field-error"><?= e($errors['password']) ?></small><?php endif; ?>
            </label>
            <button class="button button-full" type="submit">Login <span aria-hidden="true">→</span></button>
        </form>
        <p class="auth-switch">Don't have an account? <a href="/register">Get Started FREE</a></p>
    </div>
</section>
