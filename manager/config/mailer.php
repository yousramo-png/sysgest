<?php
// sysgest/manager/config/mailer.php
function send_mail(string $to, string $subject, string $html, string $text = ''): bool {
    // Si PHPMailer dispo et SMTP demandé
    if (defined('__MAIL_TYPE__') && __MAIL_TYPE__ === 'smtp' && class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = __SMTP_HOST__;
            $mail->SMTPAuth   = true;
            $mail->Username   = __SMTP_USER__;
            $mail->Password   = __SMTP_PASS__;
            $mail->SMTPSecure = __SMTP_SECURE__; // 'tls' or 'ssl'
            $mail->Port       = __SMTP_PORT__;

            $mail->setFrom(__MAIL_FROM__, __MAIL_FROM_NAME__);
            $mail->addAddress($to);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $html;
            $mail->AltBody = $text ?: strip_tags($html);

            return $mail->send();
        } catch (Throwable $e) {
            if (function_exists('app_log')) {
                app_log('error', 'SMTP send failed', ['to'=>$to, 'msg'=>$e->getMessage()]);
            }
            return false;
        }
    }

    // Fallback: mail() PHP natif
    $headers = [];
    $headers[] = "From: ".__MAIL_FROM_NAME__." <".__MAIL_FROM__.">";
    $headers[] = "MIME-Version: 1.0";
    $headers[] = "Content-Type: text/html; charset=UTF-8";
    $ok = @mail($to, '=?UTF-8?B?'.base64_encode($subject).'?=', $html, implode("\r\n", $headers));
    if (!$ok && function_exists('app_log')) {
        app_log('error', 'mail() send failed', ['to'=>$to]);
    }
    return $ok;
}
