<!-- includes/footer.php -->
        </div> <!-- End of content -->
        
        <div class="footer-text">
            &copy; <?= date('Y') ?> GC Radius | GC Network Home Lab | Author: Ken Dedes
        </div>
    </div> <!-- End of main-content -->

    <!-- Confirm Modal -->
    <div class="modal-overlay" id="confirmModal">
        <div class="modal-box">
            <div class="modal-icon" id="modalIcon">⚠️</div>
            <h4 id="modalTitle">Konfirmasi</h4>
            <p id="modalMsg">Yakin ingin melanjutkan?</p>
            <div class="modal-actions">
                <button class="modal-btn-cancel" onclick="closeModal()">Batal</button>
                <button class="modal-btn-danger" id="modalConfirmBtn">Ya</button>
            </div>
        </div>
    </div>

    <script>
    // ===== Mobile Sidebar Toggle =====
    function toggleSidebar() {
        var sidebar = document.getElementById('sidebar');
        var overlay = document.getElementById('sidebarOverlay');
        var isOpen = sidebar.classList.contains('open');
        if (isOpen) {
            closeSidebar();
        } else {
            sidebar.classList.add('open');
            overlay.classList.add('active');
        }
    }

    function closeSidebar() {
        var sidebar = document.getElementById('sidebar');
        var overlay = document.getElementById('sidebarOverlay');
        sidebar.classList.remove('open');
        overlay.classList.remove('active');
    }

    // Close sidebar on swipe-right / ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeSidebar();
            closeModal();
            closeLoginModal();
        }
    });

    // ===== Modal =====
    let pendingForm = null;
    let pendingAction = 'delete';

    function showConfirm(e, name, type) {
        e.preventDefault();
        pendingForm = e.target.closest('form');
        pendingAction = 'delete';
        document.getElementById('modalIcon').textContent = '⚠️';
        document.getElementById('modalTitle').textContent = 'Konfirmasi Hapus';
        document.getElementById('modalMsg').textContent = 'Yakin ingin menghapus ' + type + ' "' + name + '"? Data tidak dapat dikembalikan.';
        document.getElementById('modalConfirmBtn').className = 'modal-btn-danger';
        document.getElementById('modalConfirmBtn').textContent = 'Hapus';
        document.getElementById('confirmModal').classList.add('active');
    }

    function showToggleConfirm(e, username, currentState) {
        e.preventDefault();
        pendingForm = e.target.closest('form');
        pendingAction = 'toggle';
        const action = currentState === 'Active' ? 'nonaktifkan' : 'aktifkan';
        document.getElementById('modalIcon').textContent = '🔄';
        document.getElementById('modalTitle').textContent = 'Konfirmasi ' + (currentState === 'Active' ? 'Nonaktifkan' : 'Aktifkan');
        document.getElementById('modalMsg').textContent = 'Yakin ingin ' + action + ' user "' + username + '"?';
        document.getElementById('modalConfirmBtn').className = 'modal-btn-primary';
        document.getElementById('modalConfirmBtn').textContent = 'Ya, ' + action;
        document.getElementById('confirmModal').classList.add('active');
    }

    function closeModal() {
        document.getElementById('confirmModal').classList.remove('active');
        pendingForm = null;
    }

    document.getElementById('modalConfirmBtn').addEventListener('click', function() {
        if (pendingForm) pendingForm.submit();
    });

    document.getElementById('confirmModal').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeModal();
    });

    // ===== Filter & Sort =====
    (function() {
        // Helper: format bytes to human readable
        function formatBytes(bytes, precision) {
            precision = precision || 2;
            const units = ['B', 'KB', 'MB', 'GB', 'TB'];
            bytes = Math.max(bytes, 0);
            const pow = Math.min(Math.floor((bytes ? Math.log(bytes) : 0) / Math.log(1024)), units.length - 1);
            bytes /= Math.pow(1024, pow);
            return bytes.toFixed(precision) + ' ' + units[pow];
        }

        const filterBar = document.querySelector('.filter-bar');
        if (!filterBar) return;

        const table = filterBar.parentElement.querySelector('table');
        if (!table) return;

        const tbody = table.querySelector('tbody');
        const thead = table.querySelector('thead');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        const searchInput = filterBar.querySelector('.filter-search');
        const filterSelects = filterBar.querySelectorAll('.filter-select');
        const sortBtns = filterBar.querySelectorAll('.sort-btn');
        const resultInfo = filterBar.querySelector('.filter-result-info');
        const dateStart = filterBar.querySelector('.filter-date-start');
        const dateEnd = filterBar.querySelector('.filter-date-end');

        let sortCol = -1;
        let sortDir = 1; // 1=asc, -1=desc

        function getCellText(row, colIdx) {
            const cell = row.children[colIdx];
            if (!cell) return '';
            // For status column, check inner spans
            const span = cell.querySelector('span');
            if (span) return span.textContent.trim().toLowerCase();
            return cell.textContent.trim().toLowerCase();
        }

        function applyFilters() {
            const searchTerm = searchInput ? searchInput.value.toLowerCase().trim() : '';

            let visible = rows.slice();

            // Search filter
            if (searchTerm) {
                visible = visible.filter(row => {
                    return Array.from(row.children).some(cell =>
                        cell.textContent.toLowerCase().includes(searchTerm)
                    );
                });
            }

            // Dropdown filters
            filterSelects.forEach(sel => {
                const val = sel.value;
                if (!val) return;
                const colIdx = parseInt(sel.dataset.col);
                visible = visible.filter(row => {
                    const txt = getCellText(row, colIdx);
                    if (val === 'online') return txt === 'online';
                    if (val === 'offline') return txt === 'offline';
                    if (val === 'access-accept') return txt === 'access-accept';
                    if (val === 'access-reject') return txt === 'access-reject';
                    return txt === val.toLowerCase();
                });
            });

            // Date range filter (col index 6 = Auth Date)
            if (dateStart && dateStart.value) {
                const from = new Date(dateStart.value);
                from.setHours(0,0,0,0);
                visible = visible.filter(row => {
                    const dateStr = row.children[6]?.dataset.date;
                    if (!dateStr) return false;
                    const d = new Date(dateStr);
                    return d >= from;
                });
            }
            if (dateEnd && dateEnd.value) {
                const to = new Date(dateEnd.value);
                to.setHours(23,59,59,999);
                visible = visible.filter(row => {
                    const dateStr = row.children[6]?.dataset.date;
                    if (!dateStr) return false;
                    const d = new Date(dateStr);
                    return d <= to;
                });
            }

            // Sort
            if (sortCol >= 0) {
                visible.sort((a, b) => {
                    // Auth Date column (6) — sort by data-date attribute (only if present)
                    if (sortCol === 6) {
                        const da = a.children[6]?.dataset.date;
                        const db = b.children[6]?.dataset.date;
                        if (da !== undefined && db !== undefined) {
                            return da.localeCompare(db) * sortDir;
                        }
                    }
                    // Session Time column (2) — sort by data-seconds attribute (only if present)
                    if (sortCol === 2) {
                        const sa = a.children[2]?.dataset.seconds;
                        const sb = b.children[2]?.dataset.seconds;
                        if (sa !== undefined && sb !== undefined) {
                            return (parseInt(sa) - parseInt(sb)) * sortDir;
                        }
                    }
                    // Upload / Download columns (3, 4) — sort by data-bytes attribute (only if present)
                    if (sortCol === 3 || sortCol === 4) {
                        const ba = a.children[sortCol]?.dataset.bytes;
                        const bb = b.children[sortCol]?.dataset.bytes;
                        if (ba !== undefined && bb !== undefined) {
                            return (parseInt(ba) - parseInt(bb)) * sortDir;
                        }
                    }
                    let ta = getCellText(a, sortCol);
                    let tb = getCellText(b, sortCol);
                    // Numeric sort — only if text is purely numeric
                    let na = parseFloat(ta);
                    let nb = parseFloat(tb);
                    if (!isNaN(na) && !isNaN(nb) && /^\d+(\.\d+)?$/.test(ta)) {
                        return (na - nb) * sortDir;
                    }
                    return ta.localeCompare(tb) * sortDir;
                });
            }

            // Render
            tbody.innerHTML = '';
            visible.forEach(r => tbody.appendChild(r));

            // Renumber # column (index 0) after filter
            visible.forEach((r, i) => {
                if (r.children[0]) r.children[0].textContent = i + 1;
            });

            // Update summary totals (tfoot)
            const tfoot = table.querySelector('tfoot');
            if (tfoot) {
                let sumSec = 0, sumUp = 0, sumDn = 0;
                visible.forEach(r => {
                    const sec = r.children[2]?.dataset.seconds;
                    const up  = r.children[3]?.dataset.bytes;
                    const dn  = r.children[4]?.dataset.bytes;
                    if (sec) sumSec += parseInt(sec);
                    if (up)  sumUp  += parseInt(up);
                    if (dn)  sumDn  += parseInt(dn);
                });
                const totalSession = tfoot.querySelector('#totalSession');
                const totalUpload  = tfoot.querySelector('#totalUpload');
                const totalDownload = tfoot.querySelector('#totalDownload');
                if (totalSession) {
                    const d = Math.floor(sumSec / 86400);
                    const rem = sumSec % 86400;
                    const h = Math.floor(rem / 3600);
                    const m = Math.floor((rem % 3600) / 60);
                    const s = rem % 60;
                    const timeStr = String(h).padStart(2, '0') + ':' +
                                    String(m).padStart(2, '0') + ':' +
                                    String(s).padStart(2, '0');
                    totalSession.innerHTML = '<strong>' + (d > 0 ? d + 'd ' + timeStr : timeStr) + '</strong>';
                }
                if (totalUpload) totalUpload.innerHTML = '<strong>' + formatBytes(sumUp) + '</strong>';
                if (totalDownload) totalDownload.innerHTML = '<strong>' + formatBytes(sumDn) + '</strong>';
            }

            if (resultInfo) {
                resultInfo.textContent = visible.length + ' / ' + rows.length + ' data';
            }
        }

        // Search input
        if (searchInput) {
            searchInput.addEventListener('input', applyFilters);
        }

        // Date range inputs
        if (dateStart) dateStart.addEventListener('change', applyFilters);
        if (dateEnd) dateEnd.addEventListener('change', applyFilters);

        // Clear button
        const clearBtn = filterBar.querySelector('.filter-clear');
        if (clearBtn) {
            clearBtn.addEventListener('click', function() {
                searchInput.value = '';
                filterSelects.forEach(s => s.value = '');
                // Reset date ke default: 1 bulan lalu s/d hari ini
                var today = new Date();
                var oneMonthAgo = new Date(today);
                oneMonthAgo.setMonth(oneMonthAgo.getMonth() - 1);
                if (dateStart) dateStart.value = oneMonthAgo.toISOString().split('T')[0];
                if (dateEnd) dateEnd.value = today.toISOString().split('T')[0];
                sortCol = -1;
                sortBtns.forEach(b => b.classList.remove('active'));
                // Clear table header sort arrows
                thead.querySelectorAll('.sort-arrow').forEach(a => {
                    a.classList.remove('active');
                    a.textContent = '';
                });
                applyFilters();
            });
        }

        // Dropdown filters
        filterSelects.forEach(sel => {
            sel.addEventListener('change', applyFilters);
        });

        // Sort buttons (per column) — skip the clear/reset button
        sortBtns.forEach(btn => {
            if (btn.classList.contains('filter-clear')) return;
            btn.addEventListener('click', function() {
                const col = parseInt(this.dataset.col);
                if (sortCol === col) {
                    sortDir *= -1;
                } else {
                    sortCol = col;
                    sortDir = 1;
                }
                sortBtns.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                this.textContent = (sortDir === 1 ? '▲' : '▼') + ' ' + (this.dataset.label || '');
                applyFilters();
            });
        });

        // Clickable table headers
        if (thead) {
            thead.querySelectorAll('.sortable-th').forEach(th => {
                th.addEventListener('click', function() {
                    const col = parseInt(this.dataset.col);
                    if (sortCol === col) {
                        sortDir *= -1;
                    } else {
                        sortCol = col;
                        sortDir = 1;
                    }
                    // Update arrow indicators
                    thead.querySelectorAll('.sort-arrow').forEach(a => a.classList.remove('active'));
                    const arrow = this.querySelector('.sort-arrow');
                    if (arrow) {
                        arrow.classList.add('active');
                        arrow.textContent = sortDir === 1 ? ' ▲' : ' ▼';
                    }
                    sortBtns.forEach(b => b.classList.remove('active'));
                    applyFilters();
                });
            });
        }

        // Apply filters on page load (default date range)
        applyFilters();
    })();

    // ===== Klik username di tabel accounting → filter =====
    function filterByUser(username) {
        var searchInput = document.querySelector('.filter-search');
        if (searchInput) {
            // Set value & trigger native input event
            var nativeInputValueSetter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;
            nativeInputValueSetter.call(searchInput, username);
            var event = new Event('input', { bubbles: true });
            searchInput.dispatchEvent(event);
            // Scroll ke filter bar
            searchInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }

    // ===== Login & Setup Modal =====
    function openLoginModal() {
        closeSidebar();
        document.getElementById('loginModal').classList.add('active');
        var errDiv = document.getElementById('loginError');
        if (errDiv) { errDiv.style.display = 'none'; }

        // Fokus ke field pertama yang ada
        var firstField = document.getElementById('setupUsername') || document.getElementById('modalUsername');
        if (firstField) {
            firstField.value = '';
            setTimeout(function() { firstField.focus(); }, 150);
        }
        var pwField = document.getElementById('setupPassword') || document.getElementById('modalPassword');
        if (pwField) pwField.value = '';
        var cfField = document.getElementById('setupConfirm');
        if (cfField) cfField.value = '';
    }

    function closeLoginModal() {
        document.getElementById('loginModal').classList.remove('active');
    }

    // ===== First-Time Setup =====
    function submitSetup() {
        var btn = document.getElementById('setupSubmitBtn');
        var errDiv = document.getElementById('loginError');
        var username = document.getElementById('setupUsername').value.trim();
        var password = document.getElementById('setupPassword').value;
        var confirm = document.getElementById('setupConfirm').value;

        if (!username || !password) {
            showLoginError('Username dan password wajib diisi.');
            return;
        }
        if (password.length < 6) {
            showLoginError('Password minimal 6 karakter.');
            return;
        }
        if (password !== confirm) {
            showLoginError('Password dan konfirmasi tidak cocok.');
            return;
        }

        btn.disabled = true;
        btn.textContent = 'Menyimpan...';
        errDiv.style.display = 'none';

        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'login.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.onload = function() {
            btn.disabled = false;
            btn.textContent = 'Buat Akun & Masuk';
            try {
                var resp = JSON.parse(xhr.responseText);
                if (resp.success) {
                    window.location.href = resp.redirect || 'index.php';
                } else {
                    showLoginError(resp.error || 'Setup gagal.');
                }
            } catch(e) {
                showLoginError('Terjadi kesalahan. Coba lagi.');
            }
        };
        xhr.onerror = function() {
            btn.disabled = false;
            btn.textContent = 'Buat Akun & Masuk';
            showLoginError('Gagal terhubung ke server.');
        };
        xhr.send('setup_admin=1&username=' + encodeURIComponent(username) + '&password=' + encodeURIComponent(password) + '&confirm_password=' + encodeURIComponent(confirm));
    }

    function showLoginError(msg) {
        var errDiv = document.getElementById('loginError');
        errDiv.style.display = 'block';
        errDiv.style.cssText = 'background:rgba(239,68,68,0.12);border:1px solid #fca5a5;color:#991b1b;padding:8px 12px;border-radius:5px;margin-bottom:10px;font-size:0.73rem;text-align:center;';
        errDiv.textContent = msg;
    }

    // ===== Login =====

    function submitLogin() {
        var btn = document.getElementById('loginSubmitBtn');
        var errDiv = document.getElementById('loginError');
        var username = document.getElementById('modalUsername').value.trim();
        var password = document.getElementById('modalPassword').value;

        if (!username || !password) {
            showLoginError('Username dan password wajib diisi.');
            return;
        }

        btn.disabled = true;
        btn.textContent = 'Memproses...';
        errDiv.style.display = 'none';

        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'login.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.onload = function() {
            btn.disabled = false;
            btn.textContent = 'Masuk';
            try {
                var resp = JSON.parse(xhr.responseText);
                if (resp.success) {
                    window.location.href = resp.redirect || 'index.php';
                } else {
                    showLoginError(resp.error || 'Login gagal.');
                }
            } catch(e) {
                showLoginError('Terjadi kesalahan. Coba lagi.');
            }
        };
        xhr.onerror = function() {
            btn.disabled = false;
            btn.textContent = 'Masuk';
            showLoginError('Gagal terhubung ke server.');
        };
        xhr.send('login=1&username=' + encodeURIComponent(username) + '&password=' + encodeURIComponent(password));
    }

    // Enter key untuk semua field di modal
    document.getElementById('loginModal').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            var setupBtn = document.getElementById('setupSubmitBtn');
            var loginBtn = document.getElementById('loginSubmitBtn');
            if (setupBtn && setupBtn.offsetParent !== null) submitSetup();
            else if (loginBtn && loginBtn.offsetParent !== null) submitLogin();
        }
    });

    // Close login modal on overlay click
    document.getElementById('loginModal').addEventListener('click', function(e) {
        if (e.target === this) closeLoginModal();
    });

    // ===== Show/Hide Password Toggle =====
    function togglePassword(btn) {
        var input = btn.parentElement.querySelector('input');
        var eye = btn.querySelector('.pw-eye');
        var eyeOff = btn.querySelector('.pw-eye-off');
        if (input.type === 'password') {
            input.type = 'text';
            eye.style.display = 'none';
            eyeOff.style.display = '';
        } else {
            input.type = 'password';
            eye.style.display = '';
            eyeOff.style.display = 'none';
        }
    }
    </script>
    <script>lucide.createIcons();</script>
</body>
</html>
