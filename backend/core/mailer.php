<?php

declare(strict_types=1);

function mailer_autoload_path(): string
{
    return dirname(__DIR__) . '/vendor/autoload.php';
}

function smtp_encryption(string $value): string
{
    $value = strtolower($value);

    return $value === 'ssl'
        ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
        : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
}

function create_smtp_mailer(): \PHPMailer\PHPMailer\PHPMailer
{
    $autoload = mailer_autoload_path();

    if (!file_exists($autoload)) {
        throw new \RuntimeException('Dependencias do backend nao instaladas.');
    }

    require_once $autoload;

    $config = load_config('mail');

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    $mail->CharSet = 'UTF-8';
    $mail->isSMTP();
    $mail->Host = (string) $config['host'];
    $mail->SMTPAuth = true;
    $mail->Username = (string) $config['username'];
    $mail->Password = (string) $config['password'];
    $mail->SMTPSecure = smtp_encryption((string) $config['encryption']);
    $mail->Port = (int) $config['port'];
    $mail->setFrom((string) $config['from_email'], (string) $config['from_name']);

    return $mail;
}

function deliver_email(array $user, string $subject, string $html, string $text): void
{
    $mail = create_smtp_mailer();
    $mail->addAddress((string) $user['email'], (string) $user['name']);
    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body = $html;
    $mail->AltBody = $text;
    $mail->send();
}


function email_template(string $title, string $body): string
{
    return '
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>
    </head>
    <body style="margin:0;padding:0;background:#FEF3C7;font-family:Arial,sans-serif;">
        <table width="100%" cellpadding="0" cellspacing="0" style="background:#FEF3C7;padding:40px 0;">
            <tr>
                <td align="center">
                    <table width="520" cellpadding="0" cellspacing="0" style="background:#FFFFFF;border-radius:12px;overflow:hidden;box-shadow:0 4px 12px rgba(0,0,0,0.07);">

                        <!-- Cabeçalho -->
                        <tr>
                            <td style="background:#FFFBEB;padding:28px 40px;text-align:center;border-bottom:2px solid #FCD34D;">
                                <span style="color:#292524;font-size:22px;font-weight:bold;letter-spacing:1px;">
                                    Vida<span style="color:#F59E0B;">Livre</span>
                                </span>
                            </td>
                        </tr>

                        <!-- Corpo -->
                        <tr>
                            <td style="padding:36px 40px;color:#292524;font-size:15px;line-height:1.7;">
                                ' . $body . '
                            </td>
                        </tr>

                        <!-- Rodapé -->
                        <tr>
                            <td style="background:#FFFBEB;padding:20px 40px;text-align:center;border-top:1px solid #FDE68A;">
                                <p style="margin:0;font-size:12px;color:#78716C;">
                                    Este é um e-mail automático. Por favor, não responda.
                                </p>
                                <p style="margin:8px 0 0;font-size:12px;color:#78716C;">
                                    &copy; Portal Vida Livre
                                </p>
                            </td>
                        </tr>

                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>';
}
function send_password_reset_email(array $user, string $token): void
{
    $resetUrl = frontend_url('redefinir-senha.html?token=' . urlencode($token));
    $subject = 'Redefinicao de senha - Portal Vida Livre';
    $html = '
        <p>Ola, ' . htmlspecialchars((string) $user['name'], ENT_QUOTES, 'UTF-8') . '.</p>
        <p>Recebemos um pedido para redefinir sua senha.</p>
        <p><a href="' . htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') . '">Redefinir senha</a></p>
        <p>Se voce nao fez essa solicitacao, ignore este e-mail.</p>
        <p>O link expira em 1 hora.</p>
    ';
    $text = "Ola, {$user['name']}.\n\nAcesse o link para redefinir sua senha:\n{$resetUrl}\n\nO link expira em 1 hora.";

    deliver_email($user, $subject, $html, $text);
}

function send_email_verification_email(array $user, string $token): void
{
    $verificationUrl = frontend_url('confirmar-email.html?token=' . urlencode($token));
    $name = htmlspecialchars((string) $user['name'], ENT_QUOTES, 'UTF-8');
    $url  = htmlspecialchars($verificationUrl, ENT_QUOTES, 'UTF-8');

    $subject = 'Confirme seu cadastro - Portal Vida Livre';

    $body = '
        <p style="margin:0 0 16px;">Olá, <strong>' . $name . '</strong>.</p>
        <p style="margin:0 0 16px;">Seu cadastro no Portal Vida Livre foi criado com sucesso. Estamos felizes em ter você aqui.</p>
        <p style="margin:0 0 24px;">Para liberar o acesso, confirme seu e-mail clicando no botão abaixo:</p>
        <p style="text-align:center;margin:0 0 24px;">
            <a href="' . $url . '" style="display:inline-block;background:#f59e0b;color:#1a1a2e;text-decoration:none;font-weight:bold;font-size:15px;padding:14px 32px;border-radius:8px;">
                Confirmar cadastro
            </a>
        </p>
        <p style="margin:0 0 8px;font-size:13px;color:#666666;">Se o botão não funcionar, copie e cole o link abaixo no navegador:</p>
        <p style="margin:0 0 24px;font-size:13px;word-break:break-all;">
            <a href="' . $url . '" style="color:#f59e0b;">' . $url . '</a>
        </p>
        <hr style="border:none;border-top:1px solid #eeeeee;margin:24px 0;">
        <p style="margin:0;font-size:13px;color:#888888;">Se você não solicitou este cadastro, ignore este e-mail.</p>
        <p style="margin:8px 0 0;font-size:13px;color:#888888;">O link expira em <strong>24 horas</strong>.</p>
    ';

    $html = email_template($subject, $body);
    $text = "Olá, {$user['name']}.\n\nConfirme seu cadastro acessando o link abaixo:\n{$verificationUrl}\n\nO link expira em 24 horas.";

    deliver_email($user, $subject, $html, $text);
}

