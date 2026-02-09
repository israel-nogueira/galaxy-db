<?php

declare(strict_types=1);

namespace IsraelNogueira\galaxyDB\Core;

use IsraelNogueira\galaxyDB\Core\Connection;
use IsraelNogueira\galaxyDB\Core\DatabaseInterface;
use IsraelNogueira\galaxyDB\Schema\GeneralBase;
use IsraelNogueira\galaxyDB\Security\Security;
use IsraelNogueira\galaxyDB\Query\QueryBuilder;
use IsraelNogueira\galaxyDB\Query\Actions;
use IsraelNogueira\galaxyDB\Query\QueryBatch;
use IsraelNogueira\galaxyDB\Query\DataTableTrait;
use IsraelNogueira\galaxyDB\Audit\Log;
use IsraelNogueira\galaxyDB\StoredProcedures\SPExecutor;
use RuntimeException;
use ReflectionClass;
use Exception;

class GalaxyDB implements DatabaseInterface
{
    use Connection;
    use GeneralBase;
    use Security;
    use QueryBuilder;
    use Actions;
    use QueryBatch;
    use Log;
    use DataTableTrait;

    private bool $initialized = false;
    protected ?array $customConnectData = null;
    public static ?string $dbaseType = null;
    protected ?string $tableClass = null;
    protected array $columnsBlock = [];
    protected array $columnsEnab = [];
    protected array $mysqlFnBlockClass = [];
    protected array $mysqlFnEnabClass = [];
    protected array $_last_id = [];
    protected array $_num_rows = [];
    protected string $query = '';
    protected ?string $colum = null;
    protected mixed $setcolum = null;
    public bool $debug = false;

    public function __construct(?array $conn = null)
    {
        $this->_last_id = [];
        $this->_num_rows = [];

        if (basename(get_class($this)) !== "GalaxyDB") {
            $this->extended();

            if (property_exists($this, 'customConnectData') && 
                is_array($this->customConnectData) && 
                !empty($this->customConnectData)) {
                $conn = $this->customConnectData;
            }
        }

        $this->connection = $this->connect($conn ?? []);
        $this->initialized = true;
    }

    public function __set(string $name, mixed $value): void
    {
        $declaredVars = array_keys(get_mangled_object_vars($this));

        if ($this->initialized && !in_array($name, $declaredVars)) {
            $this->setInsertValue($name, $value);
            $this->setUpdateValue($name, $value);
        } else {
            $this->{$name} = $value;
        }
    }

    public function __call(string $name, array $arguments): mixed
    {
        if (in_array($name, get_class_methods(get_called_class()) ?? [])) {
            return $this->$name(...$arguments);
        }

        if (str_starts_with(strtolower($name), 'sp_')) {
            return $this->executeSP(substr($name, 3), $arguments);
        }

        throw new RuntimeException("Método desconhecido: {$name}");
    }

    public static function static(): static
    {
        return new static();
    }

    public function extended(): void
    {
        if (get_parent_class($this) !== false) {
            $this->tableClass = $this->getExtendedProperty('table', null);
            $this->columnsBlock = $this->getExtendedProperty('columnsBlocked', []);
            $this->columnsEnab = $this->getExtendedProperty('columnsEnabled', []);
            $this->mysqlFnBlockClass = $this->getExtendedProperty('functionsBlocked', []);
            $this->mysqlFnEnabClass = $this->getExtendedProperty('functionsEnabled', []);
            $this->customConnectData = $this->getExtendedProperty('customConnectData', []);
        }
    }

    public function getExtendedProperty(string $property, mixed $default = null): mixed
    {
        $reflection = new ReflectionClass($this);

        while ($reflection) {
            $properties = $reflection->getDefaultProperties();

            if (array_key_exists($property, $properties)) {
                return $this->$property ?? $default;
            }

            $reflection = $reflection->getParentClass();
        }

        return $default;
    }

    protected function executeSP(string $name, array $params): mixed
    {
        $executor = new SPExecutor($this->connection);
        return $executor->execute($name, $params);
    }

    public function setDebug(bool $debug): self
    {
        $this->debug = $debug;
        return $this;
    }

    public function raw(string $sql, array $bindings = []): array|bool
    {
        return $this->query($sql, $bindings);
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    public function find(mixed $id, string $primaryKey = 'id'): ?array
    {
        return $this->where($primaryKey, $id)->first();
    }

    public function findOrFail(mixed $id, string $primaryKey = 'id'): array
    {
        $result = $this->find($id, $primaryKey);
        
        if ($result === null) {
            throw new Exception("Registro não encontrado com {$primaryKey} = {$id}");
        }

        return $result;
    }

    public function pluck(string $column): array
    {
        $column = $this->validateIdentifier($column);
        $results = $this->select($column);
        
        return array_column($results, $column);
    }

    public function chunk(int $size, callable $callback): void
    {
        $offset = 0;
        
        do {
            $results = $this->limit($size, $offset)->select();
            
            if (empty($results)) {
                break;
            }

            if ($callback($results) === false) {
                break;
            }

            $offset += $size;
        } while (count($results) === $size);
    }
}
