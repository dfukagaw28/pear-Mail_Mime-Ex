<?php
declare(strict_types=1);

namespace dfkgw\MailMimeEx;

use \PHPUnit\Framework\TestCase;

final class MailMimeExTest extends TestCase
{
    public function testConstructMailMimeEx(): void
    {
        $message = new MailMimeEx([], '');
        $this->assertInstanceOf(MailMimeEx::class, $message);
    }

    public function testCanGetSubject(): void
    {
        $message = new MailMimeEx(['Subject' => 'テストメールです'], 'こんにちは');

        // raw subject
        $headers = $message->getRawHeaders();
        $this->assertArrayHasKey('Subject', $headers);
        $subject = $headers['Subject'];
        $expected = 'テストメールです';
        $this->assertEquals($expected, $subject);
    }

    public function testCanEncodeSubject_utf8_q(): void
    {
        $message = new MailMimeEx(['Subject' => 'テストメールです'], 'こんにちは');

        // encoded subject
        $headers = $message->getHeaders();
        $this->assertArrayHasKey('Subject', $headers);
        $subject = $headers['Subject'];
        $expected = join(
            "\r\n ",
            array(
                '=?UTF-8?Q?=E3=83=86=E3=82=B9=E3=83=88=E3=83=A1=E3=83=BC=E3=83=AB?=',
                '=?UTF-8?Q?=E3=81=A7=E3=81=99?='
            )
        );
        $this->assertEquals($expected, $subject);
    }

    public function testCanEncodeSubject_jis_b(): void
    {
        $message = new MailMimeEx(['Subject' => 'テストメールです'], 'こんにちは');

        // Change charset
        $message->getHeaderCharset();
        $message->setParam('head_encoding', 'base64');
        $message->updateHeaderCharset('ISO-2022-JP');
        $subject = $message->getRawHeaders()['Subject'];
        $expected = "\x1B\$B".'%F%9%H%a!<%k$G$9'."\x1B(B";
        //$expected = mb_convert_encoding('テストメールです', 'ISO-2022-JP', 'UTF-8');
        $this->assertEquals($expected, $subject);
    }

    // Sending ISO-2022-JP-MS message as if it is encoded by ISO-2022-JP
    // (NOT RECOMMENDED for public use, but practically useful for communication
    //  within some communities, because ISO-2022-JP-MS accepts JIS X 0213 characters
    //  which contains more characters that JIS X 0208)
    public function testCanEncodeSubject_cp932_b(): void
    {
        $message = new MailMimeEx(['Subject' => 'テストメ〜ル①㈱'], 'こんにちは');

        // Change charset
        $message->getHeaderCharset();
        $message->setParam('head_encoding', 'base64');
        $message->updateHeaderCharset('ISO-2022-JP-MS');
        // This is necessary because "ISO-2022-JP-MS" is not valid
        // for encoding name in MIME header
        $message->setParam('head_charset', 'ISO-2022-JP');

        $subject = $message->getRawHeaders()['Subject'];
        $expected = "\x1B\$B".'%F%9%H%a!A%k-!-j'."\x1B(B";
        //$expected = mb_convert_encoding('テストメ〜ル①㈱', 'ISO-2022-JP-MS', 'UTF-8');
        $this->assertEquals($expected, $subject);
    }

    public function testCanGetTextBody(): void
    {
        $message = new MailMimeEx([], 'こんにちは');

        // raw body
        $text = $message->getTextBody();
        $expected = 'こんにちは';
        $this->assertEquals($expected, $text);
    }

    public function testCanEncodeTextBody_jis(): void
    {
        $message = new MailMimeEx([], 'こんにちは');

        $message->setParam('text_encoding', '7bit');
        $message->updateTextCharset('ISO-2022-JP');

        // encoded body
        $text = $message->getTextBody();
        $expected = "\x1B\$B".'$3$s$K$A$O'."\x1B(B";
        //$expected = mb_convert_encoding('こんにちは', 'ISO-2022-JP', 'UTF-8');
        $this->assertEquals($expected, $text);
    }

    // Sending ISO-2022-JP-MS message as if it is encoded by ISO-2022-JP
    // (NOT RECOMMENDED)
    public function testCanEncodeTextBody_cp932(): void
    {
        $message = new MailMimeEx([], 'こんにちは①髙﨑');

        $message->setParam('text_encoding', '7bit');
        $message->updateTextCharset('ISO-2022-JP-MS');
        // This is necessary because "ISO-2022-JP-MS" is not valid
        // for Content-Type charset
        $message->setParam('text_charset', 'ISO-2022-JP');

        // encoded body
        $text = $message->getTextBody();
        $expected = "\x1B\$B".'$3$s$K$A$O-!|byu'."\x1B(B";
        //$expected = mb_convert_encoding('こんにちは①髙﨑', 'ISO-2022-JP-MS', 'UTF-8');
        $this->assertEquals($expected, $text);
    }

    public function testCanComposeFlowedBody(): void
    {
        $message = new MailMimeEx([], 'Hello world!');

        $message->setTextBody('Hello \r\nworld!');
        $message->setParam('text_encoding', '7bit');
        $message->setParam('text_charset', 'US-ASCII; format=flowed');
        $headers = $message->setHeaders(array());

        // Check text body
        $text = $message->getTextBody();
        $expected = 'Hello \r\nworld!';
        $this->assertEquals($expected, $text);

        // Check headers
        $headers = $message->getRawHeaders();
        // content-type
        $key = 'Content-Type';
        $expected = 'text/plain; charset=US-ASCII; format=flowed';
        $this->assertArrayHasKey($key, $headers);
        $this->assertEquals($expected, $headers[$key]);
        // content-transfer-encoding
        $key = 'Content-Transfer-Encoding';
        $expected = '7bit';
        $this->assertArrayHasKey($key, $headers);
        $this->assertEquals($expected, $headers[$key]);
    }
}
