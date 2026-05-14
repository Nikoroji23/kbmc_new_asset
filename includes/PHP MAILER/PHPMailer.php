<?php
namespace PHPMailer\PHPMailer;

class PHPMailer {
    public $CharSet = 'utf-8';
    public $ContentType = 'text/html';
    public $Encoding = '8bit';
    public $ErrorInfo = '';
    public $From = '';
    public $FromName = '';
    public $Subject = '';
    public $Body = '';
    public $AltBody = '';
    public $Mailer = 'mail';
    public $Host = 'localhost';
    public $Port = 25;
    public $SMTPSecure = '';
    public $SMTPAuth = false;
    public $Username = '';
    public $Password = '';
    public $SMTPDebug = 0;
    public $SMTPKeepAlive = false;
    public $Timeout = 300;
    public $Helo = '';
    public $AuthType = '';
    public $Sender = '';
    public $Priority = 3;
    public $WordWrap = 0;
    public $ConfirmReadingTo = '';
    public $Hostname = '';
    public $MessageID = '';
    public $MessageDate = '';
    public $UseSendmailOptions = true;
    public $SingleTo = false;
    public $do_verp = false;
    public $AllowEmpty = false;
    public $DKIM_selector = '';
    public $DKIM_identity = '';
    public $DKIM_passphrase = '';
    public $DKIM_domain = '';
    public $DKIM_copyHeaderFields = true;
    public $DKIM_extraHeaders = [];
    public $DKIM_private = '';
    public $DKIM_private_string = '';
    public $action_function = '';
    public $XMailer = '';
    public $Debugoutput = 'echo';
    public $SMTPAutoTLS = true;
    public $SMTPOptions = [];
    public $dsn = '';
    public $UseSMTPUTF8 = false;
    public $oauth;
    public $SMTPXClient = [];

    protected $smtp;
    protected $to = [];
    protected $cc = [];
    protected $bcc = [];
    protected $ReplyTo = [];
    protected $all_recipients = [];
    protected $attachment = [];
    protected $CustomHeader = [];
    protected $error_count = 0;
    protected $exceptions = false;
    protected static $language = [];
    protected $boundary = [];
    protected $uniqueid = '';
    protected $MIMEBody = '';
    protected $MIMEHeader = '';
    protected $message_type = '';
    protected $mailHeader = '';
    protected $lastMessageID = '';
    protected $RecipientsQueue = [];
    protected $ReplyToQueue = [];
    protected $SingleToArray = [];
    protected $sign_cert_file = '';
    protected $sign_key_file = '';
    protected $sign_extracerts_file = '';
    protected $sign_key_pass = '';

    const VERSION = '6.8.0';
    const STOP_MESSAGE = 0;
    const STOP_CONTINUE = 1;
    const STOP_CRITICAL = 2;
    const ENCRYPTION_STARTTLS = 'tls';
    const ENCRYPTION_SMTPS = 'ssl';
    const CONTENT_TYPE_PLAINTEXT = 'text/plain';
    const CONTENT_TYPE_TEXT_HTML = 'text/html';
    const CONTENT_TYPE_MULTIPART_ALTERNATIVE = 'multipart/alternative';
    const CONTENT_TYPE_MULTIPART_MIXED = 'multipart/mixed';
    const CONTENT_TYPE_MULTIPART_RELATED = 'multipart/related';
    const ENCODING_7BIT = '7bit';
    const ENCODING_8BIT = '8bit';
    const ENCODING_BASE64 = 'base64';
    const ENCODING_BINARY = 'binary';
    const ENCODING_QUOTED_PRINTABLE = 'quoted-printable';
    const CHARSET_ASCII = 'us-ascii';
    const CHARSET_ISO88591 = 'iso-8859-1';
    const CHARSET_UTF8 = 'utf-8';
    const CRLF = "\r\n";
    const FWS = ' ';
    const MAX_LINE_LENGTH = 998;
    const STD_LINE_LENGTH = 76;
    const MAIL_MAX_LINE_LENGTH = 63;

    public static $validator = 'php';

    public function __construct($exceptions = null) {
        if (null !== $exceptions) $this->exceptions = (bool)$exceptions;
        $this->reset();
    }

    public function reset() {
        $this->to = [];
        $this->cc = [];
        $this->bcc = [];
        $this->all_recipients = [];
        $this->ReplyTo = [];
        $this->attachment = [];
        $this->CustomHeader = [];
        $this->error_count = 0;
        $this->message_type = '';
    }

    public function isHTML($isHtml = true) {
        $this->ContentType = $isHtml ? static::CONTENT_TYPE_TEXT_HTML : static::CONTENT_TYPE_PLAINTEXT;
    }

