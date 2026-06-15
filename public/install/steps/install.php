<h2 class="text-lg font-bold text-gray-900 mb-1">Ready to Install</h2>
<p class="text-sm text-gray-500 mb-4">Review your settings and click "Install System" to begin.</p>

<div id="install-summary" class="space-y-2 mb-6">
    <div class="bg-gray-50 rounded-lg p-3 text-sm space-y-1" id="summary-content">
        <p class="text-gray-500">Loading configuration...</p>
    </div>
</div>

<div id="install-progress" class="hidden space-y-2 mb-4">
    <div class="flex items-center gap-2 mb-2">
        <svg class="w-5 h-5 text-blue-500 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
        </svg>
        <span class="text-sm font-medium text-blue-800">Installing...</span>
    </div>
    <div id="install-log" class="bg-gray-900 text-green-400 rounded-lg p-4 font-mono text-xs max-h-[200px] overflow-y-auto">
    </div>
</div>

<div id="install-error" class="hidden bg-red-50 border border-red-200 rounded-lg p-3 mb-4">
    <p class="text-sm text-red-800 font-medium">Installation failed</p>
    <p class="text-xs text-red-600 mt-1" id="error-message"></p>
</div>

<div class="flex justify-between items-center mt-6 pt-4 border-t">
    <a href="?step=admin" id="back-btn" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">← Back</a>
    <button onclick="runInstall()" id="install-btn"
        class="px-6 py-3 bg-green-600 text-white rounded-lg text-sm font-bold hover:bg-green-700 transition-colors">
        🚀 Install System
    </button>
</div>

<script>
// Show summary from sessionStorage
document.addEventListener('DOMContentLoaded', function() {
    const el = document.getElementById('summary-content');
    const items = [
        ['System Name', sessionStorage.getItem('app_name') || 'Your-HR DTR'],
        ['URL', sessionStorage.getItem('app_url') || 'http://localhost'],
        ['Timezone', sessionStorage.getItem('timezone') || 'Asia/Manila'],
        ['Database', (sessionStorage.getItem('db_username') || 'root') + '@' + (sessionStorage.getItem('db_host') || '127.0.0.1') + '/' + (sessionStorage.getItem('db_database') || 'ironone')],
        ['Admin', sessionStorage.getItem('admin_username') || 'admin'],
    ];
    el.innerHTML = items.map(([k, v]) => 
        `<p><span class="text-gray-500">${k}:</span> <span class="font-medium text-gray-800">${v}</span></p>`
    ).join('');
});

function runInstall() {
    const btn = document.getElementById('install-btn');
    const progress = document.getElementById('install-progress');
    const log = document.getElementById('install-log');
    const backBtn = document.getElementById('back-btn');
    
    btn.disabled = true;
    btn.textContent = 'Installing...';
    btn.classList.add('opacity-50');
    backBtn.classList.add('pointer-events-none', 'opacity-50');
    progress.classList.remove('hidden');

    // Collect all config from sessionStorage
    const data = new FormData();
    data.append('action', 'run_install');
    ['app_name', 'app_url', 'timezone', 'company_address', 'db_host', 'db_port', 'db_database', 'db_username', 'db_password', 'admin_username', 'admin_password', 'admin_fullname', 'admin_email'].forEach(key => {
        data.append(key, sessionStorage.getItem(key) || '');
    });

    fetch('', { method: 'POST', body: data })
        .then(r => r.json())
        .then(res => {
            if (res.steps) {
                res.steps.forEach(step => {
                    const color = step.success ? 'text-green-400' : 'text-red-400';
                    const icon = step.success ? '✓' : '✗';
                    log.innerHTML += `<div class="${color}">${icon} ${step.step}: ${step.output || 'Done'}</div>`;
                });
                log.scrollTop = log.scrollHeight;
            }

            if (res.success) {
                setTimeout(() => { window.location.href = '?step=complete'; }, 1500);
            } else {
                document.getElementById('install-error').classList.remove('hidden');
                document.getElementById('error-message').textContent = res.error || 'Unknown error occurred.';
                btn.disabled = false;
                btn.textContent = '🚀 Retry Install';
                btn.classList.remove('opacity-50');
                backBtn.classList.remove('pointer-events-none', 'opacity-50');
            }
        })
        .catch(err => {
            document.getElementById('install-error').classList.remove('hidden');
            document.getElementById('error-message').textContent = err.message;
            btn.disabled = false;
            btn.textContent = '🚀 Retry Install';
            btn.classList.remove('opacity-50');
        });
}
</script>
