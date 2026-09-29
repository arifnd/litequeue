<?php

declare(strict_types=1);

namespace Arifnd\LiteQueue\Redis;

/**
 * Lua scripts compatible with Laravel's Redis queue schema.
 */
final class LuaScripts
{
    public static function size(): string
    {
        return <<<'LUA'
return redis.call('llen', KEYS[1]) + redis.call('zcard', KEYS[2]) + redis.call('zcard', KEYS[3])
LUA;
    }

    public static function push(): string
    {
        return <<<'LUA'
redis.call('rpush', KEYS[1], ARGV[1])
redis.call('rpush', KEYS[2], 1)
LUA;
    }

    public static function later(): string
    {
        return <<<'LUA'
redis.call('zadd', KEYS[1], ARGV[1], ARGV[2])
LUA;
    }

    public static function pop(): string
    {
        return <<<'LUA'
local job = redis.call('lpop', KEYS[1])
local reserved = false

if(job ~= false) then
    reserved = cjson.decode(job)
    reserved['attempts'] = reserved['attempts'] + 1
    reserved = cjson.encode(reserved)
    redis.call('zadd', KEYS[2], ARGV[1], reserved)
    redis.call('lpop', KEYS[3])
end

return {job, reserved}
LUA;
    }

    public static function release(): string
    {
        return <<<'LUA'
redis.call('zrem', KEYS[2], ARGV[1])
redis.call('zadd', KEYS[1], ARGV[2], ARGV[1])

return true
LUA;
    }

    public static function migrateExpiredJobs(): string
    {
        return <<<'LUA'
local val = redis.call('zrangebyscore', KEYS[1], '-inf', ARGV[1], 'limit', 0, ARGV[2])

if(next(val) ~= nil) then
    redis.call('zremrangebyrank', KEYS[1], 0, #val - 1)

    for i = 1, #val, 100 do
        redis.call('rpush', KEYS[2], unpack(val, i, math.min(i+99, #val)))
        for j = i, math.min(i+99, #val) do
            redis.call('rpush', KEYS[3], 1)
        end
    end
end

return val
LUA;
    }

    public static function clear(): string
    {
        return <<<'LUA'
local size = redis.call('llen', KEYS[1]) + redis.call('zcard', KEYS[2]) + redis.call('zcard', KEYS[3])
redis.call('del', KEYS[1], KEYS[2], KEYS[3], KEYS[4])
return size
LUA;
    }
}
