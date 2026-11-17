<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\AdminBootstrapService;
use App\Services\ApiSecretCipher;
use App\Services\DemoUserService;
use App\Services\IntegrationConfigService;
use App\Services\IntegrationVerifier;
use App\Services\MembershipPlanService;
use App\Services\PlatformLaunchService;
use App\Services\RegistrationService;
use App\Services\StorageProvisioner;
use App\Services\UserIdGenerator;
use App\Support\Csrf;
use InvalidArgumentException;
use RuntimeException;

final class AdminController
{
    public function index(): void
    {
        $admin = $this->requireAdmin();
        if ($admin === null) {
            return;
        }

        $configs = $this->integrationConfigs();
        $launch = $this->launchService($configs);
        $statuses = $configs->statuses();
        render('admin/dashboard', [
            'title' => 'Admin dashboard',
            'user' => $admin,
            'requirements' => $launch->requirements(),
            'providerDefinitions' => IntegrationConfigService::definitions(),
            'providerStatuses' => $this->indexByProvider($statuses),
            'isLaunched' => $launch->isLaunched(),
            'showLaunchChecklist' => !$launch->isLaunched() && !($_SESSION['_launch_checklist_dismissed'] ?? false),
            'demoUserCount' => $this->demoUsers()->count(),
            'flashMessage' => take_flash_message(),
        ]);
    }

    public function settings(): void
    {
        $admin = $this->requireAdmin();
        if ($admin === null) {
            return;
        }

        $tab = (string) ($_GET['tab'] ?? 'launch');
        if (!in_array($tab, ['launch', 'demo-users', 'plans'], true)) {
            $tab = 'launch';
        }

        $configs = $this->integrationConfigs();
        $launch = $this->launchService($configs);
        render('admin/settings', [
            'title' => 'Admin settings',
            'user' => $admin,
            'tab' => $tab,
            'requirements' => $launch->requirements(),
            'providerDefinitions' => IntegrationConfigService::definitions(),
            'providerStatuses' => $this->indexByProvider($configs->statuses()),
            'isLaunched' => $launch->isLaunched(),
            'demoUserCount' => $this->demoUsers()->count(),
            'demoUsers' => $tab === 'demo-users' ? $this->demoUsers()->list() : [],
            'membershipPlans' => $tab === 'plans' ? (new MembershipPlanService(app_db()))->all() : [],
            'flashMessage' => take_flash_message(),
        ]);
    }

    public function saveMembershipPlan(): void
    {
        if ($this->requireAdmin() === null || !$this->validateCsrf()) {
            return;
        }

        $planKey = $_POST['plan_key'] ?? '';
        $priceInput = $_POST['monthly_price'] ?? '';
        $storageInput = $_POST['storage_gb'] ?? '';
        $budgetInput = $_POST['ai_budget'] ?? '';
        $seatsInput = $_POST['team_seats'] ?? '';
        if (!is_string($planKey) || !is_string($priceInput) || !is_string($storageInput)
            || !is_string($budgetInput) || !is_string($seatsInput)
            || !preg_match('/\A\d{1,6}(?:\.\d{1,2})?\z/', $priceInput)
            || !preg_match('/\A\d{1,6}\z/', $storageInput)
            || !preg_match('/\A\d{1,6}(?:\.\d{1,2})?\z/', $budgetInput)
            || !preg_match('/\A\d{1,5}\z/', $seatsInput)) {
            set_flash_message('error', 'Enter valid plan prices, storage, AI budget, and seat limits.');
            redirect('/admin/settings?tab=plans');
        }

        try {
            (new MembershipPlanService(app_db()))->update(
                $planKey,
                (int) round((float) $priceInput * 100),
                (int) $storageInput,
                (int) round((float) $budgetInput * 100),
                (int) $seatsInput,
                $planKey === 'free' || isset($_POST['enabled'])
            );
            set_flash_message('success', ucfirst($planKey) . ' membership settings saved.');
        } catch (InvalidArgumentException | RuntimeException $exception) {
            set_flash_message('error', $exception->getMessage());
        }

        redirect('/admin/settings?tab=plans');
    }

