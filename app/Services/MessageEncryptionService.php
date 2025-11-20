<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Contracts\Encryption\DecryptException;

class MessageEncryptionService
{
    /**
     * Encrypt message content using AES-256 encryption
     *
     * @param string $message
     * @return string
     */
    public function encrypt(string $message): string
    {
        try {
            return Crypt::encryptString($message);
        } catch (\Exception $e) {
            \Log::error('Message encryption failed: ' . $e->getMessage());
            throw new \Exception('Failed to encrypt message');
        }
    }

    /**
     * Decrypt message content
     *
     * @param string $encryptedMessage
     * @return string
     */
    public function decrypt(string $encryptedMessage): string
    {
        try {
            return Crypt::decryptString($encryptedMessage);
        } catch (DecryptException $e) {
            \Log::error('Message decryption failed: ' . $e->getMessage());
            throw new \Exception('Failed to decrypt message');
        }
    }

    /**
     * Encrypt file before storage
     *
     * @param string $filePath
     * @return string
     */
    public function encryptFile(string $filePath): string
    {
        try {
            $fileContent = file_get_contents($filePath);
            $encrypted = $this->encrypt($fileContent);

            $encryptedPath = $filePath . '.encrypted';
            file_put_contents($encryptedPath, $encrypted);

            return $encryptedPath;
        } catch (\Exception $e) {
            \Log::error('File encryption failed: ' . $e->getMessage());
            throw new \Exception('Failed to encrypt file');
        }
    }

    /**
     * Decrypt file
     *
     * @param string $encryptedFilePath
     * @param string $outputPath
     * @return string
     */
    public function decryptFile(string $encryptedFilePath, string $outputPath): string
    {
        try {
            $encryptedContent = file_get_contents($encryptedFilePath);
            $decrypted = $this->decrypt($encryptedContent);

            file_put_contents($outputPath, $decrypted);

            return $outputPath;
        } catch (\Exception $e) {
            \Log::error('File decryption failed: ' . $e->getMessage());
            throw new \Exception('Failed to decrypt file');
        }
    }

    /**
     * Hash sensitive data (one-way)
     *
     * @param string $data
     * @return string
     */
    public function hash(string $data): string
    {
        return hash('sha256', $data);
    }

    /**
     * Verify hashed data
     *
     * @param string $data
     * @param string $hash
     * @return bool
     */
    public function verifyHash(string $data, string $hash): bool
    {
        return hash_equals($hash, $this->hash($data));
    }
}
