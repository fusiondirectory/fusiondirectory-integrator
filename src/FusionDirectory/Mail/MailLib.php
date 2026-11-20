<?php

namespace FusionDirectory\Mail;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class MailLib
{

  protected string  $setFrom;
  protected ?string $setBCC;
  protected array   $recipients;
  protected string  $body;
  protected ?string $signature;
  protected string  $subject;
  protected ?string $receipt;
  protected ?array  $attachments;
  private PHPMailer $mail;

  function __construct (
    string $setFrom,
    ?string $setBCC,
    array $recipients,
    string $body,
    ?string $signature,
    string $subject,
    ?string $receipt = NULL, ?array $attachments = NULL
  )
  {
    // The TRUE value passed it to enable the exception handling properly.
    $this->mail        = new PHPMailer(TRUE);
    $this->setFrom     = $setFrom;
    $this->setBCC      = $setBCC;
    $this->recipients  = $recipients;
    $this->body        = $body;
    $this->signature   = $signature;
    $this->subject     = $subject;
    $this->receipt     = $receipt;
    $this->attachments = $attachments;

  }

  public function sendMail (): array
  {
    // Our returned array
    $errors = [];

    $this->mail->isSMTP();

    $this->mail->Host = $_ENV["MAIL_HOST"];

    if (isset($_ENV["MAIL_DEBUG"])) {

      if ($_ENV["MAIL_DEBUG"] == "CLIENT") {
        $this->mail->SMTPDebug = SMTP::DEBUG_CLIENT;
      }

      if ($_ENV["MAIL_DEBUG"] == "SERVER") {
        $this->mail->SMTPDebug = SMTP::DEBUG_SERVER;
      }

      if ($_ENV["MAIL_DEBUG"] == "CONNECTION") {
        $this->mail->SMTPDebug = SMTP::DEBUG_CONNECTION;
      }
    }

    if ($_ENV["MAIL_SECURITY"] == 'tls') {
      $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    } else {
      if ($_ENV["MAIL_SECURITY"] == 'ssl') {
        $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
      }
    }

    if (isset($_ENV["MAIL_AUTH"]) && ((strtolower($_ENV["MAIL_SECURITY"]) == 'tls' || strtolower($_ENV["MAIL_SECURITY"]) == 'ssl'))) {

      if (isset($_ENV["MAIL_USER"]) && (isset($_ENV["MAIL_PASS"]))) {
        $this->mail->Username = $_ENV["MAIL_USER"];
        $this->mail->Password = $_ENV["MAIL_PASS"];
        $this->mail->AuthType = $_ENV["MAIL_AUTH"];
        $this->mail->SMTPAuth = TRUE;
      } else {
        $this->mail->SMTPAuth = FALSE;
        $this->mail->AuthType = '';
      }
    }

    if (isset($_ENV["MAIL_PORT"])) {
      $this->mail->Port = $_ENV["MAIL_PORT"];
    } else {
      $this->mail->Port = 25;
    }

    $this->mail->CharSet  = 'UTF-8';

    if (!empty($this->attachments)) {
      foreach ($this->attachments as $attachment) {
        $this->mail->addStringAttachment($attachment['content'], $attachment['cn']);
      }
    }

    $this->mail->setFrom($this->setFrom);

    if (!empty($this->setBCC)) {
      $this->mail->addBCC($this->setBCC);
    }

    if ($this->receipt === 'TRUE') {
      $this->mail->addCustomHeader('Disposition-Notification-To', $this->setFrom);
    }

    $this->mail->Subject = $this->subject;
    $this->mail->Body    = $this->body;

    if (!empty($this->signature)) {
      $this->mail->Body .= "\n\n" . $this->signature;
    }

    // add it to keep SMTP connection open after each email sent
    $this->mail->SMTPKeepAlive = TRUE;

    if (!empty($this->recipients["count"])) {
      unset($this->recipients["count"]);
    }

    /* We have an anti-spam logic applied above the mail controller. In case of mail template, only one email is within
     the recipient address, in case of notifications (e.g), multiple address exists. Therefore, the counting of anti-spam
    increment is applied prior of this controller added by the numbers of recipients. See notifications logic in a send
    method.
    */
    foreach ($this->recipients as $mail) {
      $this->mail->addAddress($mail);

      try {
        $this->mail->send();

      } catch (\Exception $e) {
        $errors[] = $this->mail->ErrorInfo;

      }
      $this->mail->clearAddresses();

      if (empty($errors)) {
        $errors[] = "SUCCESS";
      }
    }

    $this->mail->smtpClose();
    return $errors;
  }

