<?php

namespace WpCore\Models;

use Exception;
use mysqli_result;

/**
 * @property-read string $id
 */
abstract class BaseModel
{

    protected static string $primaryKey = 'id';

    public abstract static function get_create_sql(): string;

    public static string $table_name;

    /**
     * @return string
     */
    public static function get_table(): string
    {
        global $wpdb;

        return $wpdb->prefix . '_' . static::$table_name;
    }

    public static function get_charset_collate(): string
    {
        global $wpdb;

        return $wpdb->get_charset_collate();
    }


    /**
     * @param array<mixed> $attributes
     */
    public final function __construct(protected array $attributes = [])
    {
    }

    /**
     * @param array<mixed> $where
     * @return array<mixed>|object|null
     */
    public static function get(array $where = [], ?int $limit = null): array|object|null
    {
        global $wpdb;
        $table = self::get_table();
        $query = "SELECT * FROM $table WHERE ";
        $conditions = ['1=1'];

        foreach ($where as $key => $value) {
            $conditions[] = "$key = '$value'";
        }

        $query .= implode(' AND ', $conditions);
        $query .= !empty($limit) ? " LIMIT $limit" : '';

        return array_map(fn($item) => new static($item), $wpdb->get_results($query, ARRAY_A));
    }

    /**
     * @throws Exception
     */
    public function save(): bool
    {
        if (isset($this->attributes[self::$primaryKey])) {
            return $this->update();
        } else {
            return $this->insert();
        }
    }

    private function insert(): bool
    {
        global $wpdb;

        $result = $wpdb->insert(self::get_table(), $this->attributes);

        if (!$result) {
            error_log($wpdb->last_error);

            return false;
        }

        $this->attributes[self::$primaryKey] = $wpdb->insert_id;

        return true;
    }

    /**
     * @param int $id
     * @param array<mixed> $data
     * @return static
     * @throws Exception
     */
    public static function upsert(int $id, array $data): static
    {
        $instance = self::find($id);

        if ($instance) {
            $instance->attributes = $data;
        } else {
            $instance = new static($data);
        }
        $instance->save();

        return $instance;
    }


    /**
     * @throws Exception
     */
    private function update(): bool
    {
        global $wpdb;

        $primaryKeyValue = $this->attributes[self::$primaryKey];
        $attributes = $this->attributes;
        unset($attributes[self::$primaryKey]);

        $result = $wpdb->update(
            self::get_table(),
            $attributes,
            [self::$primaryKey => $primaryKeyValue]
        );

        return self::response_bool($result);
    }

    /**
     * @throws Exception
     */
    private static function response_bool(bool|int|mysqli_result|null $result, bool $throw_ex = true): bool
    {
        global $wpdb;

        if ($result === false) {
            if ($throw_ex) {
                throw new Exception($wpdb->last_error);
            }

            error_log($wpdb->last_error);

            return false;
        }

        return true;
    }

    /**
     * @param array<mixed> $attributes
     * @param int $id
     *
     * @return bool
     * @throws Exception
     */
    public static function update_by_id(int $id, array $attributes): bool
    {
        global $wpdb;
        $primaryKey = self::$primaryKey;
        $primaryKeyValue = $id;

        unset($attributes[$primaryKey]);

        $result = $wpdb->update(
            self::get_table(),
            $attributes,
            [$primaryKey => $primaryKeyValue]
        );

        return self::response_bool($result);
    }

    /**
     * @throws Exception
     */
    public function delete(): bool
    {
        global $wpdb;

        if (!isset($this->attributes[self::$primaryKey])) {
            return false;
        }

        $result = $wpdb->delete(
            self::get_table(),
            [self::$primaryKey => $this->attributes[self::$primaryKey]]
        );

        return self::response_bool($result);
    }

    public static function find(int $id): ?static
    {
        global $wpdb;

        $table = self::get_table();
        $primaryKey = self::$primaryKey;

        $result = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE $primaryKey = %d LIMIT 1", $id),
            ARRAY_A
        );

        if ($result) {
            return new static($result);
        }

        return null;
    }

    /**
     * @param array<mixed> $data
     *
     * @return static
     */
    public static function create(array $data): static
    {
        return new static($data);
    }

    /**
     * @param array<mixed> $data
     *
     * @return static
     * @throws Exception
     */
    public static function make(array $data): static
    {
        $instance = self::create($data);
        $instance->save();

        return $instance;
    }

    /**
     * @param string $name
     * @param mixed $value
     *
     * @return void
     */
    public function __set(string $name, mixed $value)
    {
        $this->attributes[$name] = $value;
    }

    /**
     * @param string $name
     *
     * @return mixed|null
     */
    public function __get(string $name)
    {
        return $this->attributes[$name] ?? null;
    }
}
