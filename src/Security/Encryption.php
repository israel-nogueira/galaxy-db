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
     * Algoritmo de hash para derivação de chave
     */
    private const HASH_ALGO = 'sha256';

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
     * Tamanho do IV em bytes
     */
    private int $ivLength;

    /**
     * Modo de operação (CBC ou GCM)
     */
    private string $mode = 'CBC';

    /**
     * Tag de autenticação para GCM
     */
    private ?string $tag = null;

    /**
     * Construtor
     * 
     * @param string|null $key Chave de criptografia (usa GALAXY_CRYPT_KEY do .env se null)
     * @param string|null $iv Vetor de inicialização (usa GALAXY_CRYPT_IV do .env se null)
     * @param string $mode Modo de operação (CBC ou GCM)
     * @throws \RuntimeException Se cipher não estiver disponível
     */
    public function __construct(
        ?string $key = null, 
        ?string $iv = null,
        string $mode = 'CBC'
    ) {
        // Verifica disponibilidade do cipher
        if (!self::isCipherAvailable()) {
            throw new \RuntimeException(
                "Cipher " . self::CIPHER . " não está disponível no sistema"
            );
        }

        // Define modo
        $this->mode = strtoupper($mode);
        if (!in_array($this->mode, ['CBC', 'GCM'])) {
            $this->mode = 'CBC';
        }

        // Obtém chave e IV
        $this->key = $key ?? getEnv('GALAXY_CRYPT_KEY') ?? '';
        $this->iv = $iv ?? getEnv('GALAXY_CRYPT_IV') ?? '';

        // Valida chave e IV
        if (empty($this->key)) {
            throw new \RuntimeException("Chave de criptografia não configurada");
        }

        // Deriva chave se necessário
        if (strlen($this->key) < 32) {
            $this->key = $this->deriveKey($this->key);
        }

        // Define tamanho do IV
        $this->ivLength = openssl_cipher_iv_length(self::CIPHER);
        
        // Gera IV se não fornecido
        if (empty($this->iv) || strlen($this->iv) < $this->ivLength) {
            $this->iv = $this->generateIV();
        }
    }

    /**
     * Deriva chave de 32 bytes a partir de uma string
     * 
     * @param string $key Chave original
     * @return string Chave derivada
     */
    private function deriveKey(string $key): string
    {
        return hash(self::HASH_ALGO, $key, true);
    }

    /**
     * Gera vetor de inicialização aleatório
     * 
     * @return string IV gerado
     */
    private function generateIV(): string
    {
        return openssl_random_pseudo_bytes($this->ivLength);
    }

    /**
     * Criptografa dados usando AES-256-CBC/GCM
     * 
     * @param mixed $data Dados a serem criptografados
     * @param bool $encodeBase64 Se deve codificar em base64
     * @return string Dados criptografados
     * @throws \RuntimeException Se falhar a criptografia
     */
    public function encrypt(mixed $data, bool $encodeBase64 = true): string
    {
        $dataString = (string) $data;
        
        // Valida dados
        if ($dataString === '') {
            return '';
        }

        $this->tag = null;

        if ($this->mode === 'GCM') {
            $encrypted = openssl_encrypt(
                $dataString,
                self::CIPHER,
                $this->key,
                OPENSSL_RAW_DATA,
                $this->iv,
                $this->tag,
                '',
                16 // Tag length
            );
        } else {
            $encrypted = openssl_encrypt(
                $dataString,
                self::CIPHER,
                $this->key,
                OPENSSL_RAW_DATA,
                $this->iv
            );
        }

        if ($encrypted === false) {
            throw new \RuntimeException(
                "Falha na criptografia: " . openssl_error_string()
            );
        }

        // Se GCM, concatena tag no final
        if ($this->mode === 'GCM' && $this->tag !== null) {
            $encrypted = $encrypted . $this->tag;
        }

        return $encodeBase64 ? base64_encode($encrypted) : $encrypted;
    }

    /**
     * Descriptografa dados criptografados com AES-256-CBC/GCM
     * 
     * @param string $data Dados criptografados
     * @param bool $encodedBase64 Se os dados estão em base64
     * @return string Dados descriptografados
     * @throws \RuntimeException Se falhar a descriptografia
     */
    public function decrypt(string $data, bool $encodedBase64 = true): string
    {
        if ($data === '') {
            return '';
        }

        $dataDecoded = $encodedBase64 ? base64_decode($data, true) : $data;
        
        if ($dataDecoded === false) {
            throw new \RuntimeException("Base64 inválido para descriptografia");
        }

        if ($this->mode === 'GCM') {
            // Separa dados da tag (16 bytes no final)
            $tagLength = 16;
            $encryptedData = substr($dataDecoded, 0, -$tagLength);
            $this->tag = substr($dataDecoded, -$tagLength);
            
            $decrypted = openssl_decrypt(
                $encryptedData,
                self::CIPHER,
                $this->key,
                OPENSSL_RAW_DATA,
                $this->iv,
                $this->tag
            );
        } else {
            $decrypted = openssl_decrypt(
                $dataDecoded,
                self::CIPHER,
                $this->key,
                OPENSSL_RAW_DATA,
                $this->iv
            );
        }

        if ($decrypted === false) {
            throw new \RuntimeException(
                "Falha na descriptografia: " . openssl_error_string()
            );
        }

        return $decrypted;
    }

    /**
     * Criptografa dados com chave única (one-time)
     * 
     * @param mixed $data Dados a serem criptografados
     * @param bool $encodeBase64 Se deve codificar em base64
     * @return array Retorna [encrypted, iv, tag]
     */
    public function encryptOneTime(mixed $data, bool $encodeBase64 = true): array
    {
        $tempIv = $this->generateIV();
        $tempKey = $this->generateIV(); // Chave aleatória
        
        $tempEncryption = new self(
            base64_encode($tempKey),
            base64_encode($tempIv),
            $this->mode
        );
        
        $encrypted = $tempEncryption->encrypt($data, $encodeBase64);
        
        return [
            'encrypted' => $encrypted,
            'iv' => $encodeBase64 ? base64_encode($tempIv) : $tempIv,
            'key' => $encodeBase64 ? base64_encode($tempKey) : $tempKey
        ];
    }

    /**
     * Descriptografa dados com chave única
     * 
     * @param string $data Dados criptografados
     * @param string $iv Vetor de inicialização
     * @param string $key Chave de criptografia
     * @param bool $encodedBase64 Se os dados estão em base64
     * @return string Dados descriptografados
     */
    public function decryptOneTime(
        string $data,
        string $iv,
        string $key,
        bool $encodedBase64 = true
    ): string {
        $tempEncryption = new self($key, $iv, $this->mode);
        return $tempEncryption->decrypt($data, $encodedBase64);
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

    /**
     * Obtém o IV atual
     * 
     * @param bool $base64 Se deve retornar em base64
     * @return string IV
     */
    public function getIV(bool $base64 = false): string
    {
        return $base64 ? base64_encode($this->iv) : $this->iv;
    }

    /**
     * Obtém a tag GCM
     * 
     * @param bool $base64 Se deve retornar em base64
     * @return string|null Tag ou null
     */
    public function getTag(bool $base64 = false): ?string
    {
        if ($this->tag === null) {
            return null;
        }
        return $base64 ? base64_encode($this->tag) : $this->tag;
    }

    /**
     * Obtém o modo atual
     * 
     * @return string Modo (CBC ou GCM)
     */
    public function getMode(): string
    {
        return $this->mode;
    }

    /**
     * Altera o modo de operação
     * 
     * @param string $mode CBC ou GCM
     * @return self
     */
    public function setMode(string $mode): self
    {
        $mode = strtoupper($mode);
        if (in_array($mode, ['CBC', 'GCM'])) {
            $this->mode = $mode;
        }
        return $this;
    }

    /**
     * Gera par de chave e IV aleatórios
     * 
     * @param bool $base64 Se deve retornar em base64
     * @return array ['key' => string, 'iv' => string]
     */
    public static function generateKeyPair(bool $base64 = false): array
    {
        $key = openssl_random_pseudo_bytes(32);
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(self::CIPHER));
        
        return [
            'key' => $base64 ? base64_encode($key) : $key,
            'iv' => $base64 ? base64_encode($iv) : $iv
        ];
    }

    /**
     * Criptografa dados e retorna em formato JSON com metadados
     * 
     * @param mixed $data Dados a serem criptografados
     * @param bool $encodeBase64 Se deve codificar em base64
     * @return string JSON com dados criptografados
     */
    public function encryptToJson(mixed $data, bool $encodeBase64 = true): string
    {
        $encrypted = $this->encrypt($data, $encodeBase64);
        
        $result = [
            'data' => $encrypted,
            'iv' => $this->getIV($encodeBase64),
            'mode' => $this->mode
        ];

        if ($this->mode === 'GCM' && $this->tag !== null) {
            $result['tag'] = $this->getTag($encodeBase64);
        }

        return json_encode($result);
    }

    /**
     * Descriptografa dados a partir de JSON
     * 
     * @param string $json JSON com dados criptografados
     * @param bool $encodedBase64 Se os dados estão em base64
     * @return string Dados descriptografados
     */
    public function decryptFromJson(string $json, bool $encodedBase64 = true): string
    {
        $data = json_decode($json, true);
        
        if (!isset($data['data']) || !isset($data['iv'])) {
            throw new \RuntimeException("JSON inválido para descriptografia");
        }

        // Cria nova instância com IV do JSON
        $tempEncryption = new self(
            $this->key,
            $data['iv'],
            $data['mode'] ?? 'CBC'
        );

        // Se GCM, define a tag
        if (isset($data['tag']) && ($data['mode'] ?? 'CBC') === 'GCM') {
            $tempEncryption->tag = $encodedBase64 ? base64_decode($data['tag']) : $data['tag'];
        }

        return $tempEncryption->decrypt($data['data'], $encodedBase64);
    }

    /**
     * Verifica se os dados estão criptografados (detecta padrão base64)
     * 
     * @param string $data Dados a verificar
     * @return bool True se parece criptografado
     */
    public static function isEncrypted(string $data): bool
    {
        // Verifica se é base64 válido
        if (!Validator::isValidBase64($data)) {
            return false;
        }

        // Decodifica e verifica se parece binário
        $decoded = base64_decode($data, true);
        if ($decoded === false || strlen($decoded) < 16) {
            return false;
        }

        // Verifica se tem caracteres não imprimíveis
        $printable = preg_match('/[[:print:]]/', $decoded);
        return $printable === 0 || $printable === false;
    }
}