  public function sendMailHtml (): array
  {
    // Our returned array
    $errors = [];

    $this->mail->isSMTP();

    $this->mail->Host = $_ENV["MAIL_HOST"];

    if (isset($_ENV["MAIL_DEBUG"])) {

      if ($_ENV["MAIL_DEBUG"] == "CLIENT") {
        $this->mail->SMTPDebug = SMTP::DEBUG_CLIENT;
      }

      if ($_ENV["MAIL_DEBUG"] == "SERVER") {
        $this->mail->SMTPDebug = SMTP::DEBUG_SERVER;
      }

      if ($_ENV["MAIL_DEBUG"] == "CONNECTION") {
        $this->mail->SMTPDebug = SMTP::DEBUG_CONNECTION;
      }
    }

    if ($_ENV["MAIL_SECURITY"] == 'tls') {
      $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    } else {
      if ($_ENV["MAIL_SECURITY"] == 'ssl') {
        $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
      }
    }

    if (isset($_ENV["MAIL_AUTH"]) && ((strtolower($_ENV["MAIL_SECURITY"]) == 'tls' || strtolower($_ENV["MAIL_SECURITY"]) == 'ssl'))) {

      if (isset($_ENV["MAIL_USER"]) && (isset($_ENV["MAIL_PASS"]))) {
        $this->mail->Username = $_ENV["MAIL_USER"];
        $this->mail->Password = $_ENV["MAIL_PASS"];
        $this->mail->AuthType = $_ENV["MAIL_AUTH"];
        $this->mail->SMTPAuth = TRUE;
      } else {
        $this->mail->SMTPAuth = FALSE;
        $this->mail->AuthType = '';
      }
    }

    if (isset($_ENV["MAIL_PORT"])) {
      $this->mail->Port = $_ENV["MAIL_PORT"];
    } else {
      $this->mail->Port = 25;
    }

    $this->mail->CharSet  = 'UTF-8';

    if (!empty($this->attachments)) {
      foreach ($this->attachments as $attachment) {
        $this->mail->addStringAttachment($attachment['content'], $attachment['cn']);
      }
    }

    $this->mail->setFrom($this->setFrom);

    if (!empty($this->setBCC)) {
      $this->mail->addBCC($this->setBCC);
    }

    if ($this->receipt === 'TRUE') {
      $this->mail->addCustomHeader('Disposition-Notification-To', $this->setFrom);
    }

    // Define the mail as HTML
    $this->mail->isHTML(TRUE);

    $this->mail->Subject = $this->subject;
    $this->mail->Body    = $this->body;

    if (!empty($this->signature)) {
      $this->mail->Body .= "\n\n" . $this->signature;
    }

    // add it to keep SMTP connection open after each email sent
    $this->mail->SMTPKeepAlive = TRUE;

    if (!empty($this->recipients["count"])) {
      unset($this->recipients["count"]);
    }

    /* We have an anti-spam logic applied above the mail controller. In case of mail template, only one email is within
     the recipient address, in case of notifications (e.g), multiple address exists. Therefore, the counting of anti-spam
    increment is applied prior of this controller added by the numbers of recipients. See notifications logic in a send
    method.
    */
    foreach ($this->recipients as $mail) {
      $this->mail->addAddress($mail);

      try {
        $this->mail->send();

      } catch (\Exception $e) {
        $errors[] = $this->mail->ErrorInfo;

      }
      $this->mail->clearAddresses();

      if (empty($errors)) {
        $errors[] = "SUCCESS";
      }
    }

    $this->mail->smtpClose();
    return $errors;
  }
}
