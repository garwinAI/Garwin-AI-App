<?php $errors ??= []; $old ??= []; ?>
<section class="auth-wrap">
    <div class="auth-card">
        <div class="auth-heading">
            <h1>Get Started FREE</h1>
        </div>
        <?php if (isset($errors['form'])): ?><div class="alert" role="alert"><?= e($errors['form']) ?></div><?php endif; ?>
        <form method="post" action="/register" class="form-stack">
            <input type="hidden" name="_token" value="<?= e($csrfToken) ?>">
            <div class="form-row">
                <label>First name
                    <input name="first_name" autocomplete="given-name" required maxlength="100" value="<?= e($old['first_name'] ?? '') ?>" aria-invalid="<?= isset($errors['first_name']) ? 'true' : 'false' ?>">
                    <?php if (isset($errors['first_name'])): ?><small class="field-error"><?= e($errors['first_name']) ?></small><?php endif; ?>
                </label>
                <label>Last name
                    <input name="last_name" autocomplete="family-name" required maxlength="100" value="<?= e($old['last_name'] ?? '') ?>" aria-invalid="<?= isset($errors['last_name']) ? 'true' : 'false' ?>">
                    <?php if (isset($errors['last_name'])): ?><small class="field-error"><?= e($errors['last_name']) ?></small><?php endif; ?>
                </label>
            </div>
            <label>Email address
                <input type="email" name="email" autocomplete="email" required maxlength="255" value="<?= e($old['email'] ?? '') ?>" aria-invalid="<?= isset($errors['email']) ? 'true' : 'false' ?>">
                <?php if (isset($errors['email'])): ?><small class="field-error"><?= e($errors['email']) ?></small><?php endif; ?>
            </label>
            <label>Password
                <input type="password" name="password" autocomplete="new-password" required minlength="12" maxlength="1024" aria-describedby="password-hint" aria-invalid="<?= isset($errors['password']) ? 'true' : 'false' ?>">
                <small id="password-hint" class="field-hint">Use at least 12 characters.</small>
                <?php if (isset($errors['password'])): ?><small class="field-error"><?= e($errors['password']) ?></small><?php endif; ?>
            </label>
            <label>Confirm password
                <input type="password" name="password_confirmation" autocomplete="new-password" required minlength="12" maxlength="1024" aria-invalid="<?= isset($errors['password_confirmation']) ? 'true' : 'false' ?>">
                <?php if (isset($errors['password_confirmation'])): ?><small class="field-error"><?= e($errors['password_confirmation']) ?></small><?php endif; ?>
            </label>
            <label class="checkbox-label">
                <input type="checkbox" name="terms" value="accepted" required>
                <span>I agree to create an account and understand Garwin AI is currently in its foundation phase.</span>
            </label>
            <?php if (isset($errors['terms'])): ?><small class="field-error"><?= e($errors['terms']) ?></small><?php endif; ?>
            <button class="button button-full" type="submit">Get Started FREE <span aria-hidden="true">→</span></button>
        </form>
        <p class="auth-switch">Already have an account? <a href="/login">Login</a></p>
    </div>
</section>
