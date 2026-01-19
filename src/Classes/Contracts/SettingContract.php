<?php

declare(strict_types = 1);

namespace Weiran\Core\Classes\Contracts;

/**
 * Interface SettingsRepository.
 */
interface SettingContract
{
    /**
     * Delete a setting value.
     *
     * @param string $key key need delete
     */
    public function delete(string $key): bool;

    /**
     * Get a setting value by key.
     *
     * @param string $key     获取设置key
     * @param null   $default 默认值
     *
     * @return mixed
     */
    public function get(string $key, $default = null);

    /**
     * Set a setting value from key and value.
     *
     * @param array|string $key   获取设置key
     * @param array|string $value 需要设置的值
     *
     * @return bool
     */
    public function set(string|array $key, array|string $value = ''): bool;

    /**
     * 清空所有缓存
     */
    public function clear(): void;
}
