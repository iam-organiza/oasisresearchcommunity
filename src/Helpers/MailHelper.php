<?php

namespace App\Helpers;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class MailHelper
{
    public static function sendActivationEmail($email, $otp)
    {
        $mail = new PHPMailer(true);
        $constants = get_defined_constants();

        try {
            //Server settings
            // $mail->SMTPDebug = SMTP::DEBUG_SERVER;
            $mail->isSMTP();
            $mail->Host       = EMAIL_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = NO_REPLY_EMAIL;
            $mail->Password   = NO_REPLY_EMAIL;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = SMTP_PORT;

            //Recipients
            $mail->setFrom(NO_REPLY_EMAIL, 'no-reply OASIS Research Community');
            $mail->addAddress($email);

            //Content
            $mail->isHTML(true);
            $mail->Subject = 'Verify your email';
            $mail->AddEmbeddedImage($_SERVER['DOCUMENT_ROOT'] . '/public/assets/media/logos/default-logo.png', 'logo_cid');
            $mail->Body    = <<<HTML
                <style>html,body { padding:0; margin:0; font-family: Inter, Helvetica, "sans-serif"; } a:hover { color: #009ef7; }</style>
                <div id="#kt_app_body_content" style="background-color:#D5D9E2; font-family: Arial,Helvetica,sans-serif; line-height: 1.5; min-height: 100%; font-weight: normal; font-size: 15px; color: #2F3044; margin:0; padding:20px 0; width:100%;">
                    <div style="background-color:#ffffff; padding: 45px 0 34px 0; border-radius: 24px; margin:40px auto; max-width: 600px;">
                        <table align="center" border="0" cellpadding="0" cellspacing="0" width="100%" height="auto" style="border-collapse:collapse">
                            <tbody>
                                <tr>
                                    <td align="center" valign="center" style="text-align:center; padding-bottom: 10px">
                                        
                                        <div style="text-align:center; margin:0 15px 34px 15px">
                                            
                                            <div style="margin-bottom: 10px">
                                                <a href="{$constants['BASE_URL']}" rel="noopener" target="_blank">
                                                    <img alt="Logo" src="cid:logo_cid" style="height: 35px; width: 100px; object-fit: contain;" />
                                                </a>
                                            </div>
                                            
                                            <div style="font-size: 14px; font-weight: 500; margin-bottom: 27px; font-family: Arial,Helvetica,sans-serif;">
                                                <p style="margin-bottom:9px; font-size: 22px; font-weight:700; color: #000204;">Verify your email</p>
                                                <p style="margin-bottom:2px; color:#A1A5B7">To activate your OASIS Research Community Account, please verify your email address.</p>
                                                
                                                <p style="margin-bottom:2px; color:#A1A5B7">Please use the OTP below to verify your account</p>
                                                <br>
                                                <p style="margin-bottom:2px; letter-spacing: 10px; font-size: 24px; text-align: center; color: #000204;">{$otp}</p>

                                            </div>
                                        </div>
                                        
                                    </td>
                                </tr>
                                <tr>
                                    <td align="center" valign="center" style="font-size: 13px; padding:0 15px; text-align:center; font-weight: 500; color: #A1A5B7;font-family: Arial,Helvetica,sans-serif">
                                        <p>&copy; Copyright OASIS Research Community.</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            HTML;

            $mail->AltBody = 'To activate your OASIS Research Community Account, please verify your email address. Please use the OTP below to verify your account \n ' . $otp;

            $mail->send();
        } catch (Exception $e) {
            Logger::getLogger()->error('Failed to send activation email', [
                'email' => $email,
                'otp' => $otp,
                'error' => $mail->ErrorInfo,
            ]);
            ResponseHelper::badRequest($mail->ErrorInfo);
        }
    }
}
