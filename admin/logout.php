<?php
/**
 * Ends the admin session. POST only so a link or prefetch cannot sign out.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';

if (!is_post()) {
    redirect('admin/index.php');
}

require_csrf();
logout_admin();
flash('info', __('admin.logged_out'));
redirect('admin/login.php');
