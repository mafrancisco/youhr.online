<h2 class="text-lg font-bold text-gray-900 mb-1">Application Configuration</h2>
<p class="text-sm text-gray-500 mb-4">Configure your system settings.</p>

<form id="config-form" class="space-y-4">
    <div>
        <label class="block text-xs font-medium text-gray-700 mb-1">System Name</label>
        <input name="app_name" type="text" value="DTR System"
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400" />
        <p class="text-xs text-gray-400 mt-1">Enter your organization's DTR system name.</p>
    </div>
    <div>
        <label class="block text-xs font-medium text-gray-700 mb-1">Application URL</label>
        <input name="app_url" type="text" value="http://localhost"
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400" />
        <p class="text-xs text-gray-400 mt-1">Use http://localhost or your LAN IP (e.g. http://192.168.1.100)</p>
    </div>
    <div>
        <label class="block text-xs font-medium text-gray-700 mb-1">Timezone</label>
        <select name="timezone"
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
            <option value="Asia/Manila" selected>Asia/Manila (UTC+8)</option>
            <option value="Asia/Singapore">Asia/Singapore (UTC+8)</option>
            <option value="Asia/Tokyo">Asia/Tokyo (UTC+9)</option>
            <option value="UTC">UTC</option>
            <option value="America/New_York">America/New_York (UTC-5)</option>
        </select>
    </div>
    <div>
        <label class="block text-xs font-medium text-gray-700 mb-1">Company Address (Optional)</label>
        <input name="company_address" type="text" value=""
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400" />
    </div>
</form>

<div class="flex justify-between items-center mt-6 pt-4 border-t">
    <a href="?step=database" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">← Back</a>
    <a href="?step=admin" onclick="saveConfig()"
       class="px-6 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
        Next: Admin Account →
    </a>
</div>

<script>
function saveConfig() {
    const form = document.getElementById('config-form');
    const data = new FormData(form);
    for (const [key, val] of data.entries()) {
        sessionStorage.setItem(key, val);
    }
}
</script>
