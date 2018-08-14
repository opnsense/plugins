<?php

namespace OPNsense\Backup;

require_once('phpmailer/PHPMailerAutoload.php');

use OPNsense\Core\Config;
use OPNsense\Backup\MailerSettings;

/**
 * Class mail backup
 * @package OPNsense\Backup
 */
class Mailer extends Base implements IBackupProvider
{

    /**
     * get required (user interface) fields for backup connector
     * @return array configuration fields, types and description
     */
    public function getConfigurationFields()
    {
        $fields = array();

        $fields[] = array(
            "name" => "Enabled",
            "type" => "checkbox",
            "label" => gettext("Enable"),
            "value" => null
        );
        $fields[] = array(
            "name" => "Receiver",
            "type" => "text",
            "label" => gettext("Receiver Email Address"),
            "value" => null
        );
        $fields[] = array(
            "name" => "SmtpHost",
            "type" => "text",
            "label" => gettext("SMTP Host"),
            "value" => null
        );
        $fields[] = array(
            "name" => "SmtpPort",
            "type" => "text",
            "label" => gettext("SMTP Port"),
            "value" => null
        );
        $fields[] = array(
            "name" => "SmtpTLS",
            "type" => "checkbox",
            "label" => gettext("Use TLS"),
            "value" => null
        );
        $fields[] = array(
            "name" => "SmtpUsername",
            "type" => "text",
            "label" => gettext("SMTP Username (Optional)"),
            "value" => null
        );
        $fields[] = array(
            "name" => "SmtpPassword",
            "type" => "password",
            "label" => gettext("SMTP Password (Optional)"),
            "value" => null
        );
        $fields[] = array(
            "name" => "GpgEmail",
            "type" => "text",
            "label" => gettext("GPG Email (Optional)"),
            "value" => null
        );
        $fields[] = array(
            "name" => "GpgPublicKey",
            "type" => "textarea",
            "label" => gettext("GPG Public Key (Optional)"),
            "value" => null
        );
        $mailer = new MailerSettings();
        foreach ($fields as &$field) {
            $field['value'] = (string)$mailer->getNodeByReference($field['name']);
        }
        return $fields;
    }

    /**
     * backup provider name
     * @return string user friendly name
     */
    public function getName()
    {
        return gettext("Mailer");
    }

    /**
     * validate and set configuration
     * @param array $conf configuration array
     * @return array of validation errors when not saved
     */
    public function setConfiguration($conf)
    {
        $mailer = new MailerSettings();
        $this->setModelProperties($mailer, $conf);
        $validation_messages = $this->validateModel($mailer);
        if (empty($validation_messages)) {
            $mailer->serializeToConfig();
            Config::getInstance()->save();
        }
        return $validation_messages;
    }

    /**
     * @return array filelist
     */
    public function backup()
    {
        $cnf = Config::getInstance();
        $mailer = new MailerSettings();
        if ($cnf->isValid() && !empty((string)$mailer->Enabled)) {
            $confdata = file_get_contents('/conf/config.xml');
            $result = self::sendEmail($mailer, $confdata);
        }

        return array($result);
    }

    /**
     * Is this provider enabled
     * @return boolean enabled status
     */
    public function isEnabled()
    {
        $mailer = new MailerSettings();
        return (string)$mailer->Enabled === "1";
    }

    public function sendEmail($config, $confdata)
    {
        $smtpUsername = (string)$config->SmtpUsername;
        $smtpPassword = (string)$config->SmtpPassword;
        $gpgPublicKey = (string)$config->GpgPublicKey;
        $gpgEmail     = (string)$config->GpgEmail;
        $email        = (string)$config->Receiver;

        $date     = date('Y-m-d');
        $hostname = gethostname();

        PHPMailerAutoload(PHPMailer);
        $mail = new \PHPMailer(true);
        $mail->IsHTML(true);
        $mail->IsSMTP();
        $mail->SetFrom($email);
        $mail->AddAddress($email);
        $mail->Host    = (string)$config->SmtpHost;
        $mail->Port    = (string)$config->SmtpPort;
        $mail->Subject = $hostname . ' OPNsense config backup ' . $date;
        $mail->Body    = $hostname . ' config backup file';

        if ((string)$config->SmtpTLS === "1") {
            $mail->SMTPSecure = 'tls';
        } else {
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ),
            );
        }

        if ($smtpUsername != "" && $smtpPassword != "") {
            $mail->SMTPAuth = true;
            $mail->Username = $smtpUsername;
            $mail->Password = $smtpPassword;
        }

        if ($gpgPublicKey != "") {
            self::import_key($gpgPublicKey);
        }

        if ($gpgEmail != "") {
            self::encrypt_data($confdata, $gpgEmail);
        } else {
            file_put_contents("backup", $confdata);
        }

        $attachmentName = 'config_' . $hostname . '_' . $date . '.xml.asc';
        $mail->AddAttachment('backup', $attachmentName);

        $mail->Send();

        return $attachmentName;
    }

    function import_key($gpgPublicKey) {
        $descriptorspec = array(
            0 => array("pipe", "r"),
            1 => array("pipe", "w"),
            2 => array("file", "/tmp/import_key_error.txt", "w")
        );

        $process = proc_open('gpg --import', $descriptorspec, $pipes);

        if (is_resource($process)) {
            fwrite($pipes[0], $gpgPublicKey);
            fclose($pipes[0]);
            fclose($pipes[1]);

            proc_close($process);
        }
    }

    function encrypt_data($confdata, $gpgEmail) {
        $descriptorspec = array(
            0 => array("pipe", "r"),
            1 => array("pipe", "w"),
            2 => array("file", "/tmp/encrypt_data_error.txt", "w")
        );

        $process = proc_open(
            'gpg --trust-model always --batch --yes --encrypt --output - --recipient ' . escapeshellarg($gpgEmail),
            $descriptorspec,
            $pipes
        );

        if (is_resource($process)) {
            fwrite($pipes[0], $confdata);
            fclose($pipes[0]);

            do {
                $string = fread($pipes[1], 8192);
                if (strlen($string) == 0) {
                    break;
                }
                $config .= $string;
            } while(true);
            fclose($pipes[1]);
            file_put_contents("backup", $config);

            proc_close($process);
        }
    }
}
