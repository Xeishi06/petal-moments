<?php
require_once __DIR__ . '/config/functions.php';
start_session();
logout_user();
flash('success', 'You have been logged out.');
redirect('index.php');
