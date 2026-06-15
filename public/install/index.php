<?php
/**
 * Your-HR DTR - Deployment Wizard
 * 
 * This installer runs independently of Laravel.
 * It checks requirements, configures the system, and sets up the database.
 * Once complete, it redirects to the main application.
 */

session_start();

// Prevent access if already installed
$basePath = dirname(dirname(__DIR__));
$envFile  = $basePath . '/.env';
$lockFile = $basePath . '/storage/installed.lock';

if (file_exists($lockFile) && !isset($_GET['force'])) {
    header('Location: /');
    exit;
}

// Load the installer app
require_once __DIR__ . '/Installer.php';

$installer = new Installer($basePath);
$step = $_GET['step'] ?? 'welcome';
$action = $_POST['action'] ?? null;

// Handle AJAX actions
if ($action) {
    header('Content-Type: application/json');
    error_reporting(0);
    ini_set('display_errors', '0');
    
    try {
        $result = $installer->handleAction($action, $_POST);
        echo json_encode($result);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage(), 'steps' => [['step' => 'Error', 'success' => false, 'output' => $e->getMessage()]]]);
    }
    exit;
}

// Render the wizard page
$data = $installer->getStepData($step);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DTR System - Installation Wizard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; }
        .step-active { background: #2563eb; color: white; }
        .step-done { background: #16a34a; color: white; }
        .step-pending { background: #e5e7eb; color: #6b7280; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-3xl overflow-hidden">
        <!-- Header -->
        <div class="bg-blue-900 text-white px-8 py-6">
            <h1 class="text-xl font-bold">DTR System</h1>
            <p class="text-blue-200 text-sm mt-1">Installation Wizard</p>
        </div>

        <!-- Progress Steps -->
        <div class="px-8 py-4 border-b bg-gray-50">
            <div class="flex items-center justify-between">
                <?php
                $steps = ['welcome' => 'Welcome', 'requirements' => 'Requirements', 'database' => 'Database', 'config' => 'Configuration', 'admin' => 'Admin Account', 'install' => 'Install', 'complete' => 'Complete'];
                $stepKeys = array_keys($steps);
                $currentIdx = array_search($step, $stepKeys);
                foreach ($steps as $key => $label):
                    $idx = array_search($key, $stepKeys);
                    $class = $idx < $currentIdx ? 'step-done' : ($idx === $currentIdx ? 'step-active' : 'step-pending');
                ?>
                <div class="flex items-center gap-2">
                    <span class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold <?= $class ?>">
                        <?= $idx < $currentIdx ? '✓' : ($idx + 1) ?>
                    </span>
                    <span class="text-xs font-medium <?= $idx === $currentIdx ? 'text-blue-900' : 'text-gray-400' ?> hidden sm:inline">
                        <?= $label ?>
                    </span>
                </div>
                <?php if ($idx < count($steps) - 1): ?>
                <div class="flex-1 h-0.5 mx-2 <?= $idx < $currentIdx ? 'bg-green-400' : 'bg-gray-200' ?>"></div>
                <?php endif; endforeach; ?>
            </div>
        </div>

        <!-- Content -->
        <div class="px-8 py-6 min-h-[350px]">
            <?php include __DIR__ . "/steps/{$step}.php"; ?>
        </div>
    </div>
</body>
</html>
