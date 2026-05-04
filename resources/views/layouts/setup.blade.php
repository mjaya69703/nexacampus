<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ (isset($menus) ? $menus . ' - ' : '') . $pages  }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles

    <style>
        body { font-family: 'Inter', sans-serif; }
        .step-content { display: none; }
        .step-content.active { display: block; animation: fadeIn 0.4s ease-in-out; }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        /* Custom scrollbar for summary */
        .custom-scroll::-webkit-scrollbar { width: 6px; }
        .custom-scroll::-webkit-scrollbar-track { background: #f1f1f1; }
        .custom-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        .custom-scroll::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        
        .preview-box {
            width: 100%;
            height: 120px;
            background-color: #f8fafc;
            border: 2px dashed #e2e8f0;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            margin-top: 0.5rem;
            transition: all 0.2s;
        }
        .preview-box img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            /* display: none; Hidden by default */
        }
        .preview-box.has-image {
            border-style: solid;
            border-color: #bfdbfe;
            background-color: #eff6ff;
        }
        .preview-box.has-image img {
            display: block;
        }
        .preview-placeholder {
            color: #94a3b8;
            font-size: 0.75rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.25rem;
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen flex items-center justify-center p-4">

    <!-- Main Card -->
    {{  $slot }}

    <script>
        let currentStep = 1;
        const totalSteps = 4;

        function previewImage(input, previewId) {
            const file = input.files[0];
            const previewBox = document.getElementById(previewId);
            const img = previewBox.querySelector('img');

            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    img.src = e.target.result;
                    previewBox.classList.add('has-image');
                }
                reader.readAsDataURL(file);
            } else {
                img.src = "";
                previewBox.classList.remove('has-image');
            }
        }

        function changeStep(direction) {
            // Validation Logic
            if (direction === 1) {
                if (currentStep === 1) {
                    const appName = document.querySelector('input[name="app_name"]').value;
                    if(!appName) { alert('Nama Aplikasi wajib diisi!'); return; }
                }
                if (currentStep === 2) {
                    const campusName = document.querySelector('input[name="campus_name"]').value;
                    if(!campusName) { alert('Nama Kampus wajib diisi!'); return; }
                }
                if (currentStep === 3) {
                    const firstName = document.querySelector('input[name="first_name"]').value;
                    const email = document.querySelector('input[name="email"]').value;
                    const pass = document.querySelector('input[name="password"]').value;
                    if(!firstName || !email || !pass) { alert('Nama, Email, dan Password Admin wajib diisi!'); return; }
                }
                if (currentStep === 4) {
                    const validCheck = document.getElementById('check-valid').checked;
                    const tosCheck = document.getElementById('check-tos').checked;
                    if(!validCheck || !tosCheck) { alert('Anda harus menyetujui validasi data dan Syarat & Ketentuan.'); return; }
                }
            }

            // Update Step Logic
            document.getElementById(`step-${currentStep}`).classList.remove('active');
            currentStep += direction;
            document.getElementById(`step-${currentStep}`).classList.add('active');

            updateIndicators();
            updateButtons();
            
            if (currentStep === 4) {
                populateSummary();
            }
        }

        function updateIndicators() {
            for (let i = 1; i <= totalSteps; i++) {
                const indicator = document.getElementById(`indicator-${i}`);
                const circle = indicator.querySelector('div');
                const text = indicator.querySelector('span');

                if (i < currentStep) {
                    // Completed
                    circle.className = "w-6 h-6 rounded-full border-2 border-green-500 bg-green-500 flex items-center justify-center text-xs font-bold";
                    circle.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>';
                    text.className = "font-medium text-sm text-green-500";
                } else if (i === currentStep) {
                    // Active
                    circle.className = "w-6 h-6 rounded-full border-2 border-blue-500 bg-blue-500 flex items-center justify-center text-xs font-bold";
                    circle.innerHTML = i;
                    text.className = "font-medium text-sm text-white";
                } else {
                    // Pending
                    circle.className = "w-6 h-6 rounded-full border-2 border-slate-600 bg-slate-800 flex items-center justify-center text-xs font-bold text-slate-400";
                    circle.innerHTML = i;
                    text.className = "font-medium text-sm text-slate-400";
                }
            }
        }

        function updateButtons() {
            const btnPrev = document.getElementById('btn-prev');
            const btnNext = document.getElementById('btn-next');
            const btnFinish = document.getElementById('btn-finish');

            btnPrev.disabled = currentStep === 1;
            
            if (currentStep === totalSteps) {
                btnNext.classList.add('hidden');
                btnFinish.classList.remove('hidden');
            } else {
                btnNext.classList.remove('hidden');
                btnFinish.classList.add('hidden');
            }
        }

        function populateSummary() {
            // Helper to get value safely
            const getVal = (name) => {
                const el = document.querySelector(`input[name="${name}"]`);
                return el ? (el.value || '-') : '-';
            };

            // System
            document.getElementById('sum-app-name').textContent = getVal('app_name');
            document.getElementById('sum-app-ver').textContent = getVal('app_version');
            document.getElementById('sum-app-url').textContent = getVal('app_url');
            document.getElementById('sum-app-email').textContent = getVal('app_email');
            const maint = document.querySelector('input[name="maintenance_mode"]').checked ? 'Aktif' : 'Tidak Aktif';
            document.getElementById('sum-maint').textContent = maint;

            // Campus
            document.getElementById('sum-campus-name').textContent = getVal('campus_name');
            document.getElementById('sum-domain').textContent = getVal('domain');
            document.getElementById('sum-phone').textContent = getVal('phone'); // Note: phone is in both forms, taking last one
            document.getElementById('sum-email-info').textContent = getVal('email_info');
            document.getElementById('sum-address').textContent = document.querySelector('textarea[name="address"]').value || '-';

            // Admin
            const fName = getVal('first_name');
            const lName = getVal('last_name');
            document.getElementById('sum-admin-name').textContent = `${fName} ${lName}`.trim() || '-';
            document.getElementById('sum-admin-user').textContent = getVal('username');
            document.getElementById('sum-admin-email').textContent = getVal('email');
            document.getElementById('sum-admin-phone').textContent = getVal('phone');
        }

        function finishInstallation() {
            const btn = document.getElementById('btn-finish');
            const originalContent = btn.innerHTML;
            
            btn.innerHTML = `<svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Memproses...`;
            btn.disabled = true;

            // Simulate API call
            setTimeout(() => {
                alert("Instalasi Berhasil! System siap digunakan.");
                btn.innerHTML = originalContent;
                btn.disabled = false;
                // window.location.href = '/login'; // Redirect logic
            }, 2000);
        }
    </script>
    @livewireScripts
</body>
</html>

