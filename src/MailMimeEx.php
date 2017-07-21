<?php
declare(strict_types=1);

namespace dfkgw\MailMimeEx;

use \Mail_mime;

class MailMimeEx
{
    protected $message;
    protected $options;

    public function __construct(
        array $headers = array(),
        string $text = ''
    ) {
        $message = self::initMessage($headers, $text);
        $this->message = $message;

        $this->options = [];
        $content_type = (string) self::getVal($headers, 'Content-Type');
        $matches = [];

        // Set Format & DelSp parameter (RFC 3676)
        $format = null;
        $delsp = null;
        if (preg_match('/format=(\\w+)/i', $content_type, $matches)) {
            if (strtolower($matches[1]) == 'flowed') {
                $format = 'flowed';
                if (preg_match('/delsp=(\\w+)/i', $content_type, $matches)) {
                    if (strtolower($matches[1]) == 'yes') {
                        $delsp = 'yes';
                    }
                }
            }
        }
        $this->options['format'] = $format;
        $this->options['delsp'] = $delsp;
    }

    private static function initMessage(
        array $headers = array(),
        string $text = ''
    ): Mail_mime {
        // Set up a message
        $message = new Mail_mime("\r\n");

        // Set message body
        $message->setTXTBody($text);

        // Set encodings
        $message->setParam('text_encoding', '8bit');
        $message->setParam('html_encoding', 'quoted-printable');
        $message->setParam('head_encoding', 'quoted-printable');
        $message->setParam('head_charset', 'UTF-8');
        $message->setParam('html_charset', 'UTF-8');
        $message->setParam('text_charset', 'UTF-8');

        // Set headers
        $message->headers($headers);

        return $message;
    }

    /* ======== Set/get MIME headers ======== */

    /**
     * Get encoded headers
     */
    public function getHeaders()
    {
        return $this->message->headers();
    }

    /**
     * Get raw headers (in a bad-mannered way)
     */
    public function getRawHeaders()
    {
        $message_array = (array)$this->message;
        return $message_array["\0*\0headers"];
    }

    /**
     * Get option parameter
     */
    public function getOption($key)
    {
        return self::getVal($this->options, $key);
    }

    /**
     * Set headers
     */
    public function setHeaders($headers)
    {
        $encodedHeaders = $this->message->headers($headers, true);
        return $encodedHeaders;
    }

    /**
     * Get text body
     */
    public function getTextBody()
    {
        return $this->message->getTXTBody();
    }

    /**
     * Set text body
     */
    public function setTextBody($text)
    {
        $this->message->setTXTBody($text);
    }

    /**
     * Set option parameter
     */
    public function setOption($key, $value)
    {
        $this->options[$key] = $value;
    }

    /* ======== Set/get parameters ======== */

    public function setParam($name, $new_value)
    {
        $this->message->setParam($name, $new_value);
    }

    /* ======== Set/get charset parameters ======== */

    public function getHeaderCharset()
    {
        return $this->_getCharset('head_charset');
    }

    public function getTextCharset()
    {
        return $this->_getCharset('text_charset');
    }

    private function _getCharset($name)
    {
        $charset = $this->message->getParam($name);
        if (empty($charset)) {
            $charset = 'US-ASCII';
        } else {
            $pos = strpos($charset, ';');
            if ($pos !== false) {
                $charset = substr($charset, 0, $pos);
            }
        }
        return $charset;
    }

    /* ======== Convert character encodings ======== */

    public function updateHeaderCharset(string $new_charset)
    {
        $current_charset = $this->getHeaderCharset();
        if ($new_charset != $current_charset) {
            $this->setParam('head_charset', $new_charset);
            $this->_convertHeaders($new_charset, $current_charset);
        }
    }

    public function updateTextCharset(string $new_charset)
    {
        $current_charset = $this->getTextCharset();
        if ($new_charset != $current_charset) {
            $text_charset = $new_charset;

            // Format=XXX and DelSp=XXX can appear in "text_charset" parameter
            $format = $this->getOption('format');
            if (!empty($format)) {
                $text_charset .= '; format=' . $format;
                $delsp = $this->getOption('delsp');
                if (!empty($delsp)) {
                    $text_charset .= '; delsp=' . $delsp;
                }
            }

            $this->setParam('text_charset', $text_charset);
            $this->_convertTextBody($new_charset, $current_charset);
        }
    }

    private function _convertHeaders(string $new_charset, string $current_charset=null)
    {
        $headers = $this->getRawHeaders();
        $changed = false;
        foreach ($headers as $key => $val) {
            $new_val = self::_convert($val, $new_charset, $current_charset);
            if ($val != $new_val) {
                $headers[$key] = $new_val;
                $changed = true;
            }
        }
        if ($changed) {
            $this->setHeaders($headers);
        }
    }

    private function _convertTextBody(string $new_charset, string $current_charset=null)
    {
        $text = $this->getTextBody();
        $text = self::_convert($text, $new_charset, $current_charset);
        $this->setTextBody($text);
    }

    private static function _convert($value, $to_encoding, $from_encoding)
    {
        if (is_array($value)) {
            $value_new = [];
            foreach ($value as $val) {
                $value_new[] = self::_convert($val, $to_encoding, $from_encoding);
            }
        } else {
            if (empty($from_encoding)) {
                $from_encoding = mb_internal_encoding();
            }
            $value_new = mb_convert_encoding($value, $to_encoding, $from_encoding);
        }
        return $value_new;
    }

    /*======== Util ========*/
    private static function getVal(array $array, $key, $defaultValue = null)
    {
        if (array_key_exists($key, $array)) {
            return $array[$key];
        } else {
            return $defaultValue;
        }
    }
}
