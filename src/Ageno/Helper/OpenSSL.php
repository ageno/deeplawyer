<?php

namespace Ageno\Helper;

class OpenSSL
{
    /**
     * @return array
     */
    public static function generateRSA()
    {
        $rsaKey = openssl_pkey_new(
            [
                'private_key_bits' => 1024,
                'private_key_type' => OPENSSL_KEYTYPE_RSA,
            ]
        );

        $privKey = openssl_pkey_get_private($rsaKey);
        openssl_pkey_export($privKey, $pem); // Private Key
        $pubKey = self::sshEncodePublicKey($rsaKey); // Public Key

        return [$pubKey, $pem];
    }

    private static function sshEncodePublicKey($privKey)
    {
        $keyInfo = openssl_pkey_get_details($privKey);
        $buffer = pack("N", 7) . "ssh-rsa" .
            self::sshEncodeBuffer($keyInfo['rsa']['e']) .
            self::sshEncodeBuffer($keyInfo['rsa']['n']);

        return "ssh-rsa " . base64_encode($buffer);
    }

    private static function sshEncodeBuffer($buffer)
    {
        $len = strlen($buffer);

        if (ord($buffer[0]) & 0x80) {
            $len++;
            $buffer = "\x00" . $buffer;
        }

        return pack("Na*", $len, $buffer);
    }
}
