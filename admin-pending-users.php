<?php
require_once __DIR__ . '/includes/layout.php';
require_login([ROLE_OVPRE_ADMIN, ROLE_SUPER_ADMIN, ROLE_RESEARCH_DIRECTOR]);
// Account approvals removed: registration directly activates accounts
redirect_to('admin-users.php');
exit;
