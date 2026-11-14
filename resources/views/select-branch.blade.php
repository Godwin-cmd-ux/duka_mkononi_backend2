<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chagua Tawi — DukaMkononi</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f4f7fb;
            color: #2c3e50;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .card {
            width: 100%;
            max-width: 540px;
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 18px 50px rgba(44, 62, 80, 0.12);
            padding: 28px;
        }
        .brand { font-size: 12px; font-weight: 700; letter-spacing: 2px; color: #3498db; text-transform: uppercase; }
        h1 { font-size: 22px; margin: 10px 0 4px; }
        .business { font-weight: 700; color: #34495e; margin-bottom: 4px; }
        .subtitle { font-size: 13px; color: #7a8ba0; margin-bottom: 20px; }
        .branch {
            width: 100%;
            text-align: left;
            border: 1px solid #dbe7f3;
            background: #ffffff;
            border-radius: 14px;
            padding: 16px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: border-color .15s, box-shadow .15s, transform .15s;
        }
        .branch:hover { border-color: #3498db; box-shadow: 0 8px 20px rgba(52, 152, 219, 0.15); transform: translateY(-1px); }
        .branch-name { font-weight: 700; font-size: 15px; margin-bottom: 3px; }
        .branch-location { font-size: 12px; color: #7a8ba0; }
        .branch-icon { color: #3498db; margin-right: 8px; }
        .cross {
            background: #2c3e50;
            color: #ffffff;
            border-color: #2c3e50;
            margin-top: 16px;
        }
        .cross .branch-location { color: #c8d6e5; }
        .cross .branch-icon { color: #ffffff; }
        .empty { padding: 18px; border-radius: 12px; background: #fff4e6; color: #b35c00; font-size: 13px; }
        .error { padding: 12px; border-radius: 10px; background: #fdeaea; color: #c0392b; font-size: 13px; margin-top: 12px; display: none; }
        .loading { text-align: center; color: #7a8ba0; font-size: 14px; padding: 30px 0; }
        .footer { margin-top: 18px; text-align: center; }
        .footer a { color: #7a8ba0; font-size: 12px; text-decoration: none; }
    </style>
</head>
<body>
    <div class="card">
        <div class="brand">DukaMkononi</div>
        <h1>Karibu kwenye biashara yako</h1>
        <div class="business" id="businessName"></div>
        <div class="subtitle">Chagua tawi ili kuendelea. Unaweza kubadili tawi wakati wowote.</div>

        <div id="loading" class="loading">Inapakia matawi...</div>
        <div id="branches"></div>
        <div class="error" id="error"></div>

        <div class="footer">
            <a href="/login?role=msimamizi" id="logoutLink">Toka</a>
        </div>
    </div>

    <script>
        const API_BASE_URL = '';
        const token = localStorage.getItem('userToken');

        function showError(message) {
            const el = document.getElementById('error');
            el.textContent = message;
            el.style.display = 'block';
        }

        function escapeHtml(value) {
            return String(value == null ? '' : value).replace(/[&<>"']/g, function (c) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
            });
        }

        async function selectBranch(branchId, button) {
            if (!token) {
                window.location.href = '/login?role=msimamizi';
                return;
            }

            const original = button.innerHTML;
            button.disabled = true;
            button.innerHTML = '<div class="branch-name">Inachagua tawi...</div>';

            try {
                const response = await fetch(API_BASE_URL + '/api/branch-context', {
                    method: 'POST',
                    headers: {
                        'Authorization': 'Bearer ' + token,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ branch_id: branchId })
                });

                const data = await response.json();

                if (!response.ok || !data.token) {
                    throw new Error(data.error || 'Imeshindikana kuchagua tawi');
                }

                localStorage.setItem('userToken', data.token);
                localStorage.setItem('branchId', data.context === 'individual_branch' && data.branch ? data.branch.id : '');
                localStorage.setItem('branchContext', data.context);
                if (data.branch) {
                    localStorage.setItem('branchData', JSON.stringify(data.branch));
                } else {
                    localStorage.removeItem('branchData');
                }

                window.location.href = '/msimamizi/index';
            } catch (error) {
                button.disabled = false;
                button.innerHTML = original;
                showError(error.message || 'Hitilafu ya mtandao');
            }
        }

        async function loadBranches() {
            if (!token) {
                window.location.href = '/login?role=msimamizi';
                return;
            }

            try {
                const response = await fetch(API_BASE_URL + '/api/branches', {
                    headers: {
                        'Authorization': 'Bearer ' + token,
                        'Accept': 'application/json'
                    }
                });
                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.error || 'Imeshindikana kupata matawi');
                }

                document.getElementById('businessName').textContent = data.business?.business_name || '';
                document.getElementById('loading').style.display = 'none';

                const container = document.getElementById('branches');
                const branches = Array.isArray(data.branches) ? data.branches : [];

                if (data.business?.business_structure !== 'branch' || !data.can_select_branch) {
                    // Non-branch businesses and sellers never choose a branch;
                    // a seller's branch is assigned by their administrator.
                    window.location.href = '/msimamizi/index';
                    return;
                }

                container.innerHTML = branches.map(function (branch, index) {
                    return '<button type="button" class="branch" data-branch-id="' + escapeHtml(branch.id) + '">'
                        + '<div class="branch-name"><span class="branch-icon">&#127970;</span>' + escapeHtml(branch.branch_name) + '</div>'
                        + '<div class="branch-location">' + escapeHtml(branch.location || 'Eneo halijachukuliwa') + '</div>'
                        + '</button>';
                }).join('') + (
                    '<button type="button" class="branch cross" data-branch-id="">'
                    + '<div class="branch-name"><span class="branch-icon">&#128202;</span>DASHBOARD YA MATAWI YOTE</div>'
                    + '<div class="branch-location">Muhtasari wa biashara nzima (mauzo, matumizi, wauzaji wa matawi yote)</div>'
                    + '</button>'
                );

                if (branches.length === 0) {
                    container.insertAdjacentHTML('afterbegin', '<div class="empty">Biashara hii haina tawi hai. Wasiliana na msimamizi wa mfumo.</div>');
                }

                container.querySelectorAll('[data-branch-id]').forEach(function (button) {
                    button.addEventListener('click', function () {
                        selectBranch(button.getAttribute('data-branch-id'), button);
                    });
                });
            } catch (error) {
                document.getElementById('loading').style.display = 'none';
                showError(error.message || 'Hitilafu ya mtandao');
            }
        }

        document.getElementById('logoutLink').addEventListener('click', function () {
            localStorage.removeItem('userToken');
            localStorage.removeItem('userData');
            localStorage.removeItem('branchId');
            localStorage.removeItem('branchContext');
        });

        loadBranches();
    </script>
</body>
</html>
