<?php

namespace App\Handler;

use Crypt_GPG;
use Crypt_GPG_Exception;
use Crypt_GPG_FileException;
use Crypt_GPG_NoDataException;
use RuntimeException;

/**
 * Class GPGKeyHandler
 */
class GPGKeyHandler
{
    /** @var Crypt_GPG */
    private $gpg;

    /** @var string */
    private $tempDir;

    /** @var string */
    private $email;

    /** @var string */
    private $key;

    /**
     * @return string
     */
    private function createTempDir(): string {
        $path = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'userli_' . mt_rand() . microtime(true);
        if (!mkdir($concurrentDirectory = $path) && !is_dir($concurrentDirectory)) {
            throw new RuntimeException('Failed to create directory: ' . $concurrentDirectory);
        }

        return $path;
    }

    /**
     * @param string $dir
     */
    private function recursiveRemoveDir(string $dir): void {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object === '.' || $object === '..') {
                    continue;
                }
                $objectPath = $dir . DIRECTORY_SEPARATOR . $object;
                if (is_dir($objectPath) && !is_link($objectPath)) {
                    $this->recursiveRemoveDir($objectPath);
                } else {
                    unlink($objectPath);
                }
            }
            rmdir($dir);
        }
    }

    private function initializeGPGHome(): void {
        $this->tempDir = $this->createTempDir();

        try {
            $this->gpg = new Crypt_GPG(['homedir' => $this->tempDir]);
        } catch (Crypt_GPG_FileException | \PEAR_Exception $e) {
            $this->tearDownGPGHome();
            throw new RuntimeException('Failed to read GnuPG home directory: ' . $e);
        }
    }

    public function tearDownGPGHome(): void {
        if (is_dir($this->tempDir)) {
            $this->recursiveRemoveDir($this->tempDir);
        }
    }

    /**
     * @param string $email
     * @param string $data
     */
    public function import(string $email, string $data): void {
        $this->email = $email;
        $this->initializeGPGHome();

        try {
            $this->gpg->importKey($data);
        } catch (\Crypt_GPG_BadPassphraseException | Crypt_GPG_NoDataException | Crypt_GPG_Exception $e) {
            $this->tearDownGPGHome();
            throw new RuntimeException('Failed to import OpenPGP key: ' . $e);
        }

        try {
            $keys = $this->gpg->getKeys($this->email);
        } catch (Crypt_GPG_Exception $e) {
            $this->tearDownGPGHome();
            throw new RuntimeException('Failed to read keys: ' . $e);
        }

        if (count($keys) < 1) {
            $this->tearDownGPGHome();
            throw new RuntimeException(sprintf('No key found for %s', $this->email));
        }

        if (count($keys) > 1) {
            $this->tearDownGPGHome();
            throw new RuntimeException(sprintf('More than one keys found for %s', $this->email));
        }

        try {
            $this->key = $this->gpg->exportPublicKey($this->email);

        } catch (Crypt_GPG_Exception | \Crypt_GPG_KeyNotFoundException $e) {
            $this->tearDownGPGHome();
            throw new RuntimeException('Failed to export key: ' . $e);
        }
    }

    /**
     * @return string|null
     */
    public function getKey(): ?string {
        return $this->key;
    }

    /**
     * @return string|null
     */
    public function getFingerprint(): ?string {
        try {
            $fingerprint = $this->gpg->getFingerprint($this->email, Crypt_GPG::FORMAT_CANONICAL);
        } catch (Crypt_GPG_Exception $e) {
            $this->tearDownGPGHome();
            throw new RuntimeException('Failed to get GnuPG fingerprint: ' . $e);
        }

        if (!$fingerprint) {
            return null;
        }
        return (string)$fingerprint;
    }
}