    public function isSMTP() {
        $this->Mailer = 'smtp';
    }

    public function isMail() {
        $this->Mailer = 'mail';
    }

    public function isSendmail() {
        $this->Mailer = 'sendmail';
    }

    public function isQmail() {
        $this->Mailer = 'qmail';
    }

    public function addAddress($address, $name = '') {
        return $this->addOrEnqueueAnAddress('to', $address, $name);
    }

    public function addCC($address, $name = '') {
        return $this->addOrEnqueueAnAddress('cc', $address, $name);
    }

    public function addBCC($address, $name = '') {
        return $this->addOrEnqueueAnAddress('bcc', $address, $name);
    }

    public function addReplyTo($address, $name = '') {
        return $this->addOrEnqueueAnAddress('Reply-To', $address, $name);
    }

    protected function addOrEnqueueAnAddress($kind, $address, $name) {
        $address = trim((string)$address);
        $pos = strrpos($address, '@');
        if (false === $pos) {
            $this->setError('Invalid address: ' . $address);
            return false;
        }
        if ($name !== null && is_string($name)) {
            $name = trim(preg_replace('/[
]+/', '', $name));
        } else {
            $name = '';
        }
        return $this->addAnAddress($kind, $address, $name);
    }

    protected function addAnAddress($kind, $address, $name = '') {
        if ($kind === 'to') $this->to[] = [$address, $name];
        elseif ($kind === 'cc') $this->cc[] = [$address, $name];
        elseif ($kind === 'bcc') $this->bcc[] = [$address, $name];
        elseif ($kind === 'Reply-To') $this->ReplyTo[strtolower($address)] = [$address, $name];
        $this->all_recipients[strtolower($address)] = true;
        return true;
    }

    public function setFrom($address, $name = '', $auto = true) {
        $address = trim((string)$address);
        $name = trim(preg_replace('/[
]+/', '', $name));
        if (strpos($address, '@') === false) {
            $this->setError('Invalid address: ' . $address);
            return false;
        }
        $this->From = $address;
        $this->FromName = $name;
        if ($auto && empty($this->Sender)) $this->Sender = $address;
        return true;
    }

    public function send() {
        try {
            if (!$this->preSend()) return false;
            return $this->postSend();
        } catch (Exception $exc) {
            $this->setError($exc->getMessage());
            return false;
        }
    }

    public function preSend() {
        if (empty($this->Sender) && !empty(ini_get('sendmail_from'))) $this->Sender = ini_get('sendmail_from');
        return true;
    }

    public function postSend() {
        try {
            if ($this->Mailer === 'smtp') return $this->smtpSend($this->MIMEHeader, $this->MIMEBody);
            return $this->mailSend($this->MIMEHeader, $this->MIMEBody);
        } catch (Exception $exc) {
            if ($this->Mailer === 'smtp' && $this->smtp !== null) $this->smtp->quit();
            $this->setError($exc->getMessage());
            return false;
        }
    }

    protected function mailSend($header, $body) {
        $toAddr = [];
        foreach ($this->to as $to) $toAddr[] = $this->addrFormat($to);
        $to = implode(', ', $toAddr);

        $params = null;
        if (!empty($this->Sender)) $params = sprintf('-f%s', $this->Sender);

        $result = @mail($to, $this->encodeHeader($this->secureHeader($this->Subject)), $body, $header, $params);

        if (!$result) {
            $this->setError('Could not instantiate mail function.');
            return false;
        }
        return true;
    }

    protected function smtpSend($header, $body) {
        if (!$this->smtpConnect()) {
            $this->setError('SMTP connect() failed.');
            return false;
        }

        if (!empty($this->Sender) && !$this->smtp->mail($this->Sender)) {
            $this->setError('SMTP Error: Could not set sender.');
            return false;
        }

        foreach ($this->to as $to) {
            if (!$this->smtp->recipient($to[0])) {
                $this->setError('SMTP Error: Recipient failed: ' . $to[0]);
            }
        }

        if (!$this->smtp->data($header . $body)) {
            $this->setError('SMTP Error: Data not accepted.');
            return false;
        }

        $this->smtp->quit();
        $this->smtp->close();
        return true;
    }

    public function smtpConnect($options = null) {
        if (null === $this->smtp) $this->smtp = new SMTP();
        if ($this->smtp->connected()) return true;

        $this->smtp->setTimeout($this->Timeout);
        $this->smtp->setDebugLevel($this->SMTPDebug);

        $hosts = explode(';', $this->Host);
        foreach ($hosts as $hostentry) {
            $hostinfo = [];
            if (!preg_match('/^(?:(ssl|tls):\/\/)*(.*:)?(\d+)$/', $hostentry, $hostinfo)) {
                if (!preg_match('/^(?:(ssl|tls):\/\/)*(.*)?(?::(\d+))?$/', $hostentry, $hostinfo)) continue;
            }

            $prefix = '';
            $tls = ($this->SMTPSecure === static::ENCRYPTION_STARTTLS);
            if ('ssl' === ($hostinfo[1] ?? '') || ('' === ($hostinfo[1] ?? '') && static::ENCRYPTION_SMTPS === $this->SMTPSecure)) {
                $prefix = 'ssl://';
                $tls = false;
            } elseif ('tls' === ($hostinfo[1] ?? '')) {
                $prefix = 'tls://';
            }

            $host = $hostinfo[2] ?? $hostentry;
            $port = $this->Port;
            if (!empty($hostinfo[3]) && is_numeric($hostinfo[3])) $port = (int)$hostinfo[3];

            if ($this->smtp->connect($prefix . $host, $port, $this->Timeout, $options ?? [])) {
                $hello = $this->Helo ?: $this->serverHostname();
                $this->smtp->hello($hello);

                if ($tls) {
                    if (!$this->smtp->startTLS()) {
                        $this->smtp->quit();
                        continue;
                    }
                    $this->smtp->hello($hello);
                }

                if ($this->SMTPAuth && !$this->smtp->authenticate($this->Username, $this->Password, $this->AuthType)) {
                    $this->smtp->quit();
                    continue;
                }

                return true;
            }
        }

        $this->smtp->close();
        return false;
    }

    public function smtpClose() {
        if ($this->smtp !== null && $this->smtp->connected()) {
            $this->smtp->quit();
            $this->smtp->close();
        }
    }

    protected function serverHostname() {
        $result = '';
        if (!empty($this->Hostname)) $result = $this->Hostname;
        elseif (isset($_SERVER['SERVER_NAME'])) $result = $_SERVER['SERVER_NAME'];
        elseif (function_exists('gethostname') && gethostname() !== false) $result = gethostname();
        elseif (php_uname('n') !== false) $result = php_uname('n');
        return empty($result) ? 'localhost.localdomain' : $result;
    }

    public function createHeader() {
        $result = '';
        $result .= $this->headerLine('Date', date('D, j M Y H:i:s O'));

        if (count($this->to) > 0) $result .= $this->addrAppend('To', $this->to);
        $result .= $this->addrAppend('From', [[trim($this->From), $this->FromName]]);

        if (count($this->cc) > 0) $result .= $this->addrAppend('Cc', $this->cc);
        if (count($this->ReplyTo) > 0) $result .= $this->addrAppend('Reply-To', array_values($this->ReplyTo));

        $result .= $this->headerLine('Subject', $this->encodeHeader($this->secureHeader($this->Subject)));
        $result .= $this->headerLine('Message-ID', '<' . md5(uniqid(time())) . '@' . $this->serverHostname() . '>');
        $result .= $this->headerLine('X-Priority', $this->Priority ?? 3);
        $result .= $this->headerLine('X-Mailer', 'PHPMailer ' . self::VERSION);
        $result .= $this->headerLine('MIME-Version', '1.0');
        $result .= $this->getMailMIME();

        return $result;
    }

    public function getMailMIME() {
        $result = '';
        $result .= $this->headerLine('Content-Type', $this->ContentType . '; charset=' . $this->CharSet);
        $result .= $this->headerLine('Content-Transfer-Encoding', $this->Encoding);
        $result .= "
";
        return $result;
    }

    public function createBody() {
        $body = '';
        $this->Encoding = '8bit';
        $body .= $this->Body;
        return $body;
    }

    public function getSentMIMEMessage() {
        return "
" . $this->createHeader();
    }

    protected function addrAppend($type, $addr) {
        $addresses = [];
        foreach ($addr as $address) $addresses[] = $this->addrFormat($address);
        return $type . ': ' . implode(', ', $addresses) . "
";
    }

    public function addrFormat($addr) {
        if (empty($addr[1])) return $this->secureHeader($addr[0]);
        return $this->encodeHeader($this->secureHeader($addr[1]), 'phrase') . ' <' . $this->secureHeader($addr[0]) . '>';
    }

    public function encodeHeader($str, $position = 'text') {
        return $str;
    }

    public function secureHeader($str) {
        return trim(str_replace(["
", "
"], '', $str));
    }

    public function headerLine($name, $value) {
        return $name . ': ' . $value . "
";
    }

    protected function setError($msg) {
        ++$this->error_count;
        $this->ErrorInfo = $msg;
    }

    public function getSMTPInstance() {
        if (!is_object($this->smtp)) $this->smtp = new SMTP();
        return $this->smtp;
    }

    public function setSMTPInstance(SMTP $smtp) {
        $this->smtp = $smtp;
        return $this->smtp;
    }

    public function addCustomHeader($name, $value = null) {
        if (null === $value && strpos($name, ':') !== false) {
            list($name, $value) = explode(':', $name, 2);
        }
        $this->CustomHeader[] = [trim($name), trim($value ?? '')];
    }

    public function clearAddresses() { $this->to = []; $this->all_recipients = []; }
    public function clearCCs() { $this->cc = []; }
    public function clearBCCs() { $this->bcc = []; }
    public function clearReplyTos() { $this->ReplyTo = []; }
    public function clearAllRecipients() { $this->to = []; $this->cc = []; $this->bcc = []; $this->all_recipients = []; }
    public function clearAttachments() { $this->attachment = []; }
    public function clearCustomHeaders() { $this->CustomHeader = []; }

    public function set($name, $value = '') {
        if (property_exists($this, $name)) {
            $this->$name = $value;
            return true;
        }
        return false;
    }

    public function getToAddresses() { return $this->to; }
    public function getCcAddresses() { return $this->cc; }
    public function getBccAddresses() { return $this->bcc; }
    public function getReplyToAddresses() { return array_values($this->ReplyTo); }
    public function getAllRecipientAddresses() { return $this->all_recipients; }
    public function getAttachments() { return $this->attachment; }

    public function setSMTPSecure($secure) { $this->SMTPSecure = $secure; }
    public function getSMTPSecure() { return $this->SMTPSecure; }
    public function setSMTPAutoTLS($auto) { $this->SMTPAutoTLS = $auto; }
    public function getSMTPAutoTLS() { return $this->SMTPAutoTLS; }
    public function setSMTPOptions($options) { $this->SMTPOptions = $options; }
    public function getSMTPOptions() { return $this->SMTPOptions; }
    public function setSMTPDebug($level) { $this->SMTPDebug = $level; }
    public function getSMTPDebug() { return $this->SMTPDebug; }
    public function setDebugOutput($output) { $this->Debugoutput = $output; }
    public function getDebugOutput() { return $this->Debugoutput; }
    public function setSMTPKeepAlive($keepAlive) { $this->SMTPKeepAlive = $keepAlive; }
    public function getSMTPKeepAlive() { return $this->SMTPKeepAlive; }
    public function setTimeout($timeout) { $this->Timeout = $timeout; }
    public function getTimeout() { return $this->Timeout; }
    public function setHost($host) { $this->Host = $host; }
    public function getHost() { return $this->Host; }
    public function setPort($port) { $this->Port = $port; }
    public function getPort() { return $this->Port; }
    public function setHelo($helo) { $this->Helo = $helo; }
    public function getHelo() { return $this->Helo; }
    public function setAuthType($authtype) { $this->AuthType = $authtype; }
    public function getAuthType() { return $this->AuthType; }
    public function setUsername($username) { $this->Username = $username; }
    public function getUsername() { return $this->Username; }
    public function setPassword($password) { $this->Password = $password; }
    public function getPassword() { return $this->Password; }
    public function setFromName($fromName) { $this->FromName = $fromName; }
    public function getFromName() { return $this->FromName; }
    public function getFrom() { return $this->From; }
    public function setSender($sender) { $this->Sender = $sender; }
    public function getSender() { return $this->Sender; }
    public function setSubject($subject) { $this->Subject = $subject; }
    public function getSubject() { return $this->Subject; }
    public function setBody($body) { $this->Body = $body; }
    public function getBody() { return $this->Body; }
    public function setAltBody($altBody) { $this->AltBody = $altBody; }
    public function getAltBody() { return $this->AltBody; }
    public function setWordWrap($wordWrap) { $this->WordWrap = $wordWrap; }
    public function getWordWrap() { return $this->WordWrap; }
    public function setPriority($priority) { $this->Priority = $priority; }
    public function getPriority() { return $this->Priority; }
    public function setCharSet($charSet) { $this->CharSet = $charSet; }
    public function getCharSet() { return $this->CharSet; }
    public function setContentType($contentType) { $this->ContentType = $contentType; }
    public function getContentType() { return $this->ContentType; }
    public function setEncoding($encoding) { $this->Encoding = $encoding; }
    public function getEncoding() { return $this->Encoding; }
    public function setErrorInfo($errorInfo) { $this->ErrorInfo = $errorInfo; }
    public function getErrorInfo() { return $this->ErrorInfo; }
    public function setMailer($mailer) { $this->Mailer = $mailer; }
    public function getMailer() { return $this->Mailer; }
    public function setExceptions($exceptions) { $this->exceptions = $exceptions; }
    public function getExceptions() { return $this->exceptions; }
}