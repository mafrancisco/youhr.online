<h2 class="text-lg font-bold text-gray-900 mb-1">Administrator Account</h2>
<p class="text-sm text-gray-500 mb-4">Create the initial HR administrator account.</p>

<form id="admin-form" class="space-y-4">
    <div>
        <label class="block text-xs font-medium text-gray-700 mb-1">Full Name</label>
        <input name="admin_fullname" type="text" value="System Administrator" required
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400" />
    </div>
    <div>
        <label class="block text-xs font-medium text-gray-700 mb-1">Email</label>
        <input name="admin_email" type="email" value="admin@localhost" required
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400" />
    </div>
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Username</label>
            <input name="admin_username" type="text" value="admin" required
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400" />
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Password</label>
            <input name="admin_password" type="password" value="" required placeholder="Enter password"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400" />
        </div>
    </div>
</form>

<div class="bg-amber-50 border border-amber-200 rounded-lg p-3 mt-4">
    <p class="text-xs text-amber-800">
        <strong>Important:</strong> Remember these credentials. This will be the only account with HR/Admin access initially.
    </p>
</div>

<div class="flex justify-between items-center mt-6 pt-4 border-t">
    <a href="?step=config" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">← Back</a>
    <a href="?step=install" onclick="saveAdmin()"
       class="px-6 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">
        Next: Install →
    </a>
</div>

<script>
function saveAdmin() {
    const form = document.getElementById('admin-form');
    const data = new FormData(form);
    for (const [key, val] of data.entries()) {
        sessionStorage.setItem(key, val);
    }
}
</script>
