<?php
require __DIR__ . '/includes/bootstrap.php';

if (!is_post()) {
    redirect('index.php');
}
verify_csrf();
logout_user();
start_session();
flash('success', 'You have signed out.');
redirect('login.php');
