<?php $reqData = $installer->checkRequirements(); $checks = $reqData['checks']; $allPassed = $reqData['allPassed']; ?>

<h2 class="text-lg font-bold text-gray-900 mb-1">System Requirements</h2>
<p class="text-sm text-gray-500 mb-4">Checking your server environment...</p>

<div class="space-y-2 max-h-[300px] overflow-y-auto pr-2">
    <?php foreach ($checks as $check): ?>
    <div class="flex items-center justify-between py-2 px-3 rounded-lg <?= $check['status'] ? 'bg-green-50' : 'bg-red-50' ?>">
        <span class="text-sm <?= $check['status'] ? 'text-green-800' : 'text-red-800' ?>">
            <?= $check['label'] ?>
        </span>
        <span class="text-xs font-mono <?= $check['status'] ? 'text-green-600' : 'text-red-600' ?>">
            <?= $check['status'] ? '✓' : '✗' ?> <?= htmlspecialchars($check['current']) ?>
        </span>
    </div>
    <?php endforeach; ?>
</div>

<div class="flex justify-between items-center mt-6 pt-4 border-t">
    <a href="?step=welcome" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">← Back</a>
    
    <?php if ($allPassed): ?>
    <a href="?step=database" 
       class="px-6 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
        Next: Database →
    </a>
    <?php else: ?>
    <div class="flex items-center gap-3">
        <span class="text-sm text-red-600 font-medium">Fix the issues above before continuing</span>
        <a href="?step=requirements" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg text-sm hover:bg-gray-300">
            Re-check
        </a>
    </div>
    <?php endif; ?>
</div>