    public function saveIntegration(): void
    {
        if ($this->requireAdmin() === null || !$this->validateCsrf()) {
            return;
        }

        $provider = (string) ($_POST['provider'] ?? '');
        $configs = $this->integrationConfigs();

        try {
            $configs->save($provider, $_POST);
            if (($_POST['action'] ?? '') === 'test') {
                $credentials = $configs->requiredCredentials($provider);
                $result = (new IntegrationVerifier())->verify($provider, $credentials);
                $configs->recordTest($provider, $result['success'], $result['message']);
                set_flash_message($result['success'] ? 'success' : 'error', $result['message']);
            } else {
                set_flash_message('success', 'Credentials saved encrypted. Test the connection before launch.');
            }
        } catch (InvalidArgumentException | RuntimeException $exception) {
            set_flash_message('error', $exception->getMessage());
        }

        redirect('/admin/settings?tab=launch');
    }

    public function testIntegration(): void
    {
        $this->saveIntegration();
    }

    public function launch(): void
    {
        if ($this->requireAdmin() === null || !$this->validateCsrf()) {
            return;
        }

        try {
            $this->launchService($this->integrationConfigs())->markLaunchReady();
            set_flash_message(
                'success',
                'GarwinAI is marked launch-ready in this application. This action does not deploy code or activate unfinished product modules.'
            );
        } catch (RuntimeException $exception) {
            set_flash_message('error', $exception->getMessage());
        }

        redirect('/admin');
    }

    public function dismissLaunchChecklist(): void
    {
        if ($this->requireAdmin() === null || !$this->validateCsrf()) {
            return;
        }

        $_SESSION['_launch_checklist_dismissed'] = true;
        redirect('/admin');
    }

    public function createDemoUsers(): void
    {
        if ($this->requireAdmin() === null || !$this->validateCsrf()) {
            return;
        }

        $amount = filter_var($_POST['amount'] ?? null, FILTER_VALIDATE_INT);
        try {
            if ($amount === false) {
                throw new InvalidArgumentException('Enter a whole number between 1 and 100.');
            }
            $created = $this->demoUsers()->create($amount);
            set_flash_message('success', "Created {$created} demo user accounts.");
        } catch (InvalidArgumentException | RuntimeException $exception) {
            set_flash_message('error', $exception->getMessage());
        }

        redirect('/admin/settings?tab=demo-users');
    }

    public function removeDemoUsers(): void
    {
        if ($this->requireAdmin() === null || !$this->validateCsrf()) {
            return;
        }
        if (($_POST['confirm_remove'] ?? '') !== 'yes') {
            set_flash_message('error', 'Confirm demo-user deletion before continuing.');
            redirect('/admin/settings?tab=demo-users');
        }

        try {
            $removed = $this->demoUsers()->removeAll();
            set_flash_message('success', "Removed {$removed} demo user accounts and their private storage.");
        } catch (RuntimeException $exception) {
            set_flash_message('error', $exception->getMessage());
        }

        redirect('/admin/settings?tab=demo-users');
    }

    private function requireAdmin(): ?array
    {
        $user = current_user();
        if ($user === null) {
            redirect('/login');
        }
        if (!$user['is_admin']) {
            http_response_code(403);
            render('error', [
                'title' => 'Access denied',
                'message' => 'This area is restricted to Super Admin accounts.',
            ], 403);

            return null;
        }

        return $user;
    }

    private function validateCsrf(): bool
    {
        if (Csrf::isValid(isset($_POST['_token']) ? (string) $_POST['_token'] : null)) {
            return true;
        }

        render('error', ['title' => 'Request expired', 'message' => 'Refresh the page and try again.'], 419);

        return false;
    }

    private function integrationConfigs(): IntegrationConfigService
    {
        return new IntegrationConfigService(app_db(), new ApiSecretCipher());
    }

    private function launchService(IntegrationConfigService $configs): PlatformLaunchService
    {
        return new PlatformLaunchService(
            app_db(),
            $configs,
            new ApiSecretCipher(),
            new StorageProvisioner(dirname(__DIR__, 3))
        );
    }

    private function demoUsers(): DemoUserService
    {
        $pdo = app_db();
        $storage = new StorageProvisioner(dirname(__DIR__, 3));
        $registration = new RegistrationService($pdo, new UserIdGenerator($pdo), $storage);

        return new DemoUserService($pdo, $registration, $storage);
    }

    private function indexByProvider(array $statuses): array
    {
        $indexed = [];
        foreach ($statuses as $status) {
            $indexed[$status['key']] = $status;
        }

        return $indexed;
    }
}
