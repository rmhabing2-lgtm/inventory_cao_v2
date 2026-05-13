<?php
require_once __DIR__ . '/../includes/session.php';
require_login();

session_regenerate_id(true);
require_once __DIR__ . '/../login/config.php'; // DB connection

$user_id  = (int) $_SESSION['id'];
$username = htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8');
// Synchronized with SQL ENUM: ADMIN, MANAGER, STAFF
$role     = $_SESSION['role']; 
?>

<link rel="stylesheet" href="../assets/vendor/fonts/boxicons.css" />

<link rel="stylesheet" href="../assets/vendor/css/core.css" class="template-customizer-core-css" />
<link rel="stylesheet" href="../assets/vendor/css/theme-default.css" class="template-customizer-theme-css" />
<link rel="stylesheet" href="../assets/css/demo.css" />

<link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />

<script src="../assets/vendor/js/helpers.js"></script>

<script src="../assets/js/config.js"></script>
</head>

<body align="center">

    <div class="layout-wrapper layout-content-navbar">

        <div class="layout-container">
            <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>

            <?php include __DIR__ . '/../includes/navbar.php'; ?>

            <div class="content-wrapper">
                <div class="container-xxl flex-grow-1 container-p-y">

                    <h4 class="fw-bold py-3 mb-4">Edit Employee Details</h4>

                    <?php if ($role === 'ADMIN'): ?>
                        <div class="mb-4">
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#manageUsersModal">
                                <i class="bx bx-group me-1"></i> Manage Users
                            </button>
                        </div>
                        <?php include __DIR__ . '/manage_users_modal.php'; ?>
                    <?php endif; ?>

                    <?php if ($role !== 'ADMIN'): ?>
                    <div class="alert alert-warning">Only administrators can edit other users.</div>
                    <?php else: ?>
                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="row mb-2">
                                <div class="col-md-8">
                                    <input id="adminUserSearch" class="form-control"
                                        placeholder="Search username, name or email" />
                                </div>
                                <div class="col-md-4 text-end">
                                    <select id="adminUserSelect" class="form-select">
                                        <option value="">Select user to edit...</option>
                                        <?php
                                            $uQ = $conn->prepare("SELECT id, username, first_name, last_name, email FROM user ORDER BY id DESC");
                                            $uQ->execute();
                                            $uR = $uQ->get_result();
                                            while ($u = $uR->fetch_assoc()):
                                        ?>
                                        <option value="<?= (int)$u['id'] ?>">
                                            <?= htmlspecialchars($u['username'] . ' — ' . ($u['first_name'] . ' ' . $u['last_name'])) ?>
                                            (<?= htmlspecialchars($u['email']) ?>)</option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                            </div>

                            <form id="adminEditForm" method="post" action="update_employee.php">
                                <input type="hidden" name="target_id" id="target_id" value="" />
                                <div class="row">
                                    <div class="col-md-6 mb-2">
                                        <label class="form-label">First name</label>
                                        <input name="first_name" id="first_name" class="form-control" />
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <label class="form-label">Last name</label>
                                        <input name="last_name" id="last_name" class="form-control" />
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <label class="form-label">Username</label>
                                        <input name="username" id="username" class="form-control" />
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <label class="form-label">Email</label>
                                        <input name="email" id="email" type="email" class="form-control" />
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <label class="form-label">Department</label>
                                        <input name="department" id="department" class="form-control" />
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <label class="form-label">Position</label>
                                        <input name="position" id="position" class="form-control" />
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <label class="form-label">Role</label>
                                        <select name="role" id="role" class="form-select">
                                            <option value="ADMIN">Admin</option>
                                            <option value="MANAGER">Manager</option>
                                            <option value="STAFF">Staff</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <label class="form-label">Status</label>
                                        <select name="status" id="status" class="form-select">
                                            <option value="ACTIVE">ACTIVE</option>
                                            <option value="INACTIVE">INACTIVE</option>
                                            <option value="SUSPENDED">SUSPENDED</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="mt-3 text-end">
                                    <button type="submit" class="btn btn-primary">Save changes</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <script>
                    (function() {
                        const select = document.getElementById('adminUserSelect');
                        const search = document.getElementById('adminUserSearch');
                        const form = document.getElementById('adminEditForm');
                        const fields = ['first_name', 'last_name', 'username', 'email', 'department', 'position',
                            'role', 'status', 'target_id'
                        ];

                        function clearForm() {
                            fields.forEach(f => {
                                const el = document.getElementById(f);
                                if (el) el.value = '';
                            });
                        }

                        select.addEventListener('change', function() {
                            const id = this.value;
                            if (!id) {
                                clearForm();
                                return;
                            }
                            fetch('get_user.php?id=' + encodeURIComponent(id)).then(r => r.json()).then(
                            j => {
                                if (!j || !j.user) {
                                    alert('Failed to load user');
                                    return;
                                }
                                const u = j.user;
                                document.getElementById('target_id').value = u.id;
                                document.getElementById('first_name').value = u.first_name || '';
                                document.getElementById('last_name').value = u.last_name || '';
                                document.getElementById('username').value = u.username || '';
                                document.getElementById('email').value = u.email || '';
                                document.getElementById('department').value = u.department || '';
                                document.getElementById('position').value = u.position || '';
                                document.getElementById('role').value = u.role || 'STAFF';
                                document.getElementById('status').value = u.status || 'ACTIVE';
                            }).catch(e => {
                                alert('Error loading user');
                            });
                        });

                        search.addEventListener('input', function() {
                            const q = (this.value || '').toLowerCase();
                            for (let i = 0; i < select.options.length; i++) {
                                const opt = select.options[i];
                                if (!opt.value) continue;
                                const txt = (opt.text || '').toLowerCase();
                                opt.style.display = (!q || txt.indexOf(q) !== -1) ? '' : 'none';
                            }
                        });
                    })();
                    </script>
                    <?php endif; ?>

                    <?php include 'footer.php'; ?>
                </div>

                <div class="content-backdrop fade"></div>
            </div>
        </div>
    </div>

    <div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1055;">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="toast align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        <i class='bx bx-check-circle me-2'></i> <?= htmlspecialchars($_SESSION['success']) ?>
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="toast align-items-center text-white bg-danger border-0" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        <i class='bx bx-error-circle me-2'></i> <?= htmlspecialchars($_SESSION['error']) ?>
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
    </div>

    <div class="layout-overlay layout-menu-toggle"></div>

    <script src="../assets/vendor/libs/jquery/jquery.js"></script>
    <script src="../assets/vendor/libs/popper/popper.js"></script>
    <script src="../assets/vendor/js/bootstrap.js"></script>
    <script src="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="../assets/vendor/js/menu.js"></script>
    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/pages-account-settings-account.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var toastElList = [].slice.call(document.querySelectorAll('.toast'));
            var toastList = toastElList.map(function (toastEl) {
                // Initialize toast with a 1.5-second delay before auto-hiding
                return new bootstrap.Toast(toastEl, { delay: 1500 }); 
            });
            toastList.forEach(toast => toast.show());
        });
    </script>
</body>
</html>