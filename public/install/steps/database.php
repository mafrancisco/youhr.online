<h2 class="text-lg font-bold text-gray-900 mb-1">Database Configuration</h2>
<p class="text-sm text-gray-500 mb-4">Configure your MySQL database connection. The database will be created automatically if it doesn't exist.</p>

<form id="db-form" class="space-y-4">
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Host</label>
            <input name="db_host" type="text" value="127.0.0.1" 
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400" />
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Port</label>
            <input name="db_port" type="text" value="3306"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400" />
        </div>
    </div>
    <div>
        <label class="block text-xs font-medium text-gray-700 mb-1">Database Name</label>
        <input name="db_database" type="text" value="dtr_system"
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400" />
        <p class="text-xs text-gray-400 mt-1">Will be created automatically if it doesn't exist. You can use any name.</p>
    </div>
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Username</label>
            <input name="db_username" type="text" value="root"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400" />
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Password</label>
            <input name="db_password" type="password" value=""
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400" />
            <p class="text-xs text-gray-400 mt-1">Leave blank for XAMPP default.</p>
        </div>
    </div>

    <!-- SQL Import (optional) -->
    <div class="border-t pt-4">
        <label class="block text-xs font-medium text-gray-700 mb-1">Import SQL Backup (Optional)</label>
        <input type="file" name="sql_file" accept=".sql" id="sql-file-input"
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
        <p class="text-xs text-gray-400 mt-1">Select a .sql backup file to restore existing data.</p>
        <div id="sql-upload-status" class="text-xs mt-1 hidden"></div>
    </div>
</form>

<div id="db-test-result" class="mt-3 hidden rounded-lg p-3 text-sm"></div>

<div class="flex justify-between items-center mt-6 pt-4 border-t">
    <a href="?step=requirements" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">← Back</a>
    <div class="flex gap-2">
        <button onclick="testDb()" id="test-btn"
            class="px-4 py-2 border border-blue-600 text-blue-600 rounded-lg text-sm font-medium hover:bg-blue-50">
            Test Connection
        </button>
        <a href="?step=config" id="next-btn" 
           class="px-6 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 opacity-50 pointer-events-none"
           onclick="saveDbConfig()">
            Next: Configuration →
        </a>
    </div>
</div>

<script>
function testDb() {
    const form = document.getElementById('db-form');
    const data = new FormData(form);
    data.append('action', 'test_database');
    
    const btn = document.getElementById('test-btn');
    btn.textContent = 'Testing...';
    btn.disabled = true;

    fetch('', { method: 'POST', body: data })
        .then(r => r.json())
        .then(res => {
            const el = document.getElementById('db-test-result');
            el.classList.remove('hidden', 'bg-green-50', 'bg-red-50', 'text-green-800', 'text-red-800');
            el.classList.add(res.success ? 'bg-green-50' : 'bg-red-50');
            el.classList.add(res.success ? 'text-green-800' : 'text-red-800');
            el.textContent = res.message;

            if (res.success) {
                const next = document.getElementById('next-btn');
                next.classList.remove('opacity-50', 'pointer-events-none');
            }
        })
        .finally(() => { btn.textContent = 'Test Connection'; btn.disabled = false; });
}

function saveDbConfig() {
    const form = document.getElementById('db-form');
    const data = new FormData(form);
    sessionStorage.setItem('db_host', data.get('db_host'));
    sessionStorage.setItem('db_port', data.get('db_port'));
    sessionStorage.setItem('db_database', data.get('db_database'));
    sessionStorage.setItem('db_username', data.get('db_username'));
    sessionStorage.setItem('db_password', data.get('db_password'));
}

// Handle SQL file upload
document.getElementById('sql-file-input')?.addEventListener('change', function() {
    if (!this.files[0]) return;
    const data = new FormData();
    data.append('action', 'import_sql');
    data.append('sql_file', this.files[0]);
    
    const status = document.getElementById('sql-upload-status');
    status.classList.remove('hidden');
    status.textContent = 'Uploading...';
    status.className = 'text-xs mt-1 text-blue-600';

    fetch('', { method: 'POST', body: data })
        .then(r => r.json())
        .then(res => {
            status.textContent = res.message;
            status.className = 'text-xs mt-1 ' + (res.success ? 'text-green-600' : 'text-red-600');
        });
});
</script>
