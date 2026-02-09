<?php

declare(strict_types=1);

namespace IsraelNogueira\galaxyDB\Security;

/**
 * -------------------------------------------------------------------------
 * Class Encryption
 * -------------------------------------------------------------------------
 * 
 * Responsável por criptografia e descriptografia de dados usando AES-256-CBC.
 * Utiliza chaves definidas em variáveis de ambiente para maior segurança.
 * 
 * @package IsraelNogueira\galaxyDB\Security
 * @author Israel Nogueira <israel@feats.com>
 * @license GPL-3.0-or-later
 * @copyright 2023 Israel Nogueira
 * -------------------------------------------------------------------------
 */
class Encryption
{
    /**
     * Algoritmo de criptografia utilizado
     * AES-256-CBC é considerado um dos mais seguros
     */
    private const CIPHER = 'aes-256-cbc';

    /**
     * Chave de criptografia
     * 
     * @var string
     */
    private string $key;

    /**
     * Vetor de inicialização (IV)
     * 
     * @var string
     */
    private string $iv;

    /**
     * Construtor
     * 
     * @param string|null $key Chave de criptografia (usa GALAXY_CRYPT_KEY do .env se null)
     * @param string|null $iv Vetor de inicialização (usa GALAXY_CRYPT_IV do .env se null)
     */
    public function __construct(?string $key = null, ?string $iv = null)
    {
        $this->key = $key ?? getEnv('GALAXY_CRYPT_KEY');
        $this->iv  = $iv  ?? getEnv('GALAXY_CRYPT_IV');
    }

    /**
     * Criptografa dados usando AES-256-CBC
     * 
     * @param mixed $data Dados a serem criptografados
     * @return string Dados criptografados ou dados originais em caso de falha
     */
    public function encrypt(mixed $data): string
    {
        $encrypted = openssl_encrypt(
            (string) $data,
            self::CIPHER,
            $this->key,
            0,
            $this->iv
        );

        // Retorna dados originais em caso de falha na criptografia
        return $encrypted !== false ? $encrypted : (string) $data;
    }

    /**
     * Descriptografa dados que foram criptografados com AES-256-CBC
     * 
     * @param string $data Dados criptografados
     * @return string Dados descriptografados ou dados originais em caso de falha
     */
    public function decrypt(string $data): string
    {
        $decrypted = openssl_decrypt(
            $data,
            self::CIPHER,
            $this->key,
            0,
            $this->iv
        );

        // Retorna dados originais em caso de falha na descriptografia
        return $decrypted !== false ? $decrypted : $data;
    }

    /**
     * Verifica se a criptografia está configurada corretamente
     * 
     * @return bool True se configurado corretamente
     */
    public function isConfigured(): bool
    {
        return !empty($this->key) && !empty($this->iv);
    }

    /**
     * Valida se o algoritmo de criptografia está disponível
     * 
     * @return bool True se o algoritmo está disponível
     */
    public static function isCipherAvailable(): bool
    {
        return in_array(self::CIPHER, openssl_get_cipher_methods());
    }
}
