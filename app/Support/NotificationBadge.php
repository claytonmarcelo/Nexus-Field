<?php

namespace App\Support;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Sino da barra: contador e últimos avisos da conta. São duas consultas em
 * toda página autenticada; um cache de poucos segundos as tira do caminho sem
 * deixar o badge defasado por mais tempo do que o olho percebe.
 */
final class NotificationBadge
{
    private const TTL_SECONDS = 15;

    private const VERSION_KEY = 'notification-badge:version';

    /** @return array{pendentes: int, recentes: \Illuminate\Support\Collection} */
    public static function para(User $user): array
    {
        if (app()->runningUnitTests()) {
            return static::consult($user);
        }

        return Cache::remember(
            'notification-badge:v'.static::version().':'.$user->id,
            now()->addSeconds(self::TTL_SECONDS),
            static fn (): array => static::consult($user),
        );
    }

    public static function invalidate(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        Cache::increment(self::VERSION_KEY);
        Cache::remember(self::VERSION_KEY, now()->addDay(), static fn (): int => 1);
    }

    /** @return array{pendentes: int, recentes: \Illuminate\Support\Collection} */
    private static function consult(User $user): array
    {
        return [
            'pendentes' => Notification::query()
                ->where('user_id', $user->id)
                ->unread()
                ->count(),
            'recentes' => Notification::query()
                ->where('user_id', $user->id)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit(6)
                ->get(),
        ];
    }

    private static function version(): int
    {
        return (int) Cache::remember(self::VERSION_KEY, now()->addDay(), static fn (): int => 1);
    }
}
