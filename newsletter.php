<?php
require_once __DIR__ . '/config/functions.php';
start_session();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $stmt = db()->prepare("SELECT id FROM newsletter_subscribers WHERE email = :e");
        $stmt->execute([':e' => $email]);
        if ($stmt->fetch()) {
            flash('success', 'You are already subscribed!');
        } else {
            db()->prepare("INSERT INTO newsletter_subscribers (email) VALUES (:e)")->execute([':e' => $email]);
            flash('success', 'Welcome to the Petal Moments newsletter!');
        }
    } else {
        flash('error', 'Please enter a valid email.');
    }
}
redirect('index.php');
