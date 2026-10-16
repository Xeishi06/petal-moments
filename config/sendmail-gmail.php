<?php
// Gmail App Password guide for Petal Moments (XAMPP)
//
// 1. Enable 2-Step Verification in your Google Account.
// 2. Google Account -> Security -> App passwords.
//    Create an app password for Mail (Custom name, e.g. "Petal Moments").
// 3. Update config/mail.local.php:
//
//     return [
//       'base_url' => 'http://localhost/petal-moments', // HTTPS URL in production
//       'from' => 'your-gmail@gmail.com',
//       'transport' => 'smtp',              // required for Gmail app-password mail
//       'host' => 'smtp.gmail.com',
//       'port' => 587,                      // 465 for SMTPS
//       'encryption' => 'tls',              // ssl for 465
//       'username' => 'your-gmail@gmail.com',
//       'password' => 'abcd efgh ijkl mnop', // 16-character app password
//     ];
//
// 4. Update C:\xampp\sendmail\sendmail.ini:
//
//     [sendmail]
//     smtp_server=smtp.gmail.com
//     smtp_port=587
//     smtp_ssl=tls
//     auth_username=your-gmail@gmail.com
//     auth_password=abcd efgh ijkl mnop
//
// 5. Restart Apache in the XAMPP Control Panel.
// PHP mail() will then use sendmail.exe with Gmail TLS auth.
return null;