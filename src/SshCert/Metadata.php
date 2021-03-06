<?php

namespace Platformsh\Client\SshCert;

/**
 * Parses an OpenSSH RSA certificate.
 *
 * @see https://cvsweb.openbsd.org/src/usr.bin/ssh/PROTOCOL.certkeys?annotate=HEAD
 */
class Metadata {

    private $keyType;
    private $nonce;
    private $rsaExponent;
    private $publicModulus;
    private $serial;
    private $type;
    private $keyId;
    private $validPrincipals;
    private $validAfter;
    private $validBefore;
    private $criticalOptions;
    private $extensions;
    private $reserved;
    private $signatureKey;
    private $signature;

    /**
     * Constructor
     *
     * @param string $string The certificate's contents.
     */
    public function __construct(string $string)
    {
        list($type, $cert) = \explode(' ', $string);
        if ($type !== 'ssh-rsa-cert-v01@openssh.com') {
            throw new \InvalidArgumentException('Unsupported key type: ' . $type);
        }
        $bytes = \base64_decode($cert, true);
        if (!$bytes) {
            throw new \InvalidArgumentException('Unable to decode SSH certificate');
        }
        $this->keyType = $this->readString($bytes);
        $this->nonce = $this->readString($bytes);
        $this->rsaExponent = $this->readString($bytes);
        $this->publicModulus = $this->readString($bytes);
        $this->serial = $this->readUint64($bytes);
        $this->type = $this->readUint32($bytes);
        $this->keyId = $this->readString($bytes);
        $this->validPrincipals = $this->readPrincipals($this->readString($bytes));
        $this->validAfter = $this->readUint64($bytes);
        $this->validBefore = $this->readUint64($bytes);
        $this->criticalOptions = $this->readTuples($bytes);
        $this->extensions = $this->readTuples($bytes);
        $this->reserved = $this->readString($bytes);
        $this->signatureKey = $this->readString($bytes);
        $this->signature = $this->readString($bytes);
    }

    /**
     * Reads the next string, and removes it from the remaining bytes.
     *
     * @param string &$bytes
     *
     * @return string
     */
    private function readString(string &$bytes): string {
        $len = \unpack('N', \substr($bytes, 0, 4));
        // The first unnamed element from \unpack() will be keyed by 1.
        $str = \substr($bytes, 4, $len[1]);
        $bytes = \substr($bytes, 4 + $len[1]);
        return $str;
    }

    /**
     * Reads the next uint64, and removes it from the remaining bytes.
     *
     * @param string &$bytes
     *
     * @return int
     */
    private function readUint64(string &$bytes): int {
        $ret = \unpack('J', \substr($bytes, 0, 8));
        $bytes = \substr($bytes, 8);
        return (int) $ret[1];
    }

    /**
     * Reads the next uint32, and removes it from the remaining bytes.
     *
     * @param string &$bytes
     *
     * @return int
     */
    private function readUint32(string &$bytes): int {
        $ret = \unpack('N', \substr($bytes, 0, 4));
        $bytes = \substr($bytes, 4);
        return (int) $ret[1];
    }

    /**
     * Reads the next set of tuples, and removes it from the remaining bytes.
     *
     * @see https://github.com/golang/crypto/commit/59435533c88bd0b1254c738244da1fe96b59d05d
     *
     * @param string &$bytes
     *
     * @return array
     */
    private function readTuples(string &$bytes): array {
        $container = $this->readString($bytes);
        $tuples = [];
        while (strlen($container) > 0) {
            $key = $this->readString($container);
            $value = $this->readString($container);
            $tuples[$key] = $value;
        }
        return $tuples;
    }

    /**
     * Reads principals from a packed string.
     *
     * @param string $str
     *
     * @return string[]
     */
    private function readPrincipals(string $str): array {
        $principals = [];
        while (strlen($str) > 0) {
            $principals[] = $this->readString($str);
        }
        return $principals;
    }

    /**
     * Returns the "valid after" date of the certificate, as a UNIX timestamp.
     *
     * @return int
     */
    public function getValidAfter(): int {
        return $this->validAfter;
    }

    /**
     * Returns the expiry date of the certificate, as a UNIX timestamp.
     *
     * @return int
     */
    public function getValidBefore(): int {
        return $this->validBefore;
    }

    /**
     * Returns the certificate extensions.
     *
     * @return array
     */
    public function getExtensions(): array {
        return $this->extensions;
    }

    /**
     * Returns the certificate's key ID.
     *
     * According to PROTOCOL.certkeys:
     * "key id is a free-form text field that is filled in by the CA at the time
     * of signing; the intention is that the contents of this field are used to
     * identify the identity principal in log messages."
     *
     * @return string
     */
    public function getKeyId(): string {
        return $this->keyId;
    }
}
