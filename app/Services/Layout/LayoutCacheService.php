<?php

namespace App\Services\Layout;

use Illuminate\Support\Facades\Cache;

class LayoutCacheService
{
    private const CACHE_PREFIX = 'room_layout_';
    private const CACHE_TTL = 3600; // 1 hour

    /**
     * Generate cache key for layout.
     */
    public function generateCacheKey(int $roomId, array $items): string
    {
        $itemsHash = md5(json_encode($items));

        return self::CACHE_PREFIX . $roomId . '_' . $itemsHash;
    }

    /**
     * Get cached layout.
     *
     * @return array|null
     */
    public function get(int $roomId, array $items): ?array
    {
        $key = $this->generateCacheKey($roomId, $items);

        return Cache::get($key);
    }

    /**
     * Cache layout result.
     */
    public function put(int $roomId, array $items, array $result): void
    {
        $key = $this->generateCacheKey($roomId, $items);

        Cache::put($key, $result, self::CACHE_TTL);
    }

    /**
     * Clear cache for a room.
     */
    public function clear(int $roomId): void
    {
        $pattern = self::CACHE_PREFIX . $roomId . '_*';
        // Note: Laravel cache doesn't support wildcard deletion directly
        // This would need Redis or a custom implementation
        // For now, we'll clear all cache (in production, use Redis with tags)
        Cache::flush();
    }

    /**
     * Clear all layout caches.
     */
    public function clearAll(): void
    {
        Cache::flush();
    }
}